<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentBlock;

class DocumentRenderService
{
    protected string $introductionModel = \App\Models\Introduction::class;
    protected string $abbreviationsModel = \App\Models\Abbreviations::class;
    protected string $summaryModel      = \App\Models\Summary::class;
    protected string $tariffsModel      = \App\Models\Tariffs::class;

    protected bool $usesNumericVersioning = true;

    public function renderHtml(Document $document): string
    {
        $html = [];
        foreach ($document->blocks as $block) {
            $html[] = $this->renderBlock($block);
        }
        return implode("\n", array_filter($html));
    }

    protected function renderBlock(DocumentBlock $block): ?string
    {
        switch ($block->block_type) {
            case 'introduction':
                return $this->wrap('Introduction', $this->getFixedHtml($this->introductionModel,$block->document_id));
            case 'abbreviations':
                return $this->wrap('Abbreviations', $this->getFixedHtml($this->abbreviationsModel,$block->document_id));
            case 'summary':
                return $this->wrap('Summary', $this->getFixedHtml($this->summaryModel,$block->document_id));
            case 'tariffs':
                return $this->wrap('Tariffs', $this->getFixedHtml($this->tariffsModel,$block->document_id));
            case 'subsection':
                return $block->subsection
                    ? $this->wrap($block->subsection->title,$block->subsection->content_html)
                    : null;
            default:
                return null;
        }
    }

    protected function wrap(?string $title,string $content): string
    {
        $titleHtml = $title ? "<h2>".e($title)."</h2>" : "";
        return <<<HTML
<section class="doc-section">
  {$titleHtml}
  <div class="section-body">
    {$content}
  </div>
</section>
HTML;
    }

    protected function getFixedHtml(string $modelClass,int $documentId): string
    {
        $query = $modelClass::query()->where('document_id',$documentId);

        $record = $query->latest()->first();

        if (!$record) {
            return '<p><em>No content available.</em></p>';
        }

        return $record->content_html ?? nl2br(e($record->text ?? ''));
    }
}
