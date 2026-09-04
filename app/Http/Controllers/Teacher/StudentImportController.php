<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Services\StudentImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentImportController extends Controller
{
    use TeacherAccess;

    /** Halaman: unduh template + unggah berkas. */
    public function form(Kelas $class)
    {
        $this->ownClass($class);

        return view('teacher.students_import', ['class' => $class]);
    }

    /** Unduh template Excel baku. */
    public function template(Kelas $class, StudentImporter $importer)
    {
        $this->ownClass($class);

        $path = storage_path('app/private/template-mahasiswa-'.Str::random(8).'.xlsx');
        @mkdir(dirname($path), 0775, true);
        $importer->writeTemplate($path);

        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $class->course->name.' '.$class->name), '-');

        return response()->download($path, "template-mahasiswa-{$slug}.xlsx")->deleteFileAfterSend(true);
    }

    /** Proses berkas yang diunggah, lalu tampilkan hasilnya (termasuk sandi sementara). */
    public function store(Request $request, Kelas $class, StudentImporter $importer)
    {
        $this->ownClass($class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls,csv'],
        ], [
            'file.required' => 'Pilih berkas Excel yang berisi data mahasiswa.',
            'file.extensions' => 'Format berkas harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran berkas melebihi 5 MB.',
        ]);

        try {
            $rows = $importer->readRows($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'Berkas tidak bisa dibaca: '.$e->getMessage());
        }

        if ($rows === []) {
            return back()->with('error', 'Tidak ada baris data yang terbaca. Pastikan judul kolom di baris pertama tidak diubah dan data diisi mulai baris kedua.');
        }

        $results = $importer->import($class, $rows);

        return view('teacher.students_import_result', [
            'class' => $class,
            'results' => $results,
            'ringkas' => [
                'dibuat' => collect($results)->where('status', 'dibuat')->count(),
                'didaftarkan' => collect($results)->where('status', 'didaftarkan')->count(),
                'dipulihkan' => collect($results)->where('status', 'dipulihkan')->count(),
                'sudah' => collect($results)->where('status', 'sudah')->count(),
                'bentrok' => collect($results)->where('status', 'bentrok')->count(),
                'error' => collect($results)->where('status', 'error')->count(),
            ],
        ]);
    }
}
