<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CourseImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::firstOrCreate(
            ['email' => 'dosen@kampus.ac.id'],
            ['role' => 'teacher', 'name' => 'Dr. Dosen Contoh', 'password' => Hash::make('rahasia123')],
        );

        if ($teacher->courses()->exists()) {
            $this->command->info('Mata kuliah contoh sudah ada — dilewati.');
            return;
        }

        $json = json_decode(file_get_contents(database_path('data/curriculum-dart.json')), true);
        $res = app(CourseImporter::class)->import($json, $teacher);
        $course = $res['course'];

        // Kelas B & C
        $course->classes()->createMany([
            ['code' => \App\Models\Kelas::generateCode(), 'name' => 'Kelas B'],
            ['code' => \App\Models\Kelas::generateCode(), 'name' => 'Kelas C'],
        ]);

        // buka Minggu 1 & 2 sebagai contoh
        $course->weeks()->where('number', '<=', 2)->update(['is_open' => true]);

        // komposisi nilai akhir contoh (total 100%) — sebagian otomatis dari aktivitas LMS
        $components = [
            ['Aktivitas', 6, 'progress'],              // % membaca materi (otomatis)
            ['Learning Skill', 24, 'quiz_avg'],         // rata-rata kuis (otomatis)
            ['Life Skills', 16, 'manual'],
            ['Literacy Skills', 9, 'manual'],
            ['Presentasi Mid Semester', 12, 'manual'],
            ['Presentasi Akhir Semester', 12, 'manual'],
            ['Laporan', 9, 'tugas_avg'],                // rata-rata tugas (otomatis)
            ['Pemahaman Konsep', 3, 'manual'],
            ['Kualitas Produk', 9, 'manual'],
        ];
        foreach ($components as $i => [$name, $weight, $source]) {
            $course->gradeComponents()->create(compact('name', 'weight', 'source') + ['position' => $i]);
        }
        $course->update(['pass_min' => 60]); // grade_bands memakai default

        $this->command->info('Akun dosen: dosen@kampus.ac.id / rahasia123');
        foreach ($course->classes()->get() as $c) {
            $this->command->info("  {$c->name}: kode gabung {$c->code}");
        }
    }
}
