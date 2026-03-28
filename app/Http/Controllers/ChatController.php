<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\ChatConversation;
use App\Models\Report;
use App\Models\ReportSummary;
use App\Models\Introduction;
use App\Models\PropertyDevice;

class ChatController extends Controller
{
    /**
     * Get conversation history for a report
     */
    public function getConversation(Request $request, $reportId)
    {
        $conversation = ChatConversation::where('report_id', $reportId)->first();
        
        if (!$conversation) {
            return response()->json(['messages' => []]);
        }
        
        return response()->json(['messages' => $conversation->messages ?? []]);
    }

    /**
     * Clear conversation history for a report
     */
    public function clearConversation(Request $request, $reportId)
    {
        $conversation = ChatConversation::where('report_id', $reportId)->first();
        
        if ($conversation) {
            $conversation->messages = [];
            $conversation->save();
        }
        
        return response()->json(['message' => 'Conversation cleared successfully']);
    }

    /**
     * Send message to OpenAI and stream the response
     */
    public function chat(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'message' => 'required|string',
            'report_id' => 'required|integer|exists:reports,id',
            'settings' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = $request->input('message');
        $reportId = $request->input('report_id');
        $settings = $request->input('settings', []);
        
        // Get or create conversation for this report
        $conversation = ChatConversation::firstOrCreate(
            ['report_id' => $reportId],
            ['messages' => []]
        );
        
        // Get conversation history from database
        $conversationHistory = $conversation->messages ?? [];

        $apiKey = env('OPENAI_API_KEY');
        
        if (!$apiKey) {
            Log::error('OpenAI API key not configured');
            return response()->json(['error' => 'OpenAI API key not configured.'], 500);
        }

        // Default settings
        $model = $settings['model'] ?? 'gpt-4o-mini';
        $temperature = $settings['temperature'] ?? 0.7;
        $maxTokens = $settings['max_tokens'] ?? null;
        $customSystemPrompt = $settings['system_prompt'] ?? null;

        // Default system prompt for Energy Auditing Assistant
        $defaultSystemPrompt = "ROLE AND CONTEXT
You are an Energy Auditing Assistant. You support professional energy audits for buildings and facilities. Your outputs must be suitable for insertion into formal audit documentation.

SCOPE
- Focus strictly on energy auditing topics: building envelope, HVAC, lighting, domestic hot water, controls/BMS, plug loads, renewables, tariffs, metering, measurement & verification, baseline modeling, commissioning/retro-commissioning, audits (ASHRAE Level 1/2/3 style), and retrofit recommendations.
- If the user request is outside energy auditing, respond with: \"Out of scope for energy auditing.\" and suggest what audit-relevant information would be needed.

STYLE AND OUTPUT RULES
- Write in formal, technical language.
- Keep the response short and to the point.
- Do not address the reader directly (avoid \"you/your\"). Do not use conversational filler.
- Avoid opinions. Use evidence-based framing, ranges, assumptions, and measurable criteria.
- Prefer numbers, units, and testable statements. Include formulas when helpful.
- Use SI units by default; include common alternatives in parentheses when relevant.
- If a claim depends on missing data, state the dependency and provide a minimal data request list.
- Do not invent measurements, site conditions, utility rates, equipment specs, or regulatory requirements.
- When uncertain, ask questions to the user.
- IMPORTANT: Do not use any special formatting characters such as asterisks (*), underscores (_), hashtags (#), brackets ([]), or any markdown formatting. Use plain text only.

STRUCTURE
Provide outputs in this order when applicable (don't add titles):
1) Description (short paragraph)
2) Evidence / Inputs used (bullet points)
3) Calculations / Method (very brief)
4) Recommendations (prioritized, bullet points)
5) Data needed (only if required)

SAFETY AGAINST HALLUCINATION
- If the prompt lacks sufficient information for a defensible conclusion, do not guess. Provide a constrained answer plus the minimal additional inputs required.
- Do not cite specific standards, code clauses, incentives, or local regulations unless they were provided in the prompt.

