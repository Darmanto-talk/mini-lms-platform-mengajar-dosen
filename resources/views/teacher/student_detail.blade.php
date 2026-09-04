@extends('layouts.app')
@section('title', $student->name.' — Progres')
@section('body')
<div class="container wide">
  <div class="small muted"><a href="{{ route('teacher.students.index', $class) }}">&larr; {{ $class->name }} — Mahasiswa &amp; nilai</a></div>

  @if ($removedAt)
    <div class="callout mt2" style="border-color:var(--danger); background:#fdeeee">
      <strong>Mahasiswa ini sudah dikeluarkan</strong> dari {{ $class->name }} pada
      {{ \Illuminate\Support\Carbon::parse($removedAt)->translatedFormat('d M Y, H:i') }}.
      Riwayat &amp; nilai di bawah tetap tersimpan apa adanya. Ia tidak bisa mengakses materi kelas ini lagi
      sampai didaftarkan kembali.
    </div>
  @endif

  <div class="row between mt2">
    <div>
      <div class="eyebrow">{{ $class->course->name }} &middot; {{ $class->name }}</div>
      <h1 class="mt1">{{ $student->name }}</h1>
      <p class="muted small mt1">{{ $student->nim ?: 'NIM —' }} &middot; {{ $student->email }}</p>
    </div>
    <div class="card" style="min-width:170px; text-align:center">
      <div class="big-pct">{{ $detail['percent'] }}%</div>
      <div class="small muted">progres (minggu terbuka)</div>
      <div class="mt2"><span class="pill pill-xp">{{ $detail['xp'] }} XP</span></div>
    </div>
  </div>

  <div class="card mt3">
    <div class="eyebrow">Kelola akun</div>

    @if (($pw = session('pw_reset')) && (int) $pw['user_id'] === $student->id)
      <div class="pw-reveal mt2">
        <div class="pw-reveal-head">🔑 Sandi sementara untuk {{ $pw['name'] }}</div>
        <div class="pw-reveal-row">
          <code class="pw-reveal-code" id="pwRevealCode">{{ $pw['temp'] }}</code>
          <button type="button" class="btn btn-sm btn-soft" data-copy="pwRevealCode">Salin</button>
        </div>
        <p class="small muted mt1">
          Sampaikan ke mahasiswa lewat jalur pribadi (WA/email — bukan grup terbuka), lalu minta ia
          menggantinya di menu Profil. Sandi ini <strong>tidak akan tampil lagi</strong> setelah halaman ini
          ditinggalkan/dimuat ulang — reset ulang bila belum sempat dicatat.
        </p>
      </div>
    @endif

    <div class="row mt2" style="gap:8px; flex-wrap:wrap">
      <form method="post" action="{{ route('teacher.students.reset', [$class, $student]) }}">
        @csrf
        <button class="btn btn-sm btn-warn" type="submit"
          data-confirm="Setel ulang kata sandi {{ $student->name }} ke sandi sementara acak? Sandi lama tidak bisa dikembalikan.">
          Reset kata sandi
        </button>
      </form>
      @if ($removedAt)
        <form method="post" action="{{ route('teacher.students.rejoin', [$class, $student]) }}">
          @csrf
          <button class="btn btn-sm btn-cta" type="submit"
            data-confirm="Daftarkan kembali {{ $student->name }} ke kelas {{ $class->name }}?">
            Daftarkan kembali ke kelas
          </button>
        </form>
      @else
        <form method="post" action="{{ route('teacher.students.destroy', [$class, $student]) }}">
          @csrf @method('delete')
          <button class="btn btn-sm btn-danger" type="submit"
            data-confirm="Keluarkan {{ $student->name }} dari kelas {{ $class->name }}? Riwayat & nilainya tetap tersimpan dan bisa didaftarkan kembali kapan saja.">
            Keluarkan dari kelas
          </button>
        </form>
      @endif
    </div>
    <p class="small muted mt2">
      Kata sandi tersimpan terenkripsi (hash) — sandi lama tidak bisa ditampilkan ulang, hanya bisa disetel ulang.
      "Reset kata sandi" membuat sandi sementara baru untuk disampaikan ke mahasiswa; ia bisa menggantinya sendiri lewat menu Profil.
    </p>

    <div class="danger-zone mt3">
      <details>
        <summary class="btn btn-sm btn-danger">Hapus akun mahasiswa ini dari platform</summary>
        <div class="mt2">
          <p class="small" style="color:var(--danger)">
            <strong>Permanen &amp; tidak bisa dibatalkan.</strong> Menghapus akun ini ikut menghapus keikutsertaan
            di semua kelas Anda, seluruh pengumpulan tugas, percobaan kuis, dan riwayat progresnya. Gunakan ini
            hanya untuk mahasiswa yang sudah selesai/keluar dan tidak akan memakai platform lagi
            (mis. sudah lulus setelah 16 pertemuan).
          </p>
          <form method="post" action="{{ route('teacher.students.account', $student) }}" class="mt2" data-confirm-name>
            @csrf @method('delete')
            <input type="hidden" name="back" value="{{ route('teacher.students.index', $class) }}">
            <label class="field"><span>Ketik email <strong class="mono">{{ $student->email }}</strong> untuk konfirmasi</span>
              <input type="text" name="confirm" autocomplete="off" data-match="{{ $student->email }}" required>
            </label>
            <button class="btn btn-del" type="submit" disabled>Hapus akun permanen</button>
          </form>
        </div>
      </details>
    </div>
  </div>

  @foreach ($detail['weeks'] as $w)
    <div class="card mt3">
      <div class="row between">
        <h3 class="mt0">Minggu {{ $w['number'] }} — {{ $w['title'] }}</h3>
        <span class="week-status {{ $w['is_open'] ? 'open' : 'closed' }}">{{ $w['is_open'] ? 'Terbuka' : 'Tertutup' }}</span>
      </div>
      @if ($w['is_open'])
        @php $wp = $w['open_total'] ? (int) round($w['done'] / $w['open_total'] * 100) : 0; @endphp
        <div class="sd-wprog mt1">
          <div class="bar" style="flex:1"><span style="width: {{ $wp }}%"></span></div>
          <span class="small muted">{{ $w['done'] }}/{{ $w['open_total'] }} selesai</span>
        </div>
      @endif

      <div class="mt2" style="display:flex; flex-direction:column; gap:8px">
        @forelse ($w['lessons'] as $l)
          <div class="sd-lesson {{ $l['done'] ? 'is-done' : '' }}">
            <div class="row" style="gap:10px; align-items:flex-start">
              <span class="sd-mark {{ $l['done'] ? 'done' : '' }}">
                @if ($l['done'])<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>@endif
              </span>
              <span style="flex:1; min-width:0">
                <span style="font-size:13.5px; font-weight:600">{{ $l['title'] }}</span>
                <span class="sd-status">
                  @if ($l['done'])
                    Selesai dibaca @if ($l['completed_at']) &middot; {{ \Illuminate\Support\Carbon::parse($l['completed_at'])->translatedFormat('d M Y, H:i') }} @endif
                  @else
                    Belum dibuka mahasiswa
                  @endif
                </span>
              </span>
              @if ($l['done'])<span class="rs-done"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Selesai</span>@endif
              <span class="tag tag-{{ $l['type'] }}">{{ $l['type_label'] }}</span>
            </div>

            @if ($l['type'] === 'kuis' && !empty($l['quiz_attempts']))
              <div class="small muted mt1" style="padding-left:28px">
                @foreach ($l['quiz_attempts'] as $i => $a)
                  Percobaan {{ $i + 1 }}:
                  @if ($a->submitted_at)
                    <strong>{{ $a->score }}</strong> @if($a->passed)<span class="pill pill-ok">lulus</span>@else<span class="pill pill-bad">belum</span>@endif
                  @else <em>belum dikumpulkan</em> @endif<br>
                @endforeach
              </div>
            @elseif ($l['type'] === 'kuis')
              <div class="small muted mt1" style="padding-left:28px">Belum mengerjakan kuis.</div>
            @endif

            @if ($l['type'] === 'tugas')
              @php $s = $l['submission'] ?? null; @endphp
              <div class="small mt1" style="padding-left:28px">
                @if (! $s)<span class="muted">Belum mengumpulkan tugas.</span>
                @else
                  Dikumpulkan {{ \Illuminate\Support\Carbon::parse($s->submitted_at)->format('d/m/Y H:i') }}.
                  @if ($s->graded_at)Nilai <strong>{{ $s->score }}/{{ $l['max_score'] }}</strong>.
                  @elseif ($s->ai_status === 'done')<span class="pill">AI: {{ $s->ai_score }}</span> (belum ditinjau dosen)
                  @else <span class="pill">belum dinilai</span> @endif
                  <a href="{{ route('teacher.grade.index', [$class, $l['id']]) }}">buka penilaian &rarr;</a>
                @endif
              </div>
            @endif
          </div>
        @empty
          <span class="small muted">Belum ada pelajaran.</span>
        @endforelse
      </div>
    </div>
  @endforeach
</div>
@endsection
