<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StudentAccess;
use App\Jobs\GradeAssignmentWithAi;
use App\Models\Lesson;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    use StudentAccess;

    public function submit(Request $request, Lesson $lesson)
    {
        $lesson->load('week.course');
        abort_unless($lesson->type === 'tugas' || $lesson->hasCellType('assignment'), 404);
        $class = $this->classForLesson($lesson);
        abort_unless($lesson->week->is_open, 403, 'Tugas ini belum dibuka dosen.');
        abort_unless(
            app(\App\Services\Gradebook::class)->lessonUnlocked($lesson->week->course, $request->user()->id, $lesson->id),
            403,
            'Selesaikan materi sebelumnya secara berurutan dulu.'
        );

        $sub = $lesson->submissions()->where('user_id', $request->user()->id)->first();
        if ($sub && $sub->graded_at !== null) {
            return redirect()->route('lesson.show', $lesson)->with('error', 'Tugas sudah dinilai dan tidak bisa diubah.');
        }

        $allowedExt = ['pdf', 'zip', 'rar', 'dart', 'txt', 'md', 'doc', 'docx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg'];

        $data = $request->validate([
            'text' => ['nullable', 'string'],
            'link_url' => ['nullable', 'url', 'max:400'],
            'file' => ['nullable', 'file', 'max:10240', 'extensions:'.implode(',', $allowedExt)],
        ], [
            'file.extensions' => 'Jenis berkas tidak diizinkan. Boleh: '.implode(', ', $allowedExt).'.',
            'file.max' => 'Ukuran berkas melebihi 10 MB.',
        ]);

        $filePath = $sub->file_path ?? null;
        if ($request->hasFile('file')) {
            $ext = strtolower($request->file('file')->getClientOriginalExtension());
            $filePath = $request->file('file')->storeAs(
                'uploads/tugas',
                'tugas'.$lesson->id.'-u'.$request->user()->id.'-'.bin2hex(random_bytes(4)).'.'.$ext,
                'public'
            );
        }

        if (blank($data['text'] ?? null) && blank($data['link_url'] ?? null) && ! $filePath) {
            return back()->with('error', 'Isi jawaban teks, tautan, atau unggah berkas.');
        }

        $aiOn = config('services.anthropic.auto_grade_tugas') && filled(config('services.anthropic.key'));

        $sub = $lesson->submissions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'class_id' => $class->id,
                'text' => $data['text'] ?? null,
                'link_url' => $data['link_url'] ?? null,
                'file_path' => $filePath,
                'submitted_at' => now(),
                'ai_status' => $aiOn ? 'pending' : 'none',
                'ai_score' => null, 'ai_feedback' => null, 'ai_breakdown' => null,
                'ai_graded_at' => null, 'ai_error' => null,
            ]
        );

        $lesson->completions()->firstOrCreate(['user_id' => $request->user()->id]);

        if ($aiOn) {
            GradeAssignmentWithAi::dispatch($sub->id);
        }

        return redirect()->route('lesson.show', $lesson)->with(
            'success',
            $aiOn
                ? 'Tugas dikumpulkan. AI sedang memeriksa — nilai & umpan balik akan muncul sebentar lagi.'
                : 'Tugas dikumpulkan. Menunggu penilaian dosen.'
        );
    }
}
