<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Kelas;
use App\Models\User;
use App\Services\Gradebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    use TeacherAccess;

    public function index(Kelas $class, Gradebook $gb)
    {
        $this->ownClass($class);

        return view('teacher.students', [
            'class' => $class,
            'rows' => $gb->classRows($class),
            'alumni' => $class->formerStudents()->get(['users.id', 'users.name', 'users.nim', 'users.email']),
        ]);
    }

    /** Monitoring: siapa saja yang sudah mengerjakan tiap kuis & tugas. */
    public function monitor(Kelas $class)
    {
        $this->ownClass($class);
        $course = $class->course->load('weeks.lessons.quiz');
        $studentIds = $class->students()->pluck('users.id');
        $studentCount = $studentIds->count();

        $items = [];
        foreach ($course->weeks as $w) {
            foreach ($w->lessons as $l) {
                if (! in_array($l->type, ['kuis', 'tugas'], true)) {
                    continue;
                }

                if ($l->type === 'kuis' && $l->quiz) {
                    $doneIds = DB::table('quiz_attempts')
                        ->where('quiz_id', $l->quiz->id)->whereIn('user_id', $studentIds)
                        ->whereNotNull('submitted_at')->distinct()->pluck('user_id');
                    $passed = DB::table('quiz_attempts')
                        ->where('quiz_id', $l->quiz->id)->whereIn('user_id', $studentIds)
                        ->where('passed', true)->distinct()->count('user_id');
                    $link = route('teacher.quiz.results', [$class, $l]);
                } else {
                    $subs = AssignmentSubmission::where('lesson_id', $l->id)->where('class_id', $class->id)
                        ->whereIn('user_id', $studentIds)->get();
                    $doneIds = $subs->pluck('user_id');
                    $passed = $subs->whereNotNull('graded_at')->count();
                    $link = route('teacher.grade.index', [$class, $l]);
                }

                $items[] = (object) [
                    'week' => $w->number,
                    'is_open' => $w->is_open,
                    'title' => $l->title,
                    'type' => $l->type,
                    'type_label' => $l->typeLabel(),
                    'done' => $doneIds->count(),
                    'total' => $studentCount,
                    'extra' => $l->type === 'kuis' ? "$passed lulus" : "$passed dinilai",
                    'link' => $link,
                    'missing' => $class->students()->whereNotIn('users.id', $doneIds)->pluck('users.name'),
                ];
            }
        }

        return view('teacher.monitor', compact('class', 'items', 'studentCount'));
    }

    public function show(Kelas $class, User $user, Gradebook $gb)
    {
        $this->ownClass($class);
        $enrollment = $class->enrollments()->where('user_id', $user->id)->first();
        abort_unless($enrollment, 404, 'Mahasiswa tidak pernah terdaftar di kelas ini.');

        return view('teacher.student_detail', [
            'class' => $class,
            'student' => $user,
            'removedAt' => $enrollment->removed_at,
            'detail' => $gb->studentDetail($class, $user->id),
        ]);
    }

    /** Keluarkan dari kelas — bersifat lunak: riwayat & nilai tetap tersimpan dan tetap bisa dilihat/dipulihkan. */
    public function destroy(Kelas $class, User $user)
    {
        $this->ownClass($class);
        $class->enrollments()->where('user_id', $user->id)->whereNull('removed_at')->update(['removed_at' => now()]);

        return redirect()->route('teacher.students.index', $class)
            ->with('success', "{$user->name} dikeluarkan dari kelas {$class->name}. Riwayat & nilainya tetap tersimpan — lihat di daftar \"Mahasiswa yang dikeluarkan\", dan bisa didaftarkan kembali kapan saja.");
    }

    /** Daftarkan kembali mahasiswa yang sebelumnya dikeluarkan dari kelas ini. */
    public function rejoin(Kelas $class, User $user)
    {
        $this->ownClass($class);
        $enrollment = $class->enrollments()->where('user_id', $user->id)->whereNotNull('removed_at')->first();
        abort_unless($enrollment, 404, 'Mahasiswa ini tidak sedang dalam status dikeluarkan.');

        // Satu mahasiswa hanya boleh aktif di satu kelas per mata kuliah — cegah
        // rangkap kalau sudah aktif lagi di kelas lain pada mata kuliah yang sama.
        $other = $user->classes()->where('course_id', $class->course_id)->first();
        if ($other) {
            return back()->with('error',
                "{$user->name} sudah aktif di kelas \"{$other->name}\" untuk mata kuliah ini. Keluarkan dulu dari kelas itu sebelum mendaftarkannya ke {$class->name}.");
        }

        $enrollment->update(['removed_at' => null]);

        return redirect()->route('teacher.students.show', [$class, $user->id])
            ->with('success', "{$user->name} didaftarkan kembali ke kelas {$class->name}.");
    }

    /**
     * Setel ulang kata sandi mahasiswa ke sandi sementara acak.
     * Kata sandi lama TIDAK bisa ditampilkan — tersimpan sebagai hash (bcrypt)
     * yang tidak dapat dibalik. Dosen menyampaikan sandi sementara ini ke
     * mahasiswa, lalu mahasiswa menggantinya sendiri lewat menu Profil.
     */
    public function resetPassword(Kelas $class, User $user)
    {
        $this->ownClass($class);
        abort_unless($class->enrollments()->where('user_id', $user->id)->exists(), 404, 'Mahasiswa tidak pernah terdaftar di kelas ini.');
        abort_unless($user->isStudent(), 403, 'Hanya akun mahasiswa yang bisa disetel ulang di sini.');

        $temp = \App\Support\TempPassword::make();

        $user->forceFill(['password' => $temp])->save();   // cast 'hashed' meng-hash otomatis

        return redirect()->route('teacher.students.show', [$class, $user->id])
            ->with('success', "Kata sandi {$user->name} disetel ulang — lihat sandi sementaranya di bawah.")
            ->with('pw_reset', ['user_id' => $user->id, 'name' => $user->name, 'temp' => $temp]);
    }

    /**
     * Hapus akun mahasiswa dari platform sepenuhnya (mis. sudah lulus / tidak
     * aktif). Menghapus semua enrollment, pengumpulan tugas, percobaan kuis,
     * dan riwayat progres mahasiswa tsb. di seluruh kelas dosen ini.
     */
    public function destroyAccount(Request $request, User $user)
    {
        abort_unless($user->isStudent(), 403, 'Hanya akun mahasiswa yang bisa dihapus di sini.');
        $this->assertOwnStudent($user);

        $request->validate(
            ['confirm' => ['required', 'string']],
            ['confirm.required' => 'Ketik email mahasiswa untuk konfirmasi.'],
        );
        if (trim(mb_strtolower($request->input('confirm'))) !== mb_strtolower($user->email)) {
            return back()->withErrors(['confirm' => 'Email yang diketik tidak cocok. Akun tidak dihapus.']);
        }

        $name = $user->name;
        $backTo = $request->input('back');
        $user->delete(); // cascade: enrollments, submissions, quiz_attempts, completions, component_scores

        return redirect()->to($backTo ?: route('teacher.index'))
            ->with('success', "Akun mahasiswa \"{$name}\" beserta seluruh riwayat belajarnya telah dihapus permanen dari platform.");
    }

    /** Pastikan mahasiswa ini (pernah) terdaftar di salah satu kelas milik dosen yang sedang login — termasuk yang sudah dikeluarkan. */
    private function assertOwnStudent(User $user): void
    {
        $teacherId = auth()->id();
        $ok = $user->allClasses()
            ->whereHas('course', fn ($q) => $q->where('teacher_id', $teacherId))
            ->exists();

        abort_unless($ok, 403, 'Mahasiswa ini tidak terdaftar di kelas Anda.');
    }

    public function csv(Kelas $class, Gradebook $gb): StreamedResponse
    {
        $this->ownClass($class);
        $rows = $gb->classRows($class);
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $class->code), '-');

        return response()->streamDownload(function () use ($rows, $class) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NIM', 'Nama', 'Email', 'Kelas', 'Pelajaran selesai', 'Total', 'Progres %', 'XP', 'Nilai Kuis', 'Nilai Tugas', 'Nilai Akhir']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['nim'], $r['name'], $r['email'], $class->name,
                    $r['done'], $r['total'], $r['percent'], $r['xp'],
                    $r['nilai_kuis'] ?? '', $r['nilai_tugas'] ?? '', $r['nilai_akhir'] ?? '',
                ]);
            }
            fclose($out);
        }, "nilai-{$slug}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
