<?php

namespace App\Services\Tracking\Drivers;

use App\Enums\ShipmentStatus;
use App\Services\Tracking\Contracts\TrackingDriver;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use App\Support\AwbNumber;
use Carbon\CarbonImmutable;

/**
 * Stands in for a real airline API so the whole tracking flow can be demonstrated
 * and tested before any credentials exist. Output is derived from the AWB digits,
 * so the same AWB always gives the same history, which keeps tests honest.
 *
 * Switch it off with TRACKING_DEMO=false in .env. It is never a fallback for a
 * configured airline: if a real driver matched the prefix, the real driver runs.
 */
class DemoDriver implements TrackingDriver
{
    private const AIRPORTS = ['BOM', 'DEL', 'DXB', 'FRA', 'LHR', 'SIN', 'HKG', 'JFK', 'AMS', 'DOH'];

    public function key(): string
    {
        return 'demo';
    }

    public function name(): string
    {
        return 'Demo tracking feed (no live airline)';
    }

    public function supports(AwbNumber $awb): bool
    {
        return (bool) config('tracking.demo_enabled', false);
    }

    public function track(AwbNumber $awb): TrackingResult
    {
        $seed = (int) $awb->runningSerial();
        $origin = self::AIRPORTS[$seed % count(self::AIRPORTS)];
        $destination = self::AIRPORTS[intdiv($seed, 7) % count(self::AIRPORTS)];

        if ($destination === $origin) {
            $destination = self::AIRPORTS[($seed + 3) % count(self::AIRPORTS)];
        }

        $transit = self::AIRPORTS[intdiv($seed, 13) % count(self::AIRPORTS)];
        $flight = sprintf('%s%03d', ['LH', 'EK', 'AI', 'SQ', 'QR'][$seed % 5], $seed % 900 + 100);

        // How far along this AWB is: 3 to 7 milestones.
        $depth = 3 + $seed % 5;
        $pieces = 1 + $seed % 40;
        $weight = round(($seed % 1800) + 12.5, 2);

        $plan = [
            [ShipmentStatus::Booked, $origin, null, 'Booking confirmed with the carrier'],
            [ShipmentStatus::Received, $origin, null, 'Cargo received from shipper at terminal'],
            [ShipmentStatus::Manifested, $origin, $flight, 'Manifested on flight '.$flight],
            [ShipmentStatus::Departed, $origin, $flight, 'Departed '.$origin.' on '.$flight],
            [ShipmentStatus::InTransit, $transit, $flight, 'Transit handling at '.$transit],
            [ShipmentStatus::Arrived, $destination, $flight, 'Arrived at '.$destination],
            [ShipmentStatus::CustomsCleared, $destination, null, 'Customs clearance completed'],
            [ShipmentStatus::Delivered, $destination, null, 'Delivered to consignee, signed for'],
        ];

        $cursor = CarbonImmutable::now()->subHours($depth * 11 + $seed % 9);
        $events = [];

        foreach (array_slice($plan, 0, $depth) as $i => [$status, $location, $flightNumber, $description]) {
            $cursor = $cursor->addHours(6 + ($seed + $i * 7) % 15);

            $events[] = new TrackingEvent(
                status: $status,
                occurredAt: $cursor,
                location: $location,
                flightNumber: $flightNumber,
                pieces: $pieces,
                weightKg: $weight,
                description: $description,
                rawCode: $status->imp(),
            );
        }

        return TrackingResult::found(
            awb: $awb,
            driver: $this->key(),
            events: $events,
            origin: $origin,
            destination: $destination,
            pieces: $pieces,
            weightKg: $weight,
            raw: ['generated' => true, 'seed' => $seed],
        );
    }
}
