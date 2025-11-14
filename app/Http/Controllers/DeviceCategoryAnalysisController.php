<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSubsection;
use App\Services\DeviceCategoryAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeviceCategoryAnalysisController extends Controller
{
    public function __construct(private readonly DeviceCategoryAnalysisService $analysisService)
    {
    }

    /**
     * Generate analysis paragraphs for a device category subsection.
     */
    public function analyze(Document $document, DocumentSubsection $subsection): JsonResponse
    {
        $this->ensureSubsectionBelongsToDocument($document, $subsection);

        $categoryId = $this->extractCategoryId($subsection->slug);
        if (!$categoryId) {
            return response()->json(['error' => 'Subsection is not linked to a device category.'], 422);
        }

        $document->loadMissing('report');
        $propertyId = $document->report?->property_id;

        if (!$propertyId) {
            return response()->json(['error' => 'Document is not associated with a property.'], 422);
        }

        $analysis = $this->analysisService->generateAnalysis(
            $propertyId,
            $categoryId,
            $subsection->title
        );

        if (!$analysis) {
            return response()->json(['error' => 'Unable to generate analysis. Ensure devices exist for this category.'], 422);
        }

        return response()->json(['analysis' => $analysis]);
    }

    /**
     * Persist analysis paragraphs for the subsection.
     */
    public function store(Request $request, Document $document, DocumentSubsection $subsection): JsonResponse
    {
        $this->ensureSubsectionBelongsToDocument($document, $subsection);

        $data = $request->validate([
            'analysis_intro' => 'required|string',
            'analysis_outro' => 'required|string',
        ]);

        $subsection->analysis_intro = $data['analysis_intro'];
        $subsection->analysis_outro = $data['analysis_outro'];

        if (Schema::hasColumn('document_subsections', 'updated_by')) {
            $subsection->updated_by = optional($request->user())->id;
        }

        $subsection->save();

        return response()->json([
            'subsection' => $subsection->fresh(),
        ]);
    }

    private function ensureSubsectionBelongsToDocument(Document $document, DocumentSubsection $subsection): void
    {
        if ($subsection->document_id !== $document->id) {
            abort(404, 'Subsection not found for this document');
        }
    }

    private function extractCategoryId(?string $slug): ?int
    {
        if (!$slug || !Str::startsWith($slug, 'device_category_')) {
            return null;
        }

        $id = (int) Str::after($slug, 'device_category_');

        return $id > 0 ? $id : null;
    }
}

