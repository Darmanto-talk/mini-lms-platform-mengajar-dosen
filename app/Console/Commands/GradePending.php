<?php

namespace App\Console\Commands;

use App\Jobs\GradeAssignmentWithAi;
use App\Jobs\ReviewQuizAttemptWithAi;
use App\Models\AssignmentSubmission;
use App\Models\QuizAttempt;
use App\Services\AiGrader;
use Illuminate\Console\Command;

class GradePending extends Command
{
    protected $signature = 'lms:grade-pending {--sync : Jalankan langsung, bukan lewat antrean}';

    protected $description = 'Proses pemeriksaan AI untuk tugas & kuis yang masih menunggu (untuk dijadwalkan lewat cron).';

    public function handle(AiGrader $grader): int
    {
        if (! $grader->enabled()) {
            $this->warn('Kunci API penyedia AI belum diisi — fitur AI nonaktif.');
            return self::SUCCESS;
        }

        $subs = AssignmentSubmission::whereIn('ai_status', ['pending', 'failed'])->whereNull('graded_at')->pluck('id');
        foreach ($subs as $id) {
            $this->option('sync')
                ? GradeAssignmentWithAi::dispatchSync($id)
                : GradeAssignmentWithAi::dispatch($id);
        }

        $attempts = QuizAttempt::whereIn('ai_status', ['pending', 'failed'])->whereNotNull('submitted_at')->pluck('id');
        foreach ($attempts as $id) {
            $this->option('sync')
                ? ReviewQuizAttemptWithAi::dispatchSync($id)
                : ReviewQuizAttemptWithAi::dispatch($id);
        }

        $this->info("Diproses: {$subs->count()} tugas, {$attempts->count()} kuis.");

        return self::SUCCESS;
    }
}
