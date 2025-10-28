<?php

namespace App\Http\Controllers;

use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TariffController extends Controller
{
    /**
     * Display a listing of the tariffs.
     */
    public function index()
    {
        $tariffs = Tariff::with(['report', 'source'])->get();
        return response()->json($tariffs);
    }

    /**
     * Store a newly created tariff in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'source_id' => 'required|exists:energy_sources,id',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $tariff = Tariff::create($validated);
        return response()->json($tariff, 201);
    }

    /**
     * Display the specified tariff.
     */
    public function show(Tariff $tariff)
    {
        return response()->json($tariff->load(['report', 'source']));
    }

    /**
     * Update the specified tariff in storage.
     */
    public function update(Request $request, Tariff $tariff)
    {
        $validated = $request->validate([
            'report_id' => 'sometimes|required|exists:reports,id',
            'source_id' => 'sometimes|required|exists:energy_sources,id',
            'unit_cost' => 'sometimes|required|numeric|min:0',
        ]);

        $tariff->update($validated);
        return response()->json($tariff);
    }

    /**
     * Remove the specified tariff from storage.
     */
    public function destroy(Tariff $tariff)
    {
        $tariff->delete();
        return response()->json(null, 204);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'tariffs' => 'required|array',
            'tariffs.*.source_id' => 'required|exists:energy_sources,id',
            'tariffs.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $reportId = $validated['report_id'];
        $tariffs = $validated['tariffs'];

        // Use a transaction to ensure atomicity
        DB::transaction(function () use ($reportId, $tariffs) {
            // Delete existing tariffs for the report_id
            Tariff::where('report_id', $reportId)->delete();

            // Prepare new tariffs for insertion
            $newTariffs = array_map(function ($tariff) use ($reportId) {
                return [
                    'report_id' => $reportId,
                    'source_id' => $tariff['source_id'],
                    'unit_cost' => $tariff['unit_cost'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $tariffs);

            // Insert new tariffs in bulk
            Tariff::insert($newTariffs);
        });

        return response()->json(['message' => 'Tariffs updated successfully'], 200);
    }
}
