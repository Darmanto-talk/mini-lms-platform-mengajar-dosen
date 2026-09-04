# Mini-LMS — Platform Mengajar Dosen

Mini Learning Management System (LMS) berbasis **Laravel 13** dan **MySQL** yang dikembangkan
untuk mendukung kebutuhan dosen dalam mengelola materi, latihan, dan aktivitas pembelajaran
mahasiswa secara terintegrasi selama satu semester.

Kurikulum contoh yang disertakan: mata kuliah **Algoritma dan Pemrograman** (bahasa **Dart**).
Dibangun dengan Laravel 13 + **Breeze** (auth) + MySQL, dengan **pemeriksaan tugas & kuis
otomatis oleh AI (Claude / Anthropic)**.

> Versi PHP-polos sebelumnya tetap ada di folder `../mini-lms-ap` sebagai cadangan.

## Fitur

**Mahasiswa**
- Registrasi mandiri dengan **kode kelas** dari dosen
- Halaman baca materi bergaya modul (navigator pelajaran di kiri)
- Hanya bisa membuka minggu yang **sudah dibuka dosen**
- Kuis pilihan ganda (timer, batas percobaan, nilai lulus, pembahasan)
- **Penjelasan AI** untuk jawaban kuis yang salah
- **Tugas**: kumpulkan teks / tautan / berkas → dinilai AI → ditinjau dosen

**Dosen**
- Kelola mata kuliah, kelas (kode gabung otomatis), minggu, pelajaran
- Tipe pelajaran: materi / video / latihan (DartPad) / **tugas** / kuis
- Buka/tutup minggu (per minggu atau semua), pratinjau pelajaran
- **Impor materi dari JSON** (mata kuliah baru atau *perbarui* — progres & nilai
  mahasiswa dipertahankan) + **Ekspor JSON**
- Editor soal kuis, unggah gambar materi
- Tabel nilai per kelas: **Nilai Kuis + Nilai Tugas + Nilai Akhir**, ekspor CSV
- **Monitor pengerjaan** — daftar tiap kuis & tugas: siapa yang sudah/belum mengerjakan
- **Hasil & jawaban kuis** — per kuis: nilai tiap mahasiswa + rincian jawaban tiap soal
- Rincian progres per mahasiswa
- **Pemeriksaan tugas oleh AI**: nilai + umpan balik + rincian per aspek rubrik;
  dosen meninjau / menimpa sebelum nilai final

## Pemasangan (Laragon)

```bash
# 1. dependensi (sudah dijalankan)
composer install

# 2. konfigurasi
cp .env.example .env        # lalu sesuaikan DB & (opsional) ANTHROPIC_API_KEY
php artisan key:generate

# 3. database (Laravel membuat database "lms_ap" sendiri)
php artisan migrate --seed
```

Akses lewat `http://lms-ap.test` (virtual host otomatis Laragon).

**Akun dosen contoh:** `dosen@kampus.ac.id` / `rahasia123`
Kode gabung Kelas A/B/C tampil di akhir output `migrate --seed`, atau di
Panel Dosen → Kelola materi.

### Menambah akun dosen

```bash
php artisan lms:make-teacher "Nama Dosen" email@kampus.ac.id katasandi
```

## Pemeriksaan otomatis oleh AI

Isi `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...        # dari https://console.anthropic.com — kosong = fitur AI mati
ANTHROPIC_MODEL=claude-opus-5       # untuk biaya lebih hemat: claude-sonnet-4-5
AI_AUTO_GRADE_TUGAS=true            # nilai tugas otomatis saat dikumpulkan
AI_AUTO_REVIEW_KUIS=true            # AI menjelaskan jawaban kuis yang salah
```

- Saat mahasiswa mengumpulkan tugas, AI langsung menilai berdasarkan **rubrik**
  yang dosen tulis di editor pelajaran (kolom "Rubrik penilaian AI"). Semakin
  jelas rubrik → nilai makin objektif & konsisten.
- Nilai AI menjadi **nilai sementara**. Dosen membuka halaman **Nilai** →
  meninjau umpan balik AI → menyimpan **nilai final** (mengunci pengumpulan).
- Dosen juga bisa memicu AI manual lewat tombol **"Periksa dengan AI"**.
- Tanpa API key, semua penilaian dilakukan dosen secara manual seperti biasa.

### Kinerja / antrean

Default `.env` memakai `QUEUE_CONNECTION=sync` — AI berjalan langsung saat
pengumpulan (mahasiswa menunggu ±20 detik). Untuk kelas besar, ganti ke
`QUEUE_CONNECTION=database` lalu jalankan worker:

```bash
php artisan queue:work
```

Sebagai cadangan, penjadwal Laravel menjalankan `lms:grade-pending` tiap 5 menit
(aktifkan dengan satu baris cron: `* * * * * cd /path && php artisan schedule:run`).

## Format JSON materi

Sama seperti versi sebelumnya, plus kolom `rubric` per pelajaran tugas.
Contoh: `contoh-materi-algoritma-dart.json`, `materi-pertemuan-1.json` di folder ini.

```jsonc
{
  "name": "Algoritma dan Pemrograman",
  "weeks": [
    { "number": 1, "title": "...", "is_open": true, "lessons": [
      { "title": "...", "type": "materi|video|latihan|tugas|kuis", "xp": 10,
        "content": "teks + ![img](storage/uploads/a.png) + link youtube + ```dart ... ```",
        "dartpad_gist": "e75b493dae...",
        "rubric": "Kelengkapan 40, ketepatan algoritma 40, kejelasan 20",
        "max_score": 100,
        "quiz": { "time_limit_min": 15, "pass_score": 70, "questions": [
          { "text": "...", "code": "...", "options": ["a","b"], "answer": 1 } ] } }
    ] }
  ]
}
```

## Struktur (ringkas)

```
app/Models/            Course, Kelas, Week, Lesson, Quiz, Question, Option,
                       QuizAttempt, AttemptAnswer, AssignmentSubmission, ...
app/Services/          CourseImporter (impor/ekspor JSON), Gradebook (nilai/XP),
                       AiGrader (panggilan Claude)
app/Jobs/              GradeAssignmentWithAi, ReviewQuizAttemptWithAi
app/Support/           LessonContent (render markdown materi)
app/Http/Controllers/  Course/Lesson/Quiz/Assignment + Teacher/*
app/Console/Commands/   lms:make-teacher, lms:grade-pending
database/migrations/   0001_… users (+role,nim), 2026_… semua tabel LMS
database/seeders/       impor curriculum-dart.json + Kelas A/B/C
resources/views/       layouts/, auth/, student/, teacher/
public/css/app.css     tema Mini-LMS (tanpa build — dilink langsung)
```
