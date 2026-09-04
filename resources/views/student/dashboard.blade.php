@extends('layouts.app')
@section('title', 'Kelas saya')
@section('body')
<div class="container">
  <div class="eyebrow">Beranda</div>
  <h1 class="mt1">Kelas saya</h1>

  <div class="grid-2 mt3">
    <div>
      @forelse ($classes as $c)
        <a class="card" style="display:block" href="{{ route('course.show', $c) }}">
          <div class="row between">
            <div>
              <div class="eyebrow">{{ $c->name }}</div>
              <h3 class="mt1">{{ $c->course->name }}</h3>
            </div>
            <span class="pill pill-xp">{{ $c->stat['xp'] }} XP</span>
          </div>
          <div class="bar mt2"><span style="width: {{ $c->stat['percent'] }}%"></span></div>
          <div class="muted small mt1">{{ $c->stat['percent'] }}% &middot; {{ $c->stat['done'] }}/{{ $c->stat['total'] }} pelajaran</div>
        </a>
      @empty
        <div class="card"><p class="muted mt0">Anda belum tergabung di kelas mana pun. Masukkan kode kelas dari dosen di samping.</p></div>
      @endforelse
    </div>

    <div>
      <div class="card">
        <h3 class="mt0">Gabung kelas</h3>
        <form method="post" action="{{ route('join') }}" class="mt2">
          @csrf
          <label class="field"><span>Kode kelas</span>
            <input type="text" name="code" required style="text-transform:uppercase" placeholder="mis. AP7K2M">
          </label>
          <button class="btn btn-cta" type="submit" style="width:100%; justify-content:center">Gabung</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
