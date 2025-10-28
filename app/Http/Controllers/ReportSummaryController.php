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

        $reportSummary = ReportSummary::create($validated);

        return response($reportSummary, 201);
    }

    // Show a specific report summary
    public function show($id): Response
    {
        $reportSummary = ReportSummary::findOrFail($id);

        return response($reportSummary, 200);
    }

    // Update an existing report summary
    public function update(Request $request, $id): Response
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $reportSummary = ReportSummary::findOrFail($id);
        $reportSummary->update($validated);

        return response($reportSummary, 200);
    }
}
