@extends('layouts.app')
@section('title', 'Impor materi (JSON)')
@section('body')
<div class="container md">
  <div class="small muted"><a href="{{ route('teacher.index') }}">&larr; Mata kuliah saya</a></div>
  <h1 class="mt2">Impor / perbarui materi (JSON)</h1>
  <p class="muted mt1">Impor satu file per pertemuan, atau satu file berisi banyak minggu sekaligus.</p>

  <div class="card mt3">
    <form method="post" action="{{ route('teacher.import.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="field">
        <span style="display:block; font-size:13px; font-weight:600; margin-bottom:8px">Tujuan impor</span>
        <div class="imp-modes">
          <label class="imp-mode">
            <input type="radio" name="mode" value="new" checked style="width:auto" data-mode>
            <span>Buat <strong>mata kuliah baru</strong><br><span class="muted small">+ Kelas A dibuat otomatis</span></span>
          </label>
          <label class="imp-mode">
            <input type="radio" name="mode" value="update" style="width:auto" data-mode @disabled($courses->isEmpty())>
            <span><strong>Perbarui</strong> mata kuliah yang ada<br><span class="muted small">tambah / ganti minggu tertentu</span></span>
          </label>
        </div>
      </div>

      <div id="updateOpts" hidden>
        <div class="row" style="gap:14px; align-items:flex-start">
          <label class="field" style="flex:1"><span>Mata kuliah</span>
            <select id="courseSel" name="course">
              @foreach ($courses as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
          </label>
          <label class="field" style="flex:1"><span>Minggu tujuan</span>
            <select id="weekSel" name="week"></select>
          </label>
        </div>
        <div class="hint" style="margin-top:-4px">
          Pilih <strong>satu minggu</strong> → seluruh isi file masuk ke minggu itu (nomor minggu di file diabaikan) —
          cocok untuk <strong>satu file per pertemuan</strong>. Pilih <strong>“Semua minggu sesuai isi file”</strong>
          untuk file berisi banyak minggu (harus punya kolom <code>number</code>).
          Minggu lain <strong>tidak tersentuh</strong>; status selesai &amp; nilai mahasiswa dipertahankan
          untuk pelajaran yang judulnya tidak berubah.
        </div>
      </div>

      <label class="field"><span>File materi (.json)</span><input type="file" name="file" accept="application/json,.json" required></label>
      <button class="btn btn-cta" type="submit">Proses impor</button>
    </form>
  </div>

  <div class="card mt3">
    <h3 class="mt0">Format file</h3>
    <p class="small muted">Untuk <strong>satu pertemuan</strong> cukup daftar pelajaran. Untuk banyak minggu sekaligus, pakai bentuk <code>{ name, weeks:[...] }</code>. Dari mata kuliah yang ada, klik <strong>Ekspor JSON</strong> untuk template.</p>
    <pre class="code" style="font-size:12px">// satu pertemuan (dipakai dengan dropdown "Minggu tujuan")
{
  "title": "Pengantar Perulangan",
  "lessons": [
    { "title": "Konsep for", "type": "materi", "content": "## ...\n\n```dart\nfor (var i = 0; i &lt; 5; i++) print(i);\n```" },
    { "title": "Kuis for", "type": "kuis",
      "quiz": { "pass_score": 70, "questions": [
        { "text": "...", "options": ["a","b"], "answer": 1 } ] } }
  ]
}

// banyak minggu ("Semua minggu sesuai isi file")
{ "name": "Algoritma dan Pemrograman", "weeks": [
  { "number": 1, "title": "...", "is_open": true, "lessons": [ ... ] } ] }</pre>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var WEEKS = @json($courses->mapWithKeys(fn ($c) => [$c->id => $c->weeks->map(fn ($w) => ['n' => $w->number, 't' => $w->title])]));
  var courseSel = document.getElementById('courseSel');
  var weekSel = document.getElementById('weekSel');
  var updateOpts = document.getElementById('updateOpts');

  function fillWeeks() {
    var list = WEEKS[courseSel.value] || [];
    weekSel.innerHTML = '<option value="">Semua minggu sesuai isi file</option>' +
      list.map(function (w) {
        return '<option value="' + w.n + '">Minggu ' + w.n + ' — ' + (w.t || '').replace(/</g, '&lt;') + '</option>';
      }).join('');
    if (!list.length) {
      weekSel.innerHTML += '<option value="" disabled>(mata kuliah ini belum punya minggu)</option>';
    }
  }
  function syncMode() {
    var upd = document.querySelector('input[name=mode]:checked').value === 'update';
    updateOpts.hidden = !upd;
    courseSel.disabled = weekSel.disabled = !upd;
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-mode]'), function (r) {
    r.addEventListener('change', syncMode);
  });
  courseSel && courseSel.addEventListener('change', fillWeeks);
  fillWeeks();
  syncMode();
})();
</script>
@endpush
@endsection
