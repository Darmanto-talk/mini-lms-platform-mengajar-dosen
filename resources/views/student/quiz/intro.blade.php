@extends('layouts.app')
@section('title', $quiz->title)
@section('body')
@php
  $max = $quiz->max_attempts;
  $left = max(0, $max - $used);
  $qcount = $quiz->questions->count();
  $bestScore = ($best && $best->s !== null) ? (int) $best->s : null;
  $bestPassed = $best && $best->p;
  $passPct = min(100, max(0, (int) $quiz->pass_score));
@endphp
<div class="container quiz-intro">
  <a class="qi-back muted" href="{{ route('course.show', $class) }}">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    {{ $lesson->week->title }}
  </a>
  <div class="eyebrow mt2">Kuis · Minggu {{ $lesson->week->number }}</div>
  <h1>{{ $quiz->title }}</h1>

  <div class="card qi-card">
    <div class="qi-tiles">
      <div class="qi-tile"><b>{{ $qcount }}</b><span>Soal</span></div>
      <div class="qi-tile"><b>{{ $quiz->time_limit_min }}</b><span>Menit</span></div>
      <div class="qi-tile"><b>&ge;&thinsp;{{ $quiz->pass_score }}</b><span>Nilai lulus</span></div>
    </div>

    <div class="qi-rows">
      <div class="qi-row">
        <span>Percobaan</span>
        <span class="qi-val">
          <span class="qi-dots">
            @for ($i = 0; $i < $max; $i++)<i class="{{ $i < $used ? 'on' : '' }}"></i>@endfor
          </span>
          {{ $used }} / {{ $max }} terpakai
        </span>
      </div>
      <div class="qi-row">
        <span>Hadiah</span>
        <span class="qi-val">+{{ $quiz->xp }} XP <span class="muted" style="font-weight:600">bila lulus</span></span>
      </div>
    </div>

    @if ($bestScore !== null)
      <div class="qi-best">
        <div class="qi-best-head">
          <b>{{ $bestScore }}<small> / 100</small></b>
          @if ($bestPassed)<span class="pill pill-ok">Lulus</span>@else<span class="pill pill-bad">Belum lulus</span>@endif
        </div>
        <div class="bar"><span style="width: {{ $bestScore }}%"></span><i class="pass-mark" style="left: {{ $passPct }}%"></i></div>
        <div class="qi-best-foot">Nilai terbaikmu dari {{ $used }} percobaan. Ambang lulus di angka {{ $quiz->pass_score }}.</div>
      </div>
    @endif

    @if ($qcount === 0)
      <div class="qi-empty">Dosen belum menambahkan soal untuk kuis ini. Coba lagi nanti.</div>
    @elseif ($open)
      <div class="qi-cta">
        <a class="btn btn-cta" href="{{ route('quiz.attempt', $open) }}">Lanjutkan percobaan yang berjalan &rarr;</a>
      </div>
    @elseif ($left > 0)
      <form method="post" action="{{ route('quiz.start', $lesson) }}" class="qi-cta">
        @csrf
        <button class="btn btn-cta" type="submit" data-confirm="Mulai kuis sekarang? Timer akan langsung berjalan dan tidak bisa dijeda.">
          Mulai kuis &middot; {{ $left }} percobaan tersisa
        </button>
      </form>
    @else
      <div class="qi-empty">Batas percobaan sudah habis. Nilai terbaikmu tetap tersimpan.</div>
    @endif

    @if ($qcount > 0)
      <ul class="qi-rules">
        <li>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
          <span>Timer berjalan sejak kamu menekan “Mulai” dan tidak bisa dijeda. Saat waktu habis, jawaban otomatis dikumpulkan.</span>
        </li>
        <li>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Kamu punya {{ $max }} kali percobaan. Nilai tertinggi dari semua percobaan yang dipakai.</span>
        </li>
        <li>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
          <span>Pastikan koneksi internet stabil selama mengerjakan.</span>
        </li>
      </ul>
    @endif
  </div>
</div>
@endsection
