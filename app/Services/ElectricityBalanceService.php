<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyDevice;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ElectricityBalanceService
{
    protected $openaiClient;

    public function __construct()
    {
        $this->openaiClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Calculate electricity balance (aggregated by load type/category)
     */
    public function calculateBalance(int $propertyId): ?array
    {
        $property = Property::with(['propertyDevices.category'])->find($propertyId);

        if (!$property || $property->propertyDevices->isEmpty()) {
            return null;
        }

        // Group devices by category (load type)
        $groupedDevices = $property->propertyDevices->groupBy('category_id');

        $balanceData = [];
        $totalConsumption = 0;

        foreach ($groupedDevices as $categoryId => $devices) {
            $category = $devices->first()->category;
            $loadType = $category ? $category->lookup_value : 'Unknown';
            
            $categoryTotal = $devices->sum('total_consumption');
            $totalConsumption += $categoryTotal;

            $balanceData[] = [
                'load_type' => $loadType,
                'total_consumption_kwh' => round($categoryTotal, 2),
                'percentage' => 0, // Will calculate after we have total
            ];
        }

        // Calculate percentages
        if ($totalConsumption > 0) {
            foreach ($balanceData as &$item) {
                $item['percentage'] = round(($item['total_consumption_kwh'] / $totalConsumption) * 100, 0);
            }
        }

        // Add total row
        $balanceData[] = [
            'load_type' => 'Total',
            'total_consumption_kwh' => round($totalConsumption, 2),
            'percentage' => 100,
        ];

        return $balanceData;
    }

    /**
     * Analyze electricity balance using OpenAI
     */
    public function analyzeBalance(int $propertyId, array $balanceData): ?string
    {
        $property = Property::find($propertyId);

        if (!$property) {
            return null;
        }

        $propertyName = $property->property_name ?? 'the property';

        // Prepare data for OpenAI (exclude total row)
        $dataForAnalysis = array_filter($balanceData, function($item) {
            return $item['load_type'] !== 'Total';
        });

        // Create prompt for OpenAI - concise and focused
        $prompt = "Analyze the electricity consumption breakdown by load type for {$propertyName}. Currency is NIS.\n\n";
        $prompt .= "Data:\n" . json_encode($dataForAnalysis, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Write a short analysis paragraph (3-4 sentences) following this exact format:\n\n";
        $prompt .= "The previous chart illustrates the distribution of electricity consumption across different electrical systems and the percentage consumed by each. [List the systems mentioned].\n\n";
        $prompt .= "We can observe that [system] is the largest consumer, representing [percentage] of the annual consumption, which is due to [reason]. [Other systems] are [position] consumers of electricity, with [specific details].\n\n";
        $prompt .= "[System] also constitutes a significant portion of energy consumption, with [details]. [System] represents the smallest portion of the total energy consumption.\n\n";
        $prompt .= "There are applicable recommendations for all the mentioned systems, such as implementing energy-saving practices, regular maintenance, and upgrading to more efficient equipment, which can significantly reduce overall electricity consumption.\n\n";
        $prompt .= "Be concise. Focus on: which system consumes most, percentages, reasons for consumption, and recommendations. Use actual system names and percentages from the data.";

        try {
            $apiKey = env('OPENAI_API_KEY');
            
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $response = $this->openaiClient->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.6,
                    'max_tokens' => 300, // Slightly more than bills analysis
                ],
                'timeout' => 30,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['choices'][0]['message']['content'])) {
                return trim($responseData['choices'][0]['message']['content']);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error analyzing electricity balance with OpenAI: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store balance and analysis in database
     */
    public function storeBalance(int $propertyId, array $balanceData, string $analysis): bool
    {
        try {
            $property = Property::find($propertyId);
            if (!$property) {
                return false;
            }

            $property->electricity_balance = $balanceData;
            $property->electricity_balance_analysis = $analysis;
            $property->save();

            return true;
        } catch (\Exception $e) {
            Log::error('Error storing electricity balance: ' . $e->getMessage());
            return false;
        }
    }
}

