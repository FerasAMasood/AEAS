<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class DocumentSection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id', 'title', 'section_type', 'fixed_type', 
        'position', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function subsections(): HasMany
    {
        return $this->hasMany(DocumentSubsection::class, 'section_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
