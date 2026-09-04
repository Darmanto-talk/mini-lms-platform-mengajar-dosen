<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lesson_id', 'user_id', 'class_id', 'text', 'file_path', 'link_url', 'submitted_at',
        'score', 'feedback', 'graded_at', 'graded_by',
        'ai_status', 'ai_score', 'ai_feedback', 'ai_breakdown', 'ai_model', 'ai_graded_at', 'ai_error',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at'    => 'datetime',
            'ai_graded_at' => 'datetime',
            'ai_breakdown' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }
}
