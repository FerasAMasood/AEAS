<?php

namespace App\Http\Controllers;

use App\Models\PropertyDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

        return response()->json(['message' => 'Property devices added successfully.']);
    }
}
