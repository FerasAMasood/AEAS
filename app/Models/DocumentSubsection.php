<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSubsection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id','title','slug','content_html','images',
        'position','is_published','created_by','updated_by'
    ];

    protected $casts = [
        'images' => 'array',
        'is_published' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
