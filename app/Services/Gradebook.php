<?php

namespace App\Services;

use App\Models\ComponentScore;
use App\Models\Course;
use App\Models\Kelas;
use Illuminate\Support\Facades\DB;

/** Perhitungan progres, XP, papan peringkat, dan nilai. */
class Gradebook
{
    /**
     * Rekap nilai akhir satu kelas berdasarkan komposisi komponen (grade_components).
     * Nilai akhir = Σ(skor_komponen × bobot% / 100). Komponen source quiz_avg/tugas_avg
     * dihitung otomatis; source manual diambil dari component_scores.
     *
     * @return array{components: \Illuminate\Support\Collection, rows: array, weight_total: float, has_scheme: bool}
     */
    public function classGradebook(Kelas $class): array
    {
        $course = $class->course;
        $components = $course->gradeComponents()->get();
        $weightTotal = (float) $components->sum('weight');

        // pra-hitung nilai otomatis per mahasiswa
        $quizAvg  = $this->quizAvgByUser($course->id, $class->id);
        $tugasAvg = $this->tugasAvgByUser($course->id, $class->id);
        $progress = $this->progressByUser($course->id, $class->id);

        // nilai per pelajaran tertentu (source = lesson)
        $lessonScores = [];
        foreach ($components->where('source', 'lesson')->whereNotNull('lesson_id') as $c) {
            $lessonScores[$c->lesson_id] = $this->lessonScoreByUser($c->lesson_id, $class->id);
        }

        // skor manual: [component_id][user_id] => score
        $manual = [];
        foreach (ComponentScore::whereIn('component_id', $components->pluck('id'))->where('class_id', $class->id)->get() as $s) {
            $manual[$s->component_id][$s->user_id] = $s->score;
        }

        $rows = [];
        foreach ($class->students()->get(['users.id', 'users.name', 'users.nim', 'users.email']) as $u) {
            $cells = [];
            $weightedSum = 0.0;
            $weightAvail = 0.0;

            foreach ($components as $c) {
                $score = match ($c->source) {
                    'progress'  => $progress[$u->id]  ?? null,
                    'quiz_avg'  => $quizAvg[$u->id]   ?? null,
                    'tugas_avg' => $tugasAvg[$u->id]  ?? null,
                    'lesson'    => $lessonScores[$c->lesson_id][$u->id] ?? null,
                    default     => $manual[$c->id][$u->id] ?? null,
                };
                $cells[$c->id] = $score;
                // komponen yang belum ada nilainya dihitung 0
                $weightedSum += (float) ($score ?? 0) * (float) $c->weight / 100;
                if ($score !== null) {
                    $weightAvail += (float) $c->weight;
                }
            }

            $nilai = $weightTotal > 0 ? round($weightedSum, 2) : null;
            $complete = $weightTotal > 0 && abs($weightAvail - $weightTotal) < 0.01;

            $rows[] = [
                'id' => $u->id, 'name' => $u->name, 'nim' => $u->nim, 'email' => $u->email,
                'cells' => $cells,
                'nilai' => $nilai,
                'complete' => $complete,
                'grade' => $course->letterFor($nilai),
                'lulus' => $nilai === null ? null : ($nilai >= $course->pass_min),
            ];
        }

        return [
            'components'   => $components,
            'rows'         => $rows,
            'weight_total' => $weightTotal,
            'has_scheme'   => $components->isNotEmpty(),
        ];
    }

    /** Nilai akhir berbobot untuk satu mahasiswa (untuk tampilan mahasiswa & CSV). */
    public function weightedFinal(Kelas $class, int $userId): array
    {
        foreach ($this->classGradebook($class)['rows'] as $r) {
            if ($r['id'] === $userId) {
                return ['nilai' => $r['nilai'], 'grade' => $r['grade'], 'lulus' => $r['lulus'], 'complete' => $r['complete']];
            }
        }
        return ['nilai' => null, 'grade' => '-', 'lulus' => null, 'complete' => false];
    }

