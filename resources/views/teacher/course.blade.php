@extends('layouts.app')
@section('title', $course->name)
@section('body')
<div class="container wide">
  <div class="row between">
    <div><div class="eyebrow">Mata kuliah</div><h1 class="mt1">{{ $course->name }}</h1></div>
    <div class="row" style="gap:8px">
      <a class="btn btn-sm btn-soft" href="{{ route('teacher.scheme.edit', $course) }}">Komposisi nilai</a>
      <a class="btn btn-sm" href="{{ route('teacher.courses.export', $course) }}">Ekspor JSON</a>
      @if ($classes->count())<a class="btn btn-sm btn-dark" href="{{ route('course.show', $classes->first()) }}" target="_blank">Pratinjau &nearr;</a>@endif
    </div>
  </div>

  <div class="grid-2 mt3">
    <div>
      <div class="row between">
        <h2>Struktur perkuliahan</h2>
        <div class="row" style="gap:6px">
          <form method="post" action="{{ route('teacher.weeks.bulk', $course) }}" style="display:inline">@csrf<input type="hidden" name="action" value="open"><button class="btn btn-sm" data-confirm="Buka semua minggu?">Buka semua</button></form>
          <form method="post" action="{{ route('teacher.weeks.bulk', $course) }}" style="display:inline">@csrf<input type="hidden" name="action" value="close"><button class="btn btn-sm" data-confirm="Tutup semua minggu?">Tutup semua</button></form>
        </div>
      </div>
      <p class="muted small mt1">Materi tampil di semua kelas. Mahasiswa hanya bisa membuka minggu yang <strong>terbuka</strong>.</p>

      <div class="weeks mt2">
        @foreach ($course->weeks as $w)
        <details class="week">
          <summary class="week-head">
            <span style="flex:1; min-width:0">
              <span class="wk-label">Minggu {{ $w->number }}@if($w->is_exam) · Ujian @endif</span>
              <span class="wk-title" style="display:block">{{ $w->title }}</span>
            </span>
            <span class="week-status {{ $w->is_open ? 'open' : 'closed' }}">{{ $w->is_open ? 'Terbuka' : 'Tertutup' }}</span>
            <span class="pill">{{ $w->lessons->count() }} pelajaran</span>
            <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9aa9b6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </summary>
          <div class="week-body">
            <div class="week-toolbar">
              <form method="post" action="{{ route('teacher.weeks.toggle', $w) }}">@csrf
                <button class="btn btn-sm {{ $w->is_open ? 'btn-danger' : 'btn-cta' }}">{{ $w->is_open ? 'Tutup minggu ini' : 'Buka untuk mahasiswa' }}</button>
              </form>
              <span class="spacer"></span>
              <a class="btn btn-sm btn-cta" href="{{ route('teacher.lessons.create', $w) }}">+ Pelajaran</a>
              <button type="button" class="btn btn-sm btn-warn" data-toggle="wep-{{ $w->id }}">Edit minggu</button>
            </div>

            <div class="week-edit-panel" id="wep-{{ $w->id }}" hidden>
              <form method="post" action="{{ route('teacher.weeks.save', $course) }}">@csrf
                <input type="hidden" name="week_id" value="{{ $w->id }}">
                <div class="row" style="gap:12px; align-items:flex-end">
                  <label class="field" style="width:92px; margin:0"><span>Nomor</span><input type="number" name="number" value="{{ $w->number }}" min="1"></label>
                  <label class="field" style="flex:1; min-width:160px; margin:0"><span>Judul minggu</span><input type="text" name="title" value="{{ $w->title }}" required></label>
                </div>
                <label class="field mt2"><span>Ringkasan <span class="muted">(opsional)</span></span><textarea name="summary" rows="2">{{ $w->summary }}</textarea></label>
                <label class="row" style="gap:8px; margin:2px 0 0"><input type="checkbox" name="is_exam" value="1" @checked($w->is_exam) style="width:auto"> <span>Tandai sebagai minggu ujian</span></label>
                <div class="row between mt3" style="gap:10px">
                  <button class="btn btn-sm btn-cta" type="submit">Simpan perubahan</button>
                  <button class="btn btn-sm btn-del" type="submit" form="wdel-{{ $w->id }}" data-confirm="Hapus Minggu {{ $w->number }} &quot;{{ $w->title }}&quot; beserta {{ $w->lessons->count() }} pelajarannya? Tidak bisa dibatalkan.">Hapus minggu ini</button>
                </div>
              </form>
              <form method="post" id="wdel-{{ $w->id }}" action="{{ route('teacher.weeks.destroy', $w) }}">@csrf @method('delete')</form>
            </div>

            <div class="week-lessons">
              @forelse ($w->lessons as $l)
                <div class="lesson-row">
                  <span class="ln-head">
                    <span class="tag tag-{{ $l->type }}">{{ $l->typeLabel() }}</span>
                    <span class="ln-title">{{ $l->title }}</span>
                  </span>
                  <span class="lesson-row-acts">
                    <a class="btn btn-sm btn-soft" href="{{ route('teacher.lessons.preview', $l) }}">Preview</a>
                    <a class="btn btn-sm btn-dark" href="{{ route('teacher.lessons.present', $l) }}" target="_blank">Present &nearr;</a>
                    <a class="btn btn-sm btn-warn" href="{{ route('teacher.lessons.edit', $l) }}">Edit</a>
                    @if ($l->type === 'tugas' && $classes->count())
                      <a class="btn btn-sm btn-view" href="{{ route('teacher.grade.index', [$classes->first(), $l]) }}">Nilai &amp; jawaban</a>
                    @elseif ($l->type === 'kuis' && $classes->count())
                      <a class="btn btn-sm btn-view" href="{{ route('teacher.quiz.results', [$classes->first(), $l]) }}">Hasil &amp; jawaban</a>
                    @endif
                    <form method="post" action="{{ route('teacher.lessons.destroy', $l) }}" style="display:inline">
                      @csrf @method('delete')
                      <button class="btn btn-sm btn-del" data-confirm="Hapus pelajaran &quot;{{ $l->title }}&quot;? Tindakan ini tidak bisa dibatalkan.">Hapus</button>
                    </form>
                  </span>
                </div>
              @empty
                <p class="small muted" style="padding:10px 12px 4px">Belum ada pelajaran di minggu ini.</p>
              @endforelse
            </div>
          </div>
        </details>
        @endforeach
        @if (! $course->weeks->count())<div class="card"><p class="muted mt0">Belum ada minggu. Tambahkan di samping.</p></div>@endif
      </div>
    </div>

    <div>
      <div class="card">
        <h3 class="mt0">Kelas ({{ $classes->count() }})</h3>
        <div class="mt2" style="display:flex; flex-direction:column; gap:12px">
          @foreach ($classes as $c)
            <div class="klass-card klass-card--{{ $loop->index % 6 }}">
              <div class="row between"><strong><span class="kc-dot"></span>{{ $c->name }}</strong><span class="pill pill-xp">{{ $c->enrollments_count }} mhs</span></div>
              <p class="small mt1">Kode gabung: <strong class="mono">{{ $c->code }}</strong></p>
              <a class="btn btn-cta klass-primary" href="{{ route('teacher.students.index', $c) }}">Mahasiswa &amp; nilai &rarr;</a>
              <div class="klass-sub">
                <button type="button" class="btn btn-sm btn-warn" data-toggle="krp-{{ $c->id }}">Ubah nama</button>
                @if ($classes->count() > 1)
                  <form method="post" action="{{ route('teacher.classes.destroy', $c) }}">@csrf @method('delete')
                    <button class="btn btn-sm btn-del" data-confirm="Hapus kelas &quot;{{ $c->name }}&quot;? Semua mahasiswa, progres, dan nilai di kelas ini ikut terhapus permanen.">Hapus kelas</button>
                  </form>
                @else
                  <span class="klass-note small muted">Kelas terakhir — hapus lewat "Info mata kuliah"</span>
                @endif
              </div>
              <form method="post" action="{{ route('teacher.classes.rename', $c) }}" class="klass-rename-panel" id="krp-{{ $c->id }}" hidden>@csrf @method('put')
                <div class="row" style="gap:6px">
                  <input type="text" name="name" value="{{ $c->name }}" required style="flex:1">
                  <button class="btn btn-sm btn-cta" type="submit">Simpan</button>
                </div>
              </form>
            </div>
          @endforeach
        </div>
        <form method="post" action="{{ route('teacher.classes.store', $course) }}" class="mt3">@csrf
          <label class="field"><span>Tambah kelas</span><input type="text" name="name" required placeholder="mis. Kelas B"></label>
          <button class="btn btn-cta" type="submit" style="width:100%; justify-content:center">Tambah kelas</button>
        </form>
      </div>

      <div class="card">
        <h3 class="mt0">Tambah minggu</h3>
        <form method="post" action="{{ route('teacher.weeks.save', $course) }}" class="mt2">@csrf
          <input type="hidden" name="week_id" value="">
          <div class="row" style="gap:10px">
            <label class="field" style="width:90px"><span>Nomor</span><input type="number" name="number" value="{{ $course->weeks->count() + 1 }}" min="1"></label>
            <label class="field" style="flex:1"><span>Judul</span><input type="text" name="title" required></label>
          </div>
          <label class="field"><span>Ringkasan <span class="muted">(opsional)</span></span><textarea name="summary"></textarea></label>
          <label class="row" style="gap:8px"><input type="checkbox" name="is_exam" value="1" style="width:auto"> <span>Minggu ujian</span></label>
          <button class="btn btn-cta mt2" type="submit" style="width:100%; justify-content:center">Tambah minggu</button>
        </form>
      </div>

      <div class="card">
        <h3 class="mt0">Info mata kuliah</h3>
        <form method="post" action="{{ route('teacher.courses.update', $course) }}" class="mt2">@csrf @method('put')
          <label class="field"><span>Nama</span><input type="text" name="name" value="{{ $course->name }}" required></label>
          <label class="field"><span>Deskripsi</span><textarea name="description">{{ $course->description }}</textarea></label>
          <button class="btn mt2" type="submit">Simpan</button>
        </form>

        <div class="danger-zone mt3">
          <details>
            <summary class="btn btn-sm btn-danger">Hapus mata kuliah ini</summary>
            <div class="mt2">
              <p class="small" style="color:var(--danger)">
                <strong>Permanen &amp; tidak bisa dibatalkan.</strong> Menghapus mata kuliah ikut menghapus
                <strong>{{ $course->weeks->count() }} minggu</strong>,
                <strong>{{ $course->weeks->sum(fn ($w) => $w->lessons->count()) }} pelajaran</strong>,
                <strong>{{ $classes->count() }} kelas</strong>, semua kuis, tugas, nilai, dan progres mahasiswa.
              </p>
              <form method="post" action="{{ route('teacher.courses.destroy', $course) }}" class="mt2" data-confirm-name>
                @csrf @method('delete')
                <label class="field"><span>Ketik <strong class="mono">{{ $course->name }}</strong> untuk konfirmasi</span>
                  <input type="text" name="confirm" autocomplete="off" data-match="{{ $course->name }}" required>
                </label>
                <button class="btn btn-del" type="submit" disabled>Hapus mata kuliah permanen</button>
              </form>
            </div>
          </details>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
// panel toggle (edit minggu, ubah nama kelas, dsb.)
document.querySelectorAll('[data-toggle]').forEach(function (b) {
  b.addEventListener('click', function () {
    var el = document.getElementById(b.getAttribute('data-toggle'));
    if (!el) return;
    el.hidden = !el.hidden;
    b.classList.toggle('is-active', !el.hidden);
    if (!el.hidden) { var f = el.querySelector('input,textarea'); if (f) f.focus(); }
  });
});
</script>
@endpush
@endsection
