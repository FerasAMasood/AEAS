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
        return Document::with('blocks')->paginate(20);
    }

    public function store(Request $request) {
        $doc = Document::create([
            'title'=>$request->title,
            'status'=>$request->status ?? 'draft',
            'created_by'=>optional($request->user())->id,
            'updated_by'=>optional($request->user())->id,
        ]);

        foreach (['introduction','abbreviations','summary','tariffs'] as $i=>$type) {
            DocumentBlock::create([
                'document_id'=>$doc->id,
                'block_type'=>$type,
                'position'=>$i,
            ]);
        }

        return $doc->load('blocks');
    }

    public function show(Document $document) {
        return $document->load(['blocks.subsection','subsections']);
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
            $sub = DocumentSubsection::create([
                'document_id'=>$document->id,
                'title'=>$request->title,
                'slug'=>$request->slug,
                'content_html'=>$request->content_html,
                'images'=>$request->images,
                'is_published'=>$request->boolean('is_published',true),
                'position'=>$document->subsections()->count(),
                'created_by'=>optional($request->user())->id,
                'updated_by'=>optional($request->user())->id,
            ]);

            DocumentBlock::create([
                'document_id'=>$document->id,
                'block_type'=>'subsection',
                'subsection_id'=>$sub->id,
                'position'=>$document->blocks()->count(),
            ]);

            return $sub;
        });
    }

    public function setOrder(Request $request, Document $document) {
        $blocks = $request->validate(['blocks'=>'required|array'])['blocks'];
        foreach ($blocks as $i=>$b) {
            DocumentBlock::where('document_id',$document->id)
              ->where('block_type',$b['type'])
              ->when($b['type']==='subsection',fn($q)=>$q->where('subsection_id',$b['subsection_id']))
              ->update(['position'=>$i]);
        }
        return $document->load('blocks');
    }

    public function renderHtml(Document $document, DocumentRenderService $service) {
        return response($service->renderHtml($document->load('blocks.subsection')),200)
          ->header('Content-Type','text/html; charset=UTF-8');
    }
}
