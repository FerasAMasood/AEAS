<?php
// app/Http/Controllers/ReportSummaryController.php
namespace App\Http\Controllers;

use App\Models\ReportSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportSummaryController extends Controller
{
    // Store a new report summary
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'content' => 'required|string',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $reportSummary = ReportSummary::create($validated);
        $reportSummary->load(['creator', 'updater']);

        return response($reportSummary, 201);
    }

    // Show a specific report summary by ID
    public function show($id): Response
    {
        $reportSummary = ReportSummary::with(['creator', 'updater'])->findOrFail($id);

        return response($reportSummary, 200);
    }

    // Get summaries by report_id (for frontend query)
    public function index(Request $request): Response
    {
        $reportId = $request->query('report_id');
        
        if ($reportId) {
            $summaries = ReportSummary::with(['creator', 'updater'])
                ->where('report_id', $reportId)
                ->get();
            return response($summaries, 200);
        }
        
        $summaries = ReportSummary::with(['creator', 'updater'])->get();
        return response($summaries, 200);
    }

    // Update an existing report summary
    public function update(Request $request, $id): Response
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $validated['updated_by'] = $request->user()->id;

        $reportSummary = ReportSummary::findOrFail($id);
        $reportSummary->update($validated);
        $reportSummary->load(['creator', 'updater']);

        return response($reportSummary, 200);
    }

    // Rewrite summary using OpenAI
    public function rewrite(Request $request, $id): Response
    {
        set_time_limit(120); // 2 minutes for OpenAI API calls
        
        $reportSummary = ReportSummary::findOrFail($id);
        $currentContent = $reportSummary->content;

        if (empty($currentContent)) {
            return response(['error' => 'No content to rewrite.'], 400);
        }

        try {
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                \Illuminate\Support\Facades\Log::error('OpenAI API key not configured');
                return response(['error' => 'OpenAI API key not configured.'], 500);
            }

            // Strip HTML tags for the prompt (keep plain text)
            $plainText = strip_tags($currentContent);
            
            $prompt = "Rewrite the following energy audit report summary to be professional, clear, and well-structured. Keep it simple and direct. Maintain all key information and technical details. Use plain text only, no special formatting characters:\n\n" . $plainText;

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
                return response(['error' => 'Failed to generate rewritten summary.'], 500);
            }

            $rewrittenContent = trim($responseData['choices'][0]['message']['content']);

            return response(['rewritten_content' => $rewrittenContent], 200);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Illuminate\Support\Facades\Log::error('OpenAI API error: ' . $e->getMessage(), [
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);
            
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 504) {
                return response(['error' => 'The OpenAI API request timed out. Please try again later.'], 504);
            }
            
            return response(['error' => 'Failed to rewrite summary: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error rewriting summary: ' . $e->getMessage());
            return response(['error' => 'An error occurred while rewriting the summary.'], 500);
        }
    }
}
