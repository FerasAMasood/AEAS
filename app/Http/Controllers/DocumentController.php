<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSubsection;
use App\Models\DocumentBlock;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function index() {
        return Document::with('sections.subsections')->paginate(20);
    }

    public function store(Request $request) {
        $doc = Document::create([
            'title'=>$request->title,
            'status'=>$request->status ?? 'draft',
            'report_id'=>$request->report_id,
            'created_by'=>optional($request->user())->id,
            'updated_by'=>optional($request->user())->id,
        ]);

        // Create main section for fixed content
        $mainSection = \App\Models\DocumentSection::create([
            'document_id' => $doc->id,
            'title' => 'Report Content',
            'section_type' => 'fixed',
            'fixed_type' => 'main',
            'position' => 0,
            'created_by' => optional($request->user())->id,
            'updated_by' => optional($request->user())->id,
        ]);

        // Create fixed subsections under main section
        $fixedSubsections = [
            ['title' => 'Summary', 'fixed_type' => 'summary'],
            ['title' => 'Introduction', 'fixed_type' => 'introduction'],
            ['title' => 'Abbreviations', 'fixed_type' => 'abbreviations'],
            ['title' => 'Tariffs', 'fixed_type' => 'tariffs'],
            ['title' => 'Bills', 'fixed_type' => 'bills'],
        ];

        foreach ($fixedSubsections as $i => $subsectionData) {
            \App\Models\DocumentSubsection::create([
                'document_id' => $doc->id,
                'section_id' => $mainSection->id,
                'title' => $subsectionData['title'],
                'subsection_type' => 'text',
                'slug' => $subsectionData['fixed_type'],
                'content_html' => '', // Will be populated from respective models
                'position' => $i,
                'is_published' => true,
            ]);
        }

        return $doc->load(['sections.subsections']);
    }

    public function show(Document $document) {
        $document = $document->load(['sections.subsections', 'subsections']);
        $this->loadFixedSubsectionData($document);
        return $document;
    }

    public function showByReportId(Request $request, $reportId) {
        $document = Document::where('report_id', $reportId)->first();
        
        // If document doesn't exist, create it automatically
        if (!$document) {
            // Get report to use its title
            $report = \App\Models\Report::find($reportId);
            $title = $report ? $report->report_title : "Document for Report #{$reportId}";
            
            $document = Document::create([
                'title' => $title,
                'status' => 'draft',
                'report_id' => $reportId,
                'created_by' => optional($request->user())->id,
                'updated_by' => optional($request->user())->id,
            ]);

            // Create main section for fixed content
            $mainSection = \App\Models\DocumentSection::create([
                'document_id' => $document->id,
                'title' => 'Report Content',
                'section_type' => 'fixed',
                'fixed_type' => 'main',
                'position' => 0,
                'created_by' => optional($request->user())->id,
                'updated_by' => optional($request->user())->id,
            ]);

            // Create fixed subsections under main section
            $fixedSubsections = [
                ['title' => 'Summary', 'fixed_type' => 'summary'],
                ['title' => 'Introduction', 'fixed_type' => 'introduction'],
                ['title' => 'Abbreviations', 'fixed_type' => 'abbreviations'],
                ['title' => 'Tariffs', 'fixed_type' => 'tariffs'],
                ['title' => 'Bills', 'fixed_type' => 'bills'],
            ];

            foreach ($fixedSubsections as $i => $subsectionData) {
                \App\Models\DocumentSubsection::create([
                    'document_id' => $document->id,
                    'section_id' => $mainSection->id,
                    'title' => $subsectionData['title'],
                    'subsection_type' => 'text',
                    'slug' => $subsectionData['fixed_type'],
                    'content_html' => '', // Will be populated from respective models
                    'position' => $i,
                    'is_published' => true,
                ]);
            }
        } else {
            // Check if document has old structure (separate fixed sections) and migrate
            $this->migrateOldStructure($document, $request);
        }
        
        $document = $document->load(['sections.subsections', 'subsections']);
        $this->loadFixedSubsectionData($document);
        return $document;
    }

    /**
     * Migrate old structure (separate fixed sections) to new structure (subsections under main section)
     */
    private function migrateOldStructure($document, Request $request) {
        // Check if main section exists
        $mainSection = \App\Models\DocumentSection::where('document_id', $document->id)
            ->where('fixed_type', 'main')
            ->first();
        
        // If main section doesn't exist, we need to migrate
        if (!$mainSection) {
            // Get old fixed sections
            $oldFixedSections = \App\Models\DocumentSection::where('document_id', $document->id)
                ->where('section_type', 'fixed')
                ->where('fixed_type', '!=', 'main')
                ->orderBy('position')
                ->get();
            
            if ($oldFixedSections->count() > 0) {
                // Create main section
                $mainSection = \App\Models\DocumentSection::create([
                    'document_id' => $document->id,
                    'title' => 'Report Content',
                    'section_type' => 'fixed',
                    'fixed_type' => 'main',
                    'position' => 0,
                    'created_by' => optional($request->user())->id,
                    'updated_by' => optional($request->user())->id,
                ]);
                
                // Convert old sections to subsections
                foreach ($oldFixedSections as $i => $oldSection) {
                    // Check if subsection already exists
                    $existingSubsection = \App\Models\DocumentSubsection::where('document_id', $document->id)
                        ->where('slug', $oldSection->fixed_type)
                        ->first();
                    
                    if (!$existingSubsection) {
                        \App\Models\DocumentSubsection::create([
                            'document_id' => $document->id,
                            'section_id' => $mainSection->id,
                            'title' => $oldSection->title,
                            'subsection_type' => 'text',
                            'slug' => $oldSection->fixed_type,
                            'content_html' => '', // Will be populated from respective models
                            'position' => $i,
                            'is_published' => true,
                        ]);
                    }
                    
                    // Delete old section (soft delete)
                    $oldSection->delete();
                }
            }
        }
        
        // Ensure main section has all required fixed subsections
        if ($mainSection) {
            $requiredSubsections = ['summary', 'introduction', 'abbreviations', 'tariffs', 'bills'];
            $existingSubsections = \App\Models\DocumentSubsection::where('document_id', $document->id)
                ->where('section_id', $mainSection->id)
                ->whereIn('slug', $requiredSubsections)
                ->pluck('slug')
                ->toArray();
            
            $missingSubsections = array_diff($requiredSubsections, $existingSubsections);
            
            if (count($missingSubsections) > 0) {
                $fixedSubsections = [
                    'summary' => 'Summary',
                    'introduction' => 'Introduction',
                    'abbreviations' => 'Abbreviations',
                    'tariffs' => 'Tariffs',
                    'bills' => 'Bills',
                ];
                
                $position = \App\Models\DocumentSubsection::where('document_id', $document->id)
                    ->where('section_id', $mainSection->id)
                    ->max('position') ?? -1;
                
                foreach ($missingSubsections as $slug) {
                    $position++;
                    \App\Models\DocumentSubsection::create([
                        'document_id' => $document->id,
                        'section_id' => $mainSection->id,
                        'title' => $fixedSubsections[$slug],
                        'subsection_type' => 'text',
                        'slug' => $slug,
                        'content_html' => '', // Will be populated from respective models
                        'position' => $position,
                        'is_published' => true,
                    ]);
                }
            }
        }
    }

    /**
     * Load data from respective models for fixed subsections
     */
    private function loadFixedSubsectionData($document) {
        if (!$document->report_id) {
            return;
        }

        $reportId = $document->report_id;
        $report = \App\Models\Report::find($reportId);
        
        foreach ($document->sections as $section) {
            if ($section->section_type === 'fixed' && $section->fixed_type === 'main') {
                foreach ($section->subsections as $subsection) {
                    $slug = $subsection->slug;
                    
                    switch ($slug) {
                        case 'summary':
                            $summary = \App\Models\ReportSummary::where('report_id', $reportId)->first();
                            if ($summary) {
                                $subsection->content_html = $summary->content ?? '';
                                $subsection->data_source = 'report_summaries';
                                $subsection->source_id = $summary->id;
                            }
                            break;
                            
                        case 'introduction':
                            $introduction = \App\Models\Introduction::where('report_id', $reportId)->first();
                            if ($introduction) {
                                $subsection->content_html = $introduction->content ?? '';
                                $subsection->data_source = 'introductions';
                                $subsection->source_id = $introduction->id;
                            }
                            break;
                            
                        case 'abbreviations':
                            $abbreviations = $report ? $report->abbreviations()->get() : collect();
                            $abbrHtml = '<ul>';
                            foreach ($abbreviations as $abbr) {
                                $abbrHtml .= "<li><strong>{$abbr->abbreviation}:</strong> {$abbr->meaning}</li>";
                            }
                            $abbrHtml .= '</ul>';
                            $subsection->content_html = $abbrHtml;
                            $subsection->data_source = 'report_abbreviation';
                            break;
                            
                        case 'tariffs':
                            $tariffs = \App\Models\Tariff::where('report_id', $reportId)->with('source')->get();
                            $tariffHtml = '<table border="1" cellpadding="5"><tr><th>Source</th><th>Unit Cost</th></tr>';
                            foreach ($tariffs as $tariff) {
                                $sourceName = $tariff->source ? $tariff->source->name : 'N/A';
                                $tariffHtml .= "<tr><td>{$sourceName}</td><td>{$tariff->unit_cost}</td></tr>";
                            }
                            $tariffHtml .= '</table>';
                            $subsection->content_html = $tariffHtml;
                            $subsection->data_source = 'tariffs';
                            break;
                            
                        case 'bills':
                            if ($report && $report->property_id) {
                                $ebills = \App\Models\Ebill::where('property_id', $report->property_id)
                                    ->orderBy('date', 'desc')
                                    ->get();
                                $billsHtml = '<table border="1" cellpadding="5"><tr><th>Date</th><th>Value</th></tr>';
                                foreach ($ebills as $bill) {
                                    $billsHtml .= "<tr><td>{$bill->date}</td><td>{$bill->value}</td></tr>";
                                }
                                $billsHtml .= '</table>';
                                $subsection->content_html = $billsHtml;
                                $subsection->data_source = 'ebills';
                            }
                            break;
                    }
                }
            }
        }
    }

    public function update(Request $request, Document $document) {
        $document->update($request->only('title','status'));
        return $document;
    }

    public function destroy(Document $document) {
        $document->delete();
        return response()->noContent();
    }

    public function addSubsection(Request $request, Document $document) {
        return DB::transaction(function() use($request,$document){
            // Decode images JSON string before validation if it's a string
            $imagesInput = $request->input('images');
            if (is_string($imagesInput)) {
                $decoded = json_decode($imagesInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Merge decoded images back into request
                    $request->merge(['images' => $decoded]);
                } elseif (json_last_error() === JSON_ERROR_NONE && $decoded === null) {
                    // Empty JSON array or null
                    $request->merge(['images' => []]);
                }
            }
            
            $validated = $request->validate([
                'section_id' => 'required|exists:document_sections,id',
                'title' => 'nullable|string|max:255',
                'slug' => 'nullable|string|max:255',
                'subsection_type' => 'required|in:text,images,pdf',
                'content_html' => 'nullable|string',
                // Accept base64 images as array of strings
                'images' => 'nullable|array',
                'images.*.base64' => 'nullable|string',
                'images.*.caption' => 'nullable|string|max:500',
                'image_captions' => 'nullable|array',
                'image_captions.*' => 'nullable|string|max:500',
                'pdf_file' => 'nullable|file|mimes:pdf|max:10240', // 10MB max
            ]);

            // Handle base64 images
            $imagesData = [];
            $imagesInput = $request->input('images', []);
            
            if (!empty($imagesInput) && is_array($imagesInput)) {
                foreach ($imagesInput as $index => $imageInput) {
                    $base64 = is_array($imageInput) ? ($imageInput['base64'] ?? $imageInput) : $imageInput;
                    $caption = is_array($imageInput) ? ($imageInput['caption'] ?? null) : null;
                    
                    // If caption is not in the image input, try to get it from image_captions array
                    if (!$caption && $request->has('image_captions')) {
                        $captions = $request->input('image_captions', []);
                        $caption = $captions[$index] ?? null;
                    }
                    
                    if ($base64) {
                        // Validate base64 string format (data:image/...;base64,...)
                        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                            $imagesData[] = [
                                'base64' => $base64,
                                'caption' => $caption
                            ];
                        } elseif (str_starts_with($base64, 'data:image/')) {
                            // Handle case where base64 might not have proper format
                            $imagesData[] = [
                                'base64' => $base64,
                                'caption' => $caption
                            ];
                        }
                    }
                }
            }

            // Handle PDF upload
            if ($request->hasFile('pdf_file')) {
                $pdfPath = $request->file('pdf_file')->store('documents/pdfs', 'public');
                $validated['pdf_file'] = $pdfPath;
            }

            $section = \App\Models\DocumentSection::findOrFail($validated['section_id']);
            if ($section->document_id !== $document->id) {
                throw new \Exception('Section does not belong to this document');
            }

            $sub = DocumentSubsection::create([
                'document_id'=>$document->id,
                'section_id'=>$validated['section_id'],
                'title'=>$validated['title'] ?? null,
                'slug'=>$validated['slug'] ?? null,
                'subsection_type'=>$validated['subsection_type'],
                'content_html'=>$validated['content_html'] ?? '',
                'images'=>!empty($imagesData) ? $imagesData : null,
                'pdf_file'=>$validated['pdf_file'] ?? null,
                'is_published'=>$request->boolean('is_published',true),
                'position'=>$section->subsections()->count(),
            ]);

            return $sub->load('section');
        });
    }

    public function setOrder(Request $request, Document $document) {
        // Legacy method - now using sections instead of blocks
        // This method is kept for backward compatibility but may not be used
        return $document->load('sections.subsections');
    }

    public function updateSubsection(Request $request, Document $document, DocumentSubsection $subsection) {
        if ($subsection->document_id !== $document->id) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        // Decode images JSON string before validation if it's a string
        $imagesInput = $request->input('images');
        if (is_string($imagesInput)) {
            $decoded = json_decode($imagesInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Merge decoded images back into request
                $request->merge(['images' => $decoded]);
            } elseif (json_last_error() === JSON_ERROR_NONE && $decoded === null) {
                // Empty JSON array or null
                $request->merge(['images' => []]);
            }
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'subsection_type' => 'in:text,images,pdf',
            'content_html' => 'nullable|string',
            // Accept base64 images as array of strings
            'images' => 'nullable|array',
            'images.*.base64' => 'nullable|string',
            'images.*.caption' => 'nullable|string|max:500',
            'image_captions' => 'nullable|array',
            'image_captions.*' => 'nullable|string|max:500',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'section_id' => 'nullable|exists:document_sections,id',
        ]);

        // Handle base64 images
        $imagesInput = $request->input('images');
        if ($imagesInput !== null) {
            $imagesData = [];
            
            if (!empty($imagesInput) && is_array($imagesInput)) {
                foreach ($imagesInput as $index => $imageInput) {
                    $base64 = is_array($imageInput) ? ($imageInput['base64'] ?? $imageInput) : $imageInput;
                    $caption = is_array($imageInput) ? ($imageInput['caption'] ?? null) : null;
                    
                    // If caption is not in the image input, try to get it from image_captions array
                    if (!$caption && $request->has('image_captions')) {
                        $captions = $request->input('image_captions', []);
                        $caption = $captions[$index] ?? null;
                    }
                    
                    if ($base64) {
                        // Validate base64 string format (data:image/...;base64,...)
                        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                            $imagesData[] = [
                                'base64' => $base64,
                                'caption' => $caption
                            ];
                        } elseif (str_starts_with($base64, 'data:image/')) {
                            // Handle case where base64 might not have proper format
                            $imagesData[] = [
                                'base64' => $base64,
                                'caption' => $caption
                            ];
                        }
                    }
                }
            }
            
            // Set images data (empty array means remove all images)
            $validated['images'] = $imagesData;
        }

        // Handle PDF upload
        if ($request->hasFile('pdf_file')) {
            // Delete old PDF if exists
            if ($subsection->pdf_file) {
                \Storage::disk('public')->delete($subsection->pdf_file);
            }
            $pdfPath = $request->file('pdf_file')->store('documents/pdfs', 'public');
            $validated['pdf_file'] = $pdfPath;
        }

        // Handle section change
        if (isset($validated['section_id'])) {
            $section = \App\Models\DocumentSection::findOrFail($validated['section_id']);
            if ($section->document_id !== $document->id) {
                return response()->json(['message' => 'Section does not belong to this document'], 422);
            }
            // Update position to end of new section
            $validated['position'] = $section->subsections()->count();
        }

        $validated['updated_by'] = $request->user()->id;
        $subsection->update($validated);
        return $subsection->load('section');
    }

    public function deleteSubsection(Document $document, DocumentSubsection $subsection) {
        if ($subsection->document_id !== $document->id) {
            return response()->json(['message' => 'Subsection not found'], 404);
        }

        // Delete PDF file if exists
        if ($subsection->pdf_file) {
            \Storage::disk('public')->delete($subsection->pdf_file);
        }

        $subsection->delete();
        return response()->noContent();
    }

    public function setSubsectionOrder(Request $request, Document $document) {
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

    public function renderHtml(Document $document, DocumentRenderService $service) {
        return response($service->renderHtml($document->load('sections.subsections')),200)
            ->header('Content-Type','text/html; charset=UTF-8');
    }
}
