<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
  $brandCourse = $brandCourse ?? null;
  $brandUser = auth()->user();

  if ($brandCourse) {
      $brandName = $brandCourse->name;
      $stop = ['dan','atau','yang','untuk','dari','pada','dengan','serta','ke','di','the','of','and','for','&'];
      $sig = collect(preg_split('/[\s\-]+/', $brandName))
          ->filter(fn ($w) => $w !== '' && ! in_array(mb_strtolower($w), $stop, true) && mb_strlen($w) >= 3);
      $brandInitials = $sig->isNotEmpty()
          ? mb_strtoupper($sig->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''))
          : mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $brandName) ?: 'MK', 0, 2));
      $brandSub = 'Learning Management System';
      $brandDetail = 'Learning Management System';
  } else {
      $brandName = 'Mini-LMS';
      $brandInitials = 'LMS';
      $brandSub = 'Platform Mengajar Dosen';
      $brandDetail = null;
  }
@endphp
<title>@yield('title', 'Beranda') &middot; {{ $brandName }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
</head>
<body>

<nav class="nav">
  <a class="brand" href="{{ url('/') }}" title="{{ $brandName }}">
    <span class="logo">{{ $brandInitials }}</span>
    <span class="brand-txt">
      <b>{{ $brandName }}</b>
      <span class="brand-sub">{{ $brandSub }}</span>
    </span>
  </a>
  <button type="button" class="nav-toggle" data-nav-toggle aria-label="Menu" aria-expanded="false">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="nav-links" id="navLinks">
    @auth
      <span class="nav-who"><span class="who">{{ auth()->user()->name }}</span> &middot; {{ auth()->user()->isTeacher() ? 'Dosen' : 'Mahasiswa' }}</span>
      <a href="{{ auth()->user()->isTeacher() ? route('teacher.index') : route('dashboard') }}">
        {{ auth()->user()->isTeacher() ? 'Mata kuliah saya' : 'Kelas saya' }}
      </a>
      <a href="{{ route('profile.edit') }}">Profil</a>
      <form method="post" action="{{ route('logout') }}" class="nav-logout-form">@csrf
        <button type="submit" class="nav-logout">Keluar</button>
      </form>
    @else
      <a href="{{ route('login') }}">Masuk</a>
      <a href="{{ route('register') }}">Daftar</a>
    @endauth
  </div>
</nav>

@if (session('success') || session('error') || session('info') || $errors->any())
<div class="flash-wrap" id="flashWrap">
  @if (session('success'))<div class="flash success">{{ session('success') }}</div>@endif
  @if (session('error'))<div class="flash error">{{ session('error') }}</div>@endif
  @if (session('info'))<div class="flash info">{{ session('info') }}</div>@endif
  @foreach ($errors->all() as $e)<div class="flash error">{{ $e }}</div>@endforeach
</div>
@endif

<div class="page-body">
  @yield('body')
</div>

<footer class="site-foot">
  <div class="site-foot-in">
    <div class="site-foot-brand">
      <strong>{{ $brandName }}</strong>
      @if ($brandDetail)<span class="foot-detail">{{ $brandDetail }}</span>@endif
      <span class="foot-year">&copy; {{ date('Y') }}</span>
    </div>
    @auth
      <div class="site-foot-links">
        <a href="{{ auth()->user()->isTeacher() ? route('teacher.index') : route('dashboard') }}">{{ auth()->user()->isTeacher() ? 'Mata kuliah saya' : 'Kelas saya' }}</a>
        <a href="{{ route('profile.edit') }}">Profil</a>
      </div>
    @endauth
  </div>
</footer>

<div class="pdf-modal" id="pdfModal" hidden>
  <div class="pdf-modal-backdrop" data-pdf-close></div>
  <div class="pdf-modal-box">
    <div class="pdf-modal-head">
      <strong id="pdfModalTitle">Dokumen</strong>
      <a id="pdfModalDl" class="btn btn-sm btn-cta" href="#" target="_blank" rel="noopener" download>Unduh</a>
      <button type="button" class="pdf-modal-x" data-pdf-close aria-label="Tutup">&times;</button>
    </div>
    <iframe id="pdfModalFrame" title="Pratinjau dokumen"></iframe>
  </div>
</div>

<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
