<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notebook: badan pelajaran disimpan sebagai daftar "sel" (JSON), ala Jupyter.
 * Kolom lama (content, video_url, dartpad_gist, rubric) tetap ada sebagai
 * cadangan / kompatibilitas — diisi ulang dari sel saat menyimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('cells')->nullable()->after('content');
        });

        // backfill: ubah pelajaran lama menjadi satu-dua sel sederhana
        foreach (\Illuminate\Support\Facades\DB::table('lessons')->get() as $l) {
            if ($l->cells) {
                continue;
            }
            $cells = [];
            if (trim((string) $l->video_url) !== '') {
                $cells[] = ['type' => 'video', 'url' => $l->video_url];
            }
            if (trim((string) $l->content) !== '') {
                $cells[] = ['type' => 'markdown', 'source' => $l->content];
            }
            $gist = preg_replace('/[^A-Za-z0-9]/', '', (string) $l->dartpad_gist);
            if ($gist !== '' || $l->type === 'latihan') {
                $cells[] = ['type' => 'code', 'source' => '', 'gist' => $gist, 'run' => true];
            }
            if ($l->type === 'kuis') {
                $cells[] = ['type' => 'quiz'];
            }
            if ($l->type === 'tugas') {
                $cells[] = [
                    'type' => 'assignment',
                    'prompt' => '',
                    'rubric' => (string) $l->rubric,
                    'max_score' => (int) $l->max_score,
                    'project' => false,
                ];
            }
            if ($cells) {
                \Illuminate\Support\Facades\DB::table('lessons')->where('id', $l->id)
                    ->update(['cells' => json_encode($cells, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('cells');
        });
    }
};
