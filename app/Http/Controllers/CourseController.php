<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StudentAccess;
use App\Models\Kelas;
use App\Services\Gradebook;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    use StudentAccess;

    public function show(Request $request, Kelas $class, Gradebook $gb)
    {
        $class = $this->enrolledClass($class);
        $user = $request->user();
        $course = $class->course->load('weeks.lessons');

        $doneSet = array_flip($gb->completedLessonIds($course->id, $user->id));
        $unlockedSet = array_flip($gb->unlockedLessonIds($course, $user->id));

        $doneOpen = 0;
        $totalOpen = 0;
        $continueId = null;
        foreach ($course->weeks as $w) {
            if (! $w->is_open) {
                continue;
            }
            foreach ($w->lessons as $l) {
                $totalOpen++;
                if (isset($doneSet[$l->id])) {
                    $doneOpen++;
                } elseif ($continueId === null) {
                    $continueId = $l->id;
                }
            }
        }

        $final = $course->grades_published ? $gb->weightedFinal($class, $user->id) : null;

        return view('student.course', [
            'user'       => $user,
            'class'      => $class,
            'course'     => $course,
            'doneSet'    => $doneSet,
            'unlockedSet' => $unlockedSet,
            'doneCount'  => $doneOpen,
            'total'      => $totalOpen,
            'openCount'  => $course->weeks->where('is_open', true)->count(),
            'xp'         => $gb->userXp($course->id, $user->id),
            'board'      => $gb->leaderboard($class),
            'continueId' => $continueId,
            'final'      => $final,
        ]);
    }
}
