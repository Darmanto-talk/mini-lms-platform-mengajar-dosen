@extends('layouts.app')
@section('title', 'Impor mahasiswa — '.$class->name)
@section('body')
<div class="container md">
  <div class="small muted"><a href="{{ route('teacher.students.index', $class) }}">&larr; {{ $class->name }} — Mahasiswa &amp; nilai</a></div>

  <div class="eyebrow mt2">{{ $class->course->name }} &middot; {{ $class->name }}</div>
  <h1 class="mt1">Impor mahasiswa dari Excel</h1>
  <p class="muted small mt1">Daftarkan banyak mahasiswa sekaligus. Akun yang belum ada akan dibuatkan otomatis beserta kata sandi sementaranya.</p>

  <div class="card mt3">
    <h3 class="mt0">1. Unduh format baku</h3>
    <p class="small muted mt1">Berisi judul kolom yang wajib dipakai (<span class="mono">Nama</span>, <span class="mono">Email</span>, <span class="mono">NIM</span>, <span class="mono">Kata Sandi</span>) beserta dua baris contoh dan petunjuk pengisian.</p>
    <a class="btn btn-cta mt2" href="{{ route('teacher.students.import.template', $class) }}">Unduh template .xlsx</a>
  </div>

  <div class="card mt3">
    <h3 class="mt0">2. Unggah berkas yang sudah diisi</h3>
    <form method="post" action="{{ route('teacher.students.import.store', $class) }}" enctype="multipart/form-data" class="mt3">
      @csrf
      <label class="field @error('file') has-err @enderror">
        <span>Berkas Excel</span>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
        @error('file')<span class="field-err">{{ $message }}</span>@enderror
      </label>
      <div class="hint">Format .xlsx, .xls, atau .csv &middot; maksimal 5 MB.</div>
      <button class="btn btn-cta mt3" type="submit">Proses &amp; daftarkan ke {{ $class->name }}</button>
    </form>
  </div>

  <div class="card mt3">
    <h3 class="mt0">Yang perlu diketahui</h3>
    <ul class="prose small mt2" style="padding-left:20px">
      <li><strong>Email sudah punya akun?</strong> Mahasiswanya langsung didaftarkan ke kelas ini — kata sandi lamanya <em>tidak</em> diubah.</li>
      <li><strong>Sudah aktif di kelas lain pada mata kuliah ini?</strong> Barisnya dilewati — satu mahasiswa hanya boleh berada di satu kelas per mata kuliah.</li>
      <li><strong>Pernah dikeluarkan dari kelas ini?</strong> Otomatis didaftarkan kembali, riwayat &amp; nilai lamanya kembali utuh.</li>
      <li>Kolom <span class="mono">Kata Sandi</span> boleh dikosongkan — sistem membuatkan sandi sementara acak yang <strong>hanya ditampilkan sekali</strong> setelah impor selesai. Catat/salin sebelum meninggalkan halaman hasil.</li>
    </ul>
  </div>
</div>
@endsection
