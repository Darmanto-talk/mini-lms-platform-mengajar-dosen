<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Kelas;
use App\Models\User;
use App\Models\Week;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Impor / perbarui materi mata kuliah dari struktur JSON.
 * Skema: { name, description, weeks:[ { number, title, is_exam, is_open, summary,
 *   lessons:[ { title, type, xp, content, video_url, dartpad_gist, rubric, max_score,
 *     quiz:{ time_limit_min, pass_score, max_attempts, xp,
 *       questions:[ { text, code, options:[...], answer } ] } } ] } ] }
 */
class CourseImporter
{
    public const TYPES = ['materi', 'video', 'latihan', 'tugas', 'kuis'];

    /** @return string[] daftar kesalahan ([] = valid) */
    public function validate($data): array
    {
        $err = [];
        if (! is_array($data)) {
            return ['File JSON tidak berisi objek yang valid.'];
        }
        if (! isset($data['name']) || ! is_string($data['name']) || mb_strlen(trim($data['name'])) < 3) {
            $err[] = 'Kolom "name" (nama mata kuliah) wajib diisi, minimal 3 karakter.';
        }
        if (! isset($data['weeks']) || ! is_array($data['weeks']) || ! $data['weeks']) {
            $err[] = 'Kolom "weeks" wajib berupa daftar minggu dan tidak boleh kosong.';
            return $err;
        }

        $seen = [];
        foreach ($data['weeks'] as $wi => $w) {
            $label = 'Minggu ke-' . ($wi + 1);
            if (! is_array($w)) {
                $err[] = "$label: format tidak valid.";
                continue;
            }
            $num = $w['number'] ?? ($wi + 1);
            if (! is_int($num) && ! ctype_digit((string) $num)) {
                $err[] = "$label: \"number\" harus angka.";
            } elseif (isset($seen[(int) $num])) {
                $err[] = "$label: nomor minggu " . (int) $num . " dipakai lebih dari sekali.";
            } else {
                $seen[(int) $num] = true;
            }
            if (! isset($w['title']) || ! is_string($w['title']) || trim($w['title']) === '') {
                $err[] = "$label: \"title\" wajib diisi.";
            }
            foreach (($w['lessons'] ?? []) as $li => $l) {
                $ll = "$label pelajaran ke-" . ($li + 1);
                if (! is_array($l)) {
                    $err[] = "$ll: format tidak valid.";
                    continue;
                }
                if (! isset($l['title']) || ! is_string($l['title']) || trim($l['title']) === '') {
                    $err[] = "$ll: \"title\" wajib diisi.";
                }
                if (! in_array($l['type'] ?? 'materi', self::TYPES, true)) {
                    $err[] = "$ll: \"type\" harus salah satu dari: " . implode(', ', self::TYPES) . '.';
                }
                if (($l['type'] ?? '') === 'kuis' || isset($l['quiz'])) {
                    foreach (($l['quiz']['questions'] ?? []) as $qi => $q) {
                        $qq = "$ll soal ke-" . ($qi + 1);
                        if (! isset($q['text']) || trim((string) $q['text']) === '') {
                            $err[] = "$qq: \"text\" wajib diisi.";
                        }
                        $opts = $q['options'] ?? [];
                        if (! is_array($opts) || count($opts) < 2) {
                            $err[] = "$qq: butuh minimal 2 \"options\".";
                        }
                        $ans = $q['answer'] ?? 0;
                        if (is_array($opts) && ((int) $ans < 0 || (int) $ans >= count($opts))) {
                            $err[] = "$qq: \"answer\" harus indeks opsi yang benar (mulai 0).";
                        }
                    }
                }
            }
        }

        return $err;
    }

