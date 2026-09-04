@extends('layouts.app')
@section('title', 'Rekap Nilai — '.$class->name)
@section('body')
@php $book = $book; $comps = $book['components']; @endphp
<div class="container wide">
  <div class="row between">
    <div>
      <div class="eyebrow">{{ $class->course->name }}</div>
      <h1 class="mt1">{{ $class->name }} — Rekap Nilai Akhir</h1>
    </div>
    <div class="row" style="gap:8px">
      <a class="btn btn-sm" href="{{ route('teacher.scheme.edit', $class->course) }}">Ubah komposisi</a>
      <a class="btn btn-sm btn-cta" href="{{ route('teacher.gradebook.csv', $class) }}">Ekspor CSV</a>
    </div>
  </div>

  @if (! $book['has_scheme'])
    <div class="callout mt3">
      Komposisi nilai belum diatur. <a href="{{ route('teacher.scheme.edit', $class->course) }}">Atur komponen &amp; bobot dulu</a>
      (mis. Aktivitas 6%, Learning Skill 24%, Presentasi 12%, dst.).
    </div>
  @else
    <p class="muted small mt2">
      Total bobot: <strong>{{ rtrim(rtrim(number_format($book['weight_total'],2),'0'),'.') }}%</strong>
      @if (abs($book['weight_total'] - 100) > 0.01)<span class="pill pill-bad">belum 100%</span>@endif
      &middot; Nilai minimal lulus: <strong>{{ $class->course->pass_min }}</strong>
      &middot; Kolom putih = input manual, kolom abu = otomatis.
    </p>

    <form method="post" action="{{ route('teacher.gradebook.save', $class) }}" class="mt2">
      @csrf
      <div class="card" style="padding:0; overflow:auto">
        <table class="data gradebook">
          <thead>
            <tr>
              <th class="stick s0">No</th>
              <th class="stick s1">NIM</th>
              <th class="stick s2">Nama</th>
              @foreach ($comps as $c)
                <th class="{{ $c->source === 'manual' ? '' : 'auto' }}">
                  {{ $c->name }}<br><span class="wnote">{{ rtrim(rtrim(number_format($c->weight,2),'0'),'.') }}%</span>
                  @if ($c->source !== 'manual')<br><span class="wnote">{{ $c->sourceLabel() }}</span>@endif
                </th>
              @endforeach
              <th>Nilai</th><th>Grade</th><th>Lulus</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($book['rows'] as $i => $r)
              <tr>
                <td class="stick s0">{{ $i + 1 }}</td>
                <td class="stick s1 mono">{{ $r['nim'] ?: '—' }}</td>
                <td class="stick s2">{{ $r['name'] }}</td>
                @foreach ($comps as $c)
                  <td class="{{ $c->source === 'manual' ? '' : 'auto' }}">
                    @if ($c->source === 'manual')
                      <input type="number" step="0.01" min="0" max="100" class="cell-in"
                             name="score[{{ $c->id }}][{{ $r['id'] }}]"
                             value="{{ $r['cells'][$c->id] !== null ? rtrim(rtrim(number_format($r['cells'][$c->id],2),'0'),'.') : '' }}">
                    @else
                      {{ $r['cells'][$c->id] !== null ? rtrim(rtrim(number_format($r['cells'][$c->id],2),'0'),'.') : '—' }}
                    @endif
                  </td>
                @endforeach
                <td><strong>{{ $r['nilai'] !== null ? rtrim(rtrim(number_format($r['nilai'],2),'0'),'.') : '—' }}</strong>
                  @unless ($r['complete'])<br><span class="wnote muted" style="color:var(--muted)">sementara</span>@endunless</td>
                <td><strong>{{ $r['grade'] }}</strong></td>
                <td>
                  @if ($r['lulus'] === null)<span class="muted">—</span>
                  @elseif ($r['lulus'])<span class="pill pill-ok">Lulus</span>
                  @else<span class="pill pill-bad">Tidak</span>@endif
                </td>
              </tr>
            @endforeach
            @if (! count($book['rows']))<tr><td colspan="{{ 6 + $comps->count() }}" class="center muted">Belum ada mahasiswa.</td></tr>@endif
          </tbody>
        </table>
      </div>
      <div class="mt3"><button class="btn btn-cta" type="submit">Simpan nilai</button>
        <span class="hint" style="margin-left:10px">Nilai Grade &amp; Lulus dihitung ulang setelah disimpan.</span></div>
    </form>
  @endif
</div>
@endsection
