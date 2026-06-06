<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnergyBalance extends Model
{
    use HasFactory;

    protected $table = 'energy_balance';

    protected $fillable = [
        'property_id',
        'source_id',
        'value',
        'power_generated',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'power_generated' => 'decimal:4',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function source()
    {
        return $this->belongsTo(EnergySource::class, 'source_id');
    }
}
