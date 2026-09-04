<?php

namespace App\Jobs;

use App\Models\AssignmentSubmission;
use App\Services\AiGrader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GradeAssignmentWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $submissionId) {}

    public function handle(AiGrader $grader): void
    {
        $sub = AssignmentSubmission::with('lesson')->find($this->submissionId);
        if (! $sub || ! $grader->enabled()) {
            return;
        }
        // jangan timpa jika dosen sudah menilai manual
        if ($sub->graded_at !== null) {
            $sub->update(['ai_status' => 'done']);
            return;
        }

        $sub->update(['ai_status' => 'processing', 'ai_error' => null]);

        try {
            $result = $grader->gradeAssignment($sub);

            $sub->update([
                'ai_status'    => 'done',
                'ai_score'     => $result['score'],
                'ai_feedback'  => $result['feedback'],
                'ai_breakdown' => $result['breakdown'],
                'ai_model'     => $result['model'],
                'ai_graded_at' => now(),
            ]);

            // Nilai AI langsung dipakai sebagai nilai sementara sampai dosen meninjau.
            if ($sub->graded_at === null) {
                $sub->update([
                    'score'    => $result['score'],
                    'feedback' => $result['feedback'],
                ]);
            }
        } catch (Throwable $e) {
            $sub->update(['ai_status' => 'failed', 'ai_error' => mb_substr($e->getMessage(), 0, 1000)]);
            report($e);
        }
    }

    public function failed(Throwable $e): void
    {
        AssignmentSubmission::where('id', $this->submissionId)
            ->update(['ai_status' => 'failed', 'ai_error' => mb_substr($e->getMessage(), 0, 1000)]);
    }
}
