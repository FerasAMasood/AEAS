<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = ['report_id', 'messages'];

    protected $casts = [
        'messages' => 'array',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
