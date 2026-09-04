@extends('layouts.app')
@section('title', 'Hasil impor mahasiswa — '.$class->name)
@section('body')
@php
  $labels = [
    'dibuat' => ['Akun baru', 'ok'],
    'didaftarkan' => ['Didaftarkan', 'ok'],
    'dipulihkan' => ['Dipulihkan', 'ok'],
    'sudah' => ['Sudah terdaftar', 'muted'],
    'bentrok' => ['Bentrok kelas', 'bad'],
    'error' => ['Gagal', 'bad'],
  ];
  $baru = collect($results)->whereNotNull('sandi_baru');
@endphp
<div class="container wide">
  <div class="small muted"><a href="{{ route('teacher.students.index', $class) }}">&larr; {{ $class->name }} — Mahasiswa &amp; nilai</a></div>

  <div class="eyebrow mt2">{{ $class->course->name }} &middot; {{ $class->name }}</div>
  <h1 class="mt1">Hasil impor mahasiswa</h1>

  <div class="imp-sum mt3">
    <span class="pill pill-ok">{{ $ringkas['dibuat'] }} akun baru</span>
    <span class="pill pill-ok">{{ $ringkas['didaftarkan'] }} didaftarkan</span>
    <span class="pill pill-ok">{{ $ringkas['dipulihkan'] }} dipulihkan</span>
    <span class="pill">{{ $ringkas['sudah'] }} sudah terdaftar</span>
    <span class="pill pill-bad">{{ $ringkas['bentrok'] }} bentrok kelas</span>
    <span class="pill pill-bad">{{ $ringkas['error'] }} gagal</span>
  </div>

  @if ($baru->isNotEmpty())
    <div class="pw-reveal mt3">
      <div class="pw-reveal-head">🔑 {{ $baru->count() }} akun baru dibuat — sandi hanya tampil di halaman ini</div>
      <p class="small muted mt1" style="margin-bottom:10px">
        Salin dulu sebelum menutup/menyegarkan halaman. Setelah ini sandi tidak bisa ditampilkan ulang —
        kalau terlewat, gunakan tombol <strong>Reset kata sandi</strong> di halaman detail mahasiswa.
      </p>
      <div class="pw-reveal-row">
        <button type="button" class="btn btn-sm btn-cta" data-copy="akunBaruTeks">Salin semua akun &amp; sandi</button>
        <button type="button" class="btn btn-sm btn-soft" onclick="window.print()">Cetak</button>
      </div>
      <pre id="akunBaruTeks" class="code mt2" style="white-space:pre-wrap">@foreach ($baru as $r){{ $r['nama'] }} | {{ $r['email'] }} | {{ $r['sandi_baru'] }}
@endforeach</pre>
    </div>
  @endif

  <div class="card mt3 table-wrap">
    <table class="data">
      <thead><tr><th>Baris</th><th>Nama</th><th>Email</th><th>NIM</th><th>Status</th><th>Sandi sementara</th><th>Keterangan</th></tr></thead>
      <tbody>
        @foreach ($results as $r)
          @php [$label, $tone] = $labels[$r['status']] ?? ['—', 'muted']; @endphp
          <tr>
            <td class="mono small muted">{{ $r['baris'] }}</td>
            <td><strong>{{ $r['nama'] ?: '—' }}</strong></td>
            <td class="small">{{ $r['email'] ?: '—' }}</td>
            <td class="mono small">{{ $r['nim'] ?: '—' }}</td>
            <td><span class="pill {{ $tone === 'ok' ? 'pill-ok' : ($tone === 'bad' ? 'pill-bad' : '') }}">{{ $label }}</span></td>
            <td>@if ($r['sandi_baru'])<code class="mono">{{ $r['sandi_baru'] }}</code>@else<span class="muted">—</span>@endif</td>
            <td class="small muted">{{ $r['pesan'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="row mt3" style="gap:8px">
    <a class="btn btn-cta" href="{{ route('teacher.students.index', $class) }}">Selesai — lihat daftar mahasiswa</a>
    <a class="btn btn-soft" href="{{ route('teacher.students.import.form', $class) }}">Impor berkas lain</a>
  </div>
</div>
@endsection
