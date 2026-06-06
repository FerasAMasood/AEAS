<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\EnergySavingOpportunity;
use Illuminate\Http\Request;

class EnergySavingOpportunitiesController extends Controller
{
    /**
     * Get energy saving opportunities for a property.
     */
    public function index(int $propertyId)
    {
        $property = Property::findOrFail($propertyId);
        $rows = $property->energySavingOpportunities()->orderBy('sort_order')->get();

        return response()->json([
            'opportunities' => $rows->map(fn ($row) => [
                'id' => (string) $row->id,
                'description' => $row->description,
                'implementation_cost' => (float) $row->implementation_cost,
                'saving_kwh_per_year' => (float) $row->saving_kwh_per_year,
                'saving_nis_per_year' => (float) $row->saving_nis_per_year,
            ]),
        ]);
    }

    /**
     * Store (replace) energy saving opportunities for a property.
     */
    public function store(Request $request, int $propertyId)
    {
        $property = Property::findOrFail($propertyId);

        $request->validate([
            'opportunities' => 'required|array',
            'opportunities.*.description' => 'required|string',
            'opportunities.*.implementation_cost' => 'nullable|numeric|min:0',
            'opportunities.*.saving_kwh_per_year' => 'nullable|numeric|min:0',
            'opportunities.*.saving_nis_per_year' => 'nullable|numeric|min:0',
        ]);

        $property->energySavingOpportunities()->delete();

        foreach ($request->opportunities as $index => $item) {
            $property->energySavingOpportunities()->create([
                'description' => $item['description'],
                'implementation_cost' => (float) ($item['implementation_cost'] ?? 0),
                'saving_kwh_per_year' => (float) ($item['saving_kwh_per_year'] ?? 0),
                'saving_nis_per_year' => (float) ($item['saving_nis_per_year'] ?? 0),
                'sort_order' => $index,
            ]);
        }

        $rows = $property->energySavingOpportunities()->orderBy('sort_order')->get();

        return response()->json([
            'message' => 'Energy saving opportunities saved successfully',
            'opportunities' => $rows->map(fn ($row) => [
                'id' => (string) $row->id,
                'description' => $row->description,
                'implementation_cost' => (float) $row->implementation_cost,
                'saving_kwh_per_year' => (float) $row->saving_kwh_per_year,
                'saving_nis_per_year' => (float) $row->saving_nis_per_year,
            ]),
        ]);
    }
}
