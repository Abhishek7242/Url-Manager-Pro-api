<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'user_id',
        'session_id',
        'url',
        'description',
        'tags',
        'status',
        'url_clicks',        
        'reminder_at',
    ];

    // Automatically cast tags JSON to array
    protected $casts = [
        'tags' => 'array',
    ];
}