<?php

namespace App\Services\Tracking\Drivers;

use App\Enums\ShipmentStatus;
use App\Services\Tracking\Contracts\TrackingDriver;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use App\Support\AwbNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A whole airline or aggregator integration expressed as configuration.
 *
 * Most air cargo tracking APIs are "GET/POST a URL with the AWB, get back JSON
 * containing a list of movements". Rather than a new class per airline, this
 * driver takes the endpoint, the auth, and a dotted field map, so a new provider
 * is usually just another block in config/tracking.php. When a provider is
 * genuinely odd (SOAP, XML, two step auth) you subclass and override request()
 * or parse() only.
 */
class GenericHttpDriver implements TrackingDriver
{
    public function __construct(
        protected string $key,
        protected array $config,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->config['name'] ?? ucfirst(str_replace('_', ' ', $this->key));
    }

    public function supports(AwbNumber $awb): bool
    {
        $prefixes = $this->config['prefixes'] ?? null;

        // No prefix list means the provider is an aggregator: it takes anything.
        return $prefixes === null || in_array($awb->prefix, (array) $prefixes, true);
    }

    public function track(AwbNumber $awb): TrackingResult
    {
        if (empty($this->config['endpoint'])) {
            return TrackingResult::notFound(
                $awb,
                $this->key(),
                "The {$this->name()} integration has no endpoint configured yet."
            );
        }

        try {
            $payload = $this->request($awb);
        } catch (Throwable $e) {
            Log::warning('Air cargo tracking lookup failed', [
                'driver' => $this->key(), 'awb' => $awb->digits(), 'error' => $e->getMessage(),
            ]);

            return TrackingResult::notFound(
                $awb,
                $this->key(),
                "{$this->name()} could not be reached. The last known status is shown if we have one."
            );
        }

        if ($payload === null) {
            return TrackingResult::notFound($awb, $this->key());
        }

        return $this->parse($awb, $payload);
    }

    protected function request(AwbNumber $awb): ?array
    {
        $method = strtoupper($this->config['method'] ?? 'GET');
        $url = $this->interpolate($this->config['endpoint'], $awb);

        $request = Http::asJson()
            ->timeout((int) ($this->config['timeout'] ?? 12))
            ->retry((int) ($this->config['retries'] ?? 1), 300, throw: false)
            ->withHeaders($this->headers());

        $params = [];
        foreach ((array) ($this->config['params'] ?? []) as $name => $value) {
            $params[$name] = $this->interpolate((string) $value, $awb);
        }

        $response = $method === 'GET'
            ? $request->get($url, $params)
            : $request->send($method, $url, ['json' => $params]);

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $response->json() ?? [];
    }

    protected function headers(): array
    {
        $headers = (array) ($this->config['headers'] ?? []);

        // Keys live in .env, never in the repo. config/tracking.php reads env()
        // and the resolved value lands here.
        if (! empty($this->config['api_key']) && ! empty($this->config['api_key_header'])) {
            $headers[$this->config['api_key_header']] = $this->config['api_key'];
        }

        if (! empty($this->config['bearer_token'])) {
            $headers['Authorization'] = 'Bearer '.$this->config['bearer_token'];
        }

        return $headers;
    }

    protected function interpolate(string $template, AwbNumber $awb): string
    {
        return str_replace(
            ['{awb}', '{prefix}', '{serial}', '{running_serial}', '{awb_formatted}'],
            [$awb->digits(), $awb->prefix, $awb->serial, $awb->runningSerial(), $awb->formatted()],
            $template
        );
    }

    protected function parse(AwbNumber $awb, array $payload): TrackingResult
    {
        $map = (array) ($this->config['map'] ?? []);
        $rows = Arr::get($payload, $map['events'] ?? 'events');

        if (! is_array($rows) || $rows === []) {
            return TrackingResult::notFound($awb, $this->key());
        }

        $events = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $when = Arr::get($row, $map['event_time'] ?? 'timestamp');
            if (! $when) {
                continue;
            }

            $rawCode = Arr::get($row, $map['event_status'] ?? 'status');
            $status = $this->mapStatus((string) $rawCode);

            if (! $status) {
                continue;
            }

            try {
                $occurredAt = CarbonImmutable::parse($when);
            } catch (Throwable) {
                continue;
            }

            $events[] = new TrackingEvent(
                status: $status,
                occurredAt: $occurredAt,
                location: $this->airport(Arr::get($row, $map['event_location'] ?? 'location')),
                flightNumber: Arr::get($row, $map['event_flight'] ?? 'flight'),
                pieces: ($p = Arr::get($row, $map['event_pieces'] ?? 'pieces')) !== null ? (int) $p : null,
                weightKg: ($w = Arr::get($row, $map['event_weight'] ?? 'weight')) !== null ? (float) $w : null,
                description: Arr::get($row, $map['event_description'] ?? 'description'),
                rawCode: $rawCode !== null ? (string) $rawCode : null,
            );
        }

        if ($events === []) {
            return TrackingResult::notFound(
                $awb,
                $this->key(),
                'The provider answered but none of the movements could be understood. Check the status map for this airline.'
            );
        }

        return TrackingResult::found(
            awb: $awb,
            driver: $this->key(),
            events: $events,
            origin: $this->airport(Arr::get($payload, $map['origin'] ?? 'origin')),
            destination: $this->airport(Arr::get($payload, $map['destination'] ?? 'destination')),
            pieces: ($p = Arr::get($payload, $map['pieces'] ?? 'pieces')) !== null ? (int) $p : null,
            weightKg: ($w = Arr::get($payload, $map['weight'] ?? 'weight')) !== null ? (float) $w : null,
            raw: $payload,
        );
    }

    /**
     * Provider status code to our canonical milestone. The per provider
     * status_map wins; otherwise we fall back to Cargo-IMP codes and plain words.
     */
    protected function mapStatus(string $rawCode): ?ShipmentStatus
    {
        $map = (array) ($this->config['status_map'] ?? []);

        foreach ($map as $code => $target) {
            if (strcasecmp((string) $code, $rawCode) === 0) {
                return ShipmentStatus::tryFrom($target);
            }
        }

        return ShipmentStatus::tryFromLabelish($rawCode);
    }

    protected function airport(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $value) ?? '');

        return strlen($code) >= 3 ? substr($code, 0, 3) : null;
    }
}