    /** % penyelesaian materi (minggu terbuka) per mahasiswa. */
    private function progressByUser(int $courseId, int $classId): array
    {
        $total = Course::find($courseId)->totalLessons(true);
        if ($total === 0) {
            return [];
        }
        $done = DB::table('lesson_completions as lc')
            ->join('lessons as l', 'l.id', '=', 'lc.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->join('enrollments as e', function ($j) use ($classId) {
                $j->on('e.user_id', '=', 'lc.user_id')->where('e.class_id', '=', $classId);
            })
            ->where('w.course_id', $courseId)->where('w.is_open', true)
            ->groupBy('lc.user_id')
            ->select('lc.user_id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'user_id');

        return $done->map(fn ($n) => round($n / $total * 100, 2))->all();
    }

    /** Nilai satu pelajaran (kuis: percobaan terbaik; tugas: nilai final) per mahasiswa. */
    private function lessonScoreByUser(int $lessonId, int $classId): array
    {
        $lesson = \App\Models\Lesson::with('quiz')->find($lessonId);
        if (! $lesson) {
            return [];
        }

        if ($lesson->type === 'kuis' && $lesson->quiz) {
            return DB::table('quiz_attempts as qa')
                ->join('enrollments as e', function ($j) use ($classId) {
                    $j->on('e.user_id', '=', 'qa.user_id')->where('e.class_id', '=', $classId);
                })
                ->where('qa.quiz_id', $lesson->quiz->id)->whereNotNull('qa.submitted_at')
                ->groupBy('qa.user_id')
                ->select('qa.user_id', DB::raw('MAX(qa.score) as best'))
                ->pluck('best', 'user_id')->map(fn ($v) => (float) $v)->all();
        }

        if ($lesson->type === 'tugas') {
            return DB::table('assignment_submissions')
                ->where('lesson_id', $lessonId)->where('class_id', $classId)
                ->whereNotNull('score')
                ->pluck('score', 'user_id')->map(fn ($v) => (float) $v)->all();
        }

        return [];
    }

