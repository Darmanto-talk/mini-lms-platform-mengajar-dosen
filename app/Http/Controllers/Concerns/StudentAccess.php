<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Kelas;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;

trait StudentAccess
{
    protected function enrolledClass(Kelas $class): Kelas
    {
        abort_unless(
            Auth::user()->classes()->where('classes.id', $class->id)->exists(),
            403,
            'Anda belum terdaftar di kelas ini.'
        );

        return $class->loadMissing('course');
    }

    /** Kelas mahasiswa untuk course sebuah pelajaran (atau 403). */
    protected function classForLesson(Lesson $lesson): Kelas
    {
        $courseId = $lesson->week->course_id;
        $class = Auth::user()->classes()->where('course_id', $courseId)->with('course')->first();

        abort_if($class === null, 403, 'Anda belum terdaftar di kelas mata kuliah ini.');

        return $class;
    }
}
