@extends('layouts.app')
@section('title', $lesson->title)
@section('body')
@php
  use App\Support\Notebook;
  $cells = $cells ?? $lesson->notebookCells();
  $isTugas = Notebook::hasType($cells, 'assignment') || $lesson->type === 'tugas';
  $graded = $submission && $submission->graded_at !== null;
  $pct = $total ? (int) round($doneCount / $total * 100) : 0;
@endphp

<div class="reader-bar">
  <div class="row" style="gap:12px">
    <button type="button" class="btn btn-sm" id="sideToggleBar" title="Tampilkan / sembunyikan daftar materi">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      <span class="hide-sm">Daftar materi</span>
    </button>
    <a class="reader-back" href="{{ route('course.show', $class) }}">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Kembali ke kelas
    </a>
  </div>
  <span class="small muted hide-sm">{{ $class->course->name }} &middot; {{ $class->name }}</span>
</div>

<div class="reader" id="reader">
  <aside class="reader-side">
   <div class="reader-side-sticky">
    <div class="reader-side-head">
      <span class="rs-course">{{ Str::upper($class->course->name) }}</span>
      <button type="button" class="rs-toggle" id="sideToggle" title="Sembunyikan / tampilkan daftar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/></svg>
      </button>
    </div>

    <div class="reader-side-scroll">
      <div class="rs-progress">
        <div class="eyebrow">{{ Str::upper($lesson->typeLabel()) }}</div>
        <div class="rs-progress-title">{{ $lesson->title }}</div>
        <div class="rs-progress-label">Progress Belajar</div>
        <div class="bar mt1"><span style="width: {{ $pct }}%"></span></div>
        <div class="rs-progress-meta">
          <span>{{ $doneCount }} / {{ $total }} modul</span>
          <strong>{{ $pct }}%</strong>
        </div>
      </div>

      <div class="rs-section-title">Daftar Materi</div>

      <div class="rs-panel" data-panel="modul">
        @foreach ($weeks->sortBy('number') as $w)
          @php $wd = $w->lessons->filter(fn ($l) => isset($doneSet[$l->id]))->count(); @endphp
          <details class="rs-section {{ $w->is_open ? '' : 'is-locked' }}" {{ ($w->id === $lesson->week_id || ($w->is_open && $wd < $w->lessons->count() && $loop->first)) ? 'open' : '' }}>
            <summary class="rs-section-head">
              <span class="rs-section-name">Minggu {{ $w->number }} — {{ $w->title }}</span>
              @if ($w->is_open)
                <span class="rs-section-count">{{ $wd }}/{{ $w->lessons->count() }}</span>
                <svg class="rs-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              @else
                <span class="rs-lock" title="Belum dibuka dosen">🔒</span>
              @endif
            </summary>
            <div class="rs-section-body">
              @forelse ($w->lessons as $l)
                @php
                  $isDone = isset($doneSet[$l->id]);
                  $isCur  = $l->id === $lesson->id;
                  $isUnlocked = isset(($unlockedSet ?? [])[$l->id]);
                @endphp
                @if ($w->is_open && $isUnlocked)
                  <a class="rs-card {{ $isCur ? 'is-current' : '' }} {{ $isDone ? 'is-done' : '' }}" href="{{ route('lesson.show', $l) }}">
                    <span class="rs-card-ic">@include('student.partials.lesson-icon', ['name' => $l->icon()])</span>
                    <span class="rs-card-main">
                      <span class="rs-card-title">{{ $l->title }}</span>
                      <span class="rs-card-meta">{{ $l->durationLabel() }}</span>
                    </span>
                    @if ($isDone)
                      <span class="rs-done"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Selesai</span>
                    @endif
                  </a>
                @else
                  <div class="rs-card is-locked" title="{{ $w->is_open ? 'Selesaikan materi sebelumnya dulu' : 'Minggu ini belum dibuka dosen' }}">
                    <span class="rs-card-ic">@include('student.partials.lesson-icon', ['name' => $l->icon()])</span>
                    <span class="rs-card-main">
                      <span class="rs-card-title">{{ $l->title }}</span>
                      <span class="rs-card-meta">{{ $l->durationLabel() }}</span>
                    </span>
                    <span class="rs-locked-ic" aria-hidden="true">🔒</span>
                  </div>
                @endif
              @empty
                <p class="rs-empty">Belum ada materi.</p>
              @endforelse
            </div>
          </details>
        @endforeach
      </div>
    </div>
   </div>
  </aside>

  <main class="reader-main">
    <article class="reader-card">
      <div class="eyebrow">Minggu {{ $lesson->week->number }} &middot; {{ $lesson->typeLabel() }}</div>
      <h1 class="reader-title mt1">{{ $lesson->title }}</h1>
      <div class="row mt2 small muted" style="gap:16px">
        <span>+{{ $lesson->xp }} XP</span>
        <span>{{ $lesson->durationLabel() }}</span>
        @if ($completed)<span class="pill pill-ok">Sudah selesai</span>@endif
      </div>

      <div class="prose mt3">
        @include('partials.notebook', [
          'cells' => $cells, 'mode' => 'student', 'lesson' => $lesson, 'class' => $class,
          'submission' => $submission, 'quiz' => $quiz ?? null, 'graded' => $graded,
          'quizStatus' => $quizStatus ?? null,
        ])
      </div>

      @php
        $hasGatedBlock = $isTugas || Notebook::hasType($cells, 'quiz') || $lesson->type === 'kuis';
      @endphp
      <div class="reader-nav">
        @if ($neighbours['prev'])
          <a class="btn" href="{{ route('lesson.show', $neighbours['prev']) }}">&larr; Sebelumnya</a>
        @else <span></span> @endif

        @if ($hasGatedBlock)
          @if ($completed)
            @if ($neighbours['next'])
              <a class="btn btn-dark" href="{{ route('lesson.show', $neighbours['next']) }}">Selanjutnya &rarr;</a>
            @else
              <a class="btn btn-dark" href="{{ route('course.show', $class) }}">Selesai &check;</a>
            @endif
          @else
            <span class="reader-nav-hint">{{ $isTugas ? 'Kumpulkan tugas di atas untuk melanjutkan.' : 'Kerjakan kuis di atas untuk melanjutkan.' }}</span>
          @endif
        @else
          <form method="post" action="{{ route('lesson.complete', $lesson) }}">
            @csrf
            <button class="btn btn-dark" type="submit">
              @if ($neighbours['next']) Selanjutnya &rarr; @else Selesaikan kelas &check; @endif
            </button>
          </form>
        @endif
      </div>
    </article>
  </main>
</div>
@endsection
