@php use App\Support\LessonContent; @endphp
<div class="mt4">
  <h2 style="margin:0">Pengumpulan tugas</h2>
  <p class="hint">Nilai maksimum: {{ $lesson->max_score }}</p>

  @if ($submission)
    <div class="submission-box mt2">
      <div class="row between">
        <strong>Jawaban Anda</strong>
        @if ($graded)
          <span class="pill {{ $submission->score >= $lesson->max_score * 0.6 ? 'pill-ok' : 'pill-bad' }}">Nilai: {{ $submission->score }} / {{ $lesson->max_score }}</span>
        @elseif (in_array($submission->ai_status, ['processing', 'pending']))
          <span class="pill">🤖 AI sedang memeriksa…</span>
        @elseif ($submission->ai_status === 'done')
          <span class="pill pill-ok">Nilai sementara (AI): {{ $submission->ai_score }} / {{ $lesson->max_score }}</span>
        @else
          <span class="pill">Menunggu penilaian</span>
        @endif
      </div>
      <p class="small muted mt1">Dikumpulkan: {{ $submission->submitted_at->format('d M Y H:i') }}</p>
      @if ($submission->text)<div class="prose mt2">{!! LessonContent::render($submission->text) !!}</div>@endif
      @if ($submission->link_url)<p class="mt1"><a href="{{ $submission->link_url }}" target="_blank" rel="noopener">{{ $submission->link_url }}</a></p>@endif
      @if ($submission->file_path)<p class="mt1"><a class="btn btn-sm" href="{{ asset('storage/'.$submission->file_path) }}" target="_blank">Unduh berkas terkirim</a></p>@endif

      @php $shownFeedback = $graded ? $submission->feedback : ($submission->ai_status === 'done' ? $submission->ai_feedback : null); @endphp
      @if ($shownFeedback)
        <div class="callout mt2">
          <strong>{{ $graded ? 'Catatan dosen:' : 'Umpan balik AI (sementara, menunggu tinjauan dosen):' }}</strong>
          {!! nl2br(e($shownFeedback)) !!}
        </div>
      @endif
      @if (! $graded && $submission->ai_status === 'done' && is_array($submission->ai_breakdown) && count($submission->ai_breakdown))
        <div class="table-wrap mt2"><table class="data">
          <thead><tr><th>Aspek</th><th>Skor</th><th>Catatan</th></tr></thead>
          <tbody>
            @foreach ($submission->ai_breakdown as $b)
              <tr><td>{{ $b['aspek'] ?? '-' }}</td><td>{{ $b['skor'] ?? '-' }}@isset($b['maks'])/{{ $b['maks'] }}@endisset</td><td>{{ $b['catatan'] ?? '' }}</td></tr>
            @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
  @endif

  @if (! $graded)
    <form method="post" action="{{ route('assignment.submit', $lesson) }}" enctype="multipart/form-data" class="mt3 submission-box">
      @csrf
      <p class="small muted mt0">Isi minimal salah satu: jawaban teks, tautan, atau berkas.</p>
      <label class="field"><span>Jawaban / penjelasan</span>
        <textarea name="text" placeholder="Tulis jawaban di sini (boleh pakai blok kode ```dart ... ```)">{{ old('text', $submission->text ?? '') }}</textarea>
      </label>
      <label class="field"><span>Tautan <span class="muted">(opsional — GitHub, Google Drive, dll.)</span></span>
        <input type="url" name="link_url" value="{{ old('link_url', $submission->link_url ?? '') }}" placeholder="https://...">
      </label>
      <div class="field">
        <span>Unggah berkas <span class="muted">(opsional)</span></span>
        <input type="file" name="file" class="mt1" accept=".pdf,.zip,.rar,.dart,.txt,.md,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg">
        <div class="hint">Format: pdf, zip, rar, dart, txt, md, doc(x), ppt(x), gambar. Maks 10 MB.
          @if ($submission?->file_path)
            <br>Berkas saat ini: <a href="{{ asset('storage/'.$submission->file_path) }}" target="_blank">{{ basename($submission->file_path) }}</a> — unggah lagi untuk menggantinya.
          @endif
        </div>
      </div>
      <button class="btn btn-cta" type="submit">{{ $submission ? 'Perbarui pengumpulan' : 'Kumpulkan tugas' }}</button>
    </form>
  @endif
</div>
