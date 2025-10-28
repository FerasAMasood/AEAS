<?php
// app/Http/Controllers/LookupController.php

namespace App\Http\Controllers;

use App\Models\Lookup;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function index()
    {
        return Lookup::with('parentCategory')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'lookup_key' => 'required|max:3',
            'lookup_table' => 'required|string|max:255',
            'lookup_field' => 'required|string|max:255',
            'category' => 'nullable|exists:lookups,id',
            'lookup_value' => 'required|string|max:255',
        ]);

        $lookup = Lookup::create($request->all());

        return response()->json($lookup, 201);
    }

    public function show($id)
    {
        return Lookup::with('parentCategory')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'lookup_key' => 'required|max:3',
            'lookup_table' => 'required|string|max:255',
            'lookup_field' => 'required|string|max:255',
            'category' => 'nullable|exists:lookups,id',
            'lookup_value' => 'required|string|max:255',
        ]);

        $lookup = Lookup::findOrFail($id);
        $lookup->update($request->all());

        return response()->json($lookup, 200);
    }

    public function destroy($id)
    {
        $lookup = Lookup::findOrFail($id);
        $lookup->delete();

        return response()->json(null, 204);
    }

    public function search(Request $request)
    {
        $request->validate([
            'lookup_table' => 'required|string|max:255',
            'lookup_field' => 'required|string|max:255',
            'category' => 'nullable|exists:lookups,id',
        ]);

        $query = Lookup::with('parentCategory')->where('lookup_table', $request->lookup_table)
                        ->where('lookup_field', $request->lookup_field);

        if ($request->category) {
            $query->where('category', $request->category);
        }

        return $query->get();
    }
}
