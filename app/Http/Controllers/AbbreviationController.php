<?php

// app/Http/Controllers/AbbreviationController.php

namespace App\Http\Controllers;

use App\Models\Abbreviation;
use Illuminate\Http\Request;

class AbbreviationController extends Controller
{
    public function index()
    {
        return Abbreviation::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'abbreviation' => 'required|unique:abbreviations|max:255',
            'meaning' => 'required|max:255',
        ]);

        $abbreviation = Abbreviation::create($request->all());

        return response()->json($abbreviation, 201);
    }

    public function show($abbreviation)
    {
        return Abbreviation::where('abbreviation', $abbreviation)->firstOrFail();
    }

    public function update(Request $request, $abbreviation)
    {
        $request->validate([
            'abbreviation' => 'required|unique:abbreviations,abbreviation,'.$abbreviation.'|max:255',
            'meaning' => 'required|max:255',
        ]);

        $abbreviation = Abbreviation::where('abbreviation', $abbreviation)->firstOrFail();
        $abbreviation->update($request->all());

        return response()->json($abbreviation, 200);
    }

    public function destroy($abbreviation)
    {
        $abbreviation = Abbreviation::where('abbreviation', $abbreviation)->firstOrFail();
        $abbreviation->delete();

        return response()->json(null, 204);
    }
}
