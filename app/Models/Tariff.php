<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'source_id',
        'unit_cost',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function source()
    {
        return $this->belongsTo(EnergySource::class, 'source_id');
    }
}
