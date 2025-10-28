<?php

namespace App\Http\Controllers;

use App\Models\EnergySource;
use Illuminate\Http\Request;

class EnergySourceController extends Controller
{
    /**
     * Display a listing of the energy sources.
     */
    public function index()
    {
        return response()->json(EnergySource::all());
    }

    /**
     * Store a newly created energy source in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:255',
            'type' => 'required|in:thermal,electricity',
        ]);

        $energySource = EnergySource::create($validated);

        return response()->json($energySource, 201);
    }

    /**
     * Display the specified energy source.
     */
    public function show(EnergySource $energySource)
    {
        return response()->json($energySource);
    }

    /**
     * Update the specified energy source in storage.
     */
    public function update(Request $request, EnergySource $energySource)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:thermal,electricity',
        ]);

        $energySource->update($validated);

        return response()->json($energySource);
    }

    /**
     * Remove the specified energy source from storage.
     */
    public function destroy(EnergySource $energySource)
    {
        $energySource->delete();

        return response()->json(null, 204);
    }
}
