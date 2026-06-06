<?php

// app/Http/Controllers/IntroductionController.php

namespace App\Http\Controllers;

use App\Models\Introduction;
use Illuminate\Http\Request;

class IntroductionController extends Controller
{
    // Method to create a new introduction
    public function store(Request $request)
    {
        $request->validate([
            'report_id' => 'required|exists:reports,id',
            'content' => 'required|string',
        ]);

        $introduction = Introduction::create([
            'report_id' => $request->report_id,
            'content' => $request->content,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $introduction->load(['creator', 'updater']);

        return response()->json($introduction, 201);
    }

    // Method to retrieve an introduction by report ID
    public function show($report_id)
    {
        $introduction = Introduction::with(['creator', 'updater'])
            ->where('report_id', $report_id)
            ->first();

        if (!$introduction) {
            return response()->json(['message' => 'Introduction not found'], 404);
        }

        return response()->json($introduction);
    }

    // Method to update an introduction
    public function update(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $introduction = Introduction::findOrFail($id);
        $introduction->update([
            'content' => $request->content,
            'updated_by' => $request->user()->id,
        ]);

        $introduction->load(['creator', 'updater']);

        return response()->json($introduction);
    }

    // Rewrite introduction using OpenAI
    public function rewrite(Request $request, $id)
    {
        set_time_limit(120); // 2 minutes for OpenAI API calls
        
        $introduction = Introduction::findOrFail($id);
        $currentContent = $introduction->content;

        if (empty($currentContent)) {
            return response()->json(['error' => 'No content to rewrite.'], 400);
        }

        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                \Illuminate\Support\Facades\Log::error('OpenAI API key not configured');
                return response()->json(['error' => 'OpenAI API key not configured.'], 500);
            }

            // Strip HTML tags for the prompt (keep plain text)
            $plainText = strip_tags($currentContent);
            
            $prompt = "Rewrite the following energy audit report introduction to be professional, clear, and well-structured. Keep it simple and direct. Maintain all key information and technical details. Use plain text only, no special formatting characters:\n\n" . $plainText;

            $client = new \GuzzleHttp\Client([
                'timeout' => 90,
                'connect_timeout' => 10,
            ]);
            
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
                    'max_tokens' => 2000,
                    'temperature' => 0.7,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($responseData['choices'][0]['message']['content'])) {
                \Illuminate\Support\Facades\Log::error('OpenAI response missing content', ['response' => $responseData]);
                return response()->json(['error' => 'Failed to generate rewritten introduction.'], 500);
            }

            $rewrittenContent = trim($responseData['choices'][0]['message']['content']);

            return response()->json(['rewritten_content' => $rewrittenContent], 200);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Illuminate\Support\Facades\Log::error('OpenAI API error: ' . $e->getMessage(), [
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 504) {
                return response()->json(['error' => 'The OpenAI API request timed out. Please try again later.'], 504);
            }
            
            return response()->json(['error' => 'Failed to rewrite introduction: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error rewriting introduction: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while rewriting the introduction.'], 500);
        }
    }
}
