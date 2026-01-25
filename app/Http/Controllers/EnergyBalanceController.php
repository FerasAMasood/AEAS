<?php

namespace App\Http\Controllers;

use App\Models\EnergyBalance;
use App\Models\EnergySource;
use App\Models\Tariff;
use App\Models\Report;
use App\Models\Ebill;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnergyBalanceController extends Controller
{
    /**
     * Get energy sources from tariffs for a property
     * Returns unique energy sources that appear in tariffs for reports related to this property
     * Also includes calculated electricity value from bills
     */
    public function getEnergySources(Request $request, $propertyId)
    {
        // Get all reports for this property
        $reportIds = Report::where('property_id', $propertyId)->pluck('id');
        
        if ($reportIds->isEmpty()) {
            return response()->json(['sources' => [], 'electricity_from_bills' => null]);
        }

        // Get unique energy sources from tariffs for these reports
        $sourceIds = Tariff::whereIn('report_id', $reportIds)
            ->distinct()
            ->pluck('source_id');

        if ($sourceIds->isEmpty()) {
            return response()->json(['sources' => [], 'electricity_from_bills' => null]);
        }

        $sources = EnergySource::whereIn('id', $sourceIds)->get();

        // Get tariffs with unit_cost for each source (get the latest tariff for each source)
        $tariffs = Tariff::whereIn('report_id', $reportIds)
            ->whereIn('source_id', $sourceIds)
            ->get()
            ->groupBy('source_id')
            ->map(function ($group) {
                // Get the most recent tariff for each source
                return $group->sortByDesc('created_at')->first();
            });

        // Attach unit_cost to each source
        $sourcesWithTariffs = $sources->map(function ($source) use ($tariffs) {
            $tariff = $tariffs->get($source->id);
            $source->unit_cost = $tariff ? $tariff->unit_cost : null;
            return $source;
        });

        // Calculate electricity consumption from bills
        $electricityFromBills = Ebill::where('property_id', $propertyId)
            ->whereNotNull('energy_consumption_kwh')
            ->sum('energy_consumption_kwh');

        return response()->json([
            'sources' => $sourcesWithTariffs,
            'electricity_from_bills' => $electricityFromBills
        ]);
    }

    /**
     * Get energy balance data for a property
     * Includes calculated electricity value from bills
     */
    public function getEnergyBalance($propertyId)
    {
        $balanceData = EnergyBalance::where('property_id', $propertyId)
            ->with('source')
            ->get();

        // Calculate electricity consumption from bills
        $electricityFromBills = Ebill::where('property_id', $propertyId)
            ->whereNotNull('energy_consumption_kwh')
            ->sum('energy_consumption_kwh');

        return response()->json([
            'balance' => $balanceData,
            'electricity_from_bills' => $electricityFromBills
        ]);
    }

    /**
     * Save energy balance data for a property
     * Note: Electricity values are calculated from bills and should not be manually saved
     */
    public function store(Request $request, $propertyId)
    {
        $validated = $request->validate([
            'balance' => 'required|array',
            'balance.*.source_id' => 'required|exists:energy_sources,id',
            'balance.*.value' => 'nullable|numeric|min:0',
            'balance.*.power_generated' => 'nullable|numeric|min:0',
        ]);

        // Get electricity-type energy sources to exclude from manual saving
        $electricitySourceIds = EnergySource::where('type', 'electricity')->pluck('id');

        DB::transaction(function () use ($propertyId, $validated, $electricitySourceIds) {
            foreach ($validated['balance'] as $item) {
                // Skip electricity sources - they are calculated from bills
                if ($electricitySourceIds->contains($item['source_id'])) {
                    continue;
                }

                EnergyBalance::updateOrCreate(
                    [
                        'property_id' => $propertyId,
                        'source_id' => $item['source_id'],
                    ],
                    [
                        'value' => $item['value'] ?? null,
                        'power_generated' => $item['power_generated'] ?? null,
                    ]
                );
            }
        });

        return response()->json(['message' => 'Energy balance saved successfully']);
    }

    /**
     * Get energy balance analysis for a property
     */
    public function getAnalysis($propertyId)
    {
        $analysis = DB::table('energy_balance_analysis')
            ->where('property_id', $propertyId)
            ->first();

        return response()->json([
            'analysis' => $analysis ? $analysis->analysis : null
        ]);
    }

    /**
     * Save energy balance analysis for a property
     */
    public function saveAnalysis(Request $request, $propertyId)
    {
        $validated = $request->validate([
            'analysis' => 'nullable|string',
        ]);

        DB::table('energy_balance_analysis')->updateOrInsert(
            ['property_id' => $propertyId],
            [
                'analysis' => $validated['analysis'] ?? null,
                'updated_at' => now(),
            ]
        );

        return response()->json(['message' => 'Analysis saved successfully']);
    }

    /**
     * Generate AI analysis from energy balance data
     * Returns formatted energy balance data for frontend to send to chat API
     */
    public function generateAnalysis(Request $request, $propertyId)
    {
        // Get property
        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        // Get energy balance data
        $balanceData = EnergyBalance::where('property_id', $propertyId)
            ->with('source')
            ->get();

        // Get electricity from bills
        $electricityFromBills = Ebill::where('property_id', $propertyId)
            ->whereNotNull('energy_consumption_kwh')
            ->sum('energy_consumption_kwh');

        // Get energy sources with tariffs
        $reportIds = Report::where('property_id', $propertyId)->pluck('id');
        
        if ($reportIds->isEmpty()) {
            return response()->json(['error' => 'No reports found for this property'], 404);
        }
        
        $sourceIds = Tariff::whereIn('report_id', $reportIds)
            ->distinct()
            ->pluck('source_id');
        
        $sources = EnergySource::whereIn('id', $sourceIds)->get();
        $tariffs = Tariff::whereIn('report_id', $reportIds)
            ->whereIn('source_id', $sourceIds)
            ->get()
            ->groupBy('source_id')
            ->map(function ($group) {
                return $group->sortByDesc('created_at')->first();
            });

        // Build energy balance summary in narrative format
        $summaryText = $property->property_name . " uses multiple energy sources";
        
        $sourceList = [];
        $consumptionDetails = [];
        $hasElectricity = false;
        
        foreach ($sources as $source) {
            $isElectricity = $source->type === 'electricity';
            $consumption = $isElectricity && $electricityFromBills 
                ? $electricityFromBills 
                : ($balanceData->where('source_id', $source->id)->first()?->value ?? 0);
            
            $tariff = $tariffs->get($source->id);
            $unitCost = $tariff ? $tariff->unit_cost : 0;
            $cost = $consumption * $unitCost;
            
            $energyKwh = $isElectricity 
                ? ($electricityFromBills ?? 0)
                : ($balanceData->where('source_id', $source->id)->first()?->power_generated ?? 0);

            if ($consumption > 0 || $energyKwh > 0) {
                if ($isElectricity) {
                    $hasElectricity = true;
                    $sourceList[] = "electricity";
                } else {
                    $sourceList[] = strtolower($source->name);
                }
                
                // Build consumption detail in the format: "The annual X consumption is Y units, Z NIS"
                if ($consumption > 0) {
                    $consumptionText = sprintf(
                        "The annual %s consumption is approximately %s %s",
                        strtolower($source->name),
                        number_format(round($consumption), 0),
                        $source->unit
                    );
                    
                    if ($cost > 0 && $unitCost > 0) {
                        $consumptionText .= sprintf(
                            ", equivalent to %s NIS per year",
                            number_format(round($cost), 0)
                        );
                    }
                    
                    if ($isElectricity) {
                        $consumptionText .= ", as calculated from electricity bills";
                    }
                    
                    $consumptionText .= ".";
                    $consumptionDetails[] = $consumptionText;
                }
            }
        }
        
        // Build the source description part
        if (!empty($sourceList)) {
            if ($hasElectricity && count($sourceList) > 1) {
                // Put electricity first
                $otherSources = array_filter($sourceList, function($s) { return $s !== 'electricity'; });
                if (!empty($otherSources)) {
                    $summaryText .= " but primarily relies on " . implode(", ", $otherSources) . ", and " . (count($otherSources) > 1 ? "limited reliance on " : "") . "electricity";
                } else {
                    $summaryText .= " but primarily relies on electricity";
                }
            } elseif (count($sourceList) > 1) {
                $lastSource = array_pop($sourceList);
                $summaryText .= " but primarily relies on " . implode(", ", $sourceList) . ", and " . $lastSource;
            } else {
                $summaryText .= " but primarily relies on " . $sourceList[0];
            }
        }
        
        $summaryText .= ".";
        
        // Add consumption details
        if (!empty($consumptionDetails)) {
            $summaryText .= " " . implode(" ", $consumptionDetails);
        }
        
        $summaryText .= " Table (2) shows energy sources information.";

        // Return the prompt and report_id for frontend to use with chat API
        return response()->json([
            'prompt' => "Based on the following energy balance data, provide a professional analysis suitable for an energy audit report. Keep it simple and direct. Use plain text only, no special formatting characters. Follow this narrative style:\n\n" . $summaryText,
            'report_id' => $reportIds->first()
        ]);
    }
}
