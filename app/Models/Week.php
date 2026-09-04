<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Week extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_id', 'number', 'title', 'summary', 'is_exam', 'is_open', 'position'];

    protected function casts(): array
    {
        return ['is_exam' => 'boolean', 'is_open' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }
}
