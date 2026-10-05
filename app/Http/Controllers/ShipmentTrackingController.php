<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Pulls the airline's own movements into our status history, so operations can
 * see carrier events beside what the office typed, without duplicating rows.
 */
class ShipmentTrackingController extends Controller
{
    public function __construct(private readonly TrackingManager $tracking) {}

    public function sync(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('update', $shipment);

        if (! $shipment->awb_number) {
            return back()->withErrors(['awb_input' => 'Assign an AWB before pulling carrier tracking.']);
        }

        try {
            $result = $this->tracking->track($shipment->awb_number, forceRefresh: true);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['awb_input' => $e->getMessage()]);
        }

        if (! $result->found) {
            return back()->with('status', $result->message ?? 'The carrier has no movements for this AWB yet.');
        }

        // The internal driver would just re-import what we already typed.
        if ($result->driver === 'internal') {
            return back()->with('status',
                'This AWB prefix has no carrier API configured, so tracking already comes from our own records.');
        }

        $existing = $shipment->statusEvents()
            ->get(['status', 'occurred_at'])
            ->map(fn ($e) => $e->status->value.'|'.$e->occurred_at->format('Y-m-d H:i'))
            ->all();

        $imported = 0;

        foreach ($result->events as $event) {
            /** @var TrackingEvent $event */
            $key = $event->status->value.'|'.$event->occurredAt->format('Y-m-d H:i');

            if (in_array($key, $existing, true)) {
                continue;
            }

            $shipment->statusEvents()->create([
                'status' => $event->status,
                'location' => $event->location,
                'flight_number' => $event->flightNumber,
                'pieces' => $event->pieces,
                'weight_kg' => $event->weightKg,
                'occurred_at' => $event->occurredAt,
                'description' => $event->description,
                'source' => 'api:'.$result->driver,
                'visible_to_customer' => true,
            ]);

            $existing[] = $key;
            $imported++;
        }

        if ($imported > 0) {
            $newest = $shipment->statusEvents()->orderByDesc('occurred_at')->orderByDesc('id')->first();

            $shipment->update([
                'current_status' => $newest->status,
                'status_changed_at' => $newest->occurred_at,
            ]);
        }

        return back()->with('status', $imported === 0
            ? 'Carrier tracking checked, nothing new since the last pull.'
            : "{$imported} carrier ".str('event')->plural($imported)." imported from {$result->driver}.");
    }
}
