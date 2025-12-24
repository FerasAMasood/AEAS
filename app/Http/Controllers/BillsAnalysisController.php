<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\BillsAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BillsAnalysisController extends Controller
{
    protected $analysisService;

    public function __construct(BillsAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Analyze bills for a property
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
        $analysis = $this->analysisService->analyzeBills($propertyId);

        if ($analysis === null) {
            return response()->json([
                'error' => 'Failed to generate analysis. Please check OpenAI API configuration and ensure bills exist for this property.'
            ], 500);
        }

        return response()->json([
            'analysis' => $analysis,
            'message' => 'Analysis generated successfully'
        ]);
    }

    /**
     * Store analysis in database
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

        $success = $this->analysisService->storeAnalysis(
            $request->property_id,
            $request->analysis
        );

        if (!$success) {
            return response()->json(['error' => 'Failed to store analysis'], 500);
        }

        return response()->json(['message' => 'Analysis stored successfully']);
    }

    /**
     * Get stored analysis for a property
     */
    public function show(Request $request, $propertyId)
    {
        $property = Property::findOrFail($propertyId);
        
        return response()->json([
            'analysis' => $property->bills_analysis,
        ]);
    }
}

