<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyDevice;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class DeviceCategoryAnalysisService
{
    /**
     * Generate analysis paragraphs for a device category.
     */
    public function generateAnalysis(int $propertyId, int $categoryId, ?string $categoryName = null): ?array
    {
        try {
            $devices = PropertyDevice::with(['device', 'category'])
                ->where('property_id', $propertyId)
                ->where('category_id', $categoryId)
                ->get();

            if ($devices->isEmpty()) {
                return null;
            }

            $property = Property::find($propertyId);
            if (!$property) {
                return null;
            }

            $propertyTotalConsumption = PropertyDevice::where('property_id', $propertyId)->sum('total_consumption');
            $categoryTotalConsumption = $devices->sum('total_consumption');

            $categoryPercentage = $propertyTotalConsumption > 0
                ? round(($categoryTotalConsumption / $propertyTotalConsumption) * 100, 2)
                : null;

            $totalUnits = (int) $devices->sum('quantity');

            $deviceBreakdown = $devices->groupBy(function (PropertyDevice $device) {
                return $device->device->lookup_value
                    ?? $device->device->name
                    ?? $device->device_key
                    ?? 'Device';
            })->map(function ($group, $name) {
                /** @var \Illuminate\Support\Collection $group */
                return [
                    'device_type'              => $name,
                    'quantity'                 => (int) $group->sum('quantity'),
                    'average_power_watt'       => round((float) $group->avg('power'), 2),
                    'average_operation_hours'  => round((float) $group->avg('operation_hours'), 2),
                    'total_consumption_kwh'    => round((float) $group->sum('total_consumption'), 3),
                ];
            })->values()->toArray();

            $dataset = [
                'property_name'                    => $property->property_name ?? 'the property',
                'category_name'                    => $categoryName
                    ?? ($devices->first()->category->lookup_value ?? 'Device category'),
                'category_total_consumption_kwh'   => round((float) $categoryTotalConsumption, 3),
                'category_percentage_of_property'  => $categoryPercentage,
                'total_device_units'               => $totalUnits,
                'device_breakdown'                 => $deviceBreakdown,
            ];

            $prompt = "You are an energy efficiency specialist. "
                . "Using the JSON dataset below, write two concise analytical paragraphs "
                . "about the specified system inside a building. "
                . "Paragraph 1 (intro) should describe the system scale, key device types, "
                . "unit counts, and annual consumption with any available percentage of the total property load. "
                . "Paragraph 2 (outro) should interpret what the numbers mean, highlighting which devices dominate "
                . "consumption and any efficiency observations.\n\n"
                . "Dataset:\n" . json_encode($dataset, JSON_PRETTY_PRINT) . "\n\n"
                . "Return valid JSON with the following shape:\n"
                . "{\"intro\": \"<paragraph>\", \"outro\": \"<paragraph>\"}.\n"
                . "Rules: use plain text sentences (no bullet lists, no markdown, no special formatting). "
                . "Mention figures in kWh and percentages when available. "
                . "Reference the table generically as \"Table for this system\". "
                . "Do not include any additional keys.";

            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured for device category analysis.');
                return null;
            }

            $client = new Client();
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.5,
                    'max_tokens'  => 500,
                ],
                'timeout' => 45,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $rawContent   = trim($responseData['choices'][0]['message']['content'] ?? '');

            if ($rawContent === '') {
                return null;
            }

            $cleanContent = preg_replace('/```json|```/i', '', $rawContent);
            $decoded = json_decode($cleanContent, true);

            if (
                !is_array($decoded)
                || !isset($decoded['intro'])
                || !isset($decoded['outro'])
            ) {
                Log::warning('Device category analysis response missing expected keys.', ['response' => $cleanContent]);
                return null;
            }

            return [
                'intro' => trim($decoded['intro']),
                'outro' => trim($decoded['outro']),
            ];
        } catch (\Exception $e) {
            Log::error('Error generating device category analysis: ' . $e->getMessage());
            return null;
        }
    }
}

