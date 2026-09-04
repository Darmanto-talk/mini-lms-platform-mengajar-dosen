<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Kelas;
use Illuminate\Http\Request;

class CourseAdminController extends Controller
{
    use TeacherAccess;

    public function index(Request $request)
    {
        $courses = $request->user()->courses()->latest()->get()->map(function (Course $c) {
            $c->classes_list = $c->classes()->withCount('enrollments')->orderBy('name')->get();
            $c->week_count = $c->weeks()->count();
            $c->lesson_count = $c->totalLessons();
            return $c;
        });

        return view('teacher.dashboard', compact('courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string'],
        ]);

        $course = $request->user()->courses()->create($data);
        $course->classes()->create(['code' => Kelas::generateCode(), 'name' => 'Kelas A']);

        return redirect()->route('teacher.courses.show', $course)->with('success', 'Mata kuliah dibuat beserta "Kelas A".');
    }

    public function show(Course $course)
    {
        $this->ownCourse($course);
        $course->load('weeks.lessons');
        $classes = $course->classes()->withCount('enrollments')->orderBy('name')->get();

        return view('teacher.course', compact('course', 'classes'));
    }

    public function update(Request $request, Course $course)
    {
        $this->ownCourse($course);
        $course->update($request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Info mata kuliah diperbarui.');
    }

    public function destroy(Request $request, Course $course)
    {
        $this->ownCourse($course);
        $request->validate(
            ['confirm' => ['required', 'string']],
            ['confirm.required' => 'Ketik nama mata kuliah untuk konfirmasi.'],
        );
        if (mb_strtolower(trim($request->input('confirm'))) !== mb_strtolower($course->name)) {
            return back()->withErrors(['confirm' => 'Nama yang diketik tidak cocok. Mata kuliah tidak dihapus.']);
        }

        $name = $course->name;
        $course->delete(); // cascade: kelas, minggu, pelajaran, kuis, tugas, nilai, enrollment

        return redirect()->route('teacher.index')->with('success',
            "Mata kuliah \"{$name}\" beserta seluruh minggu, pelajaran, kelas, dan data mahasiswanya telah dihapus permanen.");
    }

    public function storeClass(Request $request, Course $course)
    {
        $this->ownCourse($course);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $code = Kelas::generateCode();
        $course->classes()->create(['name' => $data['name'], 'code' => $code]);

        return back()->with('success', "Kelas \"{$data['name']}\" dibuat. Kode gabung: {$code}");
    }

    public function renameClass(Request $request, Kelas $class)
    {
        $this->ownClass($class);
        $class->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back()->with('success', 'Nama kelas diperbarui.');
    }

    public function destroyClass(Kelas $class)
    {
        $this->ownClass($class);
        if ($class->course->classes()->count() <= 1) {
            return back()->with('error', 'Tidak bisa menghapus kelas terakhir pada mata kuliah.');
        }
        $course = $class->course;
        $class->delete();

        return redirect()->route('teacher.courses.show', $course)->with('success', 'Kelas dihapus beserta data progresnya.');
    }
}
