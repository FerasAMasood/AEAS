<?php

namespace App\Http\Controllers;

use App\Models\Ebill;
use App\Services\BillsAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EbillController extends Controller
{
    /**
     * Display a listing of the ebills.
     */
    public function index(Request $request)
    {
        $query = Ebill::with('property');

        // Filter by property_id if provided
        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $ebills = $query->orderBy('date', 'desc')->get();

        return response()->json($ebills);
    }

    /**
     * Store a newly created ebill in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'date' => 'required|string|regex:/^\d{4}-\d{2}$/', // Format: YYYY-MM
            'value' => 'required|numeric|min:0',
            'energy_consumption_kwh' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if ebill already exists for this property and date
        $existing = Ebill::where('property_id', $request->property_id)
            ->where('date', $request->date)
            ->first();

        if ($existing) {
            return response()->json(['errors' => ['date' => ['An ebill already exists for this property and date.']]], 422);
        }

        $ebill = Ebill::create($request->all());

        // Auto-trigger analysis after creating bill
        try {
            $analysisService = new BillsAnalysisService();
            $analysis = $analysisService->analyzeBills($request->property_id);
            if ($analysis) {
                $analysisService->storeAnalysis($request->property_id, $analysis);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::warning('Failed to auto-analyze bills after creation: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Ebill created successfully.', 'data' => $ebill->load('property')], 201);
    }

    /**
     * Display the specified ebill.
     */
    public function show($id)
    {
        $ebill = Ebill::with('property')->findOrFail($id);
        return response()->json($ebill);
    }

    /**
     * Update the specified ebill in storage.
     */
    public function update(Request $request, $id)
    {
        $ebill = Ebill::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'property_id' => 'sometimes|exists:properties,id',
            'date' => 'sometimes|string|regex:/^\d{4}-\d{2}$/', // Format: YYYY-MM
            'value' => 'sometimes|numeric|min:0',
            'energy_consumption_kwh' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if ebill already exists for this property and date (excluding current ebill)
        if ($request->has('property_id') || $request->has('date')) {
            $propertyId = $request->property_id ?? $ebill->property_id;
            $date = $request->date ?? $ebill->date;

            $existing = Ebill::where('property_id', $propertyId)
                ->where('date', $date)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json(['errors' => ['date' => ['An ebill already exists for this property and date.']]], 422);
            }
        }

        $ebill->update($request->all());

        // Auto-trigger analysis after updating bill
        try {
            $propertyId = $request->property_id ?? $ebill->property_id;
            $analysisService = new BillsAnalysisService();
            $analysis = $analysisService->analyzeBills($propertyId);
            if ($analysis) {
                $analysisService->storeAnalysis($propertyId, $analysis);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::warning('Failed to auto-analyze bills after update: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Ebill updated successfully.', 'data' => $ebill->load('property')]);
    }

    /**
     * Remove the specified ebill from storage.
     */
    public function destroy($id)
    {
        $ebill = Ebill::findOrFail($id);
        $propertyId = $ebill->property_id;
        $ebill->delete();

        // Auto-trigger analysis after deleting bill
        try {
            $analysisService = new BillsAnalysisService();
            $analysis = $analysisService->analyzeBills($propertyId);
            if ($analysis) {
                $analysisService->storeAnalysis($propertyId, $analysis);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::warning('Failed to auto-analyze bills after deletion: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Ebill deleted successfully.']);
    }

    /**
     * Store multiple ebills in bulk.
     */
    public function storeBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'ebills' => 'required|array|min:1',
            'ebills.*.date' => 'required|string|regex:/^\d{4}-\d{2}$/', // Format: YYYY-MM
            'ebills.*.value' => 'required|numeric|min:0',
            'ebills.*.energy_consumption_kwh' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $propertyId = $request->property_id;
        $ebills = $request->ebills;

        // Use transaction to ensure atomicity
        DB::transaction(function () use ($propertyId, $ebills) {
            $ebillsToInsert = [];

            foreach ($ebills as $ebill) {
                // Check if ebill already exists
                $existing = Ebill::where('property_id', $propertyId)
                    ->where('date', $ebill['date'])
                    ->first();

                if ($existing) {
                    // Update existing ebill
                    $existing->update([
                        'value' => $ebill['value'],
                        'energy_consumption_kwh' => $ebill['energy_consumption_kwh'] ?? null,
                    ]);
                } else {
                    // Prepare for bulk insert
                    $ebillsToInsert[] = [
                        'property_id' => $propertyId,
                        'date' => $ebill['date'],
                        'value' => $ebill['value'],
                        'energy_consumption_kwh' => $ebill['energy_consumption_kwh'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Insert new ebills in bulk
            if (!empty($ebillsToInsert)) {
                Ebill::insert($ebillsToInsert);
            }
        });

        // Auto-trigger analysis after bulk operation
        try {
            $analysisService = new BillsAnalysisService();
            $analysis = $analysisService->analyzeBills($propertyId);
            if ($analysis) {
                $analysisService->storeAnalysis($propertyId, $analysis);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::warning('Failed to auto-analyze bills after bulk operation: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Ebills added/updated successfully.'], 200);
    }
}
