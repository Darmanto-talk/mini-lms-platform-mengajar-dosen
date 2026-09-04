<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CourseImporter;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ImportController extends Controller
{
    use TeacherAccess;

    public function form(Request $request)
    {
        return view('teacher.import', [
            'courses' => $request->user()->courses()
                ->with(['weeks' => fn ($q) => $q->orderBy('number')])
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CourseImporter $importer)
    {
        $request->validate([
            'mode' => ['required', 'in:new,update'],
            'course' => ['nullable', 'integer'],
            'week' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'max:3072', 'mimetypes:application/json,text/plain'],
        ]);

        $data = json_decode(file_get_contents($request->file('file')->getRealPath()), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['file' => 'File bukan JSON yang valid: '.json_last_error_msg()]);
        }

        // ── impor ke SATU minggu tertentu (dipilih dari dropdown) ──
        if ($request->input('mode') === 'update' && $request->filled('week')) {
            $course = $this->ownCourse(Course::findOrFail($request->integer('course')));
            $week = $course->weeks()->findOrFail($request->integer('week'));

            try {
                $res = $importer->importWeek($week, $data);
            } catch (RuntimeException $e) {
                return back()->withErrors(['import' => explode("\n", $e->getMessage())]);
            } catch (Throwable $e) {
                return back()->withErrors(['import' => 'Gagal mengimpor: '.$e->getMessage()]);
            }

            return redirect()->route('teacher.courses.show', $res['course'])->with('success',
                "Minggu {$res['week']} diperbarui: {$res['lessons']} pelajaran, {$res['questions']} soal. "
                .'Minggu lain tidak diubah; progres & nilai mahasiswa dipertahankan.');
        }

        $target = null;
        if ($request->input('mode') === 'update') {
            $target = $this->ownCourse(Course::findOrFail($request->integer('course')));

            if (! is_array($data) || ! isset($data['weeks'])) {
                return back()->withErrors(['import' => 'File ini berisi satu pertemuan (tidak ada kolom "weeks"). Pilih "Minggu tujuan" di dropdown agar isinya dimasukkan ke minggu yang tepat.']);
            }
        }

        try {
            $res = $importer->import($data, $request->user(), $target);
        } catch (RuntimeException $e) {
            return back()->withErrors(['import' => explode("\n", $e->getMessage())]);
        } catch (Throwable $e) {
            return back()->withErrors(['import' => 'Gagal mengimpor: '.$e->getMessage()]);
        }

        if ($res['mode'] === 'update') {
            $parts = [];
            if ($res['weeks_updated']) {
                $parts[] = "{$res['weeks_updated']} minggu diperbarui";
            }
            if ($res['weeks_added']) {
                $parts[] = "{$res['weeks_added']} minggu ditambahkan";
            }
            $kept = $res['weeks_kept']
                ? " {$res['weeks_kept']} minggu lain di mata kuliah ini tidak diubah."
                : '';
            $msg = ($parts ? implode(', ', $parts) : 'Materi diperbarui')
                .": {$res['lessons']} pelajaran, {$res['questions']} soal."
                .$kept.' Progres & nilai mahasiswa dipertahankan.';
        } else {
            $msg = "Materi diimpor: {$res['weeks']} minggu, {$res['lessons']} pelajaran, {$res['questions']} soal. Kelas A dibuat otomatis.";
        }

        return redirect()->route('teacher.courses.show', $res['course'])->with('success', $msg);
    }

    public function export(Course $course, CourseImporter $importer)
    {
        $this->ownCourse($course);
        $json = json_encode($importer->export($course), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($course->name)), '-');

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="materi-'.$slug.'.json"',
        ]);
    }
}
