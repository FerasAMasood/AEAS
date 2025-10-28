<?php
// app/Models/Lookup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    use HasFactory;

    protected $fillable = [
        'lookup_key', 'lookup_table', 'lookup_field', 'category', 'lookup_value'
    ];

    public function parentCategory()
    {
        return $this->belongsTo(Lookup::class, 'category');
    }

    public function childCategories()
    {
        return $this->hasMany(Lookup::class, 'category');
    }
}
