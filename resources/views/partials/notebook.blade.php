@php
  use App\Support\Notebook;
  /** @var array $cells */
  $mode = $mode ?? 'student';
  $class = $class ?? null;
  $submission = $submission ?? null;
  $quiz = $quiz ?? null;
  $graded = $graded ?? false;
  $quizStatus = $quizStatus ?? null;
@endphp

@forelse ($cells as $cell)
  @php $t = $cell['type']; @endphp

  @if (in_array($t, Notebook::CONTENT_TYPES, true))
    <section class="nb-card nb-card--{{ $t }}@if ($t === 'callout') nb-card--co-{{ $cell['variant'] ?? 'info' }}@endif">
      <header class="nb-card-head">
        <span class="nb-card-ic" aria-hidden="true">{{ Notebook::cellIcon($cell) }}</span>
        <span class="nb-card-label">{{ Notebook::cellLabel($cell) }}</span>
      </header>
      <div class="nb-card-body">{!! Notebook::renderCell($cell, $mode) !!}</div>
    </section>

  @elseif ($t === 'quiz')
    <section class="nb-card nb-card--quiz">
      <header class="nb-card-head">
        <span class="nb-card-ic" aria-hidden="true">❓</span>
        <span class="nb-card-label">Kuis</span>
      </header>
      <div class="nb-card-body">
        <strong class="nb-card-title">{{ $quiz->title ?? $lesson->title }}</strong>
        @if ($mode === 'student')
          @if ($quiz)
            <p class="small muted mt1">{{ $quiz->questions->count() }} soal &middot; {{ $quiz->time_limit_min }} menit &middot; lulus &ge; {{ $quiz->pass_score }} &middot; maks {{ $quiz->max_attempts }} percobaan</p>
            @if ($quizStatus && $quizStatus['best'] !== null)
              <p class="mt1">Nilai terbaik Anda: <strong>{{ $quizStatus['best'] }}</strong>
                @if ($quizStatus['passed']) <span class="pill pill-ok">Lulus</span> @else <span class="pill pill-bad">Belum lulus</span> @endif
                <span class="small muted">&middot; {{ $quizStatus['used'] }}/{{ $quiz->max_attempts }} percobaan terpakai</span>
              </p>
            @endif
            <a class="btn btn-cta mt2" href="{{ route('quiz.intro', $lesson) }}">
              {{ ($quizStatus['used'] ?? 0) ? 'Buka kuis' : 'Mulai kuis' }} &rarr;
            </a>
          @else
            <p class="small muted mt1">Kuis belum disiapkan dosen.</p>
          @endif
        @else
          @if ($quiz && $quiz->questions->count())
            <p class="small muted mt1">{{ $quiz->questions->count() }} soal &middot; {{ $quiz->time_limit_min }} menit &middot; lulus &ge; {{ $quiz->pass_score }}</p>
            @foreach ($quiz->questions as $i => $q)
              <div class="{{ $mode === 'present' ? 'present-q' : 'card' }} mt2">
                <strong>Soal {{ $i + 1 }}.</strong> {{ $q->text }}
                @if ($q->code_snippet)<pre class="code mt2">{{ $q->code_snippet }}</pre>@endif
                <div class="mt2" style="display:flex; flex-direction:column; gap:6px">
                  @foreach ($q->options as $o)
                    <div class="{{ $mode === 'present' ? 'opt '.($o->is_correct ? 'key' : '') : 'quiz-opt '.($o->is_correct ? 'chosen' : '') }}" style="cursor:default">
                      <span>{{ $o->text }}</span>@if ($o->is_correct)<span class="spacer"></span><span class="pill pill-ok">kunci</span>@endif
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach
          @else
            <p class="small muted mt1">Belum ada soal. Tambahkan di editor pelajaran (bagian “Soal”).</p>
          @endif
        @endif
      </div>
    </section>

  @elseif ($t === 'assignment')
    @php $isProject = ! empty($cell['project']); @endphp
    @if ($mode === 'student')
      @if (trim((string) ($cell['prompt'] ?? '')) !== '')
        <section class="nb-card nb-card--assignment">
          <header class="nb-card-head">
            <span class="nb-card-ic" aria-hidden="true">{{ $isProject ? '🚀' : '🗂️' }}</span>
            <span class="nb-card-label">{{ $isProject ? 'Proyek' : 'Tugas' }}</span>
          </header>
          <div class="nb-card-body">{!! App\Support\LessonContent::render($cell['prompt']) !!}</div>
        </section>
      @endif
      @include('student.partials.tugas', ['lesson' => $lesson, 'submission' => $submission, 'graded' => $graded, 'isProject' => $isProject])
    @else
      <section class="nb-card nb-card--assignment">
        <header class="nb-card-head">
          <span class="nb-card-ic" aria-hidden="true">{{ $isProject ? '🚀' : '🗂️' }}</span>
          <span class="nb-card-label">Pengumpulan {{ $isProject ? 'proyek' : 'tugas' }}</span>
        </header>
        <div class="nb-card-body">
          @if (trim((string) ($cell['prompt'] ?? '')) !== '')
            <div class="{{ $mode === 'present' ? 'present-prose' : 'prose' }}">{!! App\Support\LessonContent::render($cell['prompt']) !!}</div>
          @endif
          <p class="small muted mt2">Nilai maksimum: {{ $cell['max_score'] ?? 100 }}</p>
          @if (trim((string) ($cell['rubric'] ?? '')) !== '')
            <div class="callout mt2"><strong>Rubrik penilaian AI:</strong> {{ $cell['rubric'] }}</div>
          @endif
        </div>
      </section>
    @endif
  @endif

@empty
  <p class="muted">Belum ada materi untuk pelajaran ini.</p>
@endforelse
