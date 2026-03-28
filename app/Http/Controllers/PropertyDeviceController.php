<?php

namespace App\Http\Controllers;

use App\Models\PropertyDevice;
use App\Models\Property;
use App\Services\ElectricityBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        $query = PropertyDevice::with([
            'property', 
            'category', 
            'device' => function($q) use ($request) {
                // Include category_id in the device lookup to prevent collisions
                // when same device_key exists in different categories
                // Join property_devices to access category_id for filtering
                $q->join('property_devices', 'lookups.lookup_key', '=', 'property_devices.device_key')
                  ->whereColumn('lookups.category', 'property_devices.category_id')
                  ->where('lookups.lookup_table', 'property_devices')
                  ->where('lookups.lookup_field', 'devices')
                  ->select('lookups.*');
            }
        ]);

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

    /**
     * Get devices grouped by description for recommendations.
     */
    public function getRecommendationsData(Request $request, Property $property, int $categoryId)
    {
        // Get all devices for this property and category
        $devices = PropertyDevice::with(['device', 'category'])
            ->where('property_id', $property->id)
            ->where('category_id', $categoryId)
            ->get();

        if ($devices->isEmpty()) {
            return response()->json(['error' => 'No devices found for this category.'], 404);
        }

        // Get category name
        $category = $devices->first()->category;
        $categoryName = $category ? $category->lookup_value : 'Unknown Category';

        // Group by description (trimmed and lowercase)
        $grouped = [];
        
        foreach ($devices as $device) {
            // Normalize description: trim and convert to lowercase
            $normalizedDescription = strtolower(trim($device->description ?? ''));
            
            // Use empty string as key if description is null or empty
            if ($normalizedDescription === '') {
                $normalizedDescription = '';
            }
            
            if (!isset($grouped[$normalizedDescription])) {
                $grouped[$normalizedDescription] = [
                    'description' => $device->description ?? '',
                    'devices' => [],
                    'notes' => [],
                ];
            }
            
            // Add device data
            $grouped[$normalizedDescription]['devices'][] = [
                'id' => $device->id,
                'device_key' => $device->device_key,
                'device_name' => $device->device ? $device->device->lookup_value : $device->device_key,
                'power' => $device->power,
                'quantity' => $device->quantity,
                'factor' => $device->factor,
                'operation_hours' => $device->operation_hours,
                'total_consumption' => $device->total_consumption,
                'notes' => $device->notes,
            ];
            
            // Collect notes (we'll process them later)
            if ($device->notes && trim($device->notes) !== '') {
                $grouped[$normalizedDescription]['notes'][] = trim($device->notes);
            }
        }
        
        // Process notes: concatenate if different, keep one if identical
        $groupedData = [];
        foreach ($grouped as $key => $group) {
            $notes = array_unique($group['notes']); // Remove duplicates
            
            if (count($notes) > 1) {
                // Multiple different notes - concatenate them
                $finalNotes = implode(' | ', $notes);
            } elseif (count($notes) === 1) {
                // Single note - keep it
                $finalNotes = $notes[0];
            } else {
                // No notes
                $finalNotes = null;
            }
            
            $groupedData[$key] = [
                'description' => $group['description'],
                'devices' => $group['devices'],
                'notes' => $finalNotes,
            ];
        }

        // Get electricity balance
        $balanceService = new ElectricityBalanceService();
        $electricityBalance = $balanceService->calculateBalance($property->id);
        
        // Calculate category consumption and percentage
        $categoryTotalConsumption = $devices->sum('total_consumption');
        $totalPropertyConsumption = 0;
        $categoryPercentage = 0;
        
        if ($electricityBalance) {
            // Find total from balance data
            foreach ($electricityBalance as $item) {
                if ($item['load_type'] === 'Total') {
                    $totalPropertyConsumption = $item['total_consumption_kwh'];
                    break;
                }
            }
            
            // Find category percentage
            foreach ($electricityBalance as $item) {
                if ($item['load_type'] === $categoryName) {
                    $categoryPercentage = $item['percentage'];
                    break;
                }
            }
        }

        // Get electricity tariff (cost per kWh) - try to get from latest report
        $latestReport = $property->reports()->latest()->first();
        $electricityCostPerKwh = 0.68; // Default fallback (NIS per kWh)
        
        if ($latestReport) {
            $electricityTariff = \App\Models\Tariff::where('report_id', $latestReport->id)
                ->with('source')
                ->get()
                ->first(function($tariff) {
                    return $tariff->source && $tariff->source->type === 'electricity';
                });
            
            if ($electricityTariff) {
                $electricityCostPerKwh = $electricityTariff->unit_cost;
            }
        }

        // Calculate category cost
        $categoryCost = $categoryTotalConsumption * $electricityCostPerKwh;

        // Calculate replacement analysis (e.g., fluorescent -> LED)
        $replacementAnalysis = $this->calculateReplacementAnalysis($devices, $categoryName, $categoryTotalConsumption, $categoryPercentage, $electricityCostPerKwh);

        // Calculate delamping analysis (for lighting category)
        $delampingAnalysis = [];
        if (strtolower($categoryName) === 'lighting' || strtolower($categoryName) === 'إضاءة') {
            $delampingAnalysis = $this->calculateDelampingAnalysis($devices, $electricityCostPerKwh);
        }

        // Get property name
        $propertyName = $property->property_name ?? $property->name ?? 'the building';

        // Prepare data for OpenAI
        $promptData = [
            'category_name' => $categoryName,
            'category_consumption_kwh' => round($categoryTotalConsumption, 3),
            'category_percentage' => $categoryPercentage,
            'category_cost_nis' => round($categoryCost, 2),
            'electricity_cost_per_kwh' => $electricityCostPerKwh,
            'property_name' => $propertyName,
            'grouped_devices' => $groupedData,
            'electricity_balance' => $electricityBalance,
            'replacement_analysis' => $replacementAnalysis,
            'delamping_analysis' => $delampingAnalysis,
        ];

        return response()->json([
            'grouped_data' => $groupedData,
            'category_info' => [
                'name' => $categoryName,
                'consumption_kwh' => round($categoryTotalConsumption, 3),
                'percentage' => $categoryPercentage,
                'cost_nis' => round($categoryCost, 2),
            ],
            'replacement_analysis' => $replacementAnalysis,
            'delamping_analysis' => $delampingAnalysis,
            'electricity_cost_per_kwh' => $electricityCostPerKwh,
            'property_name' => $propertyName,
        ]);
    }

    /**
     * Generate summary paragraph only
     */
    public function generateSummary(Request $request, Property $property, int $categoryId)
    {
        // Increase execution time limit for OpenAI API calls
        set_time_limit(120); // 2 minutes
        // Get devices and calculate basic info
        $devices = PropertyDevice::with(['device', 'category'])
            ->where('property_id', $property->id)
            ->where('category_id', $categoryId)
            ->get();

        if ($devices->isEmpty()) {
            return response()->json(['error' => 'No devices found for this category.'], 404);
        }

        $category = $devices->first()->category;
        $categoryName = $category ? $category->lookup_value : 'Unknown Category';
        $categoryTotalConsumption = $devices->sum('total_consumption');

        $balanceService = new ElectricityBalanceService();
        $electricityBalance = $balanceService->calculateBalance($property->id);
        
        $totalPropertyConsumption = 0;
        $categoryPercentage = 0;
        
        if ($electricityBalance) {
            foreach ($electricityBalance as $item) {
                if ($item['load_type'] === 'Total') {
                    $totalPropertyConsumption = $item['total_consumption_kwh'];
                    break;
                }
            }
            foreach ($electricityBalance as $item) {
                if ($item['load_type'] === $categoryName) {
                    $categoryPercentage = $item['percentage'];
                    break;
                }
            }
        }

        $latestReport = $property->reports()->latest()->first();
        $electricityCostPerKwh = 0.68;
        
        if ($latestReport) {
            $electricityTariff = \App\Models\Tariff::where('report_id', $latestReport->id)
                ->with('source')
                ->get()
                ->first(function($tariff) {
                    return $tariff->source && $tariff->source->type === 'electricity';
                });
            
            if ($electricityTariff) {
                $electricityCostPerKwh = $electricityTariff->unit_cost;
            }
        }

        $categoryCost = $categoryTotalConsumption * $electricityCostPerKwh;
        $propertyName = $property->property_name ?? $property->name ?? 'the building';

        $summary = $this->generateSummaryParagraph([
            'category_name' => $categoryName,
            'category_consumption_kwh' => round($categoryTotalConsumption, 3),
            'category_percentage' => $categoryPercentage,
            'category_cost_nis' => round($categoryCost, 2),
            'electricity_cost_per_kwh' => $electricityCostPerKwh,
            'property_name' => $propertyName,
            'electricity_balance' => $electricityBalance,
        ]);

        return response()->json(['summary_paragraph' => $summary]);
    }

    /**
     * Generate replacement recommendations with table data
     */
    public function generateReplacements(Request $request, Property $property, int $categoryId)
    {
        // Increase execution time limit for OpenAI API calls
        set_time_limit(120); // 2 minutes
        $devices = PropertyDevice::with(['device', 'category'])
            ->where('property_id', $property->id)
            ->where('category_id', $categoryId)
            ->get();

        if ($devices->isEmpty()) {
            return response()->json(['error' => 'No devices found for this category.'], 404);
        }

        $category = $devices->first()->category;
        $categoryName = $category ? $category->lookup_value : 'Unknown Category';
        $categoryTotalConsumption = $devices->sum('total_consumption');

        $balanceService = new ElectricityBalanceService();
        $electricityBalance = $balanceService->calculateBalance($property->id);
        
        $categoryPercentage = 0;
        if ($electricityBalance) {
            foreach ($electricityBalance as $item) {
                if ($item['load_type'] === $categoryName) {
                    $categoryPercentage = $item['percentage'];
                    break;
                }
            }
        }

        $latestReport = $property->reports()->latest()->first();
        $electricityCostPerKwh = 0.68;
        
        if ($latestReport) {
            $electricityTariff = \App\Models\Tariff::where('report_id', $latestReport->id)
                ->with('source')
                ->get()
                ->first(function($tariff) {
                    return $tariff->source && $tariff->source->type === 'electricity';
                });
            
            if ($electricityTariff) {
                $electricityCostPerKwh = $electricityTariff->unit_cost;
            }
        }

        $replacementAnalysis = $this->calculateReplacementAnalysis($devices, $categoryName, $categoryTotalConsumption, $categoryPercentage, $electricityCostPerKwh);
        $propertyName = $property->property_name ?? $property->name ?? 'the building';

        $replacements = $this->generateReplacementRecommendations([
            'category_name' => $categoryName,
            'category_consumption_kwh' => round($categoryTotalConsumption, 3),
            'category_percentage' => $categoryPercentage,
            'electricity_cost_per_kwh' => $electricityCostPerKwh,
            'property_name' => $propertyName,
            'replacement_analysis' => $replacementAnalysis,
        ]);

        return response()->json(['efficient_replacements' => $replacements]);
    }

    /**
     * Generate delamping recommendations with table data
     */
    public function generateDelamping(Request $request, Property $property, int $categoryId)
    {
        // Increase execution time limit for OpenAI API calls
        set_time_limit(120); // 2 minutes
        
        $devices = PropertyDevice::with(['device', 'category'])
            ->where('property_id', $property->id)
            ->where('category_id', $categoryId)
            ->get();

        if ($devices->isEmpty()) {
            return response()->json(['error' => 'No devices found for this category.'], 404);
        }

        $category = $devices->first()->category;
        $categoryName = $category ? $category->lookup_value : 'Unknown Category';


        $latestReport = $property->reports()->latest()->first();
        $electricityCostPerKwh = 0.68;
        
        if ($latestReport) {
            $electricityTariff = \App\Models\Tariff::where('report_id', $latestReport->id)
                ->with('source')
                ->get()
                ->first(function($tariff) {
                    return $tariff->source && $tariff->source->type === 'electricity';
                });
            
            if ($electricityTariff) {
                $electricityCostPerKwh = $electricityTariff->unit_cost;
            }
        }

        $delampingAnalysis = $this->calculateDelampingAnalysis($devices, $electricityCostPerKwh);
        $propertyName = $property->property_name ?? $property->name ?? 'the building';
        
        // Log the delamping analysis data for debugging
        Log::info('Delamping analysis calculated', [
            'property_id' => $property->id,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'devices_count' => $devices->count(),
            'delamping_analysis_count' => count($delampingAnalysis),
            'delamping_analysis' => $delampingAnalysis,
        ]);

        // Trim payload sent to OpenAI to avoid very large prompts/timeouts.
        // Sort rooms by consumption_kwh_year desc (if available) and take top 8.
        $delampingForLLM = $delampingAnalysis;
        if (!empty($delampingAnalysis)) {
            usort($delampingForLLM, function ($a, $b) {
                $aVal = $a['consumption_kwh_year'] ?? 0;
                $bVal = $b['consumption_kwh_year'] ?? 0;
                return $bVal <=> $aVal;
            });
            $originalCount = count($delampingForLLM);
            $delampingForLLM = array_slice($delampingForLLM, 0, 8);
            if ($originalCount > 8) {
                Log::info('Delamping analysis truncated for LLM', [
                    'original_count' => $originalCount,
                    'sent_count' => count($delampingForLLM),
                ]);
            }
        }

        // Check if delamping analysis is empty
        if (empty($delampingAnalysis)) {
            Log::warning('Delamping analysis is empty', [
                'property_id' => $property->id,
                'category_id' => $categoryId,
            ]);
            $response = response()->json([
                'error' => 'No delamping opportunities found for this category.',
                'unnecessary_devices' => []
            ], 200);
            
            $origin = $request->headers->get('Origin', '*');
            return $response->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true');
        }

        try {
            $delamping = $this->generateDelampingRecommendations([
                'property_name' => $propertyName,
                'electricity_cost_per_kwh' => $electricityCostPerKwh,
                'delamping_analysis' => $delampingForLLM,
            ]);
            Log::info('Delamping generation returned', ['delamping' => $delamping]);

            if ($delamping === null) {
                Log::warning('Delamping generation returned null', [
                    'property_id' => $property->id,
                    'category_id' => $categoryId,
                ]);
                $response = response()->json([
                    'error' => 'Failed to generate delamping recommendations. The OpenAI API request timed out. Please try again later.',
                    'unnecessary_devices' => []
                ], 200); // Return 200 with error message instead of 500
            } else {
                $response = response()->json(['unnecessary_devices' => $delamping]);
            }
            
            // Add CORS headers
            $origin = $request->headers->get('Origin', '*');
            return $response->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true');
        } catch (\Exception $e) {
            Log::error('Error in generateDelamping: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'category_id' => $categoryId,
                'trace' => $e->getTraceAsString(),
            ]);
            
            $response = response()->json([
                'error' => 'An error occurred while generating delamping recommendations: ' . $e->getMessage(),
                'unnecessary_devices' => []
            ], 200); // Return 200 with error message instead of 500
            
            // Add CORS headers
            $origin = $request->headers->get('Origin', '*');
            return $response->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * Generate fixes recommendations with table data
     */
    public function generateFixes(Request $request, Property $property, int $categoryId)
    {
        // Increase execution time limit for OpenAI API calls
        set_time_limit(120); // 2 minutes
        $devices = PropertyDevice::with(['device', 'category'])
            ->where('property_id', $property->id)
            ->where('category_id', $categoryId)
            ->get();

        if ($devices->isEmpty()) {
            return response()->json(['error' => 'No devices found for this category.'], 404);
        }

        $category = $devices->first()->category;
        $categoryName = $category ? $category->lookup_value : 'Unknown Category';

        // Group devices by description for fixes
        $grouped = [];
        foreach ($devices as $device) {
            $normalizedDescription = strtolower(trim($device->description ?? ''));
            if ($normalizedDescription === '') {
                $normalizedDescription = '';
            }
            
            if (!isset($grouped[$normalizedDescription])) {
                $grouped[$normalizedDescription] = [
                    'description' => $device->description ?? '',
                    'devices' => [],
                ];
            }
            
            $grouped[$normalizedDescription]['devices'][] = [
                'device_name' => $device->device ? $device->device->lookup_value : $device->device_key,
                'power' => $device->power,
                'quantity' => $device->quantity,
                'notes' => $device->notes,
            ];
        }

        $groupedData = array_values($grouped);
        $propertyName = $property->property_name ?? $property->name ?? 'the building';

        $fixes = $this->generateFixesRecommendations([
            'category_name' => $categoryName,
            'property_name' => $propertyName,
            'grouped_devices' => $groupedData,
        ]);

        return response()->json(['necessary_fixes' => $fixes]);
    }

    /**
     * Calculate delamping analysis for devices
     */
    private function calculateDelampingAnalysis($devices, $electricityCostPerKwh)
    {
        // Group devices by description to combine LED and fluorescent in same room
        $devicesByDescription = [];
        
        foreach ($devices as $device) {
            // For delamping, we need devices with descriptions that indicate room/location
            if (!$device->description || trim($device->description) === '') {
                continue;
            }
            
            $description = trim($device->description);
            
            if (!isset($devicesByDescription[$description])) {
                $devicesByDescription[$description] = [
                    'room_name' => $description,
                    'led_count' => 0,
                    'fluorescent_count' => 0,
                    'led_watt' => 0,
                    'fluorescent_watt' => 0,
                    'operation_time' => $device->operation_hours,
                    'consumption_kwh_year' => 0,
                ];
            }
            
            $deviceName = strtolower($device->device ? $device->device->lookup_value : $device->device_key);
            $isLED = strpos($deviceName, 'led') !== false;
            $isFluorescent = strpos($deviceName, 'fluorescent') !== false;
            
            if ($isLED) {
                $devicesByDescription[$description]['led_count'] += $device->quantity;
                $devicesByDescription[$description]['led_watt'] = $device->power; // Use the power value
            } elseif ($isFluorescent) {
                $devicesByDescription[$description]['fluorescent_count'] += $device->quantity;
                $devicesByDescription[$description]['fluorescent_watt'] = $device->power; // Use the power value
            }
            
            $devicesByDescription[$description]['consumption_kwh_year'] += $device->total_consumption;
        }
        
        // Convert to array format for AI processing
        $delampingData = [];
        foreach ($devicesByDescription as $desc => $data) {
            $delampingData[] = [
                'room_name' => $data['room_name'],
                'lux' => null, // Would need to be provided or calculated
                'target_lux' => null, // Would need to be provided
                'led_count' => $data['led_count'],
                'fluorescent_count' => $data['fluorescent_count'],
                'led_watt' => $data['led_watt'],
                'fluorescent_watt' => $data['fluorescent_watt'],
                'operation_time' => $data['operation_time'],
                'consumption_kwh_year' => round($data['consumption_kwh_year'], 2),
                'delamping_fix_percent' => null, // To be calculated by AI
                'delamping_saving_kwh' => null, // To be calculated by AI
                'delamping_saving_nis' => null, // To be calculated by AI
            ];
        }
        
        return $delampingData;
    }

    /**
     * Calculate replacement analysis for devices (e.g., fluorescent -> LED)
     */
    private function calculateReplacementAnalysis($devices, $categoryName, $categoryTotalConsumption, $categoryPercentage, $electricityCostPerKwh)
    {
        $analysis = [];
        
        // Common replacement mappings
        $replacementMappings = [
            'fluorescent' => [
                'target' => 'LED',
                'efficiency_ratio' => 0.6, // LED uses ~60% of fluorescent power
                'led_bulb_power' => 10, // 10W LED bulb
                'led_tube_power' => 20, // 20W LED tube
                'led_bulb_price' => 6.5, // Average of 5-8 NIS
                'led_tube_price' => 10, // NIS
            ],
        ];
        
        // Identify devices that can be replaced
        foreach ($devices as $device) {
            $deviceName = strtolower($device->device ? $device->device->lookup_value : $device->device_key);
            
            // Check if device matches a replacement mapping
            foreach ($replacementMappings as $oldType => $mapping) {
                if (strpos($deviceName, $oldType) !== false) {
                    $totalUnits = $device->quantity;
                    $oldPower = $device->power;
                    $oldConsumption = $device->total_consumption;
                    $oldCost = $oldConsumption * $electricityCostPerKwh;
                    
                    // Calculate new consumption (assuming LED uses 60% of power)
                    $newPower = $oldPower * $mapping['efficiency_ratio'];
                    $newConsumption = ($newPower * $device->quantity * $device->factor * $device->operation_hours) / 1000;
                    $newCost = $newConsumption * $electricityCostPerKwh;
                    
                    // Calculate savings
                    $savingsKwh = $oldConsumption - $newConsumption;
                    $savingsNis = $oldCost - $newCost;
                    
                    // Use the old type as key to aggregate all devices of the same type
                    $key = $oldType;
                    if (!isset($analysis[$key])) {
                        $analysis[$key] = [
                            'old_type' => ucfirst($oldType),
                            'new_type' => $mapping['target'],
                            'total_units' => $totalUnits,
                            'old_consumption_kwh' => $oldConsumption,
                            'old_cost_nis' => $oldCost,
                            'new_consumption_kwh' => $newConsumption,
                            'new_cost_nis' => $newCost,
                            'savings_kwh' => $savingsKwh,
                            'savings_nis' => $savingsNis,
                        ];
                    } else {
                        // Aggregate if multiple devices of same type
                        $analysis[$key]['total_units'] += $totalUnits;
                        $analysis[$key]['old_consumption_kwh'] += $oldConsumption;
                        $analysis[$key]['old_cost_nis'] += $oldCost;
                        $analysis[$key]['new_consumption_kwh'] += $newConsumption;
                        $analysis[$key]['new_cost_nis'] += $newCost;
                        $analysis[$key]['savings_kwh'] += $savingsKwh;
                        $analysis[$key]['savings_nis'] += $savingsNis;
                    }
                }
            }
        }
        
        // Calculate percentage of category consumption for each replacement type
        foreach ($analysis as $key => &$item) {
            $item['percentage_of_category'] = $categoryTotalConsumption > 0 
                ? round(($item['old_consumption_kwh'] / $categoryTotalConsumption) * 100, 1)
                : 0;
            
            // Round all values
            $item['old_consumption_kwh'] = round($item['old_consumption_kwh'], 1);
            $item['old_cost_nis'] = round($item['old_cost_nis'], 2);
            $item['new_consumption_kwh'] = round($item['new_consumption_kwh'], 1);
            $item['new_cost_nis'] = round($item['new_cost_nis'], 2);
            $item['savings_kwh'] = round($item['savings_kwh'], 1);
            $item['savings_nis'] = round($item['savings_nis'], 2);
        }
        unset($item);
        
        return array_values($analysis);
    }

    /**
     * Generate summary paragraph only
     */
    private function generateSummaryParagraph(array $data): ?string
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $categoryName = $data['category_name'];
            $categoryConsumption = $data['category_consumption_kwh'];
            $categoryPercentage = $data['category_percentage'];
            $categoryCost = $data['category_cost_nis'];
            $propertyName = $data['property_name'] ?? 'the building';

            $prompt = "You are an energy efficiency specialist analyzing a {$categoryName} system.\n\n";
            $prompt .= "System Data:\n";
            $prompt .= "- Property/Building: {$propertyName}\n";
            $prompt .= "- Category: {$categoryName}\n";
            $prompt .= "- Annual consumption: {$categoryConsumption} kWh\n";
            $prompt .= "- Percentage of total property consumption: {$categoryPercentage}%\n";
            $prompt .= "- Annual cost: {$categoryCost} NIS\n";
            $prompt .= "- Electricity cost: {$data['electricity_cost_per_kwh']} NIS/kWh\n\n";
            
            $prompt .= "Electricity Balance (all categories):\n";
            $prompt .= json_encode($data['electricity_balance'], JSON_PRETTY_PRINT) . "\n\n";
            
            $prompt .= "Generate a summary paragraph combining category info and saving opportunities.\n";
            $prompt .= "Format: '[Category] system representing [percentage]% of the annual electricity consumption, The electricity consumption for the [category] system is [consumption] kWh per year, with a cost of [cost] NIS per year. There are [number] opportunities to save energy which is [list opportunities], it will save [total_kwh]kWh/Year and [total_nis] NIS/Year.'\n";
            $prompt .= "Return ONLY the paragraph text, no JSON, no additional formatting.";

            $client = new \GuzzleHttp\Client([
                'timeout' => 90, // Allow more time when using higher max_tokens
                'connect_timeout' => 10,
            ]);
            
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 500,
                ],
            ]);

            $responseBody = (string) $response->getBody();
            $responseData = json_decode($responseBody, true);
            $summary = trim($responseData['choices'][0]['message']['content'] ?? '');

            return $summary ?: null;
        } catch (\Exception $e) {
            Log::error('Error generating summary: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate replacement recommendations with table data
     */
    private function generateReplacementRecommendations(array $data): ?array
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $categoryName = $data['category_name'];
            $propertyName = $data['property_name'] ?? 'the building';

            $prompt = "You are an energy efficiency specialist analyzing replacement opportunities for {$categoryName}.\n\n";
            $prompt .= "Replacement Analysis Data:\n";
            $prompt .= json_encode($data['replacement_analysis'], JSON_PRETTY_PRINT) . "\n\n";
            
            $prompt .= "Generate replacement recommendations in JSON format:\n";
            $prompt .= "{\n";
            $prompt .= '  "efficient_replacements": [\n';
            $prompt .= '    {\n';
            $prompt .= '      "old_type": "Fluorescent",\n';
            $prompt .= '      "new_type": "LED",\n';
            $prompt .= '      "summary": "There are [total_units] [old_type] units in the [category]. Its consumption is about [percentage_of_category]% of the building\'s [category] consumption, which is equivalent to [old_consumption_kwh] kWh. This consumption is relatively high, especially since the same lighting intensity can be provided with the same color degree but using more efficient and energy-saving lighting. In addition to the LED being more efficient, it is also more environmentally friendly due to its long lifespan, which is more than 10 times that of fluorescent. The price of a LED Bulb (10W) lighting unit is within 5-8 NIS and the price of a LED tube (20W) lighting unit is around 10 NIS.",\n';
            $prompt .= '      "total_units": number,\n';
            $prompt .= '      "old_consumption_kwh": number,\n';
            $prompt .= '      "old_cost_nis": number,\n';
            $prompt .= '      "new_consumption_kwh": number,\n';
            $prompt .= '      "new_cost_nis": number,\n';
            $prompt .= '      "savings_kwh": number,\n';
            $prompt .= '      "savings_nis": number,\n';
            $prompt .= '      "percentage_of_category": number\n';
            $prompt .= '    }\n';
            $prompt .= '  ]\n';
            $prompt .= "}\n\n";
            
            $prompt .= "Use the replacement_analysis data provided. For each replacement opportunity, create an object with the summary paragraph and all numerical values from the replacement_analysis data.\n";
            $prompt .= "Return ONLY valid JSON, no additional text or markdown formatting.";

            $client = new \GuzzleHttp\Client([
                'timeout' => 90, // 90 seconds for the HTTP request
                'connect_timeout' => 10, // 10 seconds to establish connection
            ]);
            
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1500,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $rawContent = trim($responseData['choices'][0]['message']['content'] ?? '');

            if ($rawContent === '') {
                return null;
            }

            $cleanContent = preg_replace('/```json|```/i', '', $rawContent);
            $decoded = json_decode($cleanContent, true);

            if (!is_array($decoded) || !isset($decoded['efficient_replacements'])) {
                Log::warning('Replacement response is not valid JSON.', ['response' => $cleanContent]);
                return null;
            }

            return $decoded['efficient_replacements'];
        } catch (\Exception $e) {
            Log::error('Error generating replacement recommendations: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate delamping recommendations with table data
     */
    private function generateDelampingRecommendations(array $data): ?array
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $propertyName = $data['property_name'] ?? 'the building';
            $electricityCostPerKwh = $data['electricity_cost_per_kwh'];
            
            // Check if delamping_analysis is empty
            if (empty($data['delamping_analysis']) || !is_array($data['delamping_analysis'])) {
                Log::warning('Delamping analysis is empty or invalid', ['data' => $data]);
                return null;
            }

            $prompt = "You are an energy efficiency specialist analyzing delamping opportunities.\n\n";
            $prompt .= "Delamping Analysis Data:\n";
            $prompt .= json_encode($data['delamping_analysis'], JSON_PRETTY_PRINT) . "\n\n";
            
            // Log the prompt for debugging
            Log::info('Delamping prompt being sent to OpenAI', [
                'prompt_length' => strlen($prompt),
                'delamping_analysis_count' => count($data['delamping_analysis']),
                'delamping_analysis_sample' => array_slice($data['delamping_analysis'], 0, 2),
            ]);
            
            $prompt .= "Generate delamping recommendations in JSON format:\n";
            $prompt .= "{\n";
            $prompt .= '  "unnecessary_devices": [\n';
            $prompt .= '    {\n';
            $prompt .= '      "summary": "Lighting intensity is a sensitive issue in the lighting process, as high lighting intensity is as harmful as low lighting, in addition to being a waste of electricity. Therefore, we must avoid this. To solve this problem, there are standard values for each type of work and the lighting intensity it requires. In ' . $propertyName . ', there were several offices with high lighting. Here is a proposal for a delamping schedule and the amount of savings in it, which amounts to approximately [total_savings_nis] NIS.",\n';
            $prompt .= '      "delamping_data": [\n';
            $prompt .= '        {\n';
            $prompt .= '          "room_name": "Room/Area name",\n';
            $prompt .= '          "lux": number (estimated),\n';
            $prompt .= '          "target_lux": number,\n';
            $prompt .= '          "led_count": number,\n';
            $prompt .= '          "fluorescent_count": number,\n';
            $prompt .= '          "led_watt": number,\n';
            $prompt .= '          "fluorescent_watt": number,\n';
            $prompt .= '          "operation_time": number,\n';
            $prompt .= '          "consumption_kwh_year": number,\n';
            $prompt .= '          "delamping_fix_percent": number,\n';
            $prompt .= '          "delamping_saving_kwh": number,\n';
            $prompt .= '          "delamping_saving_nis": number\n';
            $prompt .= '        }\n';
            $prompt .= '      ]\n';
            $prompt .= '    }\n';
            $prompt .= '  ]\n';
            $prompt .= "}\n\n";
            
            $prompt .= "For each room in delamping_analysis:\n";
            $prompt .= "- Estimate lux based on total wattage and room type (offices: 50-100 lux per 100W, corridors: 30-50 lux per 100W)\n";
            $prompt .= "- Set target_lux (offices: 300-500, corridors: 100-200, storage: 100-200, entrance: 200-300)\n";
            $prompt .= "- Calculate delamping_fix_percent: if lux > 2× target use 40-50%, if 1.5-2× use 30-40%, if 1.2-1.5× use 10-30%, if close use 0-10%\n";
            $prompt .= "- Calculate delamping_saving_kwh = consumption_kwh_year × (delamping_fix_percent / 100)\n";
            $prompt .= "- Calculate delamping_saving_nis = delamping_saving_kwh × {$electricityCostPerKwh}\n";
            $prompt .= "- Include ALL rooms from delamping_analysis\n";
            $prompt .= "- Calculate total_savings_nis as sum of all delamping_saving_nis\n";
            $prompt .= "- Group delamping_data by description/room_name if needed\n";
            $prompt .= "Return ONLY valid JSON, no additional text or markdown formatting.";

            $client = new \GuzzleHttp\Client([
                'timeout' => 90, // 90 seconds for the HTTP request
                'connect_timeout' => 10, // 10 seconds to establish connection
            ]);
            
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $rawContent = trim($responseData['choices'][0]['message']['content'] ?? '');
         
            if ($rawContent === '') {
                Log::warning('Delamping API returned empty content', ['response' => $responseData]);
                return null;
            }
            Log::info('Delamping API returned content', ['response' => $rawContent]);
            $cleanContent = preg_replace('/```json|```/i', '', $rawContent);
            $decoded = json_decode($cleanContent, true);

            if (!is_array($decoded) || !isset($decoded['unnecessary_devices'])) {
                /*Log::warning('Delamping response is not valid JSON.', [
                    'response' => $cleanContent,
                    'decoded' => $decoded,
                    'raw_content_length' => strlen($rawContent),
                ]);*/
                return null;
            }

            Log::info('Delamping recommendations generated successfully', [
                'unnecessary_devices_count' => count($decoded['unnecessary_devices']),
            ]);

            return $decoded['unnecessary_devices'];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                try {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $body = $e->getResponse()->getBody()->getContents();
                    Log::error('OpenAI API request failed', [
                        'status' => $statusCode,
                        'body' => $body,
                        'message' => $e->getMessage(),
                    ]);
                } catch (\Exception $bodyException) {
                    Log::error('OpenAI API request failed (could not read response)', [
                        'message' => $e->getMessage(),
                        'body_exception' => $bodyException->getMessage(),
                    ]);
                }
            } else {
                Log::error('OpenAI API request timeout or connection error', [
                    'message' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 500), // Limit trace length
                ]);
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Error generating delamping recommendations: ' . $e->getMessage(), [
                'trace' => substr($e->getTraceAsString(), 0, 500), // Limit trace length
            ]);
            return null;
        }
    }

    /**
     * Generate fixes recommendations with table data
     */
    private function generateFixesRecommendations(array $data): ?array
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $categoryName = $data['category_name'];
            $propertyName = $data['property_name'] ?? 'the building';

            $prompt = "You are an energy efficiency specialist analyzing necessary fixes for {$categoryName}.\n\n";
            $prompt .= "Grouped Devices by Description:\n";
            $prompt .= json_encode($data['grouped_devices'], JSON_PRETTY_PRINT) . "\n\n";
            
            $prompt .= "CRITICAL: Analyze the devices carefully. ONLY return fixes if there are ACTUAL problems.\n";
            $prompt .= "If all devices are working properly and there are no issues mentioned in notes, return an EMPTY array.\n\n";
            
            $prompt .= "Generate fixes recommendations in JSON format:\n";
            $prompt .= "{\n";
            $prompt .= '  "necessary_fixes": [\n';
            $prompt .= '    {\n';
            $prompt .= '      "summary": "A comprehensive and detailed summary paragraph (at least 4-5 sentences) about necessary fixes for this category. The summary should describe the types of issues found, their impact on the system, the importance of addressing these fixes, and the overall condition of the equipment. Include specific details about the nature of the problems, such as whether devices are broken, faulty, malfunctioning, or require immediate repair. Explain why these fixes are necessary for maintaining system reliability, safety, and proper operation. If no issues are found, the summary should state that all devices are functioning properly and no fixes are required.",\n';
            $prompt .= '      "fixes_data": [\n';
            $prompt .= '        {\n';
            $prompt .= '          "description": "Room/Area name or device description",\n';
            $prompt .= '          "device_name": "Device name",\n';
            $prompt .= '          "issue": "Issue description (e.g., faulty lamp, broken device, malfunctioning equipment)",\n';
            $prompt .= '          "quantity": number,\n';
            $prompt .= '          "power": number,\n';
            $prompt .= '          "notes": "Additional notes if available"\n';
            $prompt .= '        }\n';
            $prompt .= '      ]\n';
            $prompt .= '    }\n';
            $prompt .= '  ]\n';
            $prompt .= "}\n\n";
            
            $prompt .= "IMPORTANT RULES:\n";
            $prompt .= "1. If NO issues are found (all devices working, no problems in notes), return: {\"necessary_fixes\": []}\n";
            $prompt .= "2. If issues ARE found, include them in fixes_data array\n";
            $prompt .= "3. Do NOT create fixes for devices that are working properly\n";
            $prompt .= "4. Do NOT create fixes based on assumptions - only use explicit information from notes or device data\n\n";
            
            $prompt .= "This is for FIXES ONLY. Do NOT include:\n";
            $prompt .= "- Device replacements (e.g., replacing fluorescent with LED)\n";
            $prompt .= "- Device reductions (e.g., delamping, removing unnecessary devices)\n";
            $prompt .= "- Efficiency upgrades or modernization\n";
            $prompt .= "- Any recommendations for replacing working devices with more efficient ones\n";
            $prompt .= "- Any recommendations for removing devices to reduce consumption\n\n";
            
            $prompt .= "ONLY identify necessary fixes for devices that are:\n";
            $prompt .= "- Broken or malfunctioning (not working properly) - MUST be explicitly mentioned\n";
            $prompt .= "- Faulty (defective, damaged, or not functioning correctly) - MUST be explicitly mentioned\n";
            $prompt .= "- In need of repair (requires maintenance or repair work) - MUST be explicitly mentioned\n";
            $prompt .= "- Mentioned in notes as having issues or problems - check notes field carefully\n";
            $prompt .= "- Safety hazards or non-functional equipment - MUST be explicitly mentioned\n\n";
            
            $prompt .= "Examples of valid fixes (ONLY if explicitly mentioned):\n";
            $prompt .= "- Notes say 'faulty' or 'broken' or 'needs repair'\n";
            $prompt .= "- Notes mention specific issues like 'not working', 'damaged', 'malfunctioning'\n";
            $prompt .= "- Device data indicates problems (if applicable)\n\n";
            
            $prompt .= "Examples of what NOT to include:\n";
            $prompt .= "- 'Replace fluorescent with LED' (this is a replacement, not a fix)\n";
            $prompt .= "- 'Remove unnecessary devices' (this is a reduction, not a fix)\n";
            $prompt .= "- 'Upgrade to more efficient model' (this is a replacement, not a fix)\n";
            $prompt .= "- Devices that are working properly but could be more efficient\n";
            $prompt .= "- Assumptions about device condition without explicit evidence\n\n";
            
            $prompt .= "Summary Requirements:\n";
            $prompt .= "- If fixes are found: The summary must be comprehensive and detailed (at least 4-5 sentences, preferably 6-8 sentences)\n";
            $prompt .= "- If NO fixes are found: The summary should state that all devices in this category are functioning properly and no fixes are required (2-3 sentences is sufficient)\n";
            $prompt .= "- Describe the types of issues found (broken, faulty, malfunctioning, etc.) - ONLY if issues exist\n";
            $prompt .= "- Explain the impact of these issues on system operation, safety, and reliability - ONLY if issues exist\n";
            $prompt .= "- Make it informative and professional, suitable for an energy audit report\n\n";
            
            $prompt .= "Group fixes_data by description. Return ONLY valid JSON, no additional text or markdown formatting.";

            $client = new \GuzzleHttp\Client([
                'timeout' => 90, // 90 seconds for the HTTP request
                'connect_timeout' => 10, // 10 seconds to establish connection
            ]);
            
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1500,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $rawContent = trim($responseData['choices'][0]['message']['content'] ?? '');

            if ($rawContent === '') {
                return null;
            }

            $cleanContent = preg_replace('/```json|```/i', '', $rawContent);
            $decoded = json_decode($cleanContent, true);

            if (!is_array($decoded) || !isset($decoded['necessary_fixes'])) {
                Log::warning('Fixes response is not valid JSON.', ['response' => $cleanContent]);
                return null;
            }

            return $decoded['necessary_fixes'];
        } catch (\Exception $e) {
            Log::error('Error generating fixes recommendations: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate recommendations using OpenAI (DEPRECATED - kept for backward compatibility)
     */
    private function generateRecommendations(array $data): ?array
    {
        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured for recommendations');
                return null;
            }

            $categoryName = $data['category_name'];
            $categoryConsumption = $data['category_consumption_kwh'];
            $categoryPercentage = $data['category_percentage'];
            $categoryCost = $data['category_cost_nis'];
            
            // Get property name if available
            $propertyName = $data['property_name'] ?? 'the building';

            $prompt = "You are an energy efficiency specialist analyzing a {$categoryName} system.\n\n";
            $prompt .= "System Data:\n";
            $prompt .= "- Property/Building: {$propertyName}\n";
            $prompt .= "- Category: {$categoryName}\n";
            $prompt .= "- Annual consumption: {$categoryConsumption} kWh\n";
            $prompt .= "- Percentage of total property consumption: {$categoryPercentage}%\n";
            $prompt .= "- Annual cost: {$categoryCost} NIS\n";
            $prompt .= "- Electricity cost: {$data['electricity_cost_per_kwh']} NIS/kWh\n\n";
            
            $prompt .= "Grouped Devices by Description:\n";
            $prompt .= json_encode($data['grouped_devices'], JSON_PRETTY_PRINT) . "\n\n";
            
            $prompt .= "Electricity Balance (all categories):\n";
            $prompt .= json_encode($data['electricity_balance'], JSON_PRETTY_PRINT) . "\n\n";
            
            $prompt .= "Replacement Analysis Data:\n";
            if (!empty($data['replacement_analysis'])) {
                $prompt .= json_encode($data['replacement_analysis'], JSON_PRETTY_PRINT) . "\n\n";
            } else {
                $prompt .= "No replacement opportunities identified.\n\n";
            }
            
            $prompt .= "Delamping Analysis Data:\n";
            if (!empty($data['delamping_analysis'])) {
                $prompt .= json_encode($data['delamping_analysis'], JSON_PRETTY_PRINT) . "\n\n";
            } else {
                $prompt .= "No delamping opportunities identified.\n\n";
            }
            
            $prompt .= "Generate energy efficiency recommendations in the following JSON format:\n";
            $prompt .= "{\n";
            $prompt .= '  "summary_paragraph": "A paragraph combining category info and saving opportunities. Format: \'[Category] system representing [percentage]% of the annual electricity consumption, The electricity consumption for the [category] system is [consumption] kWh per year, with a cost of [cost] NIS per year. There are [number] opportunities to save energy which is [list opportunities], it will save [total_kwh]kWh/Year and [total_nis] NIS/Year.\'",\n';
            $prompt .= '  "system_saving_opportunities": [\n';
            $prompt .= '    {\n';
            $prompt .= '      "title": "Opportunity title",\n';
            $prompt .= '      "description": "Detailed description with savings in kWh/Year and NIS/Year",\n';
            $prompt .= '      "savings_kwh": number,\n';
            $prompt .= '      "savings_nis": number\n';
            $prompt .= '    }\n';
            $prompt .= '  ],\n';
            $prompt .= '  "necessary_fixes": [\n';
            $prompt .= '    "Fix description 1",\n';
            $prompt .= '    "Fix description 2"\n';
            $prompt .= '  ],\n';
            $prompt .= '  "efficient_replacements": [\n';
            $prompt .= '    {\n';
            $prompt .= '      "old_type": "Fluorescent",\n';
            $prompt .= '      "new_type": "LED",\n';
            $prompt .= '      "summary": "There are [X] [old_type] units in the [category]. Its consumption is about [percentage]% of the building\'s [category] consumption, which is equivalent to [consumption] kWh. This consumption is relatively high, especially since the same lighting intensity can be provided with the same color degree but using more efficient and energy-saving lighting. In addition to the LED being more efficient, it is also more environmentally friendly due to its long lifespan, which is more than 10 times that of fluorescent. The price of a LED Bulb (10W) lighting unit is within 5-8 NIS and the price of a LED tube (20W) lighting unit is around 10 NIS.",\n';
            $prompt .= '      "total_units": number,\n';
            $prompt .= '      "old_consumption_kwh": number,\n';
            $prompt .= '      "old_cost_nis": number,\n';
            $prompt .= '      "new_consumption_kwh": number,\n';
            $prompt .= '      "new_cost_nis": number,\n';
            $prompt .= '      "savings_kwh": number,\n';
            $prompt .= '      "savings_nis": number,\n';
            $prompt .= '      "percentage_of_category": number\n';
            $prompt .= '    }\n';
            $prompt .= '  ],\n';
            $prompt .= '  "unnecessary_devices": [\n';
            $prompt .= '    "Device removal recommendation 1",\n';
            $prompt .= '    "Device removal recommendation 2"\n';
            $prompt .= '  ]\n';
            $prompt .= "}\n\n";
            
            $prompt .= "IMPORTANT: The summary_paragraph must combine the category information (percentage, consumption, cost) with the system saving opportunities. ";
            $prompt .= "Calculate the total savings from all opportunities and include them in the paragraph. ";
            $prompt .= "Example format: 'Lighting system representing 14% of the annual electricity consumption, The electricity consumption for the lighting system is 27,254.836 kWh per year, with a cost of 18,510 NIS per year. There are two opportunities to save energy which is replacement fluorescent lights with LED & delamping, it will save 6,391kWh/Year and 4,314 NIS/Year.'\n\n";
            
            $prompt .= "Guidelines:\n";
            $prompt .= "1. System Saving Opportunities: Include specific opportunities like 'replacement fluorescent lights with LED & delamping' with exact savings calculations.\n";
            $prompt .= "2. Necessary Fixes: List items like 'replacing faulty lamps', 'replacing old devices', 'fixing broken devices'.\n";
            $prompt .= "3. Efficient Replacements: Use the replacement_analysis data provided above. For each replacement opportunity, create an object with the summary paragraph and all numerical values. The summary should follow this format: 'There are [total_units] [old_type] units in the [category]. Its consumption is about [percentage_of_category]% of the building's [category] consumption, which is equivalent to [old_consumption_kwh] kWh. This consumption is relatively high, especially since the same lighting intensity can be provided with the same color degree but using more efficient and energy-saving lighting. In addition to the LED being more efficient, it is also more environmentally friendly due to its long lifespan, which is more than 10 times that of fluorescent. The price of a LED Bulb (10W) lighting unit is within 5-8 NIS and the price of a LED tube (20W) lighting unit is around 10 NIS.'\n";
            $prompt .= "4. Unnecessary Devices: Use the delamping_analysis data provided above. You MUST process EVERY room in the delamping_analysis array and:\n";
            $prompt .= "   - For each room, estimate lux values based on: total wattage (LED watt × LED count + Fluorescent watt × Fluorescent count), room type inferred from room name, and typical lighting standards\n";
            $prompt .= "   - Typical lux calculations: For offices, estimate 50-100 lux per 100W. For corridors/halls, estimate 30-50 lux per 100W. For high-intensity areas, estimate 100-150 lux per 100W\n";
            $prompt .= "   - Set target_lux to appropriate standard values: offices/workspaces: 300-500 lux, corridors: 100-200 lux, storage: 100-200 lux, entrance/hall: 200-300 lux\n";
            $prompt .= "   - Calculate delamping_fix_percent based on lux difference:\n";
            $prompt .= "     * If current lux > 2× target_lux: use 40-50%\n";
            $prompt .= "     * If current lux is 1.5-2× target_lux: use 30-40%\n";
            $prompt .= "     * If current lux is 1.2-1.5× target_lux: use 10-30%\n";
            $prompt .= "     * If current lux is close to target (within 20%): use 0-10%\n";
            $prompt .= "   - Calculate delamping_saving_kwh = consumption_kwh_year × (delamping_fix_percent / 100)\n";
            $prompt .= "   - Calculate delamping_saving_nis = delamping_saving_kwh × electricity_cost_per_kwh (use {$data['electricity_cost_per_kwh']} NIS/kWh)\n";
            $prompt .= "   - Include ALL rooms from delamping_analysis in the delamping_data array - DO NOT skip any rooms\n";
            $prompt .= "   - Fill in ALL fields for each room: room_name, lux (estimated number), target_lux (number), led_count, fluorescent_count, led_watt, fluorescent_watt, operation_time, consumption_kwh_year, delamping_fix_percent (number), delamping_saving_kwh (number), delamping_saving_nis (number)\n";
            $prompt .= "   - The summary paragraph should follow this format: 'Lighting intensity is a sensitive issue in the lighting process, as high lighting intensity is as harmful as low lighting, in addition to being a waste of electricity. Therefore, we must avoid this. To solve this problem, there are standard values for each type of work and the lighting intensity it requires. In {$propertyName}, there were several offices with high lighting. Here is a proposal for a delamping schedule and the amount of savings in it, which amounts to approximately [total_savings_nis] NIS.'\n";
            $prompt .= "   - Calculate total_savings_nis as the sum of all delamping_saving_nis values from all rooms\n";
            $prompt .= "   - IMPORTANT: The delamping_data array must contain ALL rooms from delamping_analysis with ALL fields filled (no null values except where explicitly allowed)\n";
            $prompt .= "Use the grouped devices data, replacement_analysis, and delamping_analysis to identify specific opportunities. Calculate realistic savings based on the device data provided.\n";
            $prompt .= "Return ONLY valid JSON, no additional text or markdown formatting.";

            $client = new \GuzzleHttp\Client([
                'timeout' => 90, // 90 seconds for the HTTP request
                'connect_timeout' => 10, // 10 seconds to establish connection
            ]);
            
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $rawContent = trim($responseData['choices'][0]['message']['content'] ?? '');

            if ($rawContent === '') {
                return null;
            }

            // Clean JSON response (remove markdown code blocks if present)
            $cleanContent = preg_replace('/```json|```/i', '', $rawContent);
            $decoded = json_decode($cleanContent, true);

            if (!is_array($decoded)) {
                Log::warning('Recommendations response is not valid JSON.', ['response' => $cleanContent]);
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            Log::error('Error generating recommendations with OpenAI: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save recommendations data for a property category
     */
    public function saveRecommendations(Request $request, Property $property, int $categoryId)
    {
        $data = $request->validate([
            'recommendations_data' => 'required|array',
        ]);

        DB::table('property_category_recommendations')->updateOrInsert(
            [
                'property_id' => $property->id,
                'category_id' => $categoryId,
            ],
            [
                'recommendations_data' => json_encode($data['recommendations_data']),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return response()->json([
            'message' => 'Recommendations saved successfully',
        ]);
    }

    /**
     * Get saved recommendations data for a property category
     */
    public function getSavedRecommendations(Property $property, int $categoryId)
    {
        $recommendations = DB::table('property_category_recommendations')
            ->where('property_id', $property->id)
            ->where('category_id', $categoryId)
            ->first();

        if (!$recommendations) {
            return response()->json([
                'recommendations_data' => null,
            ]);
        }

        return response()->json([
            'recommendations_data' => json_decode($recommendations->recommendations_data, true),
        ]);
    }
}
