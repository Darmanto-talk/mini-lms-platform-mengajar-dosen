<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    public $timestamps = false;

    protected $fillable = ['quiz_id', 'text', 'code_snippet', 'position'];

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('position')->orderBy('id');
    }
}
