@extends('layouts.app')
@section('title', 'Hasil kuis: '.$lesson->title)
@section('body')
@php
  $done = $rows->where('submitted', '>', 0)->count();
  $passed = $rows->where('passed', true)->count();
  $avg = $rows->whereNotNull('best')->avg('best');
@endphp
<div class="container wide">
  <div class="small muted">
    <a href="{{ route('teacher.monitor', $class) }}">&larr; Monitor {{ $class->name }}</a>
    &middot; <a href="{{ route('teacher.courses.show', $class->course) }}">{{ $class->course->name }}</a>
  </div>
  <h1 class="mt2">Hasil kuis: {{ $lesson->title }}</h1>
  <p class="muted small mt1">
    Kelas {{ $class->name }} &middot; {{ $questions->count() }} soal &middot;
    <strong>{{ $done }}</strong>/{{ $rows->count() }} mengerjakan &middot;
    {{ $passed }} lulus &middot; rata-rata nilai {{ $avg ? round($avg, 1) : '—' }}
  </p>

  <div class="card mt3 table-wrap">
    <table class="data">
      <thead><tr><th>NIM</th><th>Nama</th><th>Percobaan</th><th>Nilai terbaik</th><th>Status</th><th>Terakhir</th><th></th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          <tr>
            <td class="mono">{{ $r->user->nim ?: '—' }}</td>
            <td><a href="{{ route('teacher.students.show', [$class, $r->user->id]) }}">{{ $r->user->name }}</a></td>
            <td>{{ $r->submitted }}</td>
            <td>{{ $r->best === null ? '—' : $r->best }}</td>
            <td>
              @if ($r->submitted === 0)<span class="pill pill-bad">Belum mengerjakan</span>
              @elseif ($r->passed)<span class="pill pill-ok">Lulus</span>
              @else<span class="pill">Belum lulus</span>@endif
            </td>
            <td class="small muted">{{ $r->last ? \Illuminate\Support\Carbon::parse($r->last)->format('d/m H:i') : '—' }}</td>
            <td>
              @if ($r->submitted > 0)
                <details>
                  <summary class="btn btn-sm">Lihat jawaban</summary>
                  @foreach ($r->attempts->whereNotNull('submitted_at') as $ai => $a)
                    @php $picked = $a->answers->pluck('option_id', 'question_id'); @endphp
                    <div class="submission-box mt2">
                      <strong>Percobaan {{ $ai + 1 }}</strong> — nilai {{ $a->score }}
                      {!! $a->passed ? '<span class="pill pill-ok">lulus</span>' : '<span class="pill pill-bad">belum</span>' !!}
                      <div class="mt2" style="display:flex; flex-direction:column; gap:8px">
                        @foreach ($questions as $qi => $q)
                          @php
                            $pid = $picked[$q->id] ?? null;
                            $chosen = $q->options->firstWhere('id', $pid);
                            $correct = $q->options->firstWhere('is_correct', true);
                            $ok = $chosen && $chosen->is_correct;
                          @endphp
                          <div style="border:1px solid var(--line); border-radius:8px; padding:8px 10px; font-size:13px">
                            <div><strong>{{ $qi + 1 }}.</strong> {{ $q->text }}</div>
                            <div class="mt1">
                              Jawaban: <span style="color: {{ $ok ? 'var(--ok)' : 'var(--danger)' }}; font-weight:600">{{ $chosen->text ?? '(kosong)' }}</span>
                              @unless ($ok)<span class="muted"> — benar: {{ $correct->text ?? '-' }}</span>@endunless
                            </div>
                          </div>
                        @endforeach
                      </div>
                    </div>
                  @endforeach
                </details>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="muted center">Belum ada mahasiswa di kelas ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
