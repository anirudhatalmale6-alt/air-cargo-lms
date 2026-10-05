<?php

namespace App\Services\Tracking;

use App\Enums\ShipmentStatus;
use App\Support\AwbNumber;
use Carbon\CarbonImmutable;
use JsonSerializable;

final class TrackingResult implements JsonSerializable
{
    /** @param  list<TrackingEvent>  $events */
    private function __construct(
        public readonly AwbNumber $awb,
        public readonly string $driver,
        public readonly bool $found,
        public readonly array $events = [],
        public readonly ?string $origin = null,
        public readonly ?string $destination = null,
        public readonly ?int $pieces = null,
        public readonly ?float $weightKg = null,
        public readonly ?string $message = null,
        public readonly bool $fromCache = false,
        public readonly ?CarbonImmutable $fetchedAt = null,
        public readonly array $raw = [],
    ) {}

    /** @param  list<TrackingEvent>  $events */
    public static function found(
        AwbNumber $awb,
        string $driver,
        array $events,
        ?string $origin = null,
        ?string $destination = null,
        ?int $pieces = null,
        ?float $weightKg = null,
        array $raw = [],
    ): self {
        usort($events, fn (TrackingEvent $a, TrackingEvent $b) => $a->occurredAt <=> $b->occurredAt);

        return new self(
            awb: $awb,
            driver: $driver,
            found: true,
            events: array_values($events),
            origin: $origin,
            destination: $destination,
            pieces: $pieces,
            weightKg: $weightKg,
            fetchedAt: CarbonImmutable::now(),
            raw: $raw,
        );
    }

    public static function notFound(AwbNumber $awb, string $driver, ?string $message = null): self
    {
        return new self(
            awb: $awb,
            driver: $driver,
            found: false,
            message: $message ?? 'No tracking information is available for this AWB yet.',
            fetchedAt: CarbonImmutable::now(),
        );
    }

    public function markFromCache(?CarbonImmutable $fetchedAt): self
    {
        return new self(
            awb: $this->awb,
            driver: $this->driver,
            found: $this->found,
            events: $this->events,
            origin: $this->origin,
            destination: $this->destination,
            pieces: $this->pieces,
            weightKg: $this->weightKg,
            message: $this->message,
            fromCache: true,
            fetchedAt: $fetchedAt ?? $this->fetchedAt,
            raw: $this->raw,
        );
    }

    public function latest(): ?TrackingEvent
    {
        return $this->events === [] ? null : $this->events[count($this->events) - 1];
    }

    public function latestStatus(): ?ShipmentStatus
    {
        return $this->latest()?->status;
    }

    /** Newest first, which is how the tracking page reads. */
    public function eventsDescending(): array
    {
        return array_reverse($this->events);
    }

    /**
     * Which canonical milestones have been passed. Drives the progress bar
     * without pretending a skipped milestone happened.
     */
    public function milestoneProgress(): array
    {
        $reached = [];
        foreach ($this->events as $event) {
            $reached[$event->status->value] = $event->occurredAt;
        }

        $out = [];
        $seen = false;
        foreach (array_reverse(ShipmentStatus::milestones()) as $milestone) {
            // Anything at or before the furthest reached milestone counts as passed.
            $hit = isset($reached[$milestone->value]);
            $seen = $seen || $hit;
            $out[] = [
                'status' => $milestone,
                'reached' => $seen,
                'at' => $reached[$milestone->value] ?? null,
            ];
        }

        return array_reverse($out);
    }

    public function jsonSerialize(): array
    {
        return [
            'awb' => $this->awb->formatted(),
            'awb_number' => $this->awb->digits(),
            'driver' => $this->driver,
            'found' => $this->found,
            'from_cache' => $this->fromCache,
            'fetched_at' => $this->fetchedAt?->toIso8601String(),
            'origin' => $this->origin,
            'destination' => $this->destination,
            'pieces' => $this->pieces,
            'weight_kg' => $this->weightKg,
            'latest_status' => $this->latestStatus()?->value,
            'latest_status_label' => $this->latestStatus()?->label(),
            'message' => $this->message,
            'events' => array_map(fn (TrackingEvent $e) => $e->jsonSerialize(), $this->events),
        ];
    }
}
