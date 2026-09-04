@extends('layouts.app')
@section('title', $lesson->id ? 'Edit pelajaran' : 'Pelajaran baru')
@section('body')
@php
  use Illuminate\Support\Str;
  $action = $lesson->id ? route('teacher.lessons.update', $lesson) : route('teacher.lessons.store');
  $editorCells = collect($lesson->notebookCells())->map(fn ($c) => $c + ['id' => \App\Support\Notebook::newId()])->values();
@endphp
<div class="container md9">
  <div class="small muted"><a href="{{ route('teacher.courses.show', $course) }}">&larr; {{ $course->name }}</a> / Minggu {{ $week->number }}</div>
  <h1 class="mt2">{{ $lesson->id ? 'Edit pelajaran' : 'Pelajaran baru' }}</h1>

  <div class="card mt3">
    <form method="post" action="{{ $action }}">
      @csrf
      @if ($lesson->id) @method('put') @endif
      <input type="hidden" name="week_id" value="{{ $week->id }}">
      <div class="row" style="gap:12px; align-items:flex-end">
        <label class="field" style="flex:1"><span>Judul pelajaran</span><input type="text" name="title" value="{{ old('title', $lesson->title) }}" required></label>
        <label class="field" style="width:100px"><span>XP</span><input type="number" name="xp" value="{{ old('xp', $lesson->xp) }}" min="0"></label>
        <label class="field" style="width:90px"><span>Urutan</span><input type="number" name="position" value="{{ old('position', $lesson->position) }}" min="0"></label>
      </div>

      <div class="nb-editor-wrap mt3">
        <div class="row between">
          <span class="eyebrow">Notebook pelajaran</span>
          <span class="small muted">Susun materi sebagai rangkaian sel — seret <span class="mono">↑ ↓</span> untuk mengurutkan.</span>
        </div>
        <input type="hidden" name="cells_json" id="cellsJson">
        <div class="nb-editor" id="nbEditor" data-upload-url="{{ route('teacher.upload') }}" data-file-url="{{ route('teacher.upload.file') }}"></div>
        <div id="nbEmpty" class="nb-empty" hidden>Belum ada sel. Tambahkan sel pertama di bawah.</div>
        <div class="nb-add">
          <span class="nb-add-label">+ Sel:</span>
          <button type="button" class="btn btn-sm btn-soft" data-add="markdown">Markdown</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="text">Teks</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="image">Gambar</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="video">Video</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="file">Dokumen</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="code">Kode Dart</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="callout">Callout</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="practice">Latihan</button>
          <button type="button" class="btn btn-sm btn-soft" data-add="interactive">Interaktif</button>
          <button type="button" class="btn btn-sm btn-view" data-add="quiz">Kuis</button>
          <button type="button" class="btn btn-sm btn-view" data-add="assignment">Tugas</button>
          <button type="button" class="btn btn-sm btn-view" data-add="project">Proyek</button>
        </div>
      </div>

      <div class="form-actions mt4">
        <button class="btn btn-cta" type="submit" id="nbSave">Simpan pelajaran</button>
        @if ($lesson->id)
          <button type="submit" form="lessonDeleteForm" class="btn btn-del" data-confirm="Hapus pelajaran &quot;{{ $lesson->title }}&quot;? Tindakan ini tidak bisa dibatalkan.">Hapus pelajaran</button>
        @endif
      </div>
    </form>
    @if ($lesson->id)
      <form method="post" id="lessonDeleteForm" action="{{ route('teacher.lessons.destroy', $lesson) }}">@csrf @method('delete')</form>
    @endif
  </div>

  @php $wantsQuiz = $lesson->id && ($lesson->type === 'kuis' || $lesson->hasCellType('quiz')); @endphp
  @if ($wantsQuiz && $quiz)
    <h2 class="mt4">Pengaturan kuis</h2>
    <div class="card mt2">
      <form method="post" action="{{ route('teacher.quizzes.update', $quiz) }}">@csrf @method('put')
        <label class="field"><span>Judul kuis</span><input type="text" name="title" value="{{ $quiz->title }}"></label>
        <div class="row" style="gap:12px">
          <label class="field" style="flex:1"><span>Batas waktu (menit)</span><input type="number" name="time_limit_min" value="{{ $quiz->time_limit_min }}" min="1"></label>
          <label class="field" style="flex:1"><span>Nilai lulus</span><input type="number" name="pass_score" value="{{ $quiz->pass_score }}" min="0" max="100"></label>
          <label class="field" style="flex:1"><span>Maks. percobaan</span><input type="number" name="max_attempts" value="{{ $quiz->max_attempts }}" min="1"></label>
          <label class="field" style="flex:1"><span>XP bila lulus</span><input type="number" name="xp" value="{{ $quiz->xp }}" min="0"></label>
        </div>
        <label class="row" style="gap:8px"><input type="checkbox" name="ai_feedback" value="1" @checked($quiz->ai_feedback) style="width:auto"> <span>AI menjelaskan jawaban yang salah kepada mahasiswa</span></label>
        <button class="btn mt2" type="submit">Simpan pengaturan</button>
      </form>
    </div>

    <h2 class="mt4">Soal ({{ $quiz->questions->count() }})</h2>
    @foreach ($quiz->questions as $i => $q)
      <div class="card mt2">
        <form method="post" action="{{ route('teacher.questions.save', $quiz) }}">@csrf
          <input type="hidden" name="question_id" value="{{ $q->id }}">
          <label class="field"><span>Pertanyaan {{ $i + 1 }}</span><textarea name="text" required>{{ $q->text }}</textarea></label>
          <label class="field"><span>Potongan kode <span class="muted">(opsional)</span></span><textarea name="code_snippet" class="code-area">{{ $q->code_snippet }}</textarea></label>
          <span class="small muted">Pilih jawaban benar dengan radio.</span>
          <div class="mt1" style="display:flex; flex-direction:column; gap:8px">
            @for ($k = 0; $k < 5; $k++)
              @php $opt = $q->options[$k] ?? null; @endphp
              <div class="row" style="gap:10px">
                <input type="radio" name="correct" value="{{ $k }}" @checked($opt && $opt->is_correct) style="width:auto">
                <input type="text" name="opt[]" value="{{ $opt->text ?? '' }}" placeholder="Opsi {{ chr(65 + $k) }}" style="flex:1">
              </div>
            @endfor
          </div>
          <div class="row between mt2"><button class="btn btn-sm btn-cta" type="submit">Simpan soal</button></div>
        </form>
        <form method="post" action="{{ route('teacher.questions.destroy', $q) }}" class="mt1">@csrf @method('delete')
          <button class="btn btn-sm btn-danger" data-confirm="Hapus soal ini?">Hapus soal</button>
        </form>
      </div>
    @endforeach

    <div class="card mt2">
      <form method="post" action="{{ route('teacher.questions.save', $quiz) }}">@csrf
        <input type="hidden" name="question_id" value="">
        <h3 class="mt0">Tambah soal baru</h3>
        <label class="field"><span>Pertanyaan</span><textarea name="text" required></textarea></label>
        <label class="field"><span>Potongan kode <span class="muted">(opsional)</span></span><textarea name="code_snippet" class="code-area"></textarea></label>
        <div class="mt1" style="display:flex; flex-direction:column; gap:8px">
          @for ($k = 0; $k < 5; $k++)
            <div class="row" style="gap:10px">
              <input type="radio" name="correct" value="{{ $k }}" @checked($k === 0) style="width:auto">
              <input type="text" name="opt[]" placeholder="Opsi {{ chr(65 + $k) }}" style="flex:1">
            </div>
          @endfor
        </div>
        <button class="btn btn-cta mt2" type="submit">Tambah soal</button>
      </form>
    </div>
  @elseif ($wantsQuiz)
    <div class="callout mt3">Simpan pelajaran dulu, lalu editor soal kuis akan muncul di sini.</div>
  @endif
</div>

@push('scripts')
<script>
window.NB_INITIAL = @json($editorCells);
</script>
<script src="{{ asset('js/notebook-editor.js') }}?v={{ filemtime(public_path('js/notebook-editor.js')) }}"></script>
@endpush
@endsection
