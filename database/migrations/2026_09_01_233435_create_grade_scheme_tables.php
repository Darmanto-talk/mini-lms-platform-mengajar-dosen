<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pengaturan grade & kelulusan per mata kuliah
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('pass_min')->default(60);          // nilai minimal lulus
            $table->json('grade_bands')->nullable();                   // [{min, letter}, ...]
            $table->boolean('grades_published')->default(false);       // tampilkan ke mahasiswa
        });

        // komponen penilaian (Aktivitas, Learning Skill, Presentasi, dst.)
        Schema::create('grade_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('weight', 5, 2)->default(0);   // persen, mis. 24.00
            // manual = dosen isi; progress = % penyelesaian materi;
            // quiz_avg / tugas_avg = rata-rata; lesson = 1 kuis/tugas tertentu
            $table->enum('source', ['manual', 'progress', 'quiz_avg', 'tugas_avg', 'lesson'])->default('manual');
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
        });

        // nilai per mahasiswa per komponen (untuk source = manual)
        Schema::create('component_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('grade_components')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();    // 0..100
            $table->timestamps();
            $table->unique(['component_id', 'user_id']);
            $table->index(['class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_scores');
        Schema::dropIfExists('grade_components');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['pass_min', 'grade_bands', 'grades_published']);
        });
    }
};
