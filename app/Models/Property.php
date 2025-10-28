<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_name',
        'property_type',
        'property_usage',
        'floor_number',
        'property_area',
        'number_of_rooms',
        'property_isolation_type',
        'property_address',
        'property_description',
        'number_of_floors',
    ];
    public function propertyDevices()
    {
        return $this->hasMany(PropertyDevice::class); // Adjust the class name if needed
    }
    public function reports()
    {
        return $this->hasMany(Report::class, 'property_id');
    }
}

