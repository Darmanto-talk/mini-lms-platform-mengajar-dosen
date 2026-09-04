@extends('layouts.app')
@section('title', 'Pratinjau: '.$lesson->title)
@section('body')
<div class="container md9">
  <div class="row between">
    <div class="small muted"><a href="{{ route('teacher.courses.show', $course) }}">&larr; {{ $course->name }}</a> / Minggu {{ $week->number }} / Preview</div>
    <div class="row" style="gap:8px">
      <a class="btn btn-sm btn-dark" href="{{ route('teacher.lessons.present', $lesson) }}" target="_blank">Present &nearr;</a>
      <a class="btn btn-sm btn-cta" href="{{ route('teacher.lessons.edit', $lesson) }}">Edit pelajaran</a>
    </div>
  </div>

  <div class="card lesson-doc mt3">
    <div class="eyebrow">Minggu {{ $week->number }} &middot; {{ $lesson->typeLabel() }}</div>
    <h1 class="mt1">{{ $lesson->title }}</h1>
    <div class="row mt2 small muted" style="gap:16px">
      <span>+{{ $lesson->xp }} XP</span>
      @if ($lesson->type === 'tugas')<span>Nilai maks {{ $lesson->max_score }}</span>@endif
      <span class="week-status {{ $week->is_open ? 'open' : 'closed' }}">{{ $week->is_open ? 'Minggu terbuka' : 'Minggu tertutup' }}</span>
    </div>

    <div class="prose mt3">
      @include('partials.notebook', ['cells' => $cells, 'mode' => 'preview', 'lesson' => $lesson, 'quiz' => $quiz])
    </div>
  </div>
</div>
@endsection
