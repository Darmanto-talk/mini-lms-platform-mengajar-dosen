<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\Teacher\ContentController;
use App\Http\Controllers\Teacher\CourseAdminController;
use App\Http\Controllers\Teacher\GradebookController;
use App\Http\Controllers\Teacher\GradeSchemeController;
use App\Http\Controllers\Teacher\GradingController;
use App\Http\Controllers\Teacher\ImportController;
use App\Http\Controllers\Teacher\StudentController;
use App\Http\Controllers\Teacher\StudentImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---------------- Mahasiswa ----------------
    Route::middleware('role:student')->group(function () {
        Route::post('/join', [DashboardController::class, 'join'])->name('join');

        Route::get('/course/{class}', [CourseController::class, 'show'])->name('course.show');

        Route::get('/lesson/{lesson}', [LessonController::class, 'show'])->name('lesson.show');
        Route::post('/lesson/{lesson}/complete', [LessonController::class, 'complete'])->name('lesson.complete');

        Route::get('/quiz/{lesson}', [QuizController::class, 'intro'])->name('quiz.intro');
        Route::post('/quiz/{lesson}/start', [QuizController::class, 'start'])->name('quiz.start');
        Route::get('/quiz/attempt/{attempt}', [QuizController::class, 'attempt'])->name('quiz.attempt');
        Route::post('/quiz/attempt/{attempt}/submit', [QuizController::class, 'submit'])->name('quiz.submit');
        Route::get('/quiz/result/{attempt}', [QuizController::class, 'result'])->name('quiz.result');

        Route::post('/assignment/{lesson}/submit', [AssignmentController::class, 'submit'])->name('assignment.submit');
    });

    // ---------------- Dosen ----------------
    Route::middleware('role:teacher')->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/', [CourseAdminController::class, 'index'])->name('index');
        Route::post('/courses', [CourseAdminController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}', [CourseAdminController::class, 'show'])->name('courses.show');
        Route::put('/courses/{course}', [CourseAdminController::class, 'update'])->name('courses.update');
        Route::delete('/courses/{course}', [CourseAdminController::class, 'destroy'])->name('courses.destroy');
        Route::get('/courses/{course}/export', [ImportController::class, 'export'])->name('courses.export');

        Route::get('/courses/{course}/grade-scheme', [GradeSchemeController::class, 'edit'])->name('scheme.edit');
        Route::put('/courses/{course}/grade-scheme', [GradeSchemeController::class, 'update'])->name('scheme.update');
        Route::get('/classes/{class}/gradebook', [GradebookController::class, 'show'])->name('gradebook.show');
        Route::post('/classes/{class}/gradebook', [GradebookController::class, 'save'])->name('gradebook.save');
        Route::get('/classes/{class}/gradebook.csv', [GradebookController::class, 'csv'])->name('gradebook.csv');

        Route::get('/import', [ImportController::class, 'form'])->name('import.form');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        Route::post('/upload', [ContentController::class, 'uploadImage'])->name('upload');
        Route::post('/upload-file', [ContentController::class, 'uploadFile'])->name('upload.file');

        Route::post('/courses/{course}/classes', [CourseAdminController::class, 'storeClass'])->name('classes.store');
        Route::put('/classes/{class}', [CourseAdminController::class, 'renameClass'])->name('classes.rename');
        Route::delete('/classes/{class}', [CourseAdminController::class, 'destroyClass'])->name('classes.destroy');

        Route::post('/courses/{course}/weeks', [ContentController::class, 'saveWeek'])->name('weeks.save');
        Route::delete('/weeks/{week}', [ContentController::class, 'destroyWeek'])->name('weeks.destroy');
        Route::post('/weeks/{week}/toggle', [ContentController::class, 'toggleWeek'])->name('weeks.toggle');
        Route::post('/courses/{course}/weeks/bulk', [ContentController::class, 'bulkWeeks'])->name('weeks.bulk');

        Route::get('/weeks/{week}/lessons/create', [ContentController::class, 'createLesson'])->name('lessons.create');
        Route::post('/lessons', [ContentController::class, 'storeLesson'])->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [ContentController::class, 'editLesson'])->name('lessons.edit');
        Route::put('/lessons/{lesson}', [ContentController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [ContentController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::get('/lessons/{lesson}/preview', [ContentController::class, 'preview'])->name('lessons.preview');
        Route::get('/lessons/{lesson}/present', [ContentController::class, 'present'])->name('lessons.present');

        Route::put('/quizzes/{quiz}', [ContentController::class, 'updateQuiz'])->name('quizzes.update');
        Route::post('/quizzes/{quiz}/questions', [ContentController::class, 'saveQuestion'])->name('questions.save');
        Route::delete('/questions/{question}', [ContentController::class, 'destroyQuestion'])->name('questions.destroy');

        Route::get('/classes/{class}/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/classes/{class}/students/{user}', [StudentController::class, 'show'])->name('students.show');
        Route::delete('/classes/{class}/students/{user}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('/classes/{class}/students/{user}/reset-password', [StudentController::class, 'resetPassword'])->name('students.reset');
        Route::post('/classes/{class}/students/{user}/rejoin', [StudentController::class, 'rejoin'])->name('students.rejoin');
        Route::delete('/students/{user}', [StudentController::class, 'destroyAccount'])->name('students.account');
        Route::get('/classes/{class}/grades.csv', [StudentController::class, 'csv'])->name('grades.csv');

        Route::get('/classes/{class}/students-import', [StudentImportController::class, 'form'])->name('students.import.form');
        Route::get('/classes/{class}/students-import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::post('/classes/{class}/students-import', [StudentImportController::class, 'store'])->name('students.import.store');

        Route::get('/classes/{class}/monitor', [StudentController::class, 'monitor'])->name('monitor');
        Route::get('/classes/{class}/lessons/{lesson}/grade', [GradingController::class, 'index'])->name('grade.index');
        Route::post('/classes/{class}/lessons/{lesson}/grade', [GradingController::class, 'store'])->name('grade.store');
        Route::get('/classes/{class}/lessons/{lesson}/quiz-results', [GradingController::class, 'quizResults'])->name('quiz.results');
        Route::post('/submissions/{submission}/ai-grade', [GradingController::class, 'aiGrade'])->name('grade.ai');
        Route::post('/attempts/{attempt}/ai-review', [GradingController::class, 'aiReview'])->name('attempt.ai');
    });
});

require __DIR__.'/auth.php';
