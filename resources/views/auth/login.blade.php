@extends('layouts.app')
@section('title', 'Masuk')
@section('body')
<div class="container narrow">
  <div class="eyebrow">Mini-LMS AP</div>
  <h1 class="mt1">Masuk</h1>
  <p class="muted mt1">Belum punya akun? <a href="{{ route('register') }}">Daftar dengan kode kelas</a>.</p>

  <div class="card mt3">
    <form method="post" action="{{ route('login') }}">
      @csrf
      <label class="field"><span>Email</span>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
      </label>
      <label class="field"><span>Kata sandi</span>
        <input type="password" name="password" required>
      </label>
      <label class="row" style="gap:8px"><input type="checkbox" name="remember" style="width:auto"> <span>Ingat saya</span></label>
      <button class="btn btn-cta mt2" type="submit" style="width:100%; justify-content:center">Masuk</button>
    </form>
  </div>
</div>
@endsection
