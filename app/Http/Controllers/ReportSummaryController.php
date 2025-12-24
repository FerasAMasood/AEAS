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
}
