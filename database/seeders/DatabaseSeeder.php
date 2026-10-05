<?php

namespace Database\Seeders;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Airline;
use App\Models\ChargeType;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentService;
use App\Support\AwbNumber;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AirlineSeeder::class,
            ChargeTypeSeeder::class,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@aircargo.test'],
            [
                'name' => 'System Administrator',
                'role' => UserRole::Admin,
                'password' => 'password',
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'ops@aircargo.test'],
            [
                'name' => 'Operations Desk',
                'role' => UserRole::Staff,
                'password' => 'password',
                'is_active' => true,
            ]
        );

        if (app()->environment('production')) {
            $this->command->warn('Production detected: only the admin, airline and charge type records were seeded.');

            return;
        }

        $this->seedDemoData($admin);
    }

    private function seedDemoData(User $admin): void
    {
        /** @var ShipmentService $service */
        $service = app(ShipmentService::class);

        $customers = collect([
            ['name' => 'Gulf Perishables Trading LLC', 'city' => 'Dubai', 'country' => 'AE', 'default_currency' => 'AED', 'credit_days' => 30],
            ['name' => 'Meridian Pharma Exports', 'city' => 'Mumbai', 'country' => 'IN', 'default_currency' => 'USD', 'credit_days' => 45],
            ['name' => 'Brightline Electronics GmbH', 'city' => 'Frankfurt', 'country' => 'DE', 'default_currency' => 'EUR', 'credit_days' => 14],
            ['name' => 'Southbank Fashion Group', 'city' => 'London', 'country' => 'GB', 'default_currency' => 'GBP', 'credit_days' => 0],
        ])->map(fn (array $attributes) => Customer::query()->firstOrCreate(
            ['name' => $attributes['name']],
            $attributes + ['code' => Customer::nextCode(), 'is_active' => true]
        ));

        User::query()->updateOrCreate(
            ['email' => 'customer@aircargo.test'],
            [
                'name' => 'Gulf Perishables (customer login)',
                'role' => UserRole::Customer,
                'customer_id' => $customers->first()->id,
                'password' => 'password',
                'is_active' => true,
            ]
        );

        if (Shipment::query()->exists()) {
            $this->command->info('Demo shipments already present, left untouched.');

            return;
        }

        $lufthansa = Airline::where('awb_prefix', '020')->first();
        $emirates = Airline::where('awb_prefix', '176')->first();
        $airIndia = Airline::where('awb_prefix', '098')->first();

        $plan = [
            [
                'direction' => 'export', 'airline' => $lufthansa, 'serial' => 4471182,
                'customer' => $customers[1], 'origin' => 'BOM', 'destination' => 'FRA',
                'flight' => 'LH761', 'pieces' => 18, 'gross' => 642.50, 'volume' => 3.85,
                'commodity' => 'Pharmaceutical formulations', 'incoterm' => 'CIF',
                'shipper' => 'Meridian Pharma Exports', 'consignee' => 'Rhein Apotheke Logistik GmbH',
                'reach' => 5,
            ],
            [
                'direction' => 'export', 'airline' => $emirates, 'serial' => 2290517,
                'customer' => $customers[0], 'origin' => 'BOM', 'destination' => 'DXB',
                'flight' => 'EK507', 'pieces' => 96, 'gross' => 1880.00, 'volume' => 9.20,
                'commodity' => 'Fresh grapes, chilled', 'incoterm' => 'CFR',
                'shipper' => 'Nashik Vineyards Co-op', 'consignee' => 'Gulf Perishables Trading LLC',
                'reach' => 7, 'dg' => false,
            ],
            [
                'direction' => 'import', 'airline' => $lufthansa, 'serial' => 4471215,
                'customer' => $customers[2], 'origin' => 'FRA', 'destination' => 'BOM',
                'flight' => 'LH756', 'pieces' => 7, 'gross' => 211.80, 'volume' => 1.40,
                'commodity' => 'Semiconductor test equipment', 'incoterm' => 'DAP',
                'shipper' => 'Brightline Electronics GmbH', 'consignee' => 'Pune Instruments Pvt Ltd',
                'reach' => 4,
            ],
            [
                'direction' => 'import', 'airline' => $airIndia, 'serial' => 7718340,
                'customer' => $customers[3], 'origin' => 'LHR', 'destination' => 'DEL',
                'flight' => 'AI112', 'pieces' => 42, 'gross' => 905.00, 'volume' => 11.60,
                'commodity' => 'Ready made garments', 'incoterm' => 'FOB',
                'shipper' => 'Southbank Fashion Group', 'consignee' => 'Delhi Retail Partners',
                'reach' => 3,
            ],
            [
                'direction' => 'export', 'airline' => $emirates, 'serial' => 2290524,
                'customer' => $customers[0], 'origin' => 'DEL', 'destination' => 'SIN',
                'flight' => 'EK349', 'pieces' => 4, 'gross' => 88.40, 'volume' => 0.52,
                'commodity' => 'Lithium ion batteries, UN3480', 'incoterm' => 'EXW',
                'shipper' => 'Volt Cells India', 'consignee' => 'Singapore Power Devices',
                'reach' => 2, 'dg' => true,
            ],
        ];

        $milestones = [
            ShipmentStatus::Booked, ShipmentStatus::Received, ShipmentStatus::Manifested,
            ShipmentStatus::Departed, ShipmentStatus::Arrived, ShipmentStatus::CustomsCleared,
            ShipmentStatus::Delivered,
        ];

        foreach ($plan as $i => $row) {
            if (! $row['airline']) {
                continue;
            }

            $volumetric = $row['volume'] * config('aircargo.volumetric_factor');
            $flightDate = now()->subDays(14 - $i * 3);

            $shipment = Shipment::create([
                'reference' => Shipment::nextReference($row['direction']),
                'direction' => $row['direction'],
                'customer_id' => $row['customer']->id,
                'airline_id' => $row['airline']->id,
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'flight_number' => $row['flight'],
                'flight_date' => $flightDate->toDateString(),
                'eta' => $flightDate->copy()->addDay()->toDateString(),
                'pieces' => $row['pieces'],
                'gross_weight_kg' => $row['gross'],
                'volume_cbm' => $row['volume'],
                'chargeable_weight_kg' => round(max($row['gross'], $volumetric), 2),
                'commodity' => $row['commodity'],
                'nature_of_goods' => $row['commodity'],
                'incoterm' => $row['incoterm'],
                'payment_term' => $i % 2 === 0 ? 'prepaid' : 'collect',
                'is_dangerous_goods' => $row['dg'] ?? false,
                'shipper_name' => $row['shipper'],
                'consignee_name' => $row['consignee'],
                'currency' => $row['customer']->default_currency,
                'created_by' => $admin->id,
                'current_status' => ShipmentStatus::Draft,
            ]);

            $awb = AwbNumber::fromRunningSerial($row['airline']->awb_prefix, $row['serial']);
            $service->assignAwb($shipment, $awb->digits(), $row['airline']);

            $cursor = $flightDate->copy()->subDays(2);

            foreach (array_slice($milestones, 0, $row['reach']) as $step => $status) {
                $cursor = $cursor->addHours(9 + $step * 5);

                $service->recordStatus(
                    shipment: $shipment,
                    status: $status,
                    occurredAt: $cursor,
                    location: in_array($status, [ShipmentStatus::Arrived, ShipmentStatus::CustomsCleared, ShipmentStatus::Delivered], true)
                        ? $row['destination']
                        : $row['origin'],
                    flightNumber: in_array($status, [ShipmentStatus::Manifested, ShipmentStatus::Departed, ShipmentStatus::Arrived], true)
                        ? $row['flight']
                        : null,
                    pieces: $row['pieces'],
                    weightKg: $row['gross'],
                    description: match ($status) {
                        ShipmentStatus::Booked => 'Space confirmed with the carrier',
                        ShipmentStatus::Received => 'Cargo received at the export terminal',
                        ShipmentStatus::Manifested => 'Manifested on '.$row['flight'],
                        ShipmentStatus::Departed => 'Departed '.$row['origin'].' on '.$row['flight'],
                        ShipmentStatus::Arrived => 'Arrived '.$row['destination'],
                        ShipmentStatus::CustomsCleared => 'Customs clearance completed',
                        ShipmentStatus::Delivered => 'Delivered and signed for',
                        default => null,
                    },
                    user: $admin,
                );
            }

            $this->seedCharges($shipment);
        }

        // A little free stock so the AWB screen is not empty on first run.
        foreach ([['020', 4471200], ['176', 2290600]] as [$prefix, $from]) {
            if ($airline = Airline::where('awb_prefix', $prefix)->first()) {
                $service->generateAwbStock($airline, $from, 25);
            }
        }

        $this->command->info('Seeded '.Shipment::count().' demo shipments with AWBs, status history and charges.');
    }

    private function seedCharges(Shipment $shipment): void
    {
        $chargeable = (float) $shipment->chargeable_weight_kg;

        $lines = [
            ['AIR', 'per_kg', 2.85],
            ['FSC', 'per_kg', 0.65],
            ['SSC', 'per_kg', 0.14],
            ['AWB', 'per_awb', 35.00],
            ['THC', 'per_kg', 0.22],
        ];

        foreach ($lines as [$code, $basis, $rate]) {
            $type = ChargeType::where('code', $code)->first();

            if (! $type) {
                continue;
            }

            $quantity = $basis === 'per_kg' ? $chargeable : 1;

            $shipment->charges()->create([
                'charge_type_id' => $type->id,
                'description' => $type->name,
                'basis' => $basis,
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => round($quantity * $rate, 2),
                'currency' => $shipment->currency,
                'side' => 'receivable',
            ]);
        }

        $shipment->recalculateChargesTotal();
    }
}
