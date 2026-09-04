@extends('layouts.app')
@section('title', 'Komposisi Nilai — '.$course->name)
@section('body')
@php $total = $components->sum('weight'); @endphp
<div class="container md9">
  <div class="small muted"><a href="{{ route('teacher.courses.show', $course) }}">&larr; {{ $course->name }}</a></div>
  <h1 class="mt2">Komposisi Nilai Akhir</h1>
  <p class="muted small mt1">Nilai akhir tiap mahasiswa = jumlah (skor komponen × bobot%). Berlaku untuk semua kelas mata kuliah ini.</p>

  <form method="post" action="{{ route('teacher.scheme.update', $course) }}">
    @csrf @method('put')

    <div class="card mt3">
      <div class="row between">
        <h3 class="mt0">Komponen penilaian</h3>
        <span class="pill {{ abs($total - 100) < 0.01 ? 'pill-ok' : 'pill-bad' }}" id="weightTotal">Total bobot: {{ rtrim(rtrim(number_format($total,2),'0'),'.') }}%</span>
      </div>
      @php
        $srcOpts = ['manual'=>'Input manual (dosen)','progress'=>'Otomatis: % membaca materi','quiz_avg'=>'Otomatis: rata-rata kuis','tugas_avg'=>'Otomatis: rata-rata tugas','lesson'=>'Otomatis: nilai 1 kuis/tugas'];
      @endphp
      <div class="table-wrap mt2">
        <table class="data" id="compTable">
          <thead><tr><th style="width:34%">Nama komponen</th><th style="width:100px">Bobot (%)</th><th>Sumber nilai</th><th>Pelajaran (jika "nilai 1 kuis/tugas")</th><th style="width:60px"></th></tr></thead>
          <tbody>
            @forelse ($components as $c)
              <tr>
                <td><input type="hidden" name="comp_id[]" value="{{ $c->id }}"><input type="text" name="comp_name[]" value="{{ $c->name }}" required></td>
                <td><input type="number" name="comp_weight[]" value="{{ rtrim(rtrim(number_format($c->weight,2),'0'),'.') }}" step="0.01" min="0" max="100" class="w-num"></td>
                <td>
                  <select name="comp_source[]" class="src-sel">
                    @foreach ($srcOpts as $v => $lbl)<option value="{{ $v }}" @selected($c->source===$v)>{{ $lbl }}</option>@endforeach
                  </select>
                </td>
                <td>
                  <select name="comp_lesson[]" class="lesson-sel" {{ $c->source==='lesson' ? '' : 'disabled' }}>
                    <option value="">— pilih —</option>
                    @foreach ($lessons as $l)<option value="{{ $l->id }}" @selected($c->lesson_id==$l->id)>M{{ $l->week->number }} · {{ $l->title }} ({{ $l->type }})</option>@endforeach
                  </select>
                </td>
                <td><button type="button" class="btn btn-sm btn-danger rm-row">Hapus</button></td>
              </tr>
            @empty
            @endforelse
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm mt2" id="addComp">+ Tambah komponen</button>
      <p class="hint">Aktivitas mahasiswa <strong>otomatis terkonversi</strong>: pilih sumber "% membaca materi", "rata-rata kuis", "rata-rata tugas", atau kunci ke satu kuis/tugas tertentu (mis. "Presentasi Akhir" → tugas presentasi). Sisanya (mis. "Aktivitas", "Life Skills") tetap input manual di halaman <em>Rekap Nilai Akhir</em>.</p>
    </div>

    <div class="card mt3">
      <h3 class="mt0">Kelulusan &amp; Grade</h3>
      <label class="field" style="max-width:220px"><span>Nilai minimal lulus</span>
        <input type="number" name="pass_min" value="{{ $course->pass_min }}" min="0" max="100">
      </label>

      <div class="table-wrap mt2" style="max-width:360px">
        <table class="data" id="bandTable">
          <thead><tr><th>Nilai &ge;</th><th>Grade</th><th></th></tr></thead>
          <tbody>
            @foreach ($bands as $b)
              <tr>
                <td><input type="number" name="band_min[]" value="{{ $b['min'] }}" min="0" max="100" class="w-num"></td>
                <td><input type="text" name="band_letter[]" value="{{ $b['letter'] }}" maxlength="5" style="width:70px"></td>
                <td><button type="button" class="btn btn-sm btn-danger rm-row">×</button></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm mt2" id="addBand">+ Baris grade</button>
    </div>

    <div class="card mt3">
      <label class="row" style="gap:8px"><input type="checkbox" name="publish" value="1" @checked($course->grades_published) style="width:auto">
        <span>Tampilkan nilai akhir &amp; grade ke mahasiswa</span></label>
    </div>

    <div class="mt3"><button class="btn btn-cta" type="submit">Simpan komposisi</button></div>
  </form>
</div>

<template id="compRow">
  <tr>
    <td><input type="hidden" name="comp_id[]" value=""><input type="text" name="comp_name[]" required></td>
    <td><input type="number" name="comp_weight[]" value="0" step="0.01" min="0" max="100" class="w-num"></td>
    <td><select name="comp_source[]" class="src-sel">
      <option value="manual">Input manual (dosen)</option>
      <option value="progress">Otomatis: % membaca materi</option>
      <option value="quiz_avg">Otomatis: rata-rata kuis</option>
      <option value="tugas_avg">Otomatis: rata-rata tugas</option>
      <option value="lesson">Otomatis: nilai 1 kuis/tugas</option>
    </select></td>
    <td><select name="comp_lesson[]" class="lesson-sel" disabled><option value="">— pilih —</option>
      @foreach ($lessons as $l)<option value="{{ $l->id }}">M{{ $l->week->number }} · {{ $l->title }} ({{ $l->type }})</option>@endforeach
    </select></td>
    <td><button type="button" class="btn btn-sm btn-danger rm-row">Hapus</button></td>
  </tr>
</template>
<template id="bandRow">
  <tr>
    <td><input type="number" name="band_min[]" value="0" min="0" max="100" class="w-num"></td>
    <td><input type="text" name="band_letter[]" maxlength="5" style="width:70px"></td>
    <td><button type="button" class="btn btn-sm btn-danger rm-row">×</button></td>
  </tr>
</template>

<script>
(function () {
  function wire(addBtnId, tplId, tableId) {
    var tpl = document.getElementById(tplId), tbody = document.querySelector('#' + tableId + ' tbody');
    document.getElementById(addBtnId).addEventListener('click', function () {
      tbody.appendChild(tpl.content.cloneNode(true));
    });
  }
  wire('addComp', 'compRow', 'compTable');
  wire('addBand', 'bandRow', 'bandTable');
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('rm-row')) e.target.closest('tr').remove();
    recalc();
  });
  document.addEventListener('input', function (e) { if (e.target.name === 'comp_weight[]') recalc(); });
  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('src-sel')) {
      var ls = e.target.closest('tr').querySelector('.lesson-sel');
      if (ls) ls.disabled = e.target.value !== 'lesson';
    }
  });
  function recalc() {
    var t = 0;
    document.querySelectorAll('input[name="comp_weight[]"]').forEach(function (i) { t += parseFloat(i.value) || 0; });
    var el = document.getElementById('weightTotal');
    el.textContent = 'Total bobot: ' + (Math.round(t * 100) / 100) + '%';
    el.className = 'pill ' + (Math.abs(t - 100) < 0.01 ? 'pill-ok' : 'pill-bad');
  }
})();
</script>
@endsection
