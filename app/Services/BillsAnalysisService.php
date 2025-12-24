<?php

namespace App\Services;

use App\Models\Ebill;
use App\Models\Property;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BillsAnalysisService
{
    /**
     * Analyze bills using OpenAI API
     */
    public function analyzeBills(int $propertyId): ?string
    {
        try {
            $bills = Ebill::where('property_id', $propertyId)
                ->orderBy('date', 'asc')
                ->get();

            if ($bills->isEmpty()) {
                return null;
            }

            $property = Property::find($propertyId);
            if (!$property) {
                return null;
            }

            // Prepare data for OpenAI
            $billsData = $bills->map(function ($bill) {
                return [
                    'date' => $bill->date,
                    'energy_consumption_kwh' => $bill->energy_consumption_kwh ?? 0,
                    'cost' => $bill->value ?? 0,
                ];
            })->toArray();

            $propertyName = $property->property_name ?? 'the property';

            // Create prompt for OpenAI - concise and focused
            $prompt = "Analyze the electricity consumption data for {$propertyName}. Currency is NIS.\n\n";
            $prompt .= "Data:\n" . json_encode($billsData, JSON_PRETTY_PRINT) . "\n\n";
            $prompt .= "Write a short analysis paragraph (2-3 sentences) following this exact format:\n\n";
            $prompt .= "The graph shows the electricity consumption of {$propertyName}, we notice fluctuations in consumption throughout the year. [Describe when increases/decreases occur, peak months, and reasons]. This pattern suggests that [conclusion about energy usage patterns].\n\n";
            $prompt .= "Be concise. Focus on: when consumption increases/decreases, peak months, reasons (air conditioning, heating, etc.), and what the pattern suggests. Use actual month names from the data.";

            $client = new Client();
            $apiKey = env('OPENAI_API_KEY');
            
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

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
                    'temperature' => 0.6,
                    'max_tokens' => 200, // Reduced for shorter responses
                ],
                'timeout' => 30,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['choices'][0]['message']['content'])) {
                return trim($responseData['choices'][0]['message']['content']);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error analyzing bills with OpenAI: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store analysis in database
     */
    public function storeAnalysis(int $propertyId, string $analysis): bool
    {
        try {
            $property = Property::find($propertyId);
            if (!$property) {
                return false;
            }

            $property->bills_analysis = $analysis;
            $property->save();

            return true;
        } catch (\Exception $e) {
            Log::error('Error storing bills analysis: ' . $e->getMessage());
            return false;
        }
    }
}

