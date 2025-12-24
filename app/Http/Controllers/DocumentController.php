<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentSection;
use App\Models\DocumentSubsection;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function index()
    {
        return Document::with('sections.subsections')->paginate(20);
    }

    public function store(Request $request)
    {
        $document = Document::create([
            'title'      => $request->title,
            'status'     => $request->status ?? 'draft',
            'report_id'  => $request->report_id,
            'created_by' => optional($request->user())->id,
            'updated_by' => optional($request->user())->id,
        ]);

        $this->buildFixedSubsections($document, $request);

        return $document->load('sections.subsections');
    }

    public function show(Document $document)
    {
        $document = $document->load('sections.subsections');
        $this->loadFixedSubsectionData($document);
        return $document;
    }

    public function showByReportId(Request $request, $reportId)
    {
        $document = Document::firstOrCreate(
            ['report_id' => $reportId],
            [
                'title'      => optional(\App\Models\Report::find($reportId))->report_title ?? "Document for Report #{$reportId}",
                'status'     => 'draft',
                'created_by' => optional($request->user())->id,
                'updated_by' => optional($request->user())->id,
            ]
        );

        $this->migrateOldStructure($document, $request);
        $document = $document->load('sections.subsections');
        $this->loadFixedSubsectionData($document);

        return $document;
    }

    public function update(Request $request, Document $document)
    {
        $document->update($request->only('title', 'status'));
        return $document;
    }

    public function destroy(Document $document)
    {
        $document->delete();
        return response()->noContent();
    }

    public function addSubsection(Request $request, Document $document)
    {
        $validated = $this->validateSubsectionPayload($request);

        return DB::transaction(function () use ($request, $document, $validated) {
            $section = DocumentSection::findOrFail($validated['section_id']);

            if ($section->document_id !== $document->id) {
                abort(422, 'Section does not belong to this document');
            }

            $subsectionData = array_merge(
                $validated,
                [
                    'document_id' => $document->id,
                    'position'    => $section->subsections()->max('position') + 1,
                ]
            );

            // Ensure content_html is always set (required field)
            if (!isset($subsectionData['content_html']) || $subsectionData['content_html'] === null) {
                $subsectionData['content_html'] = '';
            }

            $subsectionData = $this->applyAuditColumns($subsectionData, $request);

            if ($request->hasFile('pdf_file')) {
                $subsectionData['pdf_file'] = $this->handlePdfUpload($request, null);
            }

            if ($request->has('images')) {
                $subsectionData['images'] = $this->processBase64Images($request);
            }

            return DocumentSubsection::create($subsectionData)->load('section');
        });
    }

    public function updateSubsection(Request $request, Document $document, DocumentSubsection $subsection)
    {
        if ($subsection->document_id !== $document->id) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        $validated      = $this->validateSubsectionPayload($request, true);
        $imagesProvided = $request->has('images');
        $newSectionId   = $request->input('section_id');

        DB::transaction(function () use ($request, $document, $subsection, &$validated, $imagesProvided, $newSectionId) {
            if ($imagesProvided) {
                $validated['images'] = $this->processBase64Images($request);
            }

            if ($request->hasFile('pdf_file')) {
                $validated['pdf_file'] = $this->handlePdfUpload($request, $subsection->pdf_file);
            }

            unset($validated['section_id']);

            if ($newSectionId !== null) {
                $this->handleSectionMove($document, $subsection, (int) $newSectionId);
            }

            $this->applySubsectionUpdates($subsection, $validated);

            \Log::info('Subsection updated', [
                'subsection_id' => $subsection->id,
                'section_id'    => $subsection->section_id,
                'position'      => $subsection->position,
            ]);
        });

        return $subsection->load('section');
    }

    public function deleteSubsection(Document $document, DocumentSubsection $subsection)
    {
        if ($subsection->document_id !== $document->id) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        DB::transaction(function () use ($subsection) {
            if ($subsection->pdf_file) {
                Storage::disk('public')->delete($subsection->pdf_file);
            }
            $subsection->delete();
        });

        return response()->noContent();
    }

    public function moveSubsectionSection(Request $request, Document $document, DocumentSubsection $subsection)
    {
        if ($subsection->document_id !== $document->id) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        $data = $request->validate([
            'section_id' => 'required|integer|exists:document_sections,id',
        ]);

        DB::transaction(function () use ($document, $subsection, $data) {
            $this->handleSectionMove($document, $subsection, (int) $data['section_id']);
            $this->setUpdatedByIfPresent($subsection);
            $subsection->save();
        });

        return $subsection->load('section');
    }

    public function setSubsectionOrder(Request $request, Document $document)
    {
        $subsections = $request->validate(['subsections' => 'required|array'])['subsections'];

        DB::transaction(function () use ($document, $subsections) {
            foreach ($subsections as $i => $subsectionId) {
                DocumentSubsection::where('document_id', $document->id)
                    ->where('id', $subsectionId)
                    ->update(['position' => $i]);
            }
        });

        return response()->json(['message' => 'Subsection order updated']);
    }

    public function setOrder(Request $request, Document $document)
    {
        $validated = $request->validate([
            'blocks'                  => 'required|array',
            'blocks.*.type'           => 'required|in:section,subsection',
            'blocks.*.subsection_id'  => 'nullable|exists:document_subsections,id',
        ]);

        $this->migrateOldStructure($document, $request);

        DB::transaction(function () use ($document, $validated) {
            foreach ($validated['blocks'] as $index => $block) {
                DocumentBlock::updateOrCreate(
                    ['document_id' => $document->id, 'subsection_id' => $block['subsection_id']],
                    ['block_type' => $block['type'], 'position' => $index]
                );
            }
        });

        return $document->load('sections.subsections');
    }

    public function renderHtml(Document $document, DocumentRenderService $service)
    {
        $this->migrateOldStructure($document, request());
        return response(
            $service->renderHtml($document->load('sections.subsections')),
            200
        )->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function decodeImagesInput(Request $request): ?array
    {
        if (!$request->has('images')) {
            return null;
        }

        $images = $request->input('images');

        if (is_string($images)) {
            $decoded = json_decode($images, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages(['images' => 'Invalid images JSON payload.']);
            }

            return $decoded ?? [];
        }

        if (is_array($images)) {
            return $images;
        }

        throw ValidationException::withMessages(['images' => 'Invalid images payload.']);
    }

    private function validateSubsectionPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'title'           => 'nullable|string|max:255',
            'slug'            => 'nullable|string|max:255',
            'subsection_type' => 'in:text,images,pdf',
            'content_html'    => 'nullable|string',
            'analysis_intro'  => 'nullable|string',
            'analysis_outro'  => 'nullable|string',
            'image_captions'  => 'nullable|array',
            'image_captions.*'=> 'nullable|string|max:500',
            'pdf_file'        => 'nullable|file|mimes:pdf|max:10240',
            'section_id'      => 'required|integer|exists:document_sections,id',
        ];

        if ($isUpdate) {
            $rules['section_id'] = 'nullable|integer|exists:document_sections,id';
        }

        return $request->validate($rules);
    }

    private function processBase64Images(Request $request): array
    {
        $imagesInput = $this->decodeImagesInput($request);
        if ($imagesInput === null) {
            return [];
        }

        $captions = $request->input('image_captions', []);
        if ($captions && count($captions) !== count($imagesInput)) {
            throw ValidationException::withMessages(['image_captions' => 'Captions must match number of images.']);
        }

        $processed = [];
        foreach ($imagesInput as $index => $imageInput) {
            $base64 = is_array($imageInput) ? ($imageInput['base64'] ?? null) : $imageInput;
            if (!$base64) {
                continue;
            }

            if (!$this->safeBase64Validation($base64)) {
                throw ValidationException::withMessages(['images' => 'Invalid base64 image format.']);
            }

            $caption = is_array($imageInput) ? ($imageInput['caption'] ?? null) : ($captions[$index] ?? null);

            $processed[] = [
                'base64'  => $base64,
                'caption' => $caption,
            ];
        }

        return $processed;
    }

    private function handlePdfUpload(Request $request, ?string $oldPath): string
    {
        $file = $request->file('pdf_file');

        if (!$file) {
            return $oldPath ?? '';
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $file->store('documents/pdfs', 'public');
    }

    private function handleSectionMove(Document $document, DocumentSubsection $subsection, int $newSectionId): void
    {
        if ($newSectionId === $subsection->section_id) {
            return;
        }

        $section = DocumentSection::findOrFail($newSectionId);

        if ($section->document_id !== $document->id) {
            abort(422, 'Section does not belong to this document');
        }

        $subsection->section_id = $newSectionId;
        $subsection->position   = $section->subsections()->max('position') + 1;
    }

    private function applySubsectionUpdates(DocumentSubsection $subsection, array $validated): void
    {
        $subsection->fill($validated);
        $this->setUpdatedByIfPresent($subsection);
        $subsection->save();
        $subsection->refresh();
    }

    private function buildFixedSubsections(Document $document, Request $request): void
    {
        if ($document->sections()->exists()) {
            return;
        }

        $mainSection = $this->createMainSection($document, $request);
        $this->createFixedSubsections($mainSection, $request);
        $this->loadFixedSubsectionData($document);
    }

    private function migrateOldStructure(Document $document, Request $request): void
    {
        if ($document->sections()->exists()) {
            return;
        }

        DB::transaction(function () use ($document, $request) {
            $mainSection = $this->createMainSection($document, $request);
            $this->createFixedSubsections($mainSection, $request);

            DocumentSubsection::where('document_id', $document->id)
                ->whereNull('section_id')
                ->update(['section_id' => $mainSection->id]);

            $this->loadFixedSubsectionData($document);
        });
    }

    private function loadFixedSubsectionData(Document $document): void
    {
        if (!$document->report_id) {
            return;
        }

        $reportId = $document->report_id;
        $report   = \App\Models\Report::find($reportId);

        foreach ($document->sections as $section) {
            if ($section->section_type !== 'fixed' || $section->fixed_type !== 'main') {
                continue;
            }

            foreach ($section->subsections as $subsection) {
                switch ($subsection->slug) {
                    case 'summary':
                        $summary = \App\Models\ReportSummary::where('report_id', $reportId)->first();
                        if ($summary) {
                            $subsection->content_html = $summary->content ?? '';
                        }
                        break;

                    case 'introduction':
                        $introduction = \App\Models\Introduction::where('report_id', $reportId)->first();
                        if ($introduction) {
                            $subsection->content_html = $introduction->content ?? '';
                        }
                        break;

                    case 'abbreviations':
                        $abbreviations = $report ? $report->abbreviations()->get() : collect();
                        $html = '<ul>';
                        foreach ($abbreviations as $abbr) {
                            $html .= "<li><strong>{$abbr->abbreviation}:</strong> {$abbr->meaning}</li>";
                        }
                        $html .= '</ul>';
                        $subsection->content_html = $html;
                        break;

                    case 'tariffs':
                        $tariffs = \App\Models\Tariff::where('report_id', $reportId)->with('source')->get();
                        $html = '<table border="1" cellpadding="5"><tr><th>Source</th><th>Unit Cost</th></tr>';
                        foreach ($tariffs as $tariff) {
                            $sourceName = $tariff->source->name ?? 'N/A';
                            $html      .= "<tr><td>{$sourceName}</td><td>{$tariff->unit_cost}</td></tr>";
                        }
                        $html .= '</table>';
                        $subsection->content_html = $html;
                        break;

                    case 'bills':
                        if ($report && $report->property_id) {
                            $bills = \App\Models\Ebill::where('property_id', $report->property_id)->orderBy('date', 'desc')->get();
                            $html = '<table border="1" cellpadding="5"><tr><th>Date</th><th>Value</th></tr>';
                            foreach ($bills as $bill) {
                                $html .= "<tr><td>{$bill->date}</td><td>{$bill->value}</td></tr>";
                            }
                            $html .= '</table>';
                            $subsection->content_html = $html;
                        }
                        break;
                }
            }
        }
    }

    private function createMainSection(Document $document, Request $request): DocumentSection
    {
        return DocumentSection::create([
            'document_id' => $document->id,
            'title'       => 'Report Content',
            'section_type'=> 'fixed',
            'fixed_type'  => 'main',
            'position'    => 0,
            'created_by'  => optional($request->user())->id,
            'updated_by'  => optional($request->user())->id,
        ]);
    }

    private function createFixedSubsections(DocumentSection $section, Request $request): void
    {
        $fixed = [
            ['title' => 'Summary', 'slug' => 'summary'],
            ['title' => 'Introduction', 'slug' => 'introduction'],
            ['title' => 'Abbreviations', 'slug' => 'abbreviations'],
            ['title' => 'Tariffs', 'slug' => 'tariffs'],
            ['title' => 'Bills', 'slug' => 'bills'],
        ];

        foreach ($fixed as $position => $data) {
            $payload = [
                'document_id'     => $section->document_id,
                'section_id'      => $section->id,
                'title'           => $data['title'],
                'subsection_type' => 'text',
                'content_html'    => '',
                'position'        => $position,
            ];

            $payload = $this->applyAuditColumns($payload, $request);

            DocumentSubsection::firstOrCreate(
                ['document_id' => $section->document_id, 'slug' => $data['slug']],
                $payload
            );
        }
    }

    private function safeBase64Validation(string $value): bool
    {
        return (bool) preg_match('/^data:image\/[a-z0-9.+-]+;base64,[A-Za-z0-9+\/=]+$/i', $value);
    }

    private function applyAuditColumns(array $data, Request $request): array
    {
        if ($this->subsectionAuditColumnsEnabled()) {
            $userId = optional($request->user())->id;
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['updated_by'] = $data['updated_by'] ?? $userId;
        }

        return $data;
    }

    private function setUpdatedByIfPresent(DocumentSubsection $subsection): void
    {
        if ($this->subsectionAuditColumnsEnabled()) {
            $subsection->updated_by = optional(request()->user())->id;
        }
    }

    private function subsectionAuditColumnsEnabled(): bool
    {
        static $cached;

        if ($cached === null) {
            $cached = Schema::hasColumn('document_subsections', 'created_by')
                && Schema::hasColumn('document_subsections', 'updated_by');
        }

        return $cached;
    }
}

