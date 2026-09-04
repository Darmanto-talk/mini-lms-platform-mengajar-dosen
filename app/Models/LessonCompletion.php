<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonCompletion extends Model
{
    public $timestamps = false;

    protected $fillable = ['lesson_id', 'user_id', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Isi eksplisit — jangan bergantung pada DEFAULT CURRENT_TIMESTAMP milik DB.
        static::creating(function (self $completion) {
            $completion->completed_at ??= now();
        });
    }
}
