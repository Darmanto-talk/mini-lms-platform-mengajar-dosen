@extends('layouts.app')
@section('title', $course->name)
@section('body')
@php
  $currentWeekId = null;
  foreach ($course->weeks as $w) {
    foreach ($w->lessons as $l) { if ($l->id === $continueId) { $currentWeekId = $w->id; break 2; } }
  }
  if (! $currentWeekId) { $currentWeekId = optional($course->weeks->firstWhere('is_open', true))->id; }
  $rank = 0;
  $pct = $total ? (int) round($doneCount / $total * 100) : 0;

  // avatar acak (tapi tetap sama tiap kali dibuka) — ikon FontAwesome + warna dari id.
  // Ikon & warna di-hash terpisah (bukan id % n langsung) supaya id yang berurutan
  // (mis. 180 mahasiswa satu kelas) tidak jatuh ke pola berulang yang sama persis.
  $avatarIcons = ['user', 'user-astronaut', 'user-ninja', 'user-graduate', 'user-tie', 'user-secret', 'user-cog', 'user-check', 'user-clock', 'user-pen', 'user-shield', 'user-large'];
  $avatarColors = ['#2563eb', '#0ea5b0', '#7c3aed', '#d97706', '#dc2626', '#12805a', '#db2777', '#4f46e5', '#0891b2', '#65a30d'];
  $avatarFor = function ($id) use ($avatarIcons, $avatarColors) {
      return [
          'icon' => $avatarIcons[crc32('ic-'.$id) % count($avatarIcons)],
          'color' => $avatarColors[crc32('cl-'.$id) % count($avatarColors)],
      ];
  };
@endphp
<div class="container">
  <div class="small muted">Progress Belajar &nbsp;›&nbsp; <span style="color:var(--ink)">Koridor Kelas</span></div>

  <div class="row between mt2" style="align-items:flex-start; gap:16px">
    <div style="min-width:0">
      <h1>{{ $course->name }}</h1>
      <div class="row mt1" style="gap:8px"><span class="pill">{{ $class->name }}</span></div>
    </div>
    @if ($continueId)
      <a class="btn btn-dark" style="flex-shrink:0" href="{{ route('lesson.show', $continueId) }}">Lanjut Belajar &rarr;</a>
    @else
      <span class="pill pill-ok" style="flex-shrink:0">Semua materi selesai 🎉</span>
    @endif
  </div>

  @if (! empty($final) && $final['nilai'] !== null && $final['complete'])
    <div class="card mt3" style="border-color:var(--cta)">
      <div class="eyebrow">Nilai Akhir Mata Kuliah</div>
      <div class="row mt1" style="gap:14px; align-items:baseline">
        <span class="big-pct" style="font-size:32px; color:var(--cta)">{{ rtrim(rtrim(number_format($final['nilai'],2),'0'),'.') }}</span>
        <span class="pill pill-xp" style="font-size:14px">Grade {{ $final['grade'] }}</span>
        @if ($final['lulus'])<span class="pill pill-ok">Lulus</span>@else<span class="pill pill-bad">Tidak Lulus</span>@endif
      </div>
    </div>
  @elseif (! empty($final) && ! $final['complete'])
    <div class="callout mt3">Penilaian akhir sedang dalam proses oleh dosen.</div>
  @endif

  <div class="card mt3">
    <div class="row between"><strong>Progress Belajar</strong><strong style="font-size:16px">{{ $pct }}%</strong></div>
    <div class="bar mt2" style="height:9px"><span style="width: {{ $pct }}%"></span></div>
    <div class="row mt2 small muted" style="gap:20px">
      <span><strong style="color:var(--ink)">{{ $doneCount }}</strong> / {{ $total }} modul selesai</span>
      <span><strong style="color:var(--ink)">{{ max(0, $total - $doneCount) }}</strong> tersisa</span>
      <span class="pill pill-xp">{{ $xp }} XP</span>
      <span>{{ $openCount }} dari {{ $course->weeks->count() }} minggu dibuka</span>
    </div>
  </div>

  <div class="grid-2 mt3">
    <div>
      <h2>Daftar materi</h2>
      <div class="weeks mt2">
        @foreach ($course->weeks as $w)
          @php $wd = $w->lessons->filter(fn($l) => isset($doneSet[$l->id]))->count(); @endphp
          <details class="week {{ $w->is_open ? '' : 'locked' }}" {{ ($w->is_open && $w->id === $currentWeekId) ? 'open' : '' }} @if(!$w->is_open) onclick="return false" @endif>
            <summary class="week-head">
              <span style="flex:1; min-width:0">
                <span class="wk-label">Minggu {{ $w->number }}@if($w->is_exam) · Ujian @endif</span>
                <span class="wk-title" style="display:block">{{ $w->title }}</span>
              </span>
              @if ($w->is_open)
                <span class="pill">{{ $wd }}/{{ $w->lessons->count() }} selesai</span>
                <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9aa9b6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              @else
                <span class="lock-badge">🔒 Belum dibuka</span>
              @endif
            </summary>
            @if ($w->is_open)
              <div class="week-body">
                @forelse ($w->lessons as $l)
                  @php
                    $isDone = isset($doneSet[$l->id]);
                    $isUnlocked = isset(($unlockedSet ?? [])[$l->id]);
                  @endphp
                  @if ($isUnlocked)
                    <a class="lesson-row" href="{{ route('lesson.show', $l) }}">
                      <span class="dot {{ $isDone ? 'done' : '' }}">
                        @if ($isDone)<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>@endif
                      </span>
                      <span class="ln-title">{{ $l->title }}</span>
                      <span class="tag tag-{{ $l->type }}">{{ $l->typeLabel() }}</span>
                    </a>
                  @else
                    <div class="lesson-row is-locked" title="Selesaikan materi sebelumnya dulu">
                      <span class="dot">🔒</span>
                      <span class="ln-title">{{ $l->title }}</span>
                      <span class="tag tag-{{ $l->type }}">{{ $l->typeLabel() }}</span>
                    </div>
                  @endif
                @empty
                  <div class="lesson-row muted">Belum ada pelajaran.</div>
                @endforelse
              </div>
            @endif
          </details>
        @endforeach
      </div>
    </div>

    <div>
      <div class="card">
        <div class="row between"><strong>Papan peringkat kelas</strong><span class="muted small">{{ count($board) }} mahasiswa</span></div>
        <div class="board-list mt2" id="boardList">
          @forelse ($board as $p)
            @php
              $rank++;
              $av = $avatarFor($p['id']);
              $medal = [1 => 'Emas', 2 => 'Perak', 3 => 'Perunggu'][$rank] ?? null;
            @endphp
            <div class="board-row board-row--{{ $rank <= 3 ? $rank : 'plain' }} {{ $p['id'] === $user->id ? 'me' : '' }}">
              <span class="board-rank">{{ $rank }}</span>
              <span class="board-avatar" style="background:{{ $av['color'] }}"><i class="fa-solid fa-{{ $av['icon'] }}"></i></span>
              <span class="board-who">
                <span class="board-name">{{ $p['name'] }}</span>
                <span class="board-role">{{ $p['nim'] ?: '—' }}</span>
              </span>
              @if ($medal)
                <span class="board-badge board-badge--{{ $rank }}" title="Peringkat {{ $rank }} — Lencana {{ $medal }}">
                  <i class="fa-solid fa-medal"></i>
                </span>
              @endif
              <span class="board-pts">
                <strong>{{ $p['xp'] }}</strong>
                <span class="board-pts-label">Points</span>
              </span>
            </div>
          @empty
            <div class="muted small">Belum ada peserta.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
