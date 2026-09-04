@extends('layouts.app')
@section('title', 'Mata kuliah saya')
@section('body')
<div class="container wide">
  <div class="eyebrow">Panel dosen</div>
  <h1 class="mt1">Mata kuliah saya</h1>

  <div class="grid-2 mt3">
    <div>
      @forelse ($courses as $co)
        @php
          $students = $co->classes_list->sum('enrollments_count');
          $logo = \App\Support\CourseIcon::for($co->id);
        @endphp
        <div class="card">
          <div class="row between" style="gap:12px">
            <div class="row" style="gap:12px; min-width:0">
              <span class="mk-logo" style="background:linear-gradient(145deg, {{ $logo['from'] }}, {{ $logo['to'] }})">
                <i class="fa-solid fa-{{ $logo['icon'] }}"></i>
              </span>
              <div style="min-width:0">
                <h3 class="mt0"><a href="{{ route('teacher.courses.show', $co) }}">{{ $co->name }}</a></h3>
                <p class="muted small mt1" style="margin-bottom:0">{{ $co->week_count }} minggu &middot; {{ $co->lesson_count }} pelajaran &middot; {{ $co->classes_list->count() }} kelas &middot; {{ $students }} mahasiswa</p>
              </div>
            </div>
            <a class="btn btn-sm btn-cta" href="{{ route('teacher.courses.show', $co) }}">Kelola materi</a>
          </div>
          <div class="divider" style="margin:14px 0"></div>
          <div style="display:flex; flex-direction:column; gap:8px">
            @foreach ($co->classes_list as $c)
              <div class="row between" style="gap:10px">
                <div><strong>{{ $c->name }}</strong> <span class="muted small">&middot; kode <span class="mono">{{ $c->code }}</span> &middot; {{ $c->enrollments_count }} mhs</span></div>
                <a class="btn btn-sm btn-soft" href="{{ route('teacher.students.index', $c) }}">Nilai</a>
              </div>
            @endforeach
          </div>
        </div>
      @empty
        <div class="card"><p class="muted mt0">Belum ada mata kuliah. Buat yang pertama, atau impor dari JSON.</p></div>
      @endforelse
    </div>

    <div>
      <div class="card">
        <h3 class="mt0">Buat mata kuliah</h3>
        <form method="post" action="{{ route('teacher.courses.store') }}" class="mt2">
          @csrf
          <label class="field"><span>Nama mata kuliah</span><input type="text" name="name" required placeholder="Algoritma dan Pemrograman"></label>
          <label class="field"><span>Deskripsi <span class="muted">(opsional)</span></span><textarea name="description"></textarea></label>
          <button class="btn btn-cta" type="submit" style="width:100%; justify-content:center">Buat kosong</button>
          <div class="hint">Otomatis dibuatkan "Kelas A" dengan kode gabung.</div>
        </form>
      </div>
      <div class="card">
        <h3 class="mt0">Impor / perbarui dari JSON</h3>
        <p class="small muted mt1">Buat mata kuliah lengkap dari satu file <code>.json</code>, atau perbarui yang sudah ada.</p>
        <a class="btn btn-cta mt2" href="{{ route('teacher.import.form') }}" style="width:100%; justify-content:center">Impor materi</a>
      </div>
    </div>
  </div>
</div>
@endsection
