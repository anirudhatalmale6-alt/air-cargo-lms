<?php

namespace App\Services\Tracking;

use App\Models\Airline;
use App\Models\TrackingLookup;
use App\Models\TrackingSnapshot;
use App\Services\Tracking\Contracts\TrackingDriver;
use App\Services\Tracking\Drivers\GenericHttpDriver;
use App\Support\AwbNumber;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Throwable;

/**
 * Picks the right driver for an AWB and keeps the public tracking page fast and
 * resilient: a stored snapshot answers inside the TTL, and if a live provider is
 * down we still show the last known history rather than an error page.
 */
class TrackingManager
{
    /** @var array<string, TrackingDriver> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
        private readonly array $config,
    ) {}

    /**
     * Resolution order, first match wins:
     *   1. the airline row for this prefix, if it names a driver
     *   2. any configured driver whose prefix list covers the AWB
     *   3. the aggregator / default driver
     */
    public function driverFor(AwbNumber $awb): TrackingDriver
    {
        if ($key = $this->airlineDriverKey($awb->prefix)) {
            $driver = $this->driver($key);

            if ($driver->supports($awb)) {
                return $driver;
            }
        }

        foreach (array_keys($this->config['drivers'] ?? []) as $key) {
            if ($key === ($this->config['default'] ?? null)) {
                continue;
            }

            $driver = $this->driver($key);

            // A prefix-scoped driver only claims its own airlines.
            if (! empty($this->config['drivers'][$key]['prefixes']) && $driver->supports($awb)) {
                return $driver;
            }
        }

        return $this->driver($this->config['default'] ?? 'internal');
    }

    public function driver(?string $key = null): TrackingDriver
    {
        $key ??= $this->config['default'] ?? 'internal';

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $settings = $this->config['drivers'][$key] ?? null;

        if ($settings === null) {
            throw new RuntimeException("No tracking driver [{$key}] is configured. Add it to config/tracking.php.");
        }

        $class = $settings['via'] ?? GenericHttpDriver::class;

        $driver = $class === GenericHttpDriver::class || is_subclass_of($class, GenericHttpDriver::class)
            ? new $class($key, $settings)
            : $this->container->make($class);

        return $this->resolved[$key] = $driver;
    }

    /** @return array<string, string> key => display name */
    public function available(): array
    {
        $out = [];
        foreach (array_keys($this->config['drivers'] ?? []) as $key) {
            try {
                $out[$key] = $this->driver($key)->name();
            } catch (Throwable) {
                // A half-configured driver should not break the airline form.
                $out[$key] = $key;
            }
        }

        return $out;
    }

    public function track(string $rawAwb, bool $forceRefresh = false, ?string $ip = null): TrackingResult
    {
        $awb = AwbNumber::parse($rawAwb);
        $driver = $this->driverFor($awb);
        $startedAt = microtime(true);

        if (! $forceRefresh && $snapshot = $this->freshSnapshot($awb)) {
            $this->log($awb, $driver->key(), true, true, $startedAt, $ip);

            return $snapshot;
        }

        try {
            $result = $driver->track($awb);
        } catch (Throwable $e) {
            $this->log($awb, $driver->key(), false, false, $startedAt, $ip, $e->getMessage());

            // Stale beats nothing: show what we last knew and say when it was read.
            return $this->snapshot($awb, ignoreTtl: true)
                ?? TrackingResult::notFound($awb, $driver->key(), 'Tracking is temporarily unavailable. Please try again shortly.');
        }

        if ($result->found) {
            $this->store($result);
        } elseif ($stale = $this->snapshot($awb, ignoreTtl: true)) {
            // The provider lost it but we have history. Keep showing the history.
            $result = $stale;
        }

        $this->log($awb, $driver->key(), $result->found, $result->fromCache, $startedAt, $ip);

        return $result;
    }

    private function airlineDriverKey(string $prefix): ?string
    {
        try {
            $airline = Airline::query()->where('awb_prefix', $prefix)->where('is_active', true)->first();
        } catch (Throwable) {
            return null; // Table not migrated yet, e.g. during install.
        }

        return $airline?->tracking_driver ?: null;
    }

    private function freshSnapshot(AwbNumber $awb): ?TrackingResult
    {
        return $this->snapshot($awb, ignoreTtl: false);
    }

    private function snapshot(AwbNumber $awb, bool $ignoreTtl): ?TrackingResult
    {
        $row = TrackingSnapshot::query()->where('awb_number', $awb->digits())->first();

        if (! $row) {
            return null;
        }

        $ttl = (int) ($this->config['cache_ttl_minutes'] ?? 20);

        if (! $ignoreTtl && CarbonImmutable::parse($row->fetched_at)->addMinutes($ttl)->isPast()) {
            return null;
        }

        $events = array_map(
            fn (array $e) => TrackingEvent::fromArray($e),
            is_array($row->events) ? $row->events : []
        );

        if ($events === []) {
            return null;
        }

        return TrackingResult::found(
            awb: $awb,
            driver: $row->driver,
            events: $events,
            origin: $row->raw_response['origin'] ?? null,
            destination: $row->raw_response['destination'] ?? null,
            raw: is_array($row->raw_response) ? $row->raw_response : [],
        )->markFromCache(CarbonImmutable::parse($row->fetched_at));
    }

    private function store(TrackingResult $result): void
    {
        $latest = $result->latest();

        TrackingSnapshot::query()->updateOrCreate(
            ['awb_number' => $result->awb->digits()],
            [
                'driver' => $result->driver,
                'latest_status' => $latest?->status->value,
                'latest_location' => $latest?->location,
                'latest_event_at' => $latest?->occurredAt,
                'events' => array_map(fn (TrackingEvent $e) => $e->jsonSerialize(), $result->events),
                'raw_response' => [
                    'origin' => $result->origin,
                    'destination' => $result->destination,
                    'provider' => $result->raw,
                ],
                'fetched_at' => CarbonImmutable::now(),
            ]
        );
    }

    private function log(
        AwbNumber $awb,
        string $driver,
        bool $found,
        bool $fromCache,
        float $startedAt,
        ?string $ip,
        ?string $error = null,
    ): void {
        try {
            TrackingLookup::query()->create([
                'awb_number' => $awb->digits(),
                'driver' => $driver,
                'found' => $found,
                'from_cache' => $fromCache,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $error ? mb_substr($error, 0, 255) : null,
                'ip' => $ip,
                'created_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable) {
            // Never let the audit log break a customer lookup.
        }
    }
}
