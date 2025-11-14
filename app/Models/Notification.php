<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;


    /**
     * Table name
     */
    protected $table = 'notifications';

    /**
     * Fillable fields for mass assignment
     */
    protected $fillable = [
        'title',
        'description',
        'admin_name',
    ];

    /**
     * Accessor: short description preview
     */
    public function getShortDescriptionAttribute(): string
    {
        return strlen($this->description) > 60
            ? substr($this->description, 0, 57) . '...'
            : $this->description;
    }

    /**
     * Accessor: human-readable created_at timestamp
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at
            ? $this->created_at->diffForHumans()
            : '';
    }
}
