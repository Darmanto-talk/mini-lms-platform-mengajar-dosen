<?php

namespace App\Jobs;

use App\Models\QuizAttempt;
use App\Services\AiGrader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReviewQuizAttemptWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $attemptId) {}

    public function handle(AiGrader $grader): void
    {
        $attempt = QuizAttempt::with('quiz')->find($this->attemptId);
        if (! $attempt || ! $grader->enabled() || $attempt->submitted_at === null) {
            return;
        }

        $attempt->update(['ai_status' => 'processing']);

        try {
            $feedback = $grader->reviewQuizAttempt($attempt);
            $attempt->update([
                'ai_status'   => 'done',
                'ai_feedback' => $feedback,
            ]);
        } catch (Throwable $e) {
            $attempt->update(['ai_status' => 'failed']);
            report($e);
        }
    }
}
