<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    public $timestamps = false;

    protected $fillable = ['class_id', 'user_id', 'joined_at', 'removed_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'removed_at' => 'datetime'];
    }
}
