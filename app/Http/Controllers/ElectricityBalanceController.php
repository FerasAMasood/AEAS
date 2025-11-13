<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\ElectricityBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ElectricityBalanceController extends Controller
{
    protected $balanceService;

    public function __construct(ElectricityBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * Calculate and analyze electricity balance for a property
     */
    public function analyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $propertyId = $request->property_id;

        // Calculate balance (table data)
        $balanceData = $this->balanceService->calculateBalance($propertyId);

        if ($balanceData === null) {
            return response()->json([
                'error' => 'Failed to calculate balance. Please ensure property devices exist for this property.'
            ], 500);
        }

        // Generate analysis
        $analysis = $this->balanceService->analyzeBalance($propertyId, $balanceData);

        if ($analysis === null) {
            return response()->json([
                'error' => 'Failed to generate analysis. Please check OpenAI API configuration.'
            ], 500);
        }

        return response()->json([
            'balance' => $balanceData,
            'analysis' => $analysis,
            'message' => 'Electricity balance calculated and analyzed successfully'
        ]);
    }

    /**
     * Store balance and analysis in database
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'balance' => 'required|array',
            'analysis' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $success = $this->balanceService->storeBalance(
            $request->property_id,
            $request->balance,
            $request->analysis
        );

        if (!$success) {
            return response()->json(['error' => 'Failed to store electricity balance'], 500);
        }

        return response()->json(['message' => 'Electricity balance stored successfully']);
    }

    /**
     * Get stored balance and analysis for a property
     */
    public function show(Request $request, $propertyId)
    {
        $property = Property::findOrFail($propertyId);
        
        return response()->json([
            'balance' => $property->electricity_balance,
            'analysis' => $property->electricity_balance_analysis,
        ]);
    }
}
