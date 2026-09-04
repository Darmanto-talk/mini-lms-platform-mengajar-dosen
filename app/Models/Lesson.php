<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    protected $fillable = [
        'week_id', 'title', 'type', 'content', 'cells', 'video_url',
        'dartpad_gist', 'rubric', 'max_score', 'xp', 'position',
    ];

    protected function casts(): array
    {
        return ['cells' => 'array'];
    }

    public const TYPES = ['materi', 'video', 'latihan', 'tugas', 'kuis'];

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function typeLabel(): string
    {
        return [
            'materi' => 'Materi', 'video' => 'Video', 'latihan' => 'Latihan',
            'tugas' => 'Tugas', 'kuis' => 'Kuis',
        ][$this->type] ?? ucfirst($this->type);
    }

    /** Perkiraan durasi belajar untuk ditampilkan di daftar materi (mirip Dicoding). */
    public function durationLabel(): string
    {
        if ($this->type === 'kuis') {
            $n = $this->quiz?->questions()->count() ?? 0;

            return $n > 0 ? "{$n} soal" : 'Kuis';
        }
        if ($this->type === 'tugas') {
            return 'Tugas';
        }

        $text = (string) $this->content;
        foreach ((array) $this->cells as $c) {
            $text .= ' '.($c['source'] ?? $c['prompt'] ?? $c['caption'] ?? '');
        }
        $words = str_word_count(strip_tags($text));
        $mins = max(1, (int) ceil($words / 180));
        if ($this->video_url) {
            $mins = max($mins, 3);
        }

        return "± {$mins} menit";
    }

    /** Nama ikon jenis materi (untuk daftar sidebar). */
    public function icon(): string
    {
        return match ($this->type) {
            'video' => 'video',
            'kuis' => 'quiz',
            'tugas' => 'task',
            'latihan' => 'code',
            default => 'doc',
        };
    }

    /** Daftar sel notebook yang sudah dinormalisasi (pakai cadangan bila kolom kosong). */
    public function notebookCells(): array
    {
        return \App\Support\Notebook::normalize($this);
    }

    public function hasCellType(string $type): bool
    {
        foreach ($this->notebookCells() as $c) {
            if (($c['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    public function assignmentCell(): ?array
    {
        foreach ($this->notebookCells() as $c) {
            if (($c['type'] ?? null) === 'assignment') {
                return $c;
            }
        }

        return null;
    }
}
