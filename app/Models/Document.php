<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['report_id','title','status','created_by','updated_by'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DocumentBlock::class)->orderBy('position');
    }

    public function subsections(): HasMany
    {
        return $this->hasMany(DocumentSubsection::class)->orderBy('position');
    }
}
