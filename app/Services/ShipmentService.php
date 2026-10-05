<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Airline;
use App\Models\Awb;
use App\Models\Shipment;
use App\Models\ShipmentStatusEvent;
use App\Models\User;
use App\Support\AwbNumber;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShipmentService
{
    /**
     * Post a movement and move the shipment's headline status.
     *
     * current_status only follows the newest event by time, so entering a
     * forgotten earlier milestone does not drag the shipment backwards.
     */
    public function recordStatus(
        Shipment $shipment,
        ShipmentStatus $status,
        CarbonInterface $occurredAt,
        ?string $location = null,
        ?string $flightNumber = null,
        ?int $pieces = null,
        ?float $weightKg = null,
        ?string $description = null,
        string $source = 'manual',
        bool $visibleToCustomer = true,
        ?User $user = null,
    ): ShipmentStatusEvent {
        return DB::transaction(function () use (
            $shipment, $status, $occurredAt, $location, $flightNumber,
            $pieces, $weightKg, $description, $source, $visibleToCustomer, $user
        ) {
            $event = $shipment->statusEvents()->create([
                'status' => $status,
                'location' => $location ? strtoupper($location) : null,
                'flight_number' => $flightNumber ? strtoupper($flightNumber) : null,
                'pieces' => $pieces,
                'weight_kg' => $weightKg,
                'occurred_at' => $occurredAt,
                'description' => $description,
                'source' => $source,
                'visible_to_customer' => $visibleToCustomer,
                'recorded_by' => $user?->id,
            ]);

            $newest = $shipment->statusEvents()
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();

            if ($newest) {
                $shipment->update([
                    'current_status' => $newest->status,
                    'status_changed_at' => $newest->occurred_at,
                ]);
            }

            return $event;
        });
    }

    /**
     * Attach an AWB to a shipment. Takes either an existing stock row or a raw
     * number typed by staff, and never lets two shipments hold the same AWB.
     */
    public function assignAwb(Shipment $shipment, string $rawAwb, ?Airline $airline = null): Awb
    {
        $value = AwbNumber::parse($rawAwb);

        return DB::transaction(function () use ($shipment, $value, $airline) {
            $awb = Awb::query()->where('number', $value->digits())->lockForUpdate()->first();

            if ($awb && $awb->shipment && $awb->shipment->id !== $shipment->id) {
                throw new RuntimeException(
                    "AWB {$value->formatted()} is already on shipment {$awb->shipment->reference}."
                );
            }

            $airline ??= $awb?->airline
                ?? Airline::query()->where('awb_prefix', $value->prefix)->first();

            if (! $airline) {
                throw new RuntimeException(
                    "No airline is registered for AWB prefix {$value->prefix}. Add it under Airlines first."
                );
            }

            // Typed AWBs that are not in stock get a row, so stock stays complete.
            $awb ??= Awb::fromNumber($airline, $value, ['notes' => 'Added on assignment to a shipment']);

            // Free the previous AWB so it can be reused.
            if ($shipment->awb_id && $shipment->awb_id !== $awb->id) {
                Awb::query()->whereKey($shipment->awb_id)->update(['status' => 'available']);
            }

            $awb->update(['status' => 'assigned', 'airline_id' => $airline->id]);

            $shipment->update([
                'awb_id' => $awb->id,
                'awb_number' => $value->digits(),
                'airline_id' => $airline->id,
            ]);

            return $awb;
        });
    }

    public function releaseAwb(Shipment $shipment): void
    {
        if (! $shipment->awb_id) {
            return;
        }

        DB::transaction(function () use ($shipment) {
            Awb::query()->whereKey($shipment->awb_id)->update(['status' => 'available']);
            $shipment->update(['awb_id' => null, 'awb_number' => null]);
        });
    }

    /**
     * Generate a block of AWB stock for an airline, check digits computed here so
     * staff never type them.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateAwbStock(Airline $airline, int $fromRunningSerial, int $count): array
    {
        if ($count < 1 || $count > 1000) {
            throw new RuntimeException('Generate between 1 and 1000 numbers at a time.');
        }

        $created = 0;
        $skipped = 0;
        $batchId = (int) (Awb::max('batch_id') ?? 0) + 1;

        DB::transaction(function () use ($airline, $fromRunningSerial, $count, $batchId, &$created, &$skipped) {
            for ($i = 0; $i < $count; $i++) {
                $value = AwbNumber::fromRunningSerial($airline->awb_prefix, $fromRunningSerial + $i);

                if (Awb::query()->where('number', $value->digits())->exists()) {
                    $skipped++;

                    continue;
                }

                Awb::fromNumber($airline, $value, [
                    'batch_id' => $batchId,
                    'received_on' => now()->toDateString(),
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }
}
