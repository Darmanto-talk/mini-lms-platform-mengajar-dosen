<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->unique(['class_id', 'user_id']);
        });

        Schema::create('weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->boolean('is_exam')->default(false);
            $table->boolean('is_open')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->unique(['course_id', 'number']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_id')->constrained('weeks')->cascadeOnDelete();
            $table->string('title', 200);
            $table->enum('type', ['materi', 'video', 'latihan', 'tugas', 'kuis'])->default('materi');
            $table->mediumText('content')->nullable();
            $table->string('video_url', 400)->nullable();
            $table->string('dartpad_gist', 64)->nullable();
            $table->text('rubric')->nullable();          // kriteria penilaian AI (tugas)
            $table->unsignedInteger('max_score')->default(100);
            $table->unsignedInteger('xp')->default(10);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();
            $table->unique(['lesson_id', 'user_id']);
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete()->unique();
            $table->string('title', 200);
            $table->unsignedInteger('time_limit_min')->default(15);
            $table->unsignedInteger('pass_score')->default(70);
            $table->unsignedInteger('max_attempts')->default(2);
            $table->unsignedInteger('xp')->default(50);
            $table->boolean('ai_feedback')->default(true); // AI menjelaskan jawaban salah
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('text');
            $table->text('code_snippet')->nullable();
            $table->unsignedInteger('position')->default(0);
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('text', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->longText('ai_feedback')->nullable();
            $table->enum('ai_status', ['none', 'pending', 'processing', 'done', 'failed'])->default('none');
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('options')->nullOnDelete();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->mediumText('text')->nullable();
            $table->string('file_path', 300)->nullable();
            $table->string('link_url', 400)->nullable();
            $table->timestamp('submitted_at')->useCurrent();

            // penilaian akhir (dosen — bisa mengambil dari AI atau menimpa)
            $table->unsignedInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();

            // penilaian AI
            $table->enum('ai_status', ['none', 'pending', 'processing', 'done', 'failed'])->default('none');
            $table->unsignedInteger('ai_score')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->json('ai_breakdown')->nullable();
            $table->string('ai_model', 80)->nullable();
            $table->timestamp('ai_graded_at')->nullable();
            $table->text('ai_error')->nullable();

            $table->unique(['lesson_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('weeks');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('courses');
    }
};