TASK
Use the user's prompt below as the work request. Produce the response as audit-document text.";

        // Use custom system prompt if provided, otherwise use default
        $systemPrompt = $customSystemPrompt ?? $defaultSystemPrompt;

        // Prepare messages array
        $messages = [];
        
        // Check if this is the first message in the conversation (no history)
        $isFirstMessage = empty($conversationHistory);
        
        // Add system prompt only if this is the first message
        if ($isFirstMessage) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }
        
        // Add conversation history (previous messages)
        foreach ($conversationHistory as $historyMessage) {
            // Validate history message structure
            if (isset($historyMessage['role']) && isset($historyMessage['content'])) {
                // Only include user and assistant messages (skip system messages from history)
                if (in_array($historyMessage['role'], ['user', 'assistant'])) {
                    $messages[] = [
                        'role' => $historyMessage['role'],
                        'content' => $historyMessage['content'],
                    ];
                }
            }
        }
        
        // Build context data based on settings
        $contextData = [];
        
        if (!empty($settings)) {
            $report = Report::with(['abbreviations', 'summary', 'introduction', 'tariffs.source', 'property.ebills', 'property.propertyDevices.category'])
                ->find($reportId);
            
            if ($report) {
                // Abbreviations
                if (!empty($settings['abbreviations']) && $report->abbreviations) {
                    $abbreviationsText = "ABBREVIATIONS:\n";
                    foreach ($report->abbreviations as $abbr) {
                        $abbreviationsText .= "- {$abbr->abbreviation}: {$abbr->meaning}\n";
                    }
                    $contextData[] = $abbreviationsText;
                }
                
                // Tariffs
                if (!empty($settings['tariffs']) && $report->tariffs) {
                    $tariffsText = "TARIFFS:\n";
                    foreach ($report->tariffs as $tariff) {
                        $sourceName = $tariff->source ? $tariff->source->name : 'Unknown';
                        $tariffsText .= "- {$sourceName}: {$tariff->unit_cost} per unit\n";
                    }
                    $contextData[] = $tariffsText;
                }
                
                // Summary
                if (!empty($settings['summary']) && $report->summary) {
                    $contextData[] = "SUMMARY:\n{$report->summary->content}";
                }
                
                // Introduction
                if (!empty($settings['introduction']) && $report->introduction) {
                    $contextData[] = "INTRODUCTION:\n{$report->introduction->content}";
                }
                
                // Energy Bills
                if (!empty($settings['energyBills']) && $report->property && $report->property->ebills) {
                    $billsText = "ENERGY BILLS:\n";
                    foreach ($report->property->ebills as $bill) {
                        $billsText .= "- Date: {$bill->date}, Value: {$bill->value}, Consumption: {$bill->energy_consumption_kwh} kWh\n";
                    }
                    $contextData[] = $billsText;
                }
                
                // Device Categories
                if (!empty($settings['deviceCategories']) && is_array($settings['deviceCategories']) && $report->property && $report->property->propertyDevices) {
                    $categoryIds = $settings['deviceCategories'];
                    $filteredDevices = $report->property->propertyDevices
                        ->whereIn('category_id', $categoryIds);
                    
                    if ($filteredDevices->isNotEmpty()) {
                        $devicesByCategory = $filteredDevices->groupBy('category_id');
                        
                        foreach ($devicesByCategory as $categoryId => $devices) {
                            $firstDevice = $devices->first();
                            $category = $firstDevice && $firstDevice->category ? $firstDevice->category : null;
                            $categoryName = $category ? $category->lookup_value : "Category {$categoryId}";
                            
                            $devicesText = "DEVICE CATEGORY: {$categoryName}\n";
                            foreach ($devices as $device) {
                                $devicesText .= "- Device: " . ($device->device_key ?? 'N/A') . ", Description: " . ($device->description ?? 'N/A') . ", ";
                                $devicesText .= "Power: " . ($device->power ?? 'N/A') . " W, Quantity: " . ($device->quantity ?? 'N/A') . ", ";
                                $devicesText .= "Operation Hours: " . ($device->operation_hours ?? 'N/A') . ", Total Consumption: " . ($device->total_consumption ?? 'N/A') . " kWh\n";
                                if ($device->notes) {
                                    $devicesText .= "  Notes: {$device->notes}\n";
                                }
                            }
                            $contextData[] = $devicesText;
                        }
                    }
                }
            }
        }
        
        // Build the user message with context
        $userMessageContent = $message;
        if (!empty($contextData)) {
            $userMessageContent = "CONTEXT DATA:\n" . implode("\n\n", $contextData) . "\n\nUSER PROMPT: {$message}";
        }
        
        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessageContent,
        ];

        // Prepare request body
        $requestBody = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'stream' => true,
        ];

        if ($maxTokens !== null) {
            $requestBody['max_tokens'] = $maxTokens;
        }

        // Disable output buffering for streaming
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        return new StreamedResponse(function () use ($apiKey, $requestBody, $conversation, $message, $reportId) {
            // Disable time limit and output buffering
            set_time_limit(120);
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $client = new Client([
                'timeout' => 120,
            ]);

            $assistantResponse = ''; // Accumulate the full response

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
                        
                        $line = trim($line);
                        
                        if (empty($line)) {
                            continue;
                        }

                        // Handle SSE format
                        if (strpos($line, 'data: ') === 0) {
                            $data = substr($line, 6);
                            
                            if (trim($data) === '[DONE]') {
                                // Save conversation after streaming completes
                                $updatedMessages = $conversation->messages ?? [];
                                $updatedMessages[] = ['role' => 'user', 'content' => $message];
                                $updatedMessages[] = ['role' => 'assistant', 'content' => $assistantResponse];
                                $conversation->messages = $updatedMessages;
                                $conversation->save();
                                
                                echo "data: [DONE]\n\n";
                                flush();
                                return;
                            }

                            $json = json_decode($data, true);
                            
                            if (json_last_error() === JSON_ERROR_NONE) {
                                if (isset($json['choices'][0]['delta']['content'])) {
                                    $content = $json['choices'][0]['delta']['content'];
                                    if (!empty($content)) {
                                        $assistantResponse .= $content; // Accumulate response
                                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                        flush();
                                    }
                                } elseif (isset($json['error'])) {
                                    echo "data: " . json_encode(['error' => $json['error']['message'] ?? 'Unknown error']) . "\n\n";
                                    flush();
                                    return;
                                }
                            } else {
                                // Log JSON decode errors for debugging
                                Log::debug('JSON decode error in chat stream', [
                                    'data' => $data,
                                    'error' => json_last_error_msg()
                                ]);
                            }
                        }
                    }
                }
                
                // Save conversation if stream ended without [DONE]
                if (!empty($assistantResponse)) {
                    $updatedMessages = $conversation->messages ?? [];
                    $updatedMessages[] = ['role' => 'user', 'content' => $message];
                    $updatedMessages[] = ['role' => 'assistant', 'content' => $assistantResponse];
                    $conversation->messages = $updatedMessages;
                    $conversation->save();
                }
                
                // Send final done message
                echo "data: [DONE]\n\n";
                flush();
            } catch (RequestException $e) {
                Log::error('OpenAI API streaming error: ' . $e->getMessage());
                
                if ($e->hasResponse()) {
                    $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);
                    $errorMessage = $errorResponse['error']['message'] ?? 'An error occurred';
                } else {
                    $errorMessage = 'Network error occurred';
                }
                
                echo "data: " . json_encode(['error' => $errorMessage]) . "\n\n";
                flush();
            } catch (\Exception $e) {
                Log::error('Unexpected error in chat streaming: ' . $e->getMessage());
                echo "data: " . json_encode(['error' => 'An unexpected error occurred']) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

