@extends('layouts.app')
@section('title', 'Profil')
@section('body')
<div class="container narrow">
  <h1>Profil</h1>

  <div class="card mt3">
    <h3 class="mt0">Informasi akun</h3>
    <form method="post" action="{{ route('profile.update') }}" class="mt3">
      @csrf @method('patch')
      <label class="field @error('name') has-err @enderror">
        <span>Nama</span>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
        @error('name')<span class="field-err">{{ $message }}</span>@enderror
      </label>
      <label class="field @error('email') has-err @enderror">
        <span>Email</span>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
        @error('email')<span class="field-err">{{ $message }}</span>@enderror
      </label>
      <button class="btn btn-cta" type="submit">Simpan</button>
    </form>
  </div>

  <div class="card mt3">
    <h3 class="mt0">Ubah kata sandi</h3>
    <p class="small muted mt1">Minimal 8 karakter. Anda tidak perlu masuk ulang setelah menyimpan.</p>

    <form method="post" action="{{ route('password.update') }}" class="mt3">
      @csrf @method('put')
      <label class="field @error('current_password') has-err @enderror">
        <span>Kata sandi saat ini</span>
        <input type="password" name="current_password" autocomplete="current-password" required>
        @error('current_password')<span class="field-err">{{ $message }}</span>@enderror
      </label>
      <label class="field @error('password') has-err @enderror">
        <span>Kata sandi baru</span>
        <input type="password" name="password" autocomplete="new-password" required>
        @error('password')<span class="field-err">{{ $message }}</span>@enderror
      </label>
      <label class="field">
        <span>Ulangi kata sandi baru</span>
        <input type="password" name="password_confirmation" autocomplete="new-password" required>
      </label>
      <button class="btn btn-cta" type="submit">Ubah kata sandi</button>
    </form>
  </div>
</div>
@endsection
