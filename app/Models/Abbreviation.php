<?php
// app/Models/Abbreviation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Abbreviation extends Model
{
    use HasFactory;

    protected $fillable = [
        'abbreviation',
        'meaning',
    ];
    public function reports()
    {
        return $this->belongsToMany(Report::class, 'report_abbreviation');
    }
}
