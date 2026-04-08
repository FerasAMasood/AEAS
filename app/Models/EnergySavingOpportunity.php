<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergySavingOpportunity extends Model
{
    protected $fillable = [
        'property_id',
        'description',
        'implementation_cost',
        'saving_kwh_per_year',
        'saving_nis_per_year',
        'sort_order',
    ];

    protected $casts = [
        'implementation_cost' => 'float',
        'saving_kwh_per_year' => 'float',
        'saving_nis_per_year' => 'float',
        'sort_order' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
