<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeComponent extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_id', 'name', 'weight', 'source', 'lesson_id', 'position'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ComponentScore::class, 'component_id');
    }

    public function sourceLabel(): string
    {
        return [
            'manual'    => 'Input manual',
            'progress'  => '% penyelesaian materi',
            'quiz_avg'  => 'Rata-rata kuis',
            'tugas_avg' => 'Rata-rata tugas',
            'lesson'    => $this->lesson ? ('Nilai: '.$this->lesson->title) : 'Nilai 1 pelajaran',
        ][$this->source] ?? $this->source;
    }
}
