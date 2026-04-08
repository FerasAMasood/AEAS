<?php

namespace Database\Seeders;

use App\Models\EnergySavingOpportunity;
use App\Models\Property;
use Illuminate\Database\Seeder;

class EnergySavingOpportunitiesSeeder extends Seeder
{
    /**
     * Seed energy saving opportunities for property 4 (initial table data from requirements).
     */
    public function run(): void
    {
        $propertyId = 4;

        if (! Property::find($propertyId)) {
            return;
        }

        EnergySavingOpportunity::where('property_id', $propertyId)->delete();

        $rows = [
            ['Replacement fluorescent lights with LED', 960, 2297, 1561],
            ['Delamping', 0, 4094, 2783.92],
            ['Install New LED Units Instead Of Fault Units.', 550, 963, 655],
            ['Changing set points', 0, 19817, 13476],
            ['Install plastic air conditioner curtains', 1500, 19817, 13476],
            ['Retrofit old and Fault air conditioner with new saving units', 69380, 11813, 8033],
            ['replace the CSD air compressor with VSD energy savings', 2100, 2829, 1924],
            ['rooftop insulation', 1000, 2829, 1924],
        ];

        foreach ($rows as $index => $row) {
            EnergySavingOpportunity::create([
                'property_id' => $propertyId,
                'description' => $row[0],
                'implementation_cost' => $row[1],
                'saving_kwh_per_year' => $row[2],
                'saving_nis_per_year' => $row[3],
                'sort_order' => $index,
            ]);
        }
    }
}
