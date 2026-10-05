<?php

use App\Services\Tracking\Drivers\DemoDriver;
use App\Services\Tracking\Drivers\GenericHttpDriver;
use App\Services\Tracking\Drivers\InternalDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    | Used when no airline row and no prefix-scoped driver claims the AWB.
    | Point this at your aggregator once you have one.
    */

    'default' => env('TRACKING_DEFAULT', 'internal'),

    'cache_ttl_minutes' => (int) env('TRACKING_CACHE_TTL', 20),

    // The demo feed only answers if this is on. Turn it off in production.
    'demo_enabled' => (bool) env('TRACKING_DEMO', true),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    | ADDING AN AIRLINE OR PROVIDER: copy a block below, set 'prefixes' to the
    | airline's 3 digit AWB prefix(es), fill in the endpoint, auth and 'map',
    | then pick it in Admin > Airlines. No application code has to change.
    |
    | 'map' is dotted-path notation into the provider's JSON response.
    | 'status_map' translates the provider's status codes into our milestones
    | (see App\Enums\ShipmentStatus). Anything unmapped falls back to matching
    | Cargo-IMP codes (RCS, DEP, ARR, NFD, DLV) and plain English words.
    */

    'drivers' => [

        'internal' => [
            'via' => InternalDriver::class,
            'name' => 'Internal operations records',
        ],

        'demo' => [
            'via' => DemoDriver::class,
            'name' => 'Demo tracking feed (no live airline)',
        ],

        /*
        | Aggregator example. One account covers many airlines, so no 'prefixes'
        | key: it claims any AWB it is asked about.
        */
        'aggregator' => [
            'via' => GenericHttpDriver::class,
            'name' => env('TRACKING_AGGREGATOR_NAME', 'Air cargo tracking aggregator'),
            'endpoint' => env('TRACKING_AGGREGATOR_URL'),
            'method' => 'GET',
            'params' => ['awb' => '{awb}'],
            'api_key' => env('TRACKING_AGGREGATOR_KEY'),
            'api_key_header' => env('TRACKING_AGGREGATOR_KEY_HEADER', 'X-API-Key'),
            'timeout' => 12,
            'retries' => 2,
            'map' => [
                'origin' => 'shipment.origin',
                'destination' => 'shipment.destination',
                'pieces' => 'shipment.pieces',
                'weight' => 'shipment.weight',
                'events' => 'shipment.events',
                'event_status' => 'code',
                'event_time' => 'timestamp',
                'event_location' => 'airport',
                'event_flight' => 'flight',
                'event_pieces' => 'pieces',
                'event_weight' => 'weight',
                'event_description' => 'text',
            ],
            'status_map' => [
                'RCS' => 'received',
                'MAN' => 'manifested',
                'DEP' => 'departed',
                'TRM' => 'in_transit',
                'ARR' => 'arrived',
                'CCD' => 'customs_cleared',
                'NFD' => 'ready_for_delivery',
                'DLV' => 'delivered',
            ],
        ],

        /*
        | Single airline example, prefix scoped. 020 is Lufthansa Cargo.
        | Left endpoint-less on purpose: it reports "not configured yet" instead
        | of pretending, until real credentials arrive.
        */
        'lufthansa' => [
            'via' => GenericHttpDriver::class,
            'name' => 'Lufthansa Cargo',
            'prefixes' => ['020'],
            'endpoint' => env('TRACKING_LH_URL'),
            'method' => 'GET',
            'params' => ['awbNumber' => '{running_serial}', 'awbPrefix' => '{prefix}'],
            'bearer_token' => env('TRACKING_LH_TOKEN'),
            'map' => [
                'events' => 'shipmentDetails.statusEvents',
                'event_status' => 'statusCode',
                'event_time' => 'eventDate',
                'event_location' => 'station',
                'event_flight' => 'flightNumber',
                'event_description' => 'statusDescription',
            ],
        ],

    ],

];
