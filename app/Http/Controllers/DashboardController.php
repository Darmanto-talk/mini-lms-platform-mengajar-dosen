<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Services\Gradebook;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, Gradebook $gb)
    {
        $user = $request->user();

        if ($user->isTeacher()) {
            return redirect()->route('teacher.index');
        }

        $classes = $user->classes()->with('course')->orderByPivot('joined_at', 'desc')->get()
            ->map(function (Kelas $c) use ($user, $gb) {
                $courseId = $c->course_id;
                $total = $c->course->totalLessons(true);
                $done = count($gb->completedLessonIds($courseId, $user->id, true));
                $c->stat = [
                    'total' => $total,
                    'done' => $done,
                    'percent' => $total ? (int) round($done / $total * 100) : 0,
                    'xp' => $gb->userXp($courseId, $user->id),
                ];
                return $c;
            });

        return view('student.dashboard', compact('user', 'classes'));
    }

    public function join(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $class = Kelas::where('code', strtoupper(trim($data['code'])))->with('course')->first();

        if (! $class) {
            return back()->with('error', 'Kode kelas tidak ditemukan.');
        }

        $user = $request->user();

        if ($user->classes()->where('classes.id', $class->id)->exists()) {
            return redirect()->route('course.show', $class)->with('info', 'Anda sudah terdaftar di kelas itu.');
        }

        // Satu mahasiswa hanya boleh aktif di SATU kelas per mata kuliah (boleh
        // beda kelas untuk mata kuliah yang berbeda). Tolak kalau sudah aktif
        // di kelas lain pada mata kuliah yang sama.
        $other = $user->classes()->where('course_id', $class->course_id)->first();
        if ($other) {
            return back()->with('error',
                "Anda sudah terdaftar di \"{$other->name}\" untuk mata kuliah {$class->course->name}. "
                .'Satu mahasiswa hanya boleh berada di satu kelas untuk mata kuliah yang sama — minta dosen memindahkan Anda bila perlu ganti kelas.');
        }

        // Pernah terdaftar lalu dikeluarkan dosen? Aktifkan lagi baris lamanya
        // (unik per kelas+mahasiswa) alih-alih membuat baris baru.
        $existing = $class->enrollments()->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->update(['removed_at' => null]);
        } else {
            $class->enrollments()->create(['user_id' => $user->id]);
        }

        return redirect()->route('course.show', $class)
            ->with('success', "Berhasil bergabung ke {$class->course->name} — {$class->name}.");
    }
}
