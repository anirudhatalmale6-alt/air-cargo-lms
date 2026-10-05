<?php

namespace App\Services\Tracking;

use App\Enums\ShipmentStatus;
use Carbon\CarbonImmutable;
use JsonSerializable;

/** One normalised movement, whatever shape the airline sent it in. */
final class TrackingEvent implements JsonSerializable
{
    public function __construct(
        public readonly ShipmentStatus $status,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?string $location = null,
        public readonly ?string $flightNumber = null,
        public readonly ?int $pieces = null,
        public readonly ?float $weightKg = null,
        public readonly ?string $description = null,
        public readonly ?string $rawCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: ShipmentStatus::tryFrom($data['status'] ?? '') ?? ShipmentStatus::InTransit,
            occurredAt: CarbonImmutable::parse($data['occurred_at']),
            location: $data['location'] ?? null,
            flightNumber: $data['flight_number'] ?? null,
            pieces: isset($data['pieces']) ? (int) $data['pieces'] : null,
            weightKg: isset($data['weight_kg']) ? (float) $data['weight_kg'] : null,
            description: $data['description'] ?? null,
            rawCode: $data['raw_code'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'location' => $this->location,
            'flight_number' => $this->flightNumber,
            'pieces' => $this->pieces,
            'weight_kg' => $this->weightKg,
            'description' => $this->description,
            'raw_code' => $this->rawCode,
        ];
    }
}
