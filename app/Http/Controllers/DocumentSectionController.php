<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentSectionController extends Controller
{
    public function index(Document $document)
    {
        return $document->sections()->with(['subsections', 'creator', 'updater'])->get();
    }

    public function store(Request $request, Document $document)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'section_type' => 'in:fixed,dynamic',
            'fixed_type' => 'nullable|in:summary,introduction,abbreviations,tariffs,bills',
        ]);

        $validated['document_id'] = $document->id;
        $validated['position'] = $document->sections()->count();
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $section = DocumentSection::create($validated);
        return $section->load(['subsections', 'creator', 'updater']);
    }

    public function show(Document $document, DocumentSection $section)
    {
        if ($section->document_id !== $document->id) {
            return response()->json(['message' => 'Section not found'], 404);
        }
        return $section->load(['subsections', 'creator', 'updater']);
    }

    public function update(Request $request, Document $document, DocumentSection $section)
    {
        if ($section->document_id !== $document->id) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        // Fixed sections can only have their title updated
        if ($section->section_type === 'fixed') {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
            ]);
        } else {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
            ]);
        }

        $validated['updated_by'] = $request->user()->id;
        $section->update($validated);
        return $section->load(['subsections', 'creator', 'updater']);
    }

    public function destroy(Document $document, DocumentSection $section)
    {
        if ($section->document_id !== $document->id) {
            return response()->json(['message' => 'Section not found'], 404);
        }

        $section->delete();
        return response()->noContent();
    }

    public function setOrder(Request $request, Document $document)
    {
        $sections = $request->validate(['sections' => 'required|array'])['sections'];
        
        DB::transaction(function () use ($document, $sections) {
            foreach ($sections as $i => $sectionId) {
                DocumentSection::where('document_id', $document->id)
                    ->where('id', $sectionId)
                    ->update(['position' => $i]);
            }
        });

        return $document->sections()->with(['subsections', 'creator', 'updater'])->get();
    }
}
