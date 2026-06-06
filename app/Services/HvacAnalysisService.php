<?php

namespace App\Services;

use App\Models\Property;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HvacAnalysisService
{
    /**
     * Generate HVAC section paragraph using OpenAI API
     */
    public function analyzeHvac(int $propertyId): ?string
    {
        try {
            $property = Property::with(['propertyDevices.category', 'ebills'])->find($propertyId);
            if (!$property) {
                return null;
            }

            $devices = $property->propertyDevices ?? [];
            $categoryNames = [];
            $hvacDevices = [];
            $totalHvacConsumption = 0;
            $totalHvacCost = 0;
            $totalPropertyConsumption = 0;

            foreach ($devices as $device) {
                $catName = $device->category->lookup_value ?? $device->category->name ?? null;
                if ($catName) {
                    $categoryNames[$catName] = ($categoryNames[$catName] ?? 0) + 1;
                }
                $consumption = (float) ($device->total_consumption ?? 0);
                $totalPropertyConsumption += $consumption;

                $isHvac = $catName && (
                    stripos($catName, 'hvac') !== false ||
                    stripos($catName, 'havac') !== false ||
                    stripos($catName, 'cooling room') !== false ||
                    stripos($catName, 'air condition') !== false
                );
                if ($isHvac) {
                    $hvacDevices[] = [
                        'device' => $device->device_key ?? $device->description ?? 'AC unit',
                        'quantity' => (int) ($device->quantity ?? 0),
                        'power' => (float) ($device->power ?? 0),
                        'operation_hours' => (float) ($device->operation_hours ?? 0),
                        'total_consumption_kwh' => $consumption,
                    ];
                    $totalHvacConsumption += $consumption;
                }
            }

            // Rough cost from bills if available (NIS per kWh estimate)
            $bills = $property->ebills;
            $totalBillsKwh = $bills->sum('energy_consumption_kwh');
            $totalBillsNis = $bills->sum('value');
            $costPerKwh = ($totalBillsKwh > 0 && $totalBillsNis > 0)
                ? $totalBillsNis / $totalBillsKwh
                : 0.68;
            $totalHvacCost = $totalHvacConsumption * $costPerKwh;
            $hvacPercentage = $totalPropertyConsumption > 0
                ? round(100 * $totalHvacConsumption / $totalPropertyConsumption, 1)
                : 0;

            $propertyName = $property->property_name ?? 'the building';
            $totalAcUnits = array_sum(array_column($hvacDevices, 'quantity'));

            $prompt = "You are an energy auditor. Write a single professional paragraph for the HVAC (Heating, Ventilation, and Air Conditioning) section of an energy audit report.\n\n";
            $prompt .= "Property: {$propertyName}. Use plain text only, no markdown or special characters.\n\n";
            $prompt .= "Data for this property:\n";
            $prompt .= "- Total HVAC-related electricity consumption: " . number_format($totalHvacConsumption, 2) . " kWh per year\n";
            $prompt .= "- HVAC share of total building consumption: {$hvacPercentage}%\n";
            $prompt .= "- Estimated HVAC cost: " . number_format($totalHvacCost, 0) . " NIS per year\n";
            $prompt .= "- Number of air conditioning / HVAC units: {$totalAcUnits}\n";
            if (!empty($hvacDevices)) {
                $prompt .= "- HVAC devices breakdown: " . json_encode($hvacDevices, JSON_PRETTY_PRINT) . "\n";
            }
            $prompt .= "\nWrite one cohesive paragraph (3–5 sentences) that:\n";
            $prompt .= "1. States that HVAC systems are among the most important in buildings for comfort (temperature, humidity, air quality).\n";
            $prompt .= "2. States what percentage of total annual electricity consumption HVAC represents and the annual usage in kWh and NIS.\n";
            $prompt .= "3. Mentions the number and type of AC units (e.g. split units) and varying capacities if evident from data.\n";
            $prompt .= "4. Notes any issues such as old vs newer units, improper usage (e.g. set to very low temperature like 16°C), doors left open, and air conditioners running constantly, leading to increased consumption.\n";
            $prompt .= "Keep the tone professional and suitable for an official energy audit report. Output only the paragraph, no headings or labels.";

            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $client = new Client();
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.5,
                    'max_tokens' => 600,
                ],
                'timeout' => 60,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            if (isset($responseData['choices'][0]['message']['content'])) {
                return trim($responseData['choices'][0]['message']['content']);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error generating HVAC analysis: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store HVAC analysis in database
     */
    public function storeAnalysis(int $propertyId, string $analysis): bool
    {
        try {
            $property = Property::find($propertyId);
            if (!$property) {
                return false;
            }
            $property->hvac_analysis = $analysis;
            $property->save();
            return true;
        } catch (\Exception $e) {
            Log::error('Error storing HVAC analysis: ' . $e->getMessage());
            return false;
        }
    }
}
