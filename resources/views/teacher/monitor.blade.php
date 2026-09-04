@extends('layouts.app')
@section('title', 'Monitor '.$class->name)
@section('body')
<div class="container wide">
  <div class="row between">
    <div><div class="eyebrow">{{ $class->course->name }}</div><h1 class="mt1">{{ $class->name }} — Monitor pengerjaan</h1></div>
    <div class="row" style="gap:8px">
      <a class="btn btn-sm" href="{{ route('teacher.students.index', $class) }}">Nilai &amp; rekap</a>
      <a class="btn btn-sm" href="{{ route('teacher.courses.show', $class->course) }}">Kelola materi</a>
    </div>
  </div>
  <p class="muted small mt2">{{ $studentCount }} mahasiswa di kelas ini. Klik nama tugas/kuis untuk melihat jawaban tiap mahasiswa.</p>

  <div class="card mt3 table-wrap">
    <table class="data">
      <thead><tr><th>Mgg</th><th>Aktivitas</th><th>Tipe</th><th>Sudah mengerjakan</th><th>Keterangan</th><th>Belum mengerjakan</th><th></th></tr></thead>
      <tbody>
        @forelse ($items as $it)
          @php $pct = $it->total ? (int) round($it->done / $it->total * 100) : 0; @endphp
          <tr>
            <td>{{ $it->week }} @if(!$it->is_open)<span class="lock-badge" title="minggu tertutup">🔒</span>@endif</td>
            <td><a href="{{ $it->link }}"><strong>{{ $it->title }}</strong></a></td>
            <td><span class="tag tag-{{ $it->type }}">{{ $it->type_label }}</span></td>
            <td>
              <div class="bar" style="width:130px"><span style="width: {{ $pct }}%"></span></div>
              <span class="small muted"><strong>{{ $it->done }}</strong> / {{ $it->total }} ({{ $pct }}%)</span>
            </td>
            <td class="small muted">{{ $it->extra }}</td>
            <td class="small">
              @if ($it->missing->isEmpty())
                <span class="pill pill-ok">semua sudah</span>
              @else
                <span class="muted">{{ $it->missing->take(6)->join(', ') }}@if($it->missing->count() > 6) +{{ $it->missing->count() - 6 }} lagi @endif</span>
              @endif
            </td>
            <td><a class="btn btn-sm" href="{{ $it->link }}">Lihat jawaban</a></td>
          </tr>
        @empty
          <tr><td colspan="7" class="muted center">Belum ada kuis atau tugas pada mata kuliah ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
