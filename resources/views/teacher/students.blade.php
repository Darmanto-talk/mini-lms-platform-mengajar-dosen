@extends('layouts.app')
@section('title', $class->name.' — Nilai')
@section('body')
<div class="container wide">
  <div class="row between">
    <div><div class="eyebrow">{{ $class->course->name }}</div><h1 class="mt1">{{ $class->name }} — Mahasiswa &amp; nilai</h1></div>
    <div class="row" style="gap:8px">
      <a class="btn btn-sm btn-cta" href="{{ route('teacher.gradebook.show', $class) }}">Rekap Nilai Akhir</a>
      <a class="btn btn-sm btn-view" href="{{ route('teacher.monitor', $class) }}">Monitor pengerjaan</a>
      <a class="btn btn-sm btn-soft" href="{{ route('teacher.students.import.form', $class) }}">Impor mahasiswa (Excel)</a>
      <a class="btn btn-sm" href="{{ route('teacher.courses.show', $class->course) }}">Kelola materi</a>
      <a class="btn btn-sm" href="{{ route('teacher.grades.csv', $class) }}">Ekspor progres</a>
    </div>
  </div>
  <p class="muted small mt2">Kode gabung: <strong class="mono">{{ $class->code }}</strong> &middot; {{ count($rows) }} mahasiswa.
     Tabel ini rekap kuis &amp; tugas otomatis. <strong>Nilai akhir resmi</strong> (berbobot: aktivitas, presentasi, laporan, dll.) ada di
     <a href="{{ route('teacher.gradebook.show', $class) }}">Rekap Nilai Akhir</a>.</p>

  <div class="card mt3 table-wrap">
    <table class="data">
      <thead><tr><th>NIM</th><th>Nama</th><th>Progres</th><th>XP</th><th>Rata2 kuis</th><th>Rata2 tugas</th><th>Rata2 gabungan</th><th></th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          <tr>
            <td class="mono">{{ $r['nim'] ?: '—' }}</td>
            <td><a href="{{ route('teacher.students.show', [$class, $r['id']]) }}"><strong>{{ $r['name'] }}</strong></a><div class="small muted">{{ $r['email'] }}</div></td>
            <td><div class="bar" style="width:110px"><span style="width: {{ $r['percent'] }}%"></span></div><span class="small muted">{{ $r['percent'] }}% · {{ $r['done'] }}/{{ $r['total'] }}</span></td>
            <td><strong>{{ $r['xp'] }}</strong></td>
            <td>@if($r['nilai_kuis'] === null)<span class="muted">—</span>@else<strong>{{ $r['nilai_kuis'] }}</strong>@endif<div class="small muted">{{ count($r['quizzes']) }} kuis</div></td>
            <td>@if($r['nilai_tugas'] === null)<span class="muted">—</span>@else<strong>{{ $r['nilai_tugas'] }}</strong>@endif<div class="small muted">{{ $r['assignments']->whereNotNull('score')->count() }} dinilai</div></td>
            <td>@if($r['nilai_akhir'] === null)<span class="muted">—</span>@else<strong style="font-size:15px">{{ $r['nilai_akhir'] }}</strong>@endif</td>
            <td>
              <div class="row" style="gap:6px">
                <a class="btn btn-sm btn-soft" href="{{ route('teacher.students.show', [$class, $r['id']]) }}">Detail</a>
                <form method="post" action="{{ route('teacher.students.destroy', [$class, $r['id']]) }}">@csrf @method('delete')
                  <button class="btn btn-sm btn-danger" data-confirm="Keluarkan {{ $r['name'] }} dari kelas?">Keluarkan</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="muted center">Belum ada mahasiswa yang bergabung.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($alumni->isNotEmpty())
    <details class="mt3">
      <summary class="btn btn-sm btn-soft">Mahasiswa yang dikeluarkan ({{ $alumni->count() }})</summary>
      <div class="card mt2 table-wrap">
        <p class="small muted mt0">Riwayat &amp; nilai mereka tetap tersimpan — bisa dilihat lagi atau didaftarkan kembali ke kelas ini kapan saja.</p>
        <table class="data">
          <thead><tr><th>NIM</th><th>Nama</th><th>Dikeluarkan</th><th></th></tr></thead>
          <tbody>
            @foreach ($alumni as $a)
              <tr>
                <td class="mono">{{ $a->nim ?: '—' }}</td>
                <td><strong>{{ $a->name }}</strong><div class="small muted">{{ $a->email }}</div></td>
                <td class="small muted">{{ \Illuminate\Support\Carbon::parse($a->pivot->removed_at)->translatedFormat('d M Y') }}</td>
                <td>
                  <div class="row" style="gap:6px">
                    <a class="btn btn-sm btn-soft" href="{{ route('teacher.students.show', [$class, $a->id]) }}">Detail</a>
                    <form method="post" action="{{ route('teacher.students.rejoin', [$class, $a->id]) }}">@csrf
                      <button class="btn btn-sm btn-cta" data-confirm="Daftarkan kembali {{ $a->name }} ke kelas {{ $class->name }}?">Daftarkan kembali</button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </details>
  @endif
</div>
@endsection
