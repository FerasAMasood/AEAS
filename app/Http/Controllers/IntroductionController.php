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
        ]);

        return response()->json($introduction, 201);
    }

    // Method to retrieve an introduction by report ID
    public function show($report_id)
    {
        $introduction = Introduction::where('report_id', $report_id)->first();

        if (!$introduction) {
            return response()->json(['message' => 'Introduction not found'], 404);
        }

        return response()->json($introduction);
    }
}
