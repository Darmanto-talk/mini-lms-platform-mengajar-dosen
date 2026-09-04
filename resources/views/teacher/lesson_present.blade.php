<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Present: {{ $lesson->title }}</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="present">
<div class="present-bar">
  <span class="pb-title">{{ $course->name }} — Minggu {{ $week->number }}</span>
  <span class="pb-step">{{ $step }} / {{ $stepTotal }}</span>
  <a href="{{ route('teacher.lessons.preview', $lesson) }}">Preview</a>
  <a href="{{ route('teacher.lessons.edit', $lesson) }}">Edit</a>
  <a class="pb-exit" href="{{ route('teacher.courses.show', $course) }}">Keluar &times;</a>
</div>

<div class="present-stage">
  <div class="eyebrow">Minggu {{ $week->number }} &middot; {{ $lesson->typeLabel() }}</div>
  <h1>{{ $lesson->title }}</h1>

  <div class="present-prose">
    @include('partials.notebook', ['cells' => $cells, 'mode' => 'present', 'lesson' => $lesson, 'quiz' => $quiz])
  </div>
</div>

<div class="present-nav">
  @if ($prev)
    <a href="{{ route('teacher.lessons.present', $prev) }}">&larr; {{ \Illuminate\Support\Str::limit($prev->title, 40) }}</a>
  @else
    <span class="is-off">&larr; Awal</span>
  @endif
  @if ($next)
    <a href="{{ route('teacher.lessons.present', $next) }}">{{ \Illuminate\Support\Str::limit($next->title, 40) }} &rarr;</a>
  @else
    <span class="is-off">Selesai &rarr;</span>
  @endif
</div>

<script>
  var PREV = @json($prev ? route('teacher.lessons.present', $prev) : null);
  var NEXT = @json($next ? route('teacher.lessons.present', $next) : null);
  var EXIT = @json(route('teacher.courses.show', $course));
  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea')) return;
    if ((e.key === 'ArrowRight' || e.key === 'PageDown') && NEXT) location.href = NEXT;
    if ((e.key === 'ArrowLeft' || e.key === 'PageUp') && PREV) location.href = PREV;
    if (e.key === 'Escape') location.href = EXIT;
  });
</script>
</body>
</html>
