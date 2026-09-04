<?php

namespace App\Support;

/**
 * Kata sandi sementara yang mudah dibacakan/diketik ulang oleh mahasiswa,
 * tetapi tetap acak. Dipakai saat dosen menyetel ulang sandi satu mahasiswa
 * maupun saat impor massal dari Excel.
 */
class TempPassword
{
    private const WORDS = ['belajar', 'materi', 'kampus', 'rajin', 'tugas', 'kuis', 'kelas', 'dosen', 'jaya', 'pintar'];

    public static function make(): string
    {
        return ucfirst(self::WORDS[array_rand(self::WORDS)]).'-'.random_int(1000, 9999);
    }
}
