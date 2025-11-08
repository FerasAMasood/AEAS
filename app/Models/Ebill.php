<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebill extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'date',
        'value',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
