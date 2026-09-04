<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StudentAccess;
use App\Models\Lesson;
use App\Services\Gradebook;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use StudentAccess;

    public function show(Request $request, Lesson $lesson, Gradebook $gb)
    {
        $lesson->load('week.course');
        $class = $this->classForLesson($lesson);
        $user = $request->user();
        $course = $lesson->week->course;

        if (! $lesson->week->is_open) {
            return response()->view('student.locked', [
                'class' => $class,
                'week' => $lesson->week->number,
            ], 403);
        }

        $unlockedSet = array_flip($gb->unlockedLessonIds($course, $user->id));
        if (! isset($unlockedSet[$lesson->id])) {
            return response()->view('student.locked', [
                'class' => $class,
                'week' => $lesson->week->number,
                'title' => 'Selesaikan materi sebelumnya dulu',
                'body' => 'Materi ini akan terbuka setelah Anda menyelesaikan materi sebelumnya secara berurutan.',
            ], 403);
        }

        $cells = $lesson->notebookCells();
        $isPureQuiz = count($cells) === 1 && ($cells[0]['type'] ?? null) === 'quiz';
        if ($isPureQuiz || ($lesson->type === 'kuis' && ! $cells)) {
            return redirect()->route('quiz.intro', $lesson);
        }

        $doneSet = array_flip($gb->completedLessonIds($course->id, $user->id));
        $weeks = $course->weeks()->with('lessons')->get();
        $total = $course->totalLessons(true);

        $ordered = $weeks->where('is_open', true)->flatMap->lessons->pluck('id')->values();
        $pos = $ordered->search($lesson->id);
        $neighbours = [
            'prev' => $pos !== false && $pos > 0 ? $ordered[$pos - 1] : null,
            'next' => $pos !== false && $pos < $ordered->count() - 1 ? $ordered[$pos + 1] : null,
        ];

        $hasAssignment = \App\Support\Notebook::hasType($cells, 'assignment');
        $hasQuizCell = \App\Support\Notebook::hasType($cells, 'quiz');

        $submission = ($hasAssignment || $lesson->type === 'tugas')
            ? $lesson->submissions()->where('user_id', $user->id)->first()
            : null;

        $quiz = null;
        $quizStatus = null;
        if ($hasQuizCell || $lesson->type === 'kuis') {
            $quiz = $lesson->quiz()->with('questions.options')->first();
            if ($quiz) {
                $done = $quiz->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at');
                $best = (clone $done)->max('score');
                $quizStatus = [
                    'used' => (clone $done)->count(),
                    'best' => $best,
                    'passed' => $best !== null ? (bool) (clone $done)->where('passed', true)->exists() : false,
                ];
            }
        }

        return view('student.lesson', [
            'user'       => $user,
            'class'      => $class,
            'lesson'     => $lesson,
            'cells'      => $cells,
            'completed'  => isset($doneSet[$lesson->id]),
            'submission' => $submission,
            'quiz'       => $quiz,
            'quizStatus' => $quizStatus,
            'weeks'      => $weeks,
            'doneSet'    => $doneSet,
            'unlockedSet' => $unlockedSet,
            'doneCount'  => count(array_intersect(array_keys($doneSet), $ordered->all())),
            'total'      => $total,
            'neighbours' => $neighbours,
        ]);
    }

    public function complete(Request $request, Lesson $lesson, Gradebook $gb)
    {
        $lesson->load('week.course');
        $this->classForLesson($lesson);
        abort_unless($lesson->week->is_open, 403, 'Materi ini belum dibuka dosen.');
        abort_unless(
            $gb->lessonUnlocked($lesson->week->course, $request->user()->id, $lesson->id),
            403,
            'Selesaikan materi sebelumnya secara berurutan.'
        );

        $lesson->completions()->firstOrCreate(['user_id' => $request->user()->id]);

        $course = $lesson->week->course;
        $ordered = $course->weeks()->where('is_open', true)->with('lessons')->get()
            ->flatMap->lessons->pluck('id')->values();
        $pos = $ordered->search($lesson->id);

        if ($pos !== false && $pos < $ordered->count() - 1) {
            // pindah ke pelajaran berikutnya tanpa notifikasi (biar tidak mengganggu)
            return redirect()->route('lesson.show', $ordered[$pos + 1]);
        }

        $class = $request->user()->classes()->where('course_id', $course->id)->first();

        return redirect()->route('course.show', $class)->with('success', 'Selamat! Anda telah menyelesaikan semua materi yang terbuka.');
    }
}
