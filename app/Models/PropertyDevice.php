<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'category_id',
        'device_key',
        'description',
        'notes',
        'factor',
        'power',
        'quantity',
        'operation_hours',
        'total_consumption'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function category()
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    public function device()
    {
        return $this->belongsTo(Lookup::class, 'device_key');
    }
}
