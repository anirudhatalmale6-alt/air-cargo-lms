<?php

namespace App\Services\Tracking\Drivers;

use App\Models\Shipment;
use App\Services\Tracking\Contracts\TrackingDriver;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use App\Support\AwbNumber;
use Carbon\CarbonImmutable;

/**
 * Serves tracking straight out of our own shipment_status_events. For cargo this
 * office handles itself, operations already type the real milestones, so no API
 * is involved and the customer sees the same history staff see.
 */
class InternalDriver implements TrackingDriver
{
    public function key(): string
    {
        return 'internal';
    }

    public function name(): string
    {
        return 'Internal operations records';
    }

    public function supports(AwbNumber $awb): bool
    {
        return true;
    }

    public function track(AwbNumber $awb): TrackingResult
    {
        $shipment = Shipment::query()
            ->with(['statusEvents' => fn ($q) => $q->where('visible_to_customer', true)->orderBy('occurred_at')])
            ->where('awb_number', $awb->digits())
            ->first();

        if (! $shipment) {
            return TrackingResult::notFound($awb, $this->key(), 'This AWB is not in our records.');
        }

        $events = $shipment->statusEvents->map(fn ($e) => new TrackingEvent(
            status: $e->status,
            occurredAt: CarbonImmutable::parse($e->occurred_at),
            location: $e->location,
            flightNumber: $e->flight_number,
            pieces: $e->pieces,
            weightKg: $e->weight_kg !== null ? (float) $e->weight_kg : null,
            description: $e->description,
            rawCode: $e->status->imp(),
        ))->all();

        if ($events === []) {
            return TrackingResult::notFound(
                $awb,
                $this->key(),
                'The AWB is on file but no movement has been recorded yet.'
            );
        }

        return TrackingResult::found(
            awb: $awb,
            driver: $this->key(),
            events: $events,
            origin: $shipment->origin,
            destination: $shipment->destination,
            pieces: $shipment->pieces,
            weightKg: (float) $shipment->gross_weight_kg,
            raw: ['shipment_reference' => $shipment->reference],
        );
    }
}
