<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index()
    {
        return Property::with('reports')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'property_name' => 'required|string|max:255',
            'property_type' => 'required|in:warehouse,apartment,separate house,building',
            'property_usage' => 'required|in:residential,industrial,managerial,commercial',
            'floor_number' => 'required|integer',
            'property_area' => 'required|numeric',
            'number_of_rooms' => 'required|integer',
            'property_isolation_type' => 'required|string|max:255',
            'property_address' => 'required|string|max:255',
            'property_description' => 'nullable|string',
            'number_of_floors' => 'required|integer',
        ]);

        $property = Property::create($request->all());

        return response()->json($property, 201);
    }

    public function show(Property $property)
    {
        return $property;
    }

    public function update(Request $request, Property $property)
    {
        $request->validate([
            'property_name' => 'sometimes|required|string|max:255',
            'property_type' => 'sometimes|required|in:warehouse,apartment,separate house,building',
            'property_usage' => 'sometimes|required|in:residential,industrial,managerial,commercial',
            'floor_number' => 'sometimes|required|integer',
            'property_area' => 'sometimes|required|numeric',
            'number_of_rooms' => 'sometimes|required|integer',
            'property_isolation_type' => 'sometimes|required|string|max:255',
            'property_address' => 'sometimes|required|string|max:255',
            'property_description' => 'nullable|string',
            'number_of_floors' => 'sometimes|required|integer',
        ]);

        $property->update($request->all());

        return response()->json($property, 200);
    }

    public function destroy(Property $property)
    {
        $property->delete();

        return response()->json(null, 204);
    }
}
