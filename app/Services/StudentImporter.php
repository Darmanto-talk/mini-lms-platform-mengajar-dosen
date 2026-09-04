<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Kelas;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Impor daftar mahasiswa ke sebuah kelas dari berkas Excel.
 *
 * Kolom baku: Nama | Email | NIM | Kata Sandi (opsional)
 * Kolom "Kata Sandi" boleh dikosongkan — sistem membuatkan sandi sementara
 * yang ditampilkan sekali ke dosen untuk dibagikan ke mahasiswa.
 */
class StudentImporter
{
    public const HEADERS = ['Nama', 'Email', 'NIM', 'Kata Sandi'];

    /** Tulis berkas template .xlsx ke path sementara, kembalikan path-nya. */
    public function writeTemplate(string $path): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Data Mahasiswa');

        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray([
            ['Budi Santoso', 'budi.santoso@example.com', '2024010001', ''],
            ['Siti Aminah', 'siti.aminah@example.com', '2024010002', 'SandiPilihan123'],
        ], null, 'A2');

        // gaya baris judul
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7EDF2');
        $sheet->getStyle('A1:D1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        foreach (['A' => 26, 'B' => 34, 'C' => 18, 'D' => 22] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->getStyle('C')->getNumberFormat()->setFormatCode('@'); // NIM tetap teks

        // catatan pemakaian di bawah contoh
        $sheet->setCellValue('A5', 'Petunjuk:');
        $sheet->setCellValue('A6', '1. Hapus dua baris contoh di atas sebelum mengisi data asli.');
        $sheet->setCellValue('A7', '2. Nama dan Email wajib diisi. Email harus unik (belum dipakai akun lain).');
        $sheet->setCellValue('A8', '3. NIM boleh dikosongkan.');
        $sheet->setCellValue('A9', '4. Kata Sandi boleh dikosongkan — sistem akan membuatkan sandi sementara otomatis (minimal 8 karakter bila diisi).');
        $sheet->setCellValue('A10', '5. Jangan mengubah judul kolom di baris 1.');
        $sheet->getStyle('A5')->getFont()->setBold(true);

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /**
     * Baca berkas Excel/CSV → daftar baris ternormalisasi.
     *
     * @return array<int, array{nama:string, email:string, nim:string, sandi:string, baris:int}>
     */
    public function readRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];

        foreach ($sheet->toArray(null, true, false, false) as $i => $raw) {
            $line = $i + 1;
            if ($line === 1) {
                continue;   // baris judul
            }

            $nama = trim((string) ($raw[0] ?? ''));
            $email = trim((string) ($raw[1] ?? ''));
            $nim = trim((string) ($raw[2] ?? ''));
            $sandi = trim((string) ($raw[3] ?? ''));

            // lewati baris kosong & baris petunjuk template
            if ($nama === '' && $email === '') {
                continue;
            }
            if ($email === '' && preg_match('/^(petunjuk|\d+\.)/i', $nama)) {
                continue;
            }

            $rows[] = ['nama' => $nama, 'email' => mb_strtolower($email), 'nim' => $nim, 'sandi' => $sandi, 'baris' => $line];
        }

        return $rows;
    }

    /**
     * Proses baris-baris tadi: buat akun bila perlu, lalu daftarkan ke kelas.
     * Tiap baris berdiri sendiri — satu baris gagal tidak membatalkan sisanya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function import(Kelas $class, array $rows): array
    {
        $courseId = $class->course_id;
        $results = [];
        $seenEmail = [];

        foreach ($rows as $row) {
            $res = $row + ['status' => 'error', 'pesan' => '', 'sandi_baru' => null];

            $v = Validator::make($row, [
                'nama' => ['required', 'string', 'min:3', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'nim' => ['nullable', 'string', 'max:30'],
                'sandi' => ['nullable', 'string', 'min:8', 'max:100'],
            ], [
                'nama.required' => 'Nama wajib diisi.',
                'nama.min' => 'Nama minimal 3 karakter.',
                'email.required' => 'Email wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'sandi.min' => 'Kata sandi minimal 8 karakter.',
            ]);

            if ($v->fails()) {
                $res['pesan'] = implode(' ', $v->errors()->all());
                $results[] = $res;

                continue;
            }

            if (isset($seenEmail[$row['email']])) {
                $res['pesan'] = 'Email ini muncul lebih dari sekali di berkas (baris '.$seenEmail[$row['email']].').';
                $results[] = $res;

                continue;
            }
            $seenEmail[$row['email']] = $row['baris'];

            try {
                $results[] = $this->handleRow($class, $courseId, $row, $res);
            } catch (\Throwable $e) {
                $res['pesan'] = 'Gagal diproses: '.$e->getMessage();
                $results[] = $res;
            }
        }

        return $results;
    }

    /** Satu baris: cari/buat akun, lalu urus pendaftaran kelasnya. */
    private function handleRow(Kelas $class, int $courseId, array $row, array $res): array
    {
        $user = User::where('email', $row['email'])->first();

        if ($user && ! $user->isStudent()) {
            $res['pesan'] = 'Email ini dipakai akun dosen — dilewati.';

            return $res;
        }

        if (! $user) {
            $sandi = $row['sandi'] !== '' ? $row['sandi'] : TempPassword::make();
            $user = User::create([
                'role' => 'student',
                'name' => $row['nama'],
                'nim' => $row['nim'] !== '' ? $row['nim'] : null,
                'email' => $row['email'],
                'password' => $sandi,           // cast 'hashed' meng-hash otomatis
            ]);
            $res['sandi_baru'] = $sandi;
            $baru = true;
        } else {
            // akun sudah ada: lengkapi NIM bila sebelumnya kosong, sandi TIDAK diubah
            if ($row['nim'] !== '' && blank($user->nim)) {
                $user->update(['nim' => $row['nim']]);
            }
            $baru = false;
        }

        $res['user_id'] = $user->id;

        // ---- pendaftaran kelas (aturan: 1 kelas aktif per mata kuliah) ----
        if ($class->students()->where('users.id', $user->id)->exists()) {
            $res['status'] = 'sudah';
            $res['pesan'] = $baru ? 'Akun dibuat, sudah terdaftar di kelas ini.' : 'Sudah terdaftar di kelas ini.';

            return $res;
        }

        $lain = $user->classes()->where('course_id', $courseId)->first();
        if ($lain) {
            $res['status'] = 'bentrok';
            $res['pesan'] = 'Sudah aktif di kelas "'.$lain->name.'" pada mata kuliah ini — keluarkan dulu dari sana.';

            return $res;
        }

        $lama = $class->enrollments()->where('user_id', $user->id)->first();
        if ($lama) {
            $lama->update(['removed_at' => null]);
            $res['status'] = 'dipulihkan';
            $res['pesan'] = 'Pernah dikeluarkan — didaftarkan kembali, riwayat lamanya kembali.';

            return $res;
        }

        Enrollment::create(['class_id' => $class->id, 'user_id' => $user->id]);
        $res['status'] = $baru ? 'dibuat' : 'didaftarkan';
        $res['pesan'] = $baru
            ? 'Akun baru dibuat & didaftarkan ke kelas.'
            : 'Akun sudah ada sebelumnya — didaftarkan ke kelas (sandi tidak diubah).';

        return $res;
    }
}
