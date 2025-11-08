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
}
