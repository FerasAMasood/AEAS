<?php

// app/Models/Introduction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Introduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'content',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
