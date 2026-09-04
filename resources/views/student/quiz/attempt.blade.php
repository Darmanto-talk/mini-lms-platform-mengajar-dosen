@extends('layouts.app')
@section('title', $quiz->title)
@section('body')
<div class="container">
  <div class="row between">
    <div>
      <div class="eyebrow">Kuis · Minggu {{ $lesson->week->number }}</div>
      <h1 class="mt1">{{ $quiz->title }}</h1>
    </div>
    <div class="card card-tight" style="text-align:center; min-width:120px">
      <div class="muted small">Sisa waktu</div>
      <div class="big-pct" style="font-size:26px" data-timer="{{ $remaining }}">--:--</div>
    </div>
  </div>

  <form method="post" action="{{ route('quiz.submit', $attempt) }}" id="quizForm" class="mt3">
    @csrf
    @foreach ($questions as $i => $q)
      <div class="card">
        <span class="muted small">Soal {{ $i + 1 }} dari {{ $questions->count() }}</span>
        <h3 class="mt1" style="line-height:1.5">{{ $q->text }}</h3>
        @if ($q->code_snippet)<pre class="code mt2">{{ $q->code_snippet }}</pre>@endif
        <div class="mt2" style="display:flex; flex-direction:column; gap:10px">
          @foreach ($q->options as $o)
            <label class="quiz-opt" for="o_{{ $q->id }}_{{ $o->id }}">
              <input type="radio" id="o_{{ $q->id }}_{{ $o->id }}" name="answer[{{ $q->id }}]" value="{{ $o->id }}">
              <span>{{ $o->text }}</span>
            </label>
          @endforeach
        </div>
      </div>
    @endforeach

    <div class="row between mt3">
      @if ($class)<a class="btn btn-sm" href="{{ route('course.show', $class) }}">Keluar sementara</a>@else<span></span>@endif
      <button class="btn btn-cta" type="submit" data-confirm="Kumpulkan jawaban sekarang? Tidak bisa diubah lagi.">Kumpulkan jawaban</button>
    </div>
  </form>
</div>
@endsection
