<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComponentScore extends Model
{
    protected $fillable = ['component_id', 'user_id', 'class_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }
}
