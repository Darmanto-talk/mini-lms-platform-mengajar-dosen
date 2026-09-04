@extends('layouts.app')
@section('title', 'Hasil '.$quiz->title)
@section('body')
@php
  $correct = 0;
  foreach ($questions as $q) {
    $pick = $picked[$q->id] ?? null;
    foreach ($q->options as $o) { if ($o->id === (int) $pick && $o->is_correct) $correct++; }
  }
  $passed = (bool) $attempt->passed;
@endphp
<div class="container md9">
  <div class="eyebrow">Hasil kuis</div>
  <h1 class="mt1">{{ $quiz->title }}</h1>

  <div class="card mt3 center">
    <div class="big-pct" style="color: {{ $passed ? 'var(--ok)' : 'var(--danger)' }}">{{ $attempt->score }}</div>
    <p class="mt1"><strong>{{ $correct }}</strong> dari <strong>{{ $questions->count() }}</strong> soal benar</p>
    <p class="mt1">
      @if ($passed)<span class="pill pill-ok">Lulus</span> &mdash; +{{ $quiz->xp }} XP
      @else<span class="pill pill-bad">Belum lulus</span> &mdash; nilai lulus &ge; {{ $quiz->pass_score }} @endif
    </p>
    <p class="muted small mt1">Percobaan tersisa: {{ $attemptsLeft }}</p>
    <div class="row mt3" style="justify-content:center">
      <a class="btn" href="{{ route('quiz.intro', $lesson) }}">Halaman kuis</a>
      @if ($class)<a class="btn btn-cta" href="{{ route('course.show', $class) }}">Kembali ke kelas</a>@endif
    </div>
  </div>

  @if ($attempt->ai_status === 'processing' || $attempt->ai_status === 'pending')
    <div class="callout mt3">🤖 AI sedang menyiapkan penjelasan untuk jawaban Anda — muat ulang halaman ini sebentar lagi.</div>
  @elseif ($attempt->ai_feedback)
    <div class="card mt3">
      <h2 class="mt0">Penjelasan AI</h2>
      <div class="prose">{!! nl2br(e($attempt->ai_feedback)) !!}</div>
    </div>
  @endif

  <h2 class="mt4">Pembahasan</h2>
  @foreach ($questions as $i => $q)
    @php $pick = $picked[$q->id] ?? null; @endphp
    <div class="card">
      <span class="muted small">Soal {{ $i + 1 }}</span>
      <h3 class="mt1" style="line-height:1.5">{{ $q->text }}</h3>
      @if ($q->code_snippet)<pre class="code mt2">{{ $q->code_snippet }}</pre>@endif
      <div class="mt2" style="display:flex; flex-direction:column; gap:8px">
        @foreach ($q->options as $o)
          @php
            $isPick = $o->id === (int) $pick; $isCorrect = $o->is_correct;
            $border = $isCorrect ? 'var(--ok)' : ($isPick ? 'var(--danger)' : 'var(--line)');
            $bg = $isCorrect ? '#e7f6ef' : ($isPick ? '#fbeaea' : '#fff');
          @endphp
          <div class="quiz-opt" style="cursor:default; border-color: {{ $border }}; background: {{ $bg }}">
            <span>{{ $o->text }}</span>
            <span class="spacer"></span>
            @if ($isCorrect)<span class="pill pill-ok">Jawaban benar</span>@endif
            @if ($isPick && ! $isCorrect)<span class="pill pill-bad">Pilihan Anda</span>@endif
          </div>
        @endforeach
        @if ($pick === null)<span class="muted small">Tidak dijawab.</span>@endif
      </div>
    </div>
  @endforeach
</div>
@endsection
