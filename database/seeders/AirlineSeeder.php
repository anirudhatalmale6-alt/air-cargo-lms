<?php

namespace Database\Seeders;

use App\Models\Airline;
use Illuminate\Database\Seeder;

class AirlineSeeder extends Seeder
{
    /**
     * A starter set of real IATA accounting prefixes. The client can add, edit or
     * remove any of these from Admin > Airlines.
     */
    public function run(): void
    {
        $airlines = [
            ['020', 'LH', 'DLH', 'Lufthansa Cargo'],
            ['176', 'EK', 'UAE', 'Emirates SkyCargo'],
            ['098', 'AI', 'AIC', 'Air India'],
            ['618', 'SQ', 'SIA', 'Singapore Airlines Cargo'],
            ['157', 'QR', 'QTR', 'Qatar Airways Cargo'],
            ['074', 'KL', 'KLM', 'KLM Cargo'],
            ['125', 'BA', 'BAW', 'British Airways World Cargo'],
            ['160', 'CX', 'CPA', 'Cathay Cargo'],
            ['235', 'TK', 'THY', 'Turkish Cargo'],
            ['001', 'AA', 'AAL', 'American Airlines Cargo'],
        ];

        foreach ($airlines as [$prefix, $iata, $icao, $name]) {
            Airline::query()->updateOrCreate(
                ['awb_prefix' => $prefix],
                [
                    'iata_code' => $iata,
                    'icao_code' => $icao,
                    'name' => $name,
                    // Everything starts on our own records. Point an airline at a
                    // real API from the Airlines screen once credentials exist.
                    // Qatar is left on the demo feed so the carrier-API path can
                    // be seen end to end before any credentials arrive.
                    'tracking_driver' => $prefix === '157' ? 'demo' : 'internal',
                    'is_active' => true,
                ]
            );
        }
    }
}
