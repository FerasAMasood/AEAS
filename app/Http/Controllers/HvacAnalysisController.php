<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\HvacAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HvacAnalysisController extends Controller
{
    public function __construct(
        protected HvacAnalysisService $analysisService
    ) {}

    /**
     * Generate HVAC paragraph for a property (AI)
     */
    public function analyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $analysis = $this->analysisService->analyzeHvac((int) $request->property_id);
        if ($analysis === null) {
            return response()->json([
                'error' => 'Failed to generate HVAC analysis. Please check OpenAI API configuration and ensure the property has relevant data.',
            ], 500);
        }

        return response()->json([
            'analysis' => $analysis,
            'message' => 'HVAC analysis generated successfully',
        ]);
    }

    /**
     * Store HVAC analysis
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'analysis' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ok = $this->analysisService->storeAnalysis(
            (int) $request->property_id,
            $request->analysis
        );
        if (!$ok) {
            return response()->json(['error' => 'Failed to store analysis'], 500);
        }
        return response()->json(['message' => 'HVAC analysis stored successfully']);
    }

    /**
     * Get stored HVAC analysis for a property
     */
    public function show($propertyId)
    {
        $property = Property::findOrFail($propertyId);
        return response()->json([
            'analysis' => $property->hvac_analysis,
        ]);
    }
}
