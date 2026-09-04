<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cadangan bila tidak ada queue worker: proses pemeriksaan AI yang tertunda.
Schedule::command('lms:grade-pending --sync')->everyFiveMinutes()->withoutOverlapping();

// Bersihkan percobaan kuis yang ditinggalkan (mulai tapi tak dikumpulkan > 1 hari).
Schedule::command('queue:prune-failed')->daily();
