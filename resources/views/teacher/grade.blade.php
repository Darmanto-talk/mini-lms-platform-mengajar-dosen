@extends('layouts.app')
@section('title', 'Nilai tugas: '.$lesson->title)
@section('body')
@php
  $done = $rows->filter(fn($r) => $r->sub && $r->sub->submitted_at)->count();
  $graded = $rows->filter(fn($r) => $r->sub && $r->sub->graded_at)->count();
@endphp
<div class="container wide">
  <div class="small muted">
    <a href="{{ route('teacher.courses.show', $class->course) }}">&larr; {{ $class->course->name }}</a>
    / <a href="{{ route('teacher.students.index', $class) }}">{{ $class->name }}</a>
  </div>
  <h1 class="mt2">Nilai tugas: {{ $lesson->title }}</h1>
  <p class="muted small mt1">Kelas {{ $class->name }} &middot; {{ $done }}/{{ $rows->count() }} mengumpulkan &middot; {{ $graded }} dinilai &middot; nilai maksimum {{ $lesson->max_score }}
    @if ($aiEnabled) &middot; <span class="pill pill-ok">Pemeriksaan AI aktif</span> @else &middot; <span class="pill">AI nonaktif</span> @endif
  </p>
  @if ($lesson->rubric)<div class="callout"><strong>Rubrik:</strong> {{ $lesson->rubric }}</div>@endif

  @foreach ($rows as $r)
    @php $s = $r->sub; @endphp
    <div class="card mt3">
      <div class="row between">
        <div><strong>{{ $r->user->name }}</strong> <span class="small muted">{{ $r->user->nim }}</span></div>
        @if (! $s || ! $s->submitted_at)<span class="pill pill-bad">Belum mengumpulkan</span>
        @elseif ($s->graded_at)<span class="pill pill-ok">Nilai final: {{ $s->score }} / {{ $lesson->max_score }}</span>
        @elseif ($s->ai_status === 'done')<span class="pill">Usulan AI: {{ $s->ai_score }} / {{ $lesson->max_score }}</span>
        @elseif (in_array($s->ai_status, ['pending','processing']))<span class="pill">🤖 AI memproses…</span>
        @else<span class="pill">Menunggu penilaian</span>@endif
      </div>

      @if ($s && $s->submitted_at)
        <p class="small muted mt1">Dikumpulkan: {{ \Illuminate\Support\Carbon::parse($s->submitted_at)->format('d M Y H:i') }}</p>
        <div class="submission-box mt2">
          @if ($s->text)<div class="prose">{!! \App\Support\LessonContent::render($s->text) !!}</div>@endif
          @if ($s->link_url)<p class="mt1">Tautan: <a href="{{ $s->link_url }}" target="_blank" rel="noopener">{{ $s->link_url }}</a></p>@endif
          @if ($s->file_path)<p class="mt1"><a class="btn btn-sm" href="{{ asset('storage/'.$s->file_path) }}" target="_blank">Unduh berkas</a></p>@endif
        </div>

        @if ($s->ai_status === 'done')
          <div class="callout mt2">
            <strong>🤖 Umpan balik AI</strong> ({{ $s->ai_model }}):<br>{!! nl2br(e($s->ai_feedback)) !!}
            @if (is_array($s->ai_breakdown) && count($s->ai_breakdown))
              <div class="table-wrap mt2"><table class="data">
                <thead><tr><th>Aspek</th><th>Skor</th><th>Catatan</th></tr></thead>
                <tbody>@foreach ($s->ai_breakdown as $b)<tr><td>{{ $b['aspek'] ?? '-' }}</td><td>{{ $b['skor'] ?? '-' }}@isset($b['maks'])/{{ $b['maks'] }}@endisset</td><td>{{ $b['catatan'] ?? '' }}</td></tr>@endforeach</tbody>
              </table></div>
            @endif
          </div>
        @elseif ($s->ai_status === 'failed')
          <div class="flash error mt2">Pemeriksaan AI gagal: {{ $s->ai_error }}</div>
        @endif

        <div class="row mt2" style="gap:8px; align-items:flex-end">
          @if ($aiEnabled && ! $s->graded_at)
            <form method="post" action="{{ route('teacher.grade.ai', $s) }}">@csrf
              <button class="btn btn-sm" data-confirm="Jalankan pemeriksaan AI untuk pengumpulan ini?">{{ $s->ai_status === 'done' ? 'Ulangi periksa AI' : 'Periksa dengan AI' }}</button>
            </form>
          @endif
        </div>

        <form method="post" action="{{ route('teacher.grade.store', [$class, $lesson]) }}" class="mt2">
          @csrf
          <input type="hidden" name="submission" value="{{ $s->id }}">
          <div class="row" style="gap:12px; align-items:flex-end">
            <label class="field grade-cell" style="margin:0"><span>Nilai final (0–{{ $lesson->max_score }})</span>
              <input type="number" name="score" min="0" max="{{ $lesson->max_score }}" value="{{ $s->score ?? $s->ai_score }}">
            </label>
            <label class="field" style="flex:1; margin:0"><span>Catatan untuk mahasiswa</span>
              <input type="text" name="feedback" value="{{ $s->feedback ?? $s->ai_feedback }}">
            </label>
            <button class="btn btn-cta" type="submit">Simpan nilai final</button>
          </div>
          <div class="hint">Menyimpan nilai final mengunci pengumpulan (mahasiswa tidak bisa mengubah).</div>
        </form>
      @endif
    </div>
  @endforeach
  @if (! $rows->count())<div class="card mt3 muted">Belum ada mahasiswa di kelas ini.</div>@endif
</div>
@endsection
