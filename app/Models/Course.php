<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Course extends Model
{
    protected $fillable = ['teacher_id', 'name', 'description', 'pass_min', 'grade_bands', 'grades_published'];

    protected function casts(): array
    {
        return [
            'grade_bands' => 'array',
            'grades_published' => 'boolean',
            'pass_min' => 'integer',
        ];
    }

    public const DEFAULT_BANDS = [
        ['min' => 85, 'letter' => 'A'],
        ['min' => 80, 'letter' => 'A-'],
        ['min' => 75, 'letter' => 'B+'],
        ['min' => 70, 'letter' => 'B'],
        ['min' => 65, 'letter' => 'B-'],
        ['min' => 60, 'letter' => 'C'],
        ['min' => 50, 'letter' => 'D'],
        ['min' => 0,  'letter' => 'E'],
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(Week::class)->orderBy('number');
    }

    public function gradeComponents(): HasMany
    {
        return $this->hasMany(GradeComponent::class)->orderBy('position')->orderBy('id');
    }

    public function bands(): array
    {
        return $this->grade_bands ?: self::DEFAULT_BANDS;
    }

    public function letterFor(?float $nilai): string
    {
        if ($nilai === null) {
            return '-';
        }
        foreach ($this->bands() as $b) {
            if ($nilai >= (float) $b['min']) {
                return $b['letter'];
            }
        }
        return end($this->bands())['letter'] ?? 'E';
    }

    public function totalLessons(bool $onlyOpen = false): int
    {
        return Lesson::whereHas('week', function ($q) use ($onlyOpen) {
            $q->where('course_id', $this->id);
            if ($onlyOpen) {
                $q->where('is_open', true);
            }
        })->count();
    }
}
