<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralRecommendationsController extends Controller
{
    /**
     * Generate general saving recommendations for a property
     */
    public function generate(Request $request, $propertyId)
    {
        try {
            $property = Property::with(['reports.tariffs', 'propertyDevices.category', 'ebills'])->findOrFail($propertyId);

            // Collect all property information
            $propertyData = $this->collectPropertyData($property);

            // Get existing recommendations from request
            $existingRecommendations = $request->input('existing_recommendations', []);
            $propertyData['existing_recommendations'] = $existingRecommendations;

            // Build prompt for OpenAI
            $prompt = $this->buildPrompt($propertyData);

            $apiKey = env('OPENAI_API_KEY');
            
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return response()->json(['error' => 'OpenAI API key not configured.'], 500);
            }

            // Prepare request body
            $requestBody = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are an expert Energy Auditing Consultant with extensive experience in building energy efficiency, HVAC systems, lighting optimization, building envelope improvements, and energy management. Your recommendations must be highly specific, technically accurate, and based on professional energy audit standards (ASHRAE Level 1/2/3). Provide detailed, actionable recommendations with specific technical details, potential savings estimates, and implementation guidance. Each recommendation should be comprehensive enough to stand alone as professional audit documentation. Use plain text only, no special formatting characters. Provide each recommendation on a separate line."
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'stream' => true,
            ];

            // Disable output buffering for streaming
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Return streaming response
            return new StreamedResponse(function () use ($apiKey, $requestBody) {
                // Disable time limit and output buffering
                set_time_limit(120);
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                $client = new Client([
                    'timeout' => 120,
                ]);

                try {
                    $response = $client->post('https://api.openai.com/v1/chat/completions', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $requestBody,
                        'stream' => true,
                    ]);

                    $stream = $response->getBody();
                    $buffer = '';

                    while (!$stream->eof()) {
                        $chunk = $stream->read(1024); // Read 1KB at a time
                        
                        if ($chunk === false || $chunk === '') {
                            usleep(100000); // Wait 100ms if no data
                            continue;
                        }

                        $buffer .= $chunk;
                        
                        // Process complete lines
                        while (($pos = strpos($buffer, "\n")) !== false) {
                            $line = substr($buffer, 0, $pos);
                            $buffer = substr($buffer, $pos + 1);
                            
                            if (empty(trim($line))) {
                                continue;
                            }
                            
                            if (strpos($line, 'data: ') === 0) {
                                $data = substr($line, 6);
                                
                                if ($data === '[DONE]') {
                                    echo "data: [DONE]\n\n";
                                    ob_flush();
                                    flush();
                                    return;
                                }
                                
                                try {
                                    $json = json_decode($data, true);
                                    if (isset($json['choices'][0]['delta']['content'])) {
                                        $content = $json['choices'][0]['delta']['content'];
                                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                        ob_flush();
                                        flush();
                                    }
                                } catch (\Exception $e) {
                                    // Skip invalid JSON
                                    Log::debug('JSON decode error in recommendations stream', [
                                        'data' => $data,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        }
                    }
                    
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();
                } catch (RequestException $e) {
                    Log::error('OpenAI API streaming error: ' . $e->getMessage());
                    echo "data: " . json_encode(['error' => 'Failed to stream response']) . "\n\n";
                    ob_flush();
                    flush();
                } catch (\Exception $e) {
                    Log::error('Unexpected error in recommendations streaming: ' . $e->getMessage());
                    echo "data: " . json_encode(['error' => 'Unexpected error occurred']) . "\n\n";
                    ob_flush();
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating general recommendations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate recommendations'], 500);
        }
    }

    /**
     * Save general recommendations for a property
     */
    public function store(Request $request, $propertyId)
    {
        $request->validate([
            'recommendations' => 'required|array',
            'recommendations.*' => 'required|string',
            'selected_status' => 'sometimes|array',
            'selected_status.*' => 'sometimes|boolean',
            'order' => 'sometimes|array',
            'order.*' => 'sometimes|integer',
        ]);

        try {
            $property = Property::findOrFail($propertyId);
            
            $recommendations = $request->recommendations;
            $selectedStatus = $request->input('selected_status', []);
            $order = $request->input('order', []);
            
            // Ensure selected_status array matches recommendations array length
            while (count($selectedStatus) < count($recommendations)) {
                $selectedStatus[] = true; // Default to selected if not provided
            }
            
            // Ensure order array matches recommendations array length
            if (empty($order) || count($order) !== count($recommendations)) {
                $order = array_keys($recommendations); // Default to sequential order
            }
            
            // Store in database
            $exists = DB::table('general_recommendations')
                ->where('property_id', $propertyId)
                ->exists();
            
            if ($exists) {
                DB::table('general_recommendations')
                    ->where('property_id', $propertyId)
                    ->update([
                        'recommendations' => json_encode($recommendations),
                        'selected_status' => json_encode($selectedStatus),
                        'order' => json_encode($order),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('general_recommendations')->insert([
                    'property_id' => $propertyId,
                    'recommendations' => json_encode($recommendations),
                    'selected_status' => json_encode($selectedStatus),
                    'order' => json_encode($order),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'message' => 'Recommendations saved successfully',
                'recommendations' => $recommendations,
                'selected_status' => $selectedStatus,
                'order' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving general recommendations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save recommendations'], 500);
        }
    }

    /**
     * Get saved general recommendations for a property
     */
    public function show($propertyId)
    {
        try {
            $property = Property::findOrFail($propertyId);
            
            $record = DB::table('general_recommendations')
                ->where('property_id', $propertyId)
                ->first();

            if ($record) {
                $recommendations = json_decode($record->recommendations, true);
                $selectedStatus = json_decode($record->selected_status ?? '[]', true);
                $order = json_decode($record->order ?? '[]', true);
                
                // Ensure selected_status array matches recommendations array length
                while (count($selectedStatus) < count($recommendations ?? [])) {
                    $selectedStatus[] = true; // Default to selected if not provided
                }
                
                // Ensure order array matches recommendations array length
                if (empty($order) || count($order) !== count($recommendations ?? [])) {
                    $order = array_keys($recommendations ?? []); // Default to sequential order
                }
                
                return response()->json([
                    'recommendations' => $recommendations ?? [],
                    'selected_status' => $selectedStatus,
                    'order' => $order
                ]);
            }

            return response()->json([
                'recommendations' => [],
                'selected_status' => [],
                'order' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching general recommendations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch recommendations'], 500);
        }
    }

    /**
     * Collect all property data for the prompt
     */
    private function collectPropertyData($property)
    {
        $data = [
            'property_name' => $property->property_name ?? 'Unknown',
        ];

        // Collect device information
        $devices = [];
        foreach ($property->propertyDevices ?? [] as $device) {
            $categoryName = $device->category->name ?? 'Unknown';
            if (!isset($devices[$categoryName])) {
                $devices[$categoryName] = [];
            }
            $devices[$categoryName][] = [
                'device' => $device->device_key ?? 'Unknown',
                'quantity' => $device->quantity ?? 0,
                'power' => $device->power ?? 0,
                'operation_hours' => $device->operation_hours ?? 0,
                'total_consumption' => $device->total_consumption ?? 0,
            ];
        }
        $data['devices'] = $devices;

        // Collect energy bills information
        $bills = [];
        $totalBillsConsumption = 0;
        $totalBillsCost = 0;
        foreach ($property->ebills ?? [] as $bill) {
            $bills[] = [
                'month' => $bill->month ?? 'Unknown',
                'year' => $bill->year ?? 0,
                'consumption' => $bill->energy_consumption_kwh ?? 0,
                'cost' => $bill->value ?? 0,
            ];
            $totalBillsConsumption += $bill->energy_consumption_kwh ?? 0;
            $totalBillsCost += $bill->value ?? 0;
        }
        $data['bills'] = $bills;
        $data['total_bills_consumption'] = $totalBillsConsumption;
        $data['total_bills_cost'] = $totalBillsCost;

        // Collect tariff information from reports
        $tariffs = [];
        foreach ($property->reports ?? [] as $report) {
            foreach ($report->tariffs ?? [] as $tariff) {
                $tariffs[] = [
                    'name' => $tariff->name ?? 'Unknown',
                    'type' => $tariff->type ?? 'Unknown',
                    'unit_cost' => $tariff->unit_cost ?? 0,
                    'unit' => $tariff->unit ?? 'Unknown',
                ];
            }
        }
        $data['tariffs'] = $tariffs;

        // Collect report information if available
        $reports = [];
        foreach ($property->reports ?? [] as $report) {
            $reports[] = [
                'title' => $report->title ?? 'Unknown',
            ];
        }
        $data['reports'] = $reports;

        return $data;
    }

    /**
     * Build the prompt for OpenAI
     */
    private function buildPrompt($propertyData)
    {
        $prompt = "Based on the following property information, provide general energy saving recommendations. ";
        $prompt .= "Keep it simple and direct. Use plain text only, no special formatting characters. ";
        $prompt .= "Provide recommendations as a bulleted list, with each recommendation on a new line.\n\n";
        
        $prompt .= "Property Name: {$propertyData['property_name']}\n\n";

        if (!empty($propertyData['devices'])) {
            $prompt .= "Electrical Devices:\n";
            foreach ($propertyData['devices'] as $category => $devices) {
                $prompt .= "- {$category}: " . count($devices) . " device(s)\n";
                foreach ($devices as $device) {
                    $prompt .= "  - {$device['device']}: {$device['quantity']} units, {$device['power']}W each, ";
                    $prompt .= "{$device['operation_hours']} hours/year, Total: {$device['total_consumption']} kWh/year\n";
                }
            }
            $prompt .= "\n";
        }

        if (!empty($propertyData['bills'])) {
            $prompt .= "Energy Bills:\n";
            $prompt .= "- Total Consumption: {$propertyData['total_bills_consumption']} kWh\n";
            $prompt .= "- Total Cost: {$propertyData['total_bills_cost']} NIS\n";
            $prompt .= "- Number of bills: " . count($propertyData['bills']) . "\n\n";
        }

        if (!empty($propertyData['tariffs'])) {
            $prompt .= "Energy Tariffs:\n";
            foreach ($propertyData['tariffs'] as $tariff) {
                $prompt .= "- {$tariff['name']} ({$tariff['type']}): {$tariff['unit_cost']} NIS/{$tariff['unit']}\n";
            }
            $prompt .= "\n";
        }

        // Include existing recommendations to avoid duplicates
        if (!empty($propertyData['existing_recommendations'])) {
            $prompt .= "Existing Recommendations (DO NOT repeat or suggest similar recommendations):\n";
            foreach ($propertyData['existing_recommendations'] as $index => $existingRec) {
                $prompt .= ($index + 1) . ". " . $existingRec . "\n";
            }
            $prompt .= "\n";
            $prompt .= "IMPORTANT: Do not provide recommendations that are similar to or duplicate the existing recommendations listed above. Provide only NEW and DIFFERENT recommendations.\n\n";
        }

        $prompt .= "Please provide expert-level, highly specific energy saving recommendations for this property. ";
        $prompt .= "Each recommendation should include:\n";
        $prompt .= "1. Specific technical details about what should be done\n";
        $prompt .= "2. The rationale and expected impact\n";
        $prompt .= "3. Potential energy savings estimates where applicable\n";
        $prompt .= "4. Implementation considerations or priority level\n\n";
        $prompt .= "Make recommendations comprehensive, professional, and suitable for formal audit documentation. ";
        $prompt .= "Each recommendation should be detailed enough to be actionable by facility managers or contractors. ";
        $prompt .= "Provide each recommendation on a separate line, with clear separation between recommendations.";

        return $prompt;
    }
}