    private function quizAvgByUser(int $courseId, int $classId): array
    {
        return DB::table('quiz_attempts as qa')
            ->join('quizzes as qz', 'qz.id', '=', 'qa.quiz_id')
            ->join('lessons as l', 'l.id', '=', 'qz.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->join('enrollments as e', function ($j) use ($classId) {
                $j->on('e.user_id', '=', 'qa.user_id')->where('e.class_id', '=', $classId);
            })
            ->where('w.course_id', $courseId)->whereNotNull('qa.submitted_at')
            ->groupBy('qa.user_id', 'qz.id')
            ->select('qa.user_id', DB::raw('MAX(qa.score) as best'))
            ->get()->groupBy('user_id')
            ->map(fn ($g) => round($g->avg('best'), 2))->all();
    }

    private function tugasAvgByUser(int $courseId, int $classId): array
    {
        return DB::table('assignment_submissions as s')
            ->join('lessons as l', 'l.id', '=', 's.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->where('w.course_id', $courseId)->where('s.class_id', $classId)
            ->whereNotNull('s.score')
            ->groupBy('s.user_id')
            ->select('s.user_id', DB::raw('AVG(s.score) as avg'))
            ->pluck('avg', 'user_id')
            ->map(fn ($v) => round((float) $v, 2))->all();
    }

    /** id pelajaran yang sudah diselesaikan user pada course (opsional: hanya minggu terbuka). */
    public function completedLessonIds(int $courseId, int $userId, bool $onlyOpen = false): array
    {
        return DB::table('lesson_completions as lc')
            ->join('lessons as l', 'l.id', '=', 'lc.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->where('w.course_id', $courseId)
            ->where('lc.user_id', $userId)
            ->when($onlyOpen, fn ($q) => $q->where('w.is_open', true))
            ->pluck('lc.lesson_id')->map(fn ($i) => (int) $i)->all();
    }

    /**
     * id pelajaran yang boleh dibuka mahasiswa: berlaku aturan berurutan —
     * sebuah pelajaran terbuka bila semua pelajaran sebelumnya (di minggu yang
     * sudah dibuka dosen) sudah selesai. Pelajaran yang sudah selesai selalu
     * bisa dibuka kembali.
     */
    public function unlockedLessonIds(Course $course, int $userId): array
    {
        $done = array_flip($this->completedLessonIds($course->id, $userId, true));

        $ordered = $course->weeks()->where('is_open', true)->orderBy('number')->orderBy('position')
            ->with('lessons')->get()
            ->flatMap->lessons->pluck('id')->map(fn ($i) => (int) $i)->all();

        // Sebuah pelajaran terbuka bila SEMUA pelajaran sebelumnya sudah selesai.
        // Begitu ada satu pelajaran yang belum selesai, semua sesudahnya terkunci
        // (kecuali yang memang sudah pernah diselesaikan).
        $unlocked = [];
        $allPriorDone = true;
        foreach ($ordered as $id) {
            if ($allPriorDone || isset($done[$id])) {
                $unlocked[] = $id;
            }
            if (! isset($done[$id])) {
                $allPriorDone = false;
            }
        }

        return $unlocked;
    }

    public function lessonUnlocked(Course $course, int $userId, int $lessonId): bool
    {
        return in_array($lessonId, $this->unlockedLessonIds($course, $userId), true);
    }

    public function userXp(int $courseId, int $userId): int
    {
        $lessonXp = (int) DB::table('lesson_completions as lc')
            ->join('lessons as l', 'l.id', '=', 'lc.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->where('w.course_id', $courseId)->where('lc.user_id', $userId)
            ->sum('l.xp');

        $quizXp = (int) DB::table('quiz_attempts as qa')
            ->join('quizzes as qz', 'qz.id', '=', 'qa.quiz_id')
            ->join('lessons as l', 'l.id', '=', 'qz.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->where('w.course_id', $courseId)->where('qa.user_id', $userId)->where('qa.passed', true)
            ->distinct()->sum('qz.xp');

        return $lessonXp + $quizXp;
    }

    /** Papan peringkat satu kelas. */
    public function leaderboard(Kelas $class): array
    {
        $rows = $class->students()->get(['users.id', 'users.name', 'users.nim'])->map(function ($u) use ($class) {
            return ['id' => $u->id, 'name' => $u->name, 'nim' => $u->nim, 'xp' => $this->userXp($class->course_id, $u->id)];
        })->sortByDesc('xp')->values()->all();

        return $rows;
    }

    /** Ringkasan progres + nilai semua mahasiswa satu kelas (panel dosen). */
    public function classRows(Kelas $class): array
    {
        $courseId = $class->course_id;
        $total = Course::find($courseId)->totalLessons(true);

        return $class->students()->get(['users.id', 'users.name', 'users.nim', 'users.email'])->map(function ($u) use ($class, $courseId, $total) {
            $done = count($this->completedLessonIds($courseId, $u->id, true));

            $quizzes = DB::table('quiz_attempts as qa')
                ->join('quizzes as qz', 'qz.id', '=', 'qa.quiz_id')
                ->join('lessons as l', 'l.id', '=', 'qz.lesson_id')
                ->join('weeks as w', 'w.id', '=', 'l.week_id')
                ->where('w.course_id', $courseId)->where('qa.user_id', $u->id)
                ->whereNotNull('qa.submitted_at')
                ->groupBy('qz.id', 'qz.title')
                ->get([DB::raw('qz.title as title'), DB::raw('MAX(qa.score) as best'), DB::raw('MAX(qa.passed) as passed')]);

            $assignments = DB::table('assignment_submissions as s')
                ->join('lessons as l', 'l.id', '=', 's.lesson_id')
                ->join('weeks as w', 'w.id', '=', 'l.week_id')
                ->where('w.course_id', $courseId)->where('s.user_id', $u->id)->where('s.class_id', $class->id)
                ->orderBy('w.number')->orderBy('l.position')
                ->get(['l.title', 's.submitted_at', 's.score', 's.graded_at', 's.ai_status', 's.ai_score']);

            $qScores = $quizzes->pluck('best')->map(fn ($v) => (int) $v)->all();
            $aScores = $assignments->whereNotNull('score')->pluck('score')->map(fn ($v) => (int) $v)->all();
            $both = array_merge($qScores, $aScores);

            return [
                'id' => $u->id, 'name' => $u->name, 'nim' => $u->nim, 'email' => $u->email,
                'done' => $done, 'total' => $total, 'percent' => $total ? (int) round($done / $total * 100) : 0,
                'xp' => $this->userXp($courseId, $u->id),
                'quizzes' => $quizzes, 'assignments' => $assignments,
                'nilai_kuis' => $qScores ? (int) round(array_sum($qScores) / count($qScores)) : null,
                'nilai_tugas' => $aScores ? (int) round(array_sum($aScores) / count($aScores)) : null,
                'nilai_akhir' => $both ? (int) round(array_sum($both) / count($both)) : null,
            ];
        })->all();
    }

    /** Rincian progres satu mahasiswa (halaman detail dosen). */
    public function studentDetail(Kelas $class, int $userId): array
    {
        $courseId = $class->course_id;
        $course = Course::with('weeks.lessons.quiz')->find($courseId);

        $doneAt = DB::table('lesson_completions as lc')
            ->join('lessons as l', 'l.id', '=', 'lc.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id')
            ->where('w.course_id', $courseId)->where('lc.user_id', $userId)
            ->pluck('lc.completed_at', 'lc.lesson_id')->all();
        $doneSet = $doneAt; // id => completed_at (isset() tetap bekerja)

        $weeks = $course->weeks->map(function ($w) use ($userId, $class, $doneSet, $doneAt) {
            $openDone = 0; $openTotal = 0;
            $lessons = $w->lessons->map(function ($l) use ($userId, $class, $doneSet, $doneAt, $w, &$openDone, &$openTotal) {
                $done = isset($doneSet[$l->id]);
                if ($w->is_open) { $openTotal++; if ($done) $openDone++; }

                $extra = [];
                if ($l->type === 'kuis' && $l->quiz) {
                    $extra['quiz_attempts'] = DB::table('quiz_attempts')
                        ->where('quiz_id', $l->quiz->id)->where('user_id', $userId)
                        ->orderBy('id')->get(['started_at', 'submitted_at', 'score', 'passed', 'ai_feedback']);
                }
                if ($l->type === 'tugas') {
                    $extra['submission'] = DB::table('assignment_submissions')
                        ->where('lesson_id', $l->id)->where('user_id', $userId)->where('class_id', $class->id)->first();
                }

                return array_merge([
                    'id' => $l->id, 'title' => $l->title, 'type' => $l->type,
                    'type_label' => $l->typeLabel(), 'max_score' => $l->max_score, 'done' => $done,
                    'completed_at' => $doneAt[$l->id] ?? null,
                ], $extra);
            })->all();

            return [
                'number' => $w->number, 'title' => $w->title, 'is_exam' => $w->is_exam, 'is_open' => $w->is_open,
                'done' => $openDone, 'open_total' => $openTotal, 'lessons' => $lessons,
            ];
        })->all();

        $doneOpen = count($this->completedLessonIds($courseId, $userId, true));
        $totalOpen = $course->totalLessons(true);

        return [
            'weeks' => $weeks,
            'xp' => $this->userXp($courseId, $userId),
            'percent' => $totalOpen ? (int) round($doneOpen / $totalOpen * 100) : 0,
        ];
    }
}
