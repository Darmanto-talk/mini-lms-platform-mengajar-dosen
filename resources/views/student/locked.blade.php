@extends('layouts.app')
@section('title', $title ?? 'Belum dibuka')
@section('body')
<div class="container narrow center">
  <div style="font-size:44px">🔒</div>
  <h1 class="mt2">{{ $title ?? 'Minggu '.$week.' belum dibuka' }}</h1>
  <p class="muted mt2">{{ $body ?? 'Dosen belum membuka materi minggu ini. Silakan cek kembali sesuai jadwal perkuliahan.' }}</p>
  <a class="btn btn-cta mt3" href="{{ route('course.show', $class) }}">Kembali ke daftar materi</a>
</div>
@endsection
