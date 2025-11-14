<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Background extends Model
{
    use HasFactory;

    protected $table = 'backgrounds';

    protected $fillable = [
        'background',
        'type',
        'name',
    ];

    /**
     * Helper: Simple preview accessor (optional)
     */
    public function getPreviewAttribute()
    {
        return match ($this->type) {
            'solid' => $this->background,
            'gradient' => $this->background,
            'image' => basename($this->background),
            'live' => ucfirst($this->background),
            default => $this->background,
        };
    }
}
