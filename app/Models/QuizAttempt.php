<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quiz_id', 'user_id', 'started_at', 'submitted_at',
        'score', 'passed', 'ai_feedback', 'ai_status',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'submitted_at' => 'datetime',
            'passed'       => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }
}
