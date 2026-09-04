<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Jobs\GradeAssignmentWithAi;
use App\Jobs\ReviewQuizAttemptWithAi;
use App\Models\AssignmentSubmission;
use App\Models\Kelas;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Services\AiGrader;
use Illuminate\Http\Request;

class GradingController extends Controller
{
    use TeacherAccess;

    /** Monitoring hasil kuis satu kelas: siapa yang sudah mengerjakan + jawabannya. */
    public function quizResults(Kelas $class, Lesson $lesson)
    {
        $this->ownClass($class);
        abort_unless($lesson->week->course_id === $class->course_id && $lesson->type === 'kuis', 404);
        $lesson->load('quiz.questions.options');
        $quiz = $lesson->quiz;
        abort_if($quiz === null, 404);

        $rows = $class->students()->get()->map(function ($u) use ($quiz) {
            $attempts = $quiz->attempts()->where('user_id', $u->id)->orderBy('id')
                ->with('answers')->get();
            $best = $attempts->whereNotNull('submitted_at')->max('score');
            return (object) [
                'user' => $u,
                'attempts' => $attempts,
                'submitted' => $attempts->whereNotNull('submitted_at')->count(),
                'best' => $best,
                'passed' => $attempts->contains(fn ($a) => $a->passed),
                'last' => $attempts->whereNotNull('submitted_at')->max('submitted_at'),
            ];
        });

        return view('teacher.quiz_results', [
            'class' => $class,
            'lesson' => $lesson,
            'quiz' => $quiz,
            'questions' => $quiz->questions,
            'rows' => $rows,
        ]);
    }

    public function index(Kelas $class, Lesson $lesson)
    {
        $this->ownClass($class);
        abort_unless($lesson->week->course_id === $class->course_id && $lesson->type === 'tugas', 404);

        $rows = $class->students()->get()->map(function ($u) use ($lesson, $class) {
            $sub = AssignmentSubmission::where('lesson_id', $lesson->id)
                ->where('user_id', $u->id)->where('class_id', $class->id)->first();
            return (object) ['user' => $u, 'sub' => $sub];
        });

        return view('teacher.grade', [
            'class' => $class,
            'lesson' => $lesson,
            'rows' => $rows,
            'aiEnabled' => app(AiGrader::class)->enabled(),
        ]);
    }

    public function store(Request $request, Kelas $class, Lesson $lesson)
    {
        $this->ownClass($class);
        abort_unless($lesson->week->course_id === $class->course_id && $lesson->type === 'tugas', 404);

        $data = $request->validate([
            'submission' => ['required', 'integer'],
            'score' => ['nullable', 'integer', 'min:0', 'max:'.$lesson->max_score],
            'feedback' => ['nullable', 'string'],
        ]);

        $sub = AssignmentSubmission::where('id', $data['submission'])
            ->where('lesson_id', $lesson->id)->where('class_id', $class->id)->firstOrFail();

        $sub->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?: null,
            'graded_at' => now(),
            'graded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Nilai tugas disimpan.');
    }

    /** Jalankan pemeriksaan AI untuk satu pengumpulan (sinkron, agar jalan tanpa worker). */
    public function aiGrade(Request $request, AssignmentSubmission $submission)
    {
        $submission->load('lesson.week.course');
        abort_unless($submission->lesson->week->course->teacher_id === $request->user()->id, 403);
        abort_unless(app(AiGrader::class)->enabled(), 400, 'Fitur AI tidak aktif (ANTHROPIC_API_KEY belum diisi).');

        $submission->update(['ai_status' => 'pending']);
        GradeAssignmentWithAi::dispatchSync($submission->id);

        $s = $submission->fresh();
        return back()->with(
            $s->ai_status === 'done' ? 'success' : 'error',
            $s->ai_status === 'done'
                ? "AI selesai memeriksa. Usulan nilai: {$s->ai_score}."
                : ('Pemeriksaan AI gagal: '.$s->ai_error)
        );
    }

    public function aiReview(Request $request, QuizAttempt $attempt)
    {
        $attempt->load('quiz.lesson.week.course');
        abort_unless($attempt->quiz->lesson->week->course->teacher_id === $request->user()->id, 403);
        abort_unless(app(AiGrader::class)->enabled(), 400, 'Fitur AI tidak aktif.');

        $attempt->update(['ai_status' => 'pending']);
        ReviewQuizAttemptWithAi::dispatchSync($attempt->id);

        return back()->with('success', 'Ulasan AI untuk percobaan kuis diperbarui.');
    }
}
