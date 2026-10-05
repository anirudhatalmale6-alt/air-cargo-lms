<?php

namespace Database\Seeders;

use App\Models\ChargeType;
use Illuminate\Database\Seeder;

class ChargeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['AIR', 'Air freight', 'per_kg', 2.50],
            ['FSC', 'Fuel surcharge', 'per_kg', 0.60],
            ['SSC', 'Security surcharge', 'per_kg', 0.14],
            ['AWB', 'AWB / documentation fee', 'per_awb', 35.00],
            ['THC', 'Terminal handling', 'per_kg', 0.20],
            ['HND', 'Handling fee', 'flat', 25.00],
            ['CUS', 'Customs clearance', 'flat', 60.00],
            ['DOC', 'Document handling', 'flat', 15.00],
            ['DGR', 'Dangerous goods fee', 'flat', 85.00],
            ['PCK', 'Palletising / packing', 'per_piece', 4.00],
            ['STO', 'Storage / demurrage', 'per_kg', 0.08],
            ['DEL', 'Local delivery', 'flat', 70.00],
        ];

        foreach ($types as [$code, $name, $basis, $rate]) {
            ChargeType::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'basis' => $basis, 'default_rate' => $rate, 'is_active' => true]
            );
        }
    }
}
