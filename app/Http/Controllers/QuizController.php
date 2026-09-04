<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StudentAccess;
use App\Jobs\ReviewQuizAttemptWithAi;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Gradebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    use StudentAccess;

    private function quizFor(Lesson $lesson): array
    {
        $lesson->load('week.course', 'quiz.questions.options');
        $class = $this->classForLesson($lesson);
        abort_unless($lesson->week->is_open, 403, 'Kuis ini belum dibuka dosen.');
        abort_unless(
            app(Gradebook::class)->lessonUnlocked($lesson->week->course, auth()->id(), $lesson->id),
            403,
            'Selesaikan materi sebelumnya secara berurutan sebelum mengerjakan kuis ini.'
        );
        abort_if($lesson->quiz === null, 404, 'Kuis belum tersedia.');

        return [$lesson, $lesson->quiz, $class];
    }

    public function intro(Request $request, Lesson $lesson)
    {
        [$lesson, $quiz, $class] = $this->quizFor($lesson);
        $uid = $request->user()->id;

        $open = $quiz->attempts()->where('user_id', $uid)->whereNull('submitted_at')->latest('id')->first();
        $used = $quiz->attempts()->where('user_id', $uid)->whereNotNull('submitted_at')->count();
        $best = $quiz->attempts()->where('user_id', $uid)->whereNotNull('submitted_at')
            ->selectRaw('MAX(score) s, MAX(passed) p')->first();

        return view('student.quiz.intro', compact('lesson', 'quiz', 'class', 'open', 'used', 'best'));
    }

    public function start(Request $request, Lesson $lesson)
    {
        [$lesson, $quiz] = $this->quizFor($lesson);
        $uid = $request->user()->id;

        $open = $quiz->attempts()->where('user_id', $uid)->whereNull('submitted_at')->latest('id')->first();
        if ($open) {
            return redirect()->route('quiz.attempt', $open);
        }
        if ($quiz->attempts()->where('user_id', $uid)->whereNotNull('submitted_at')->count() >= $quiz->max_attempts) {
            return redirect()->route('quiz.intro', $lesson)->with('error', 'Batas percobaan kuis sudah habis.');
        }

        $attempt = $quiz->attempts()->create(['user_id' => $uid, 'started_at' => now()]);

        return redirect()->route('quiz.attempt', $attempt);
    }

    public function attempt(Request $request, QuizAttempt $attempt)
    {
        $this->ownAttempt($request, $attempt);
        $attempt->load('quiz.questions.options', 'quiz.lesson.week');

        if ($attempt->submitted_at !== null) {
            return redirect()->route('quiz.result', $attempt);
        }

        $deadline = $attempt->started_at->copy()->addMinutes($attempt->quiz->time_limit_min);
        $remaining = now()->diffInSeconds($deadline, false);
        if ($remaining <= 0) {
            $this->finalize($attempt, []);

            return redirect()->route('quiz.result', $attempt)->with('info', 'Waktu kuis habis. Jawaban otomatis dikumpulkan.');
        }

        $class = $request->user()->classes()->where('course_id', $attempt->quiz->lesson->week->course_id)->first();

        return view('student.quiz.attempt', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'lesson' => $attempt->quiz->lesson,
            'class' => $class,
            'questions' => $attempt->quiz->questions,
            'remaining' => $remaining,
        ]);
    }

    public function submit(Request $request, QuizAttempt $attempt)
    {
        $this->ownAttempt($request, $attempt);
        if ($attempt->submitted_at === null) {
            $this->finalize($attempt, (array) $request->input('answer', []));
        }

        return redirect()->route('quiz.result', $attempt);
    }

    public function result(Request $request, QuizAttempt $attempt)
    {
        $this->ownAttempt($request, $attempt);
        $attempt->load('quiz.questions.options', 'quiz.lesson.week.course');

        if ($attempt->submitted_at === null) {
            return redirect()->route('quiz.attempt', $attempt);
        }

        $picked = $attempt->answers()->pluck('option_id', 'question_id');
        $used = $attempt->quiz->attempts()->where('user_id', $request->user()->id)->whereNotNull('submitted_at')->count();
        $class = $request->user()->classes()->where('course_id', $attempt->quiz->lesson->week->course_id)->first();

        return view('student.quiz.result', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'lesson' => $attempt->quiz->lesson,
            'class' => $class,
            'questions' => $attempt->quiz->questions,
            'picked' => $picked,
            'attemptsLeft' => max(0, $attempt->quiz->max_attempts - $used),
        ]);
    }

    private function ownAttempt(Request $request, QuizAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }

    private function finalize(QuizAttempt $attempt, array $answers): void
    {
        $attempt->load('quiz.questions.options');
        $questions = $attempt->quiz->questions;
        $totalQ = $questions->count();

        DB::transaction(function () use ($attempt, $questions, $answers, $totalQ) {
            $correct = 0;
            foreach ($questions as $q) {
                $chosenId = isset($answers[$q->id]) ? (int) $answers[$q->id] : null;
                $opt = $q->options->firstWhere('id', $chosenId);
                $attempt->answers()->updateOrCreate(
                    ['question_id' => $q->id],
                    ['option_id' => $opt?->id]
                );
                if ($opt && $opt->is_correct) {
                    $correct++;
                }
            }
            $score = $totalQ > 0 ? (int) round($correct / $totalQ * 100) : 0;
            $passed = $score >= $attempt->quiz->pass_score;

            $attempt->update([
                'submitted_at' => now(),
                'score' => $score,
                'passed' => $passed,
                'ai_status' => (config('services.anthropic.auto_review_kuis') && $attempt->quiz->ai_feedback && filled(config('services.anthropic.key'))) ? 'pending' : 'none',
            ]);

            $attempt->quiz->lesson->completions()->firstOrCreate(['user_id' => $attempt->user_id]);
        });

        if ($attempt->fresh()->ai_status === 'pending') {
            ReviewQuizAttemptWithAi::dispatch($attempt->id);
        }
    }
}
