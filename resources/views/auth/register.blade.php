@extends('layouts.app')
@section('title', 'Daftar')
@section('body')
<div class="container narrow">
  <div class="eyebrow">Mini-LMS AP</div>
  <h1 class="mt1">Daftar mahasiswa</h1>
  <p class="muted mt1">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>.</p>

  <div class="card mt3">
    <form method="post" action="{{ route('register') }}">
      @csrf
      <label class="field"><span>Kode kelas</span>
        <input type="text" name="class_code" value="{{ old('class_code') }}" required style="text-transform:uppercase" placeholder="mis. AP7K2M">
        <div class="hint">Dapatkan kode ini dari dosen pengampu.</div>
      </label>
      <label class="field"><span>Nama lengkap</span>
        <input type="text" name="name" value="{{ old('name') }}" required>
      </label>
      <label class="field"><span>NIM <span class="muted">(opsional)</span></span>
        <input type="text" name="nim" value="{{ old('nim') }}">
      </label>
      <label class="field"><span>Email</span>
        <input type="email" name="email" value="{{ old('email') }}" required>
      </label>
      <div class="row" style="gap:14px">
        <label class="field" style="flex:1"><span>Kata sandi</span>
          <input type="password" name="password" required>
        </label>
        <label class="field" style="flex:1"><span>Ulangi kata sandi</span>
          <input type="password" name="password_confirmation" required>
        </label>
      </div>
      <button class="btn btn-cta" type="submit" style="width:100%; justify-content:center">Daftar &amp; gabung kelas</button>
    </form>
  </div>
</div>
@endsection
