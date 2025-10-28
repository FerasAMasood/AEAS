<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['property_id', 'report_title', 'auditor_name', 'date', 'cover_image'];

    
    // Optional: Define the relationship if a Property model exists
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    public function abbreviations()
    {
        return $this->belongsToMany(Abbreviation::class, 'report_abbreviation');
    }
    public function summary()
    {
        return $this->hasOne(ReportSummary::class);
    }
    public function introduction()
    {
        return $this->hasOne(introduction::class);
    }
}
