<?php

namespace App\Providers;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Kelas;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Week;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nama mata kuliah aktif → dipakai untuk merk di header & footer.
        View::composer('layouts.app', function ($view) {
            $view->with('brandCourse', $this->currentCourse());
        });
    }

    /** Mata kuliah yang sedang dibuka (dari parameter route, atau kelas mahasiswa). */
    private function currentCourse(): ?Course
    {
        $route = request()->route();

        foreach ($route?->parameters() ?? [] as $param) {
            $course = match (true) {
                $param instanceof Course => $param,
                $param instanceof Kelas => $param->course,
                $param instanceof Week => $param->course,
                $param instanceof Lesson => $param->week?->course,
                $param instanceof Quiz => $param->lesson?->week?->course,
                $param instanceof QuizAttempt => $param->quiz?->lesson?->week?->course,
                $param instanceof AssignmentSubmission => $param->lesson?->week?->course,
                default => null,
            };
            if ($course) {
                return $course;
            }
        }

        // Mahasiswa: seluruh aplikasi = satu mata kuliah.
        $user = auth()->user();
        if ($user && $user->isStudent()) {
            return $user->classes()->with('course')->first()?->course;
        }

        return null;
    }
}