    /**
     * @throws RuntimeException jika JSON tidak valid
     * @return array{course:Course, weeks:int, lessons:int, questions:int, mode:string}
     */
    public function import($data, User $teacher, ?Course $target = null): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new RuntimeException(implode("\n", $errors));
        }

        return DB::transaction(function () use ($data, $teacher, $target) {
            $mode = $target ? 'update' : 'create';
            $snapDone = $snapQuiz = $snapSub = $snapOpen = [];
            $weeksUpdated = $weeksAdded = $weeksKept = 0;

            // nomor-nomor minggu yang ada di file (yang akan disentuh)
            $jsonWeekNums = [];
            foreach ($data['weeks'] as $wi => $w) {
                $jsonWeekNums[] = (int) ($w['number'] ?? ($wi + 1));
            }

            if ($target) {
                $target->update([
                    'name' => trim($data['name']),
                    'description' => trim((string) ($data['description'] ?? '')) ?: null,
                ]);
                $course = $target;

                $key = fn ($num, $title) => $num . '::' . Str::lower(trim((string) $title));

                foreach (DB::table('lesson_completions as lc')
                    ->join('lessons as l', 'l.id', '=', 'lc.lesson_id')
                    ->join('weeks as w', 'w.id', '=', 'l.week_id')
                    ->where('w.course_id', $course->id)
                    ->get(['w.number as wn', 'l.title as lt', 'lc.user_id', 'lc.completed_at']) as $r) {
                    $snapDone[$key($r->wn, $r->lt)][] = (array) $r;
                }
                foreach (DB::table('quiz_attempts as qa')
                    ->join('quizzes as qz', 'qz.id', '=', 'qa.quiz_id')
                    ->join('lessons as l', 'l.id', '=', 'qz.lesson_id')
                    ->join('weeks as w', 'w.id', '=', 'l.week_id')
                    ->where('w.course_id', $course->id)
                    ->get(['w.number as wn', 'l.title as lt', 'qa.*']) as $r) {
                    $snapQuiz[$key($r->wn, $r->lt)][] = (array) $r;
                }
                foreach (DB::table('assignment_submissions as s')
                    ->join('lessons as l', 'l.id', '=', 's.lesson_id')
                    ->join('weeks as w', 'w.id', '=', 'l.week_id')
                    ->where('w.course_id', $course->id)
                    ->get(['w.number as wn', 'l.title as lt', 's.*']) as $r) {
                    $snapSub[$key($r->wn, $r->lt)][] = (array) $r;
                }

                $existingNums = [];
                foreach ($course->weeks()->get(['number', 'is_open']) as $w) {
                    $snapOpen[(int) $w->number] = (int) $w->is_open;
                    $existingNums[(int) $w->number] = true;
                }

                // MERGE: hanya minggu yang ada di file yang diganti; minggu lain dibiarkan.
                $weeksKept = count(array_diff(array_keys($existingNums), $jsonWeekNums));
                foreach ($jsonWeekNums as $n) {
                    isset($existingNums[$n]) ? $weeksUpdated++ : $weeksAdded++;
                }
                $course->weeks()->whereIn('number', $jsonWeekNums)->delete();
            } else {
                $course = Course::create([
                    'teacher_id' => $teacher->id,
                    'name' => trim($data['name']),
                    'description' => trim((string) ($data['description'] ?? '')) ?: null,
                ]);
                $course->classes()->create(['code' => Kelas::generateCode(), 'name' => 'Kelas A']);
                $weeksAdded = count($jsonWeekNums);
            }

            $keyFn = fn ($num, $title) => $num . '::' . Str::lower(trim((string) $title));

            $nWeeks = $nLessons = $nQuestions = 0;

            foreach ($data['weeks'] as $wi => $w) {
                $number = (int) ($w['number'] ?? ($wi + 1));
                $isOpen = array_key_exists('is_open', $w)
                    ? (bool) $w['is_open']
                    : (bool) ($snapOpen[$number] ?? false);

                $week = $course->weeks()->create([
                    'number' => $number,
                    'title' => trim($w['title']),
                    'summary' => trim((string) ($w['summary'] ?? '')) ?: null,
                    'is_exam' => ! empty($w['is_exam']),
                    'is_open' => $isOpen,
                    'position' => $number,
                ]);
                $nWeeks++;

                $built = $this->buildLessons($week, $w['lessons'] ?? [], $snapDone, $snapQuiz, $snapSub, $keyFn);
                $nLessons += $built['lessons'];
                $nQuestions += $built['questions'];
            }

            return [
                'course' => $course->fresh(),
                'weeks' => $nWeeks,
                'weeks_updated' => $weeksUpdated,
                'weeks_added' => $weeksAdded,
                'weeks_kept' => $weeksKept,
                'lessons' => $nLessons,
                'questions' => $nQuestions,
                'mode' => $mode,
            ];
        });
    }

    /**
     * Impor daftar pelajaran ke SATU minggu yang sudah ada (impor per-pertemuan).
     * File cukup berisi `{ "lessons": [...] }` (opsional `title`, `summary`, `is_open`).
     * Nomor minggu ditentukan oleh minggu tujuan, bukan isi file.
     *
     * @return array{course:Course, week:int, lessons:int, questions:int}
     */
    public function importWeek(Week $week, $data): array
    {
        $wk = $this->asWeekPayload($data);
        $lessons = $wk['lessons'] ?? null;
        if (! is_array($lessons) || $lessons === []) {
            throw new RuntimeException('File tidak berisi daftar "lessons". Contoh: { "lessons": [ { "title": "...", "type": "materi", "content": "..." } ] }');
        }
        $errs = $this->validateLessons($lessons, 'Minggu '.$week->number);
        if ($errs) {
            throw new RuntimeException(implode("\n", $errs));
        }

        return DB::transaction(function () use ($week, $wk, $lessons) {
            $course = $week->course;
            $keyFn = fn ($num, $title) => $num . '::' . Str::lower(trim((string) $title));
            [$snapDone, $snapQuiz, $snapSub] = $this->snapshotProgress($course->id, $week->number);

            $attrs = [];
            if (isset($wk['title']) && trim((string) $wk['title']) !== '') {
                $attrs['title'] = trim($wk['title']);
            }
            if (array_key_exists('summary', $wk)) {
                $attrs['summary'] = trim((string) $wk['summary']) ?: null;
            }
            if (array_key_exists('is_exam', $wk)) {
                $attrs['is_exam'] = ! empty($wk['is_exam']);
            }
            if (array_key_exists('is_open', $wk)) {
                $attrs['is_open'] = (bool) $wk['is_open'];
            }
            if ($attrs) {
                $week->update($attrs);
            }

            $week->lessons()->delete();
            $built = $this->buildLessons($week, $lessons, $snapDone, $snapQuiz, $snapSub, $keyFn);

            return [
                'course' => $course->fresh(),
                'week' => $week->number,
                'lessons' => $built['lessons'],
                'questions' => $built['questions'],
            ];
        });
    }

    /** Terima berbagai bentuk file jadi satu objek minggu: {title?, lessons:[...]}. */
    private function asWeekPayload($data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['lessons']) && is_array($data['lessons'])) {
            return $data;                                   // { lessons: [...] }
        }
        if (isset($data['weeks'][0]) && is_array($data['weeks'][0])) {
            return $data['weeks'][0];                       // { weeks: [ {lessons:[...]} ] }
        }
        if (array_is_list($data)) {
            return ['lessons' => $data];                    // [ {lesson}, {lesson} ]
        }

        return $data;
    }

    /** @return array{0:array,1:array,2:array} snapshot [done, quiz, sub] by "week#::title" */
    private function snapshotProgress(int $courseId, ?int $onlyWeek = null): array
    {
        $key = fn ($num, $title) => $num . '::' . Str::lower(trim((string) $title));
        $snapDone = $snapQuiz = $snapSub = [];

        $scope = fn ($q) => $q->where('w.course_id', $courseId)
            ->when($onlyWeek !== null, fn ($q) => $q->where('w.number', $onlyWeek));

        foreach ($scope(DB::table('lesson_completions as lc')
            ->join('lessons as l', 'l.id', '=', 'lc.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id'))
            ->get(['w.number as wn', 'l.title as lt', 'lc.user_id', 'lc.completed_at']) as $r) {
            $snapDone[$key($r->wn, $r->lt)][] = (array) $r;
        }
        foreach ($scope(DB::table('quiz_attempts as qa')
            ->join('quizzes as qz', 'qz.id', '=', 'qa.quiz_id')
            ->join('lessons as l', 'l.id', '=', 'qz.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id'))
            ->get(['w.number as wn', 'l.title as lt', 'qa.*']) as $r) {
            $snapQuiz[$key($r->wn, $r->lt)][] = (array) $r;
        }
        foreach ($scope(DB::table('assignment_submissions as s')
            ->join('lessons as l', 'l.id', '=', 's.lesson_id')
            ->join('weeks as w', 'w.id', '=', 'l.week_id'))
            ->get(['w.number as wn', 'l.title as lt', 's.*']) as $r) {
            $snapSub[$key($r->wn, $r->lt)][] = (array) $r;
        }

        return [$snapDone, $snapQuiz, $snapSub];
    }

    /** @return string[] kesalahan pada daftar pelajaran ([] = valid) */
    private function validateLessons(array $lessons, string $label): array
    {
        $err = [];
        foreach ($lessons as $li => $l) {
            $ll = "$label pelajaran ke-" . ($li + 1);
            if (! is_array($l)) {
                $err[] = "$ll: format tidak valid.";
                continue;
            }
            if (! isset($l['title']) || ! is_string($l['title']) || trim($l['title']) === '') {
                $err[] = "$ll: \"title\" wajib diisi.";
            }
            if (! in_array($l['type'] ?? 'materi', self::TYPES, true)) {
                $err[] = "$ll: \"type\" harus salah satu dari: " . implode(', ', self::TYPES) . '.';
            }
            foreach (($l['quiz']['questions'] ?? []) as $qi => $q) {
                $qq = "$ll soal ke-" . ($qi + 1);
                if (! isset($q['text']) || trim((string) $q['text']) === '') {
                    $err[] = "$qq: \"text\" wajib diisi.";
                }
                $opts = $q['options'] ?? [];
                if (! is_array($opts) || count($opts) < 2) {
                    $err[] = "$qq: butuh minimal 2 \"options\".";
                }
            }
        }

        return $err;
    }

    /**
     * Buat pelajaran-pelajaran (+kuis) sebuah minggu, lalu pulihkan progres
     * mahasiswa dari snapshot berdasarkan judul.
     *
     * @return array{lessons:int, questions:int}
     */
    private function buildLessons($week, array $lessons, array $snapDone, array $snapQuiz, array $snapSub, callable $keyFn): array
    {
        $gist = fn ($v) => ($g = preg_replace('/[^A-Za-z0-9]/', '', (string) $v)) !== '' ? substr($g, 0, 64) : null;
        $nLessons = $nQuestions = 0;
        $number = (int) $week->number;

        foreach ($lessons as $li => $l) {
            $type = in_array($l['type'] ?? 'materi', self::TYPES, true) ? $l['type'] : 'materi';

            $attrs = [
                'title' => trim($l['title']),
                'type' => $type,
                'content' => trim((string) ($l['content'] ?? '')) ?: null,
                'video_url' => trim((string) ($l['video_url'] ?? '')) ?: null,
                'dartpad_gist' => $gist($l['dartpad_gist'] ?? null),
                'rubric' => trim((string) ($l['rubric'] ?? '')) ?: null,
                'max_score' => max(1, (int) ($l['max_score'] ?? 100)),
                'xp' => max(0, (int) ($l['xp'] ?? 10)),
                'position' => $li + 1,
                'cells' => null,
            ];

            if (isset($l['cells']) && is_array($l['cells'])) {
                $cells = \App\Support\Notebook::sanitize($l['cells']);
                if ($cells) {
                    $legacy = \App\Support\Notebook::deriveLegacy($cells, $type);
                    $attrs = array_merge($attrs, $legacy, ['cells' => $cells, 'title' => $attrs['title']]);
                }
            }

            $lesson = $week->lessons()->create($attrs);
            $nLessons++;

            $k = $keyFn($number, $l['title']);
            foreach ($snapDone[$k] ?? [] as $r) {
                DB::table('lesson_completions')->insertOrIgnore([
                    'lesson_id' => $lesson->id, 'user_id' => $r['user_id'], 'completed_at' => $r['completed_at'],
                ]);
            }
            foreach ($snapSub[$k] ?? [] as $r) {
                unset($r['id'], $r['wn'], $r['lt']);
                $r['lesson_id'] = $lesson->id;
                DB::table('assignment_submissions')->insert($r);
            }

            $hasQuizCell = is_array($lesson->cells)
                && collect($lesson->cells)->contains(fn ($c) => ($c['type'] ?? null) === 'quiz');
            if ($type === 'kuis' || isset($l['quiz']) || $hasQuizCell) {
                $qz = $l['quiz'] ?? [];
                $quiz = $lesson->quiz()->create([
                    'title' => trim((string) ($qz['title'] ?? $l['title'])),
                    'time_limit_min' => max(1, (int) ($qz['time_limit_min'] ?? 15)),
                    'pass_score' => min(100, max(0, (int) ($qz['pass_score'] ?? 70))),
                    'max_attempts' => max(1, (int) ($qz['max_attempts'] ?? 2)),
                    'xp' => max(0, (int) ($qz['xp'] ?? 50)),
                ]);

                foreach ($snapQuiz[$k] ?? [] as $r) {
                    DB::table('quiz_attempts')->insert([
                        'quiz_id' => $quiz->id, 'user_id' => $r['user_id'],
                        'started_at' => $r['started_at'], 'submitted_at' => $r['submitted_at'],
                        'score' => $r['score'], 'passed' => $r['passed'],
                        'ai_feedback' => $r['ai_feedback'] ?? null, 'ai_status' => $r['ai_status'] ?? 'none',
                    ]);
                }

                foreach (($qz['questions'] ?? []) as $qi => $q) {
                    $question = $quiz->questions()->create([
                        'text' => trim((string) $q['text']),
                        'code_snippet' => trim((string) ($q['code'] ?? $q['code_snippet'] ?? '')) ?: null,
                        'position' => $qi + 1,
                    ]);
                    $nQuestions++;
                    $answer = (int) ($q['answer'] ?? 0);
                    foreach (array_values($q['options'] ?? []) as $oi => $optText) {
                        $question->options()->create([
                            'text' => mb_substr((string) $optText, 0, 500),
                            'is_correct' => $oi === $answer,
                            'position' => $oi,
                        ]);
                    }
                }
            }
        }

        return ['lessons' => $nLessons, 'questions' => $nQuestions];
    }

    /** Bangun struktur JSON dari sebuah mata kuliah (ekspor / template). */
    public function export(Course $course): array
    {
        $out = [
            'name' => $course->name,
            'description' => $course->description ?? '',
            'weeks' => [],
        ];

        foreach ($course->weeks()->with('lessons.quiz.questions.options')->get() as $w) {
            $week = [
                'number' => (int) $w->number,
                'title' => $w->title,
                'is_exam' => (bool) $w->is_exam,
                'is_open' => (bool) $w->is_open,
                'lessons' => [],
            ];
            if ($w->summary) {
                $week['summary'] = $w->summary;
            }

            foreach ($w->lessons as $l) {
                $lesson = ['title' => $l->title, 'type' => $l->type, 'xp' => (int) $l->xp];
                if (is_array($l->cells) && $l->cells !== []) {
                    $lesson['cells'] = $l->cells;
                } else {
                    foreach (['content', 'video_url', 'dartpad_gist', 'rubric'] as $f) {
                        if ($l->$f) {
                            $lesson[$f] = $l->$f;
                        }
                    }
                    if ($l->type === 'tugas' && (int) $l->max_score !== 100) {
                        $lesson['max_score'] = (int) $l->max_score;
                    }
                }
                if ($l->quiz) {
                    $quiz = [
                        'time_limit_min' => (int) $l->quiz->time_limit_min,
                        'pass_score' => (int) $l->quiz->pass_score,
                        'max_attempts' => (int) $l->quiz->max_attempts,
                        'xp' => (int) $l->quiz->xp,
                        'questions' => [],
                    ];
                    foreach ($l->quiz->questions as $q) {
                        $answer = 0;
                        $opts = [];
                        foreach ($q->options->values() as $i => $o) {
                            $opts[] = $o->text;
                            if ($o->is_correct) {
                                $answer = $i;
                            }
                        }
                        $qq = ['text' => $q->text, 'options' => $opts, 'answer' => $answer];
                        if ($q->code_snippet) {
                            $qq['code'] = $q->code_snippet;
                        }
                        $quiz['questions'][] = $qq;
                    }
                    $lesson['quiz'] = $quiz;
                }
                $week['lessons'][] = $lesson;
            }
            $out['weeks'][] = $week;
        }

        return $out;
    }
}
