<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\TeacherAccess;
use App\Http\Controllers\Controller;
use App\Models\ComponentScore;
use App\Models\Kelas;
use App\Services\Gradebook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradebookController extends Controller
{
    use TeacherAccess;

    public function show(Kelas $class, Gradebook $gb)
    {
        $this->ownClass($class);

        return view('teacher.gradebook', [
            'class' => $class,
            'book'  => $gb->classGradebook($class),
        ]);
    }

    public function save(Request $request, Kelas $class)
    {
        $this->ownClass($class);
        $compIds = $class->course->gradeComponents()->where('source', 'manual')->pluck('id')->all();

        // score[componentId][userId] = nilai
        $matrix = (array) $request->input('score', []);
        $studentIds = $class->students()->pluck('users.id')->all();

        foreach ($matrix as $compId => $perUser) {
            if (! in_array((int) $compId, $compIds, true)) {
                continue;
            }
            foreach ((array) $perUser as $uid => $val) {
                if (! in_array((int) $uid, $studentIds, true)) {
                    continue;
                }
                $val = trim((string) $val);
                if ($val === '') {
                    ComponentScore::where(['component_id' => $compId, 'user_id' => $uid])->delete();
                    continue;
                }
                $score = max(0, min(100, (float) str_replace(',', '.', $val)));
                ComponentScore::updateOrCreate(
                    ['component_id' => $compId, 'user_id' => $uid],
                    ['class_id' => $class->id, 'score' => $score],
                );
            }
        }

        return back()->with('success', 'Nilai komponen disimpan.');
    }

    public function csv(Kelas $class, Gradebook $gb): StreamedResponse
    {
        $this->ownClass($class);
        $book = $gb->classGradebook($class);
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $class->code), '-');

        return response()->streamDownload(function () use ($book, $class) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $head = ['No', 'NIM', 'Nama'];
            foreach ($book['components'] as $c) {
                $head[] = $c->name.' ('.rtrim(rtrim(number_format($c->weight, 2), '0'), '.').'%)';
            }
            $head = array_merge($head, ['Nilai Akhir', 'Grade', 'Lulus']);
            fputcsv($out, $head);

            foreach ($book['rows'] as $i => $r) {
                $line = [$i + 1, $r['nim'], $r['name']];
                foreach ($book['components'] as $c) {
                    $line[] = $r['cells'][$c->id] ?? '';
                }
                $line[] = $r['nilai'] ?? '';
                $line[] = $r['grade'];
                $line[] = $r['lulus'] === null ? '' : ($r['lulus'] ? 'Lulus' : 'Tidak Lulus');
                fputcsv($out, $line);
            }
            fclose($out);
        }, "nilai-akhir-{$slug}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
