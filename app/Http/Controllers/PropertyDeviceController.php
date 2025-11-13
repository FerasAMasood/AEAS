<?php

namespace App\Http\Controllers;

use App\Models\PropertyDevice;
use App\Models\Property;
use App\Services\ElectricityBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PropertyDeviceController extends Controller
{
    public function storeBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'items' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $propertyId = $request->property_id;
        $items = json_decode($request->input('items'), true);

        $propertyDevices = [];

        foreach ($items as $categoryId => $devices) {
            foreach ($devices as $device) {
                $propertyDevices[] = [
                    'property_id' => $propertyId,
                    'category_id' => $categoryId,
                    'device_key' => $device['device_key'], // Make sure device_key is provided in each device
                    'description' => $device['description'] ?? null,
                    'notes' => $device['notes'] ?? null,
                    'factor' => $device['factor'],
                    'power' => $device['wattage'],
                    'quantity' => $device['quantity'],
                    'operation_hours' => $device['op_hours'],
                    'total_consumption' => $device['total_consumption'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        PropertyDevice::insert($propertyDevices);

        // Auto-trigger electricity balance analysis if this is the first time saving devices
        try {
            $property = Property::find($propertyId);
            if ($property && !$property->electricity_balance) {
                // First time saving devices - auto-generate balance
                $balanceService = new ElectricityBalanceService();
                $balanceData = $balanceService->calculateBalance($propertyId);
                if ($balanceData) {
                    $analysis = $balanceService->analyzeBalance($propertyId, $balanceData);
                    if ($analysis) {
                        $balanceService->storeBalance($propertyId, $balanceData, $analysis);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to auto-generate electricity balance after saving devices: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Property devices added successfully.']);
    }

    public function index(Request $request)
    {
        $query = PropertyDevice::with(['property', 'category', 'device']);

        // Filter by property_id if provided
        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $propertyDevices = $query->get();

        return response()->json($propertyDevices);
    }

    public function show($id)
    {
        $propertyDevice = PropertyDevice::with(['property', 'category', 'device'])->findOrFail($id);
        return response()->json($propertyDevice);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'category_id' => 'required|exists:lookups,id',
            'device_key' => 'required|string',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'factor' => 'required|numeric',
            'power' => 'required|numeric',
            'quantity' => 'required|integer',
            'operation_hours' => 'required|numeric',
            'total_consumption' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $propertyDevice = PropertyDevice::create($request->all());

        return response()->json(['message' => 'Property device created successfully.', 'data' => $propertyDevice], 201);
    }

    public function update(Request $request, $id)
    {
        $propertyDevice = PropertyDevice::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'property_id' => 'sometimes|exists:properties,id',
            'category_id' => 'sometimes|exists:lookups,id',
            'device_key' => 'sometimes|string',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'factor' => 'sometimes|numeric',
            'power' => 'sometimes|numeric',
            'quantity' => 'sometimes|integer',
            'operation_hours' => 'sometimes|numeric',
            'total_consumption' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $propertyDevice->update($request->all());

        return response()->json(['message' => 'Property device updated successfully.', 'data' => $propertyDevice]);
    }

    public function destroy($id)
    {
        $propertyDevice = PropertyDevice::findOrFail($id);
        $propertyDevice->delete();

        return response()->json(['message' => 'Property device deleted successfully.']);
    }
}
