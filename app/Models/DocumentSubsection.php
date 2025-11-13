<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentSubsection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id', 'section_id', 'title', 'slug', 'subsection_type',
        'content_html', 'images', 'pdf_file', 'position', 'is_published'
    ];

    protected $casts = [
        // 'images' => 'array', // Handled manually via accessor/mutator
        'is_published' => 'boolean',
        'position' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(DocumentSection::class, 'section_id');
    }

    /**
     * Get the images attribute with full URLs
     * Custom accessor overrides the cast, so we handle JSON decoding manually
     */
    public function getImagesAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // Decode JSON string to array
        $images = is_string($value) ? json_decode($value, true) : $value;
        
        if (!is_array($images)) {
            return $images;
        }

        // Convert image paths to full URLs
        return array_map(function ($image) {
            if (is_string($image)) {
                // Legacy format: just a path string
                // Storage::url() returns path like /storage/documents/images/file.jpg
                $url = Storage::disk('public')->url($image);
                return $url;
            } elseif (is_array($image) && isset($image['path'])) {
                // New format: array with path and caption
                // Storage::url() returns path like /storage/documents/images/file.jpg
                $url = Storage::disk('public')->url($image['path']);
                return [
                    'path' => $url,
                    'caption' => $image['caption'] ?? null
                ];
            }
            return $image;
        }, $images);
    }

    /**
     * Set the images attribute - encode to JSON for storage
     */
    public function setImagesAttribute($value)
    {
        // If it's already JSON, store as-is, otherwise encode
        if (is_string($value)) {
            $this->attributes['images'] = $value;
        } else {
            $this->attributes['images'] = json_encode($value);
        }
    }
}
