<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\Kelas;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Week;
use Illuminate\Support\Facades\Auth;

trait TeacherAccess
{
    protected function ownCourse(Course $course): Course
    {
        abort_unless($course->teacher_id === Auth::id(), 403, 'Mata kuliah ini bukan milik Anda.');

        return $course;
    }

    protected function ownClass(Kelas $class): Kelas
    {
        $class->loadMissing('course');
        abort_unless($class->course->teacher_id === Auth::id(), 403);

        return $class;
    }

    protected function ownWeek(Week $week): Week
    {
        $week->loadMissing('course');
        abort_unless($week->course->teacher_id === Auth::id(), 403);

        return $week;
    }

    protected function ownLesson(Lesson $lesson): Lesson
    {
        $lesson->loadMissing('week.course');
        abort_unless($lesson->week->course->teacher_id === Auth::id(), 403);

        return $lesson;
    }

    protected function ownQuiz(Quiz $quiz): Quiz
    {
        $quiz->loadMissing('lesson.week.course');
        abort_unless($quiz->lesson->week->course->teacher_id === Auth::id(), 403);

        return $quiz;
    }
}
