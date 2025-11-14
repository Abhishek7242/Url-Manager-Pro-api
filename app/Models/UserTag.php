<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTag extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'tag', 'icon'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
