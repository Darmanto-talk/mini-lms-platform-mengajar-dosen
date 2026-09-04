<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Week;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    use TeacherAccess;

    // ---------------- minggu ----------------

    public function saveWeek(Request $request, Course $course)
    {
        $this->ownCourse($course);
        $data = $request->validate([
            'week_id' => ['nullable', 'integer'],
            'number' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string'],
            'is_exam' => ['nullable'],
        ]);

        $clash = $course->weeks()->where('number', $data['number'])
            ->when($data['week_id'] ?? null, fn ($q) => $q->where('id', '!=', $data['week_id']))->exists();
        if ($clash) {
            return back()->with('error', "Sudah ada minggu bernomor {$data['number']}.");
        }

        $attrs = [
            'number' => $data['number'],
            'title' => $data['title'],
            'summary' => $request->input('summary') ?: null,
            'is_exam' => $request->boolean('is_exam'),
            'position' => $data['number'],
        ];

        if ($data['week_id'] ?? null) {
            $course->weeks()->whereKey($data['week_id'])->update($attrs);
            $msg = 'Minggu diperbarui.';
        } else {
            $course->weeks()->create($attrs + ['is_open' => false]);
            $msg = 'Minggu ditambahkan.';
        }

        return back()->with('success', $msg);
    }

    public function destroyWeek(Week $week)
    {
        $this->ownWeek($week);
        $course = $week->course;
        $week->delete();

        return redirect()->route('teacher.courses.show', $course)->with('success', 'Minggu dihapus beserta pelajarannya.');
    }

    public function toggleWeek(Week $week)
    {
        $this->ownWeek($week);
        $week->update(['is_open' => ! $week->is_open]);

        return back()->with('success', "Minggu {$week->number} ".($week->is_open ? 'dibuka untuk mahasiswa.' : 'ditutup.'));
    }

    public function bulkWeeks(Request $request, Course $course)
    {
        $this->ownCourse($course);
        $open = $request->input('action') === 'open';
        $course->weeks()->update(['is_open' => $open]);

        return back()->with('success', $open ? 'Semua minggu dibuka.' : 'Semua minggu ditutup.');
    }

    // ---------------- pelajaran ----------------

    public function createLesson(Week $week)
    {
        $this->ownWeek($week);
        $lesson = new Lesson(['type' => 'materi', 'xp' => 10, 'max_score' => 100, 'position' => 0]);

        return view('teacher.lesson_form', ['course' => $week->course, 'week' => $week, 'lesson' => $lesson, 'quiz' => null]);
    }

    public function editLesson(Lesson $lesson)
    {
        $this->ownLesson($lesson);

        return view('teacher.lesson_form', [
            'course' => $lesson->week->course,
            'week' => $lesson->week,
            'lesson' => $lesson,
            'quiz' => $lesson->quiz()->with('questions.options')->first(),
        ]);
    }

    public function storeLesson(Request $request)
    {
        $week = Week::findOrFail($request->integer('week_id'));
        $this->ownWeek($week);
        $lesson = $week->lessons()->create($this->lessonData($request, $week));
        $this->syncQuiz($lesson);

        return redirect()->route('teacher.lessons.edit', $lesson)->with('success', 'Pelajaran disimpan.');
    }

    public function updateLesson(Request $request, Lesson $lesson)
    {
        $this->ownLesson($lesson);
        $lesson->update($this->lessonData($request, $lesson->week));
        $this->syncQuiz($lesson);

        return redirect()->route('teacher.lessons.edit', $lesson)->with('success', 'Pelajaran disimpan.');
    }

    public function destroyLesson(Lesson $lesson)
    {
        $this->ownLesson($lesson);
        $course = $lesson->week->course;
        $lesson->delete();

        return redirect()->route('teacher.courses.show', $course)->with('success', 'Pelajaran dihapus.');
    }

    public function preview(Lesson $lesson)
    {
        $this->ownLesson($lesson);

        return view('teacher.lesson_preview', [
            'course' => $lesson->week->course,
            'week' => $lesson->week,
            'lesson' => $lesson,
            'cells' => $lesson->notebookCells(),
            'quiz' => $lesson->quiz()->with('questions.options')->first(),
        ]);
    }

    public function present(Lesson $lesson)
    {
        $this->ownLesson($lesson);
        $course = $lesson->week->course->load(['weeks.lessons']);

        // urutan seluruh pelajaran dalam mata kuliah, untuk navigasi prev/next
        $ordered = $course->weeks->flatMap->lessons->values();
        $pos = $ordered->search(fn ($l) => $l->id === $lesson->id);
        $prev = $pos > 0 ? $ordered[$pos - 1] : null;
        $next = $pos !== false && $pos < $ordered->count() - 1 ? $ordered[$pos + 1] : null;

        return view('teacher.lesson_present', [
            'course' => $course,
            'week' => $lesson->week,
            'lesson' => $lesson,
            'cells' => $lesson->notebookCells(),
            'quiz' => $lesson->quiz()->with('questions.options')->first(),
            'prev' => $prev,
            'next' => $next,
            'step' => ($pos === false ? 0 : $pos + 1),
            'stepTotal' => $ordered->count(),
        ]);
    }

    private function lessonData(Request $request, Week $week): array
    {
        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'cells_json' => ['nullable', 'string'],
            'xp' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        // Pengaman kehilangan data: `cells_json` kosong = editor gagal menyiapkan
        // data (bug JS). Tolak simpan agar materi lama tidak terhapus.
        $raw = trim((string) $request->input('cells_json', ''));
        $decoded = $raw === '' ? null : json_decode($raw, true);
        if (! is_array($decoded)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cells_json' => 'Data notebook tidak terbaca — pelajaran TIDAK disimpan agar materi lama tidak hilang. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        $cells = \App\Support\Notebook::sanitize($decoded);
        $legacy = \App\Support\Notebook::deriveLegacy($cells, (string) $request->input('type', 'materi'));

        return [
            'title' => $request->string('title')->toString(),
            'type' => $legacy['type'],
            'cells' => $cells,
            'content' => $legacy['content'],
            'video_url' => $legacy['video_url'],
            'dartpad_gist' => $legacy['dartpad_gist'],
            'rubric' => $legacy['rubric'],
            'max_score' => $legacy['max_score'],
            'xp' => max(0, (int) $request->input('xp', 10)),
            'position' => (int) ($request->input('position') ?: ($week->lessons()->max('position') + 1)),
        ];
    }

    private function syncQuiz(Lesson $lesson): void
    {
        $wantsQuiz = $lesson->type === 'kuis' || $lesson->hasCellType('quiz');
        if ($wantsQuiz && ! $lesson->quiz) {
            $lesson->quiz()->create(['title' => $lesson->title]);
        }
    }

    // ---------------- kuis ----------------

    public function updateQuiz(Request $request, Quiz $quiz)
    {
        $this->ownQuiz($quiz);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'time_limit_min' => ['required', 'integer', 'min:1'],
            'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'xp' => ['required', 'integer', 'min:0'],
            'ai_feedback' => ['nullable'],
        ]);
        $quiz->update([
            'title' => $request->input('title') ?: $quiz->title,
            'time_limit_min' => $data['time_limit_min'],
            'pass_score' => $data['pass_score'],
            'max_attempts' => $data['max_attempts'],
            'xp' => $data['xp'],
            'ai_feedback' => $request->boolean('ai_feedback'),
        ]);

        return redirect()->route('teacher.lessons.edit', $quiz->lesson)->with('success', 'Pengaturan kuis disimpan.');
    }

    public function saveQuestion(Request $request, Quiz $quiz)
    {
        $this->ownQuiz($quiz);
        $data = $request->validate([
            'question_id' => ['nullable', 'integer'],
            'text' => ['required', 'string'],
            'code_snippet' => ['nullable', 'string'],
            'opt' => ['required', 'array', 'min:2'],
            'opt.*' => ['nullable', 'string'],
            'correct' => ['required', 'integer', 'min:0'],
        ]);

        $opts = [];
        $correct = 0;
        foreach ($data['opt'] as $idx => $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            if ((int) $data['correct'] === $idx) {
                $correct = count($opts);
            }
            $opts[] = $t;
        }
        if (count($opts) < 2) {
            return back()->with('error', 'Pertanyaan butuh minimal 2 opsi terisi.');
        }
        $correct = min($correct, count($opts) - 1);

        $code = $request->input('code_snippet') ?: null;
        $question = ($data['question_id'] ?? null)
            ? tap(Question::where('quiz_id', $quiz->id)->findOrFail($data['question_id']))->update([
                'text' => $data['text'], 'code_snippet' => $code,
            ])
            : $quiz->questions()->create([
                'text' => $data['text'], 'code_snippet' => $code,
                'position' => (int) $quiz->questions()->max('position') + 1,
            ]);

        $question->options()->delete();
        foreach ($opts as $i => $t) {
            $question->options()->create([
                'text' => mb_substr($t, 0, 500), 'is_correct' => $i === $correct, 'position' => $i,
            ]);
        }

        return redirect()->route('teacher.lessons.edit', $quiz->lesson)->with('success', 'Pertanyaan disimpan.');
    }

    public function destroyQuestion(Question $question)
    {
        $question->load('quiz.lesson.week.course');
        abort_unless($question->quiz->lesson->week->course->teacher_id === auth()->id(), 403);
        $lesson = $question->quiz->lesson;
        $question->delete();

        return redirect()->route('teacher.lessons.edit', $lesson)->with('success', 'Pertanyaan dihapus.');
    }

    // ---------------- unggah gambar ----------------

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);
        $path = $request->file('image')->store('uploads', 'public'); // "uploads/xxxx.jpg"

        return response()->json([
            'ok' => true,
            'markdown' => '![keterangan gambar](storage/'.$path.')',
            'url' => asset('storage/'.$path),
        ]);
    }

    public const DOC_EXT = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'ods', 'odp', 'txt', 'csv', 'rtf', 'zip', 'rar'];

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:25600', 'extensions:'.implode(',', self::DOC_EXT)],
        ], [
            'file.extensions' => 'Format tidak didukung. Boleh: '.implode(', ', self::DOC_EXT).'.',
            'file.max' => 'Ukuran berkas melebihi 25 MB.',
        ]);

        $up = $request->file('file');
        $ext = strtolower($up->getClientOriginalExtension());
        $orig = pathinfo($up->getClientOriginalName(), PATHINFO_FILENAME);
        $path = $up->storeAs('uploads/docs', bin2hex(random_bytes(6)).'.'.$ext, 'public');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'name' => mb_substr($orig, 0, 120) ?: 'Dokumen',
            'ext' => $ext,
            'size' => $up->getSize(),
            'url' => asset('storage/'.$path),
        ]);
    }
}
