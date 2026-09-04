<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeSchemeController extends Controller
{
    use TeacherAccess;

    public function edit(Course $course)
    {
        $this->ownCourse($course);

        $lessons = \App\Models\Lesson::whereHas('week', fn ($q) => $q->where('course_id', $course->id))
            ->whereIn('type', ['kuis', 'tugas'])
            ->with('week:id,number')
            ->get(['id', 'week_id', 'title', 'type'])
            ->sortBy(fn ($l) => [$l->week->number, $l->position])
            ->values();

        return view('teacher.grade_scheme', [
            'course' => $course,
            'components' => $course->gradeComponents()->get(),
            'bands' => $course->bands(),
            'lessons' => $lessons,
        ]);
    }

    public function update(Request $request, Course $course)
    {
        $this->ownCourse($course);

        $data = $request->validate([
            'pass_min'       => ['required', 'integer', 'min:0', 'max:100'],
            'publish'        => ['nullable'],
            'comp_id'        => ['array'],
            'comp_id.*'      => ['nullable', 'integer'],
            'comp_name'      => ['array'],
            'comp_name.*'    => ['nullable', 'string', 'max:120'],
            'comp_weight'    => ['array'],
            'comp_weight.*'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comp_source'    => ['array'],
            'comp_source.*'  => ['nullable', 'in:manual,progress,quiz_avg,tugas_avg,lesson'],
            'comp_lesson'    => ['array'],
            'comp_lesson.*'  => ['nullable', 'integer'],
            'band_min'       => ['array'],
            'band_min.*'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'band_letter'    => ['array'],
            'band_letter.*'  => ['nullable', 'string', 'max:5'],
        ]);

        DB::transaction(function () use ($course, $data, $request) {
            // komponen
            $keepIds = [];
            foreach (($data['comp_name'] ?? []) as $i => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $source = $data['comp_source'][$i] ?? 'manual';
                $attrs = [
                    'name'      => $name,
                    'weight'    => (float) ($data['comp_weight'][$i] ?? 0),
                    'source'    => $source,
                    'lesson_id' => $source === 'lesson' ? ($data['comp_lesson'][$i] ?? null) : null,
                    'position'  => $i,
                ];
                $id = $data['comp_id'][$i] ?? null;
                if ($id) {
                    $course->gradeComponents()->whereKey($id)->update($attrs);
                    $keepIds[] = $id;
                } else {
                    $keepIds[] = $course->gradeComponents()->create($attrs)->id;
                }
            }
            $course->gradeComponents()->whereNotIn('id', $keepIds ?: [0])->delete();

            // grade bands
            $bands = [];
            foreach (($data['band_letter'] ?? []) as $i => $letter) {
                $letter = trim((string) $letter);
                if ($letter === '') {
                    continue;
                }
                $bands[] = ['min' => (int) ($data['band_min'][$i] ?? 0), 'letter' => $letter];
            }
            usort($bands, fn ($a, $b) => $b['min'] <=> $a['min']);

            $course->update([
                'pass_min' => $data['pass_min'],
                'grade_bands' => $bands ?: null,
                'grades_published' => $request->boolean('publish'),
            ]);
        });

        return back()->with('success', 'Komposisi nilai disimpan.');
    }
}
