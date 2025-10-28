<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentBlock extends Model
{
    protected $fillable = ['document_id','block_type','subsection_id','position'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function subsection(): BelongsTo
    {
        return $this->belongsTo(DocumentSubsection::class);
    }
}
