<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class ReportAbbreviationController extends Controller
{
    public function createAssociations(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'abbreviation_ids' => 'required|array',
            'abbreviation_ids.*' => 'exists:abbreviations,id'
        ]);

        $report = Report::findOrFail($validated['report_id']);
        $report->abbreviations()->sync($validated['abbreviation_ids']); // Syncs abbreviations

        return response()->json(['message' => 'Abbreviations associated successfully.']);
    }

    public function getAssociations($reportId)
    {
        $report = Report::with('abbreviations:id')->findOrFail($reportId);
        $abbreviationIds = $report->abbreviations->pluck('id'); // Get only the IDs

        return response()->json($abbreviationIds);
    }
}
