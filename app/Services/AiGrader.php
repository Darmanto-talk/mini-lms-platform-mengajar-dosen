<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\QuizAttempt;
use App\Services\Ai\AiDriver;
use App\Services\Ai\AnthropicDriver;
use App\Services\Ai\OpenAiDriver;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Pemeriksaan otomatis oleh AI — memberi nilai objektif + umpan balik.
 *
 * Penyedia dipilih lewat AI_PROVIDER di .env ('anthropic' atau 'openai'); prompt
 * dan aturan penilaian di bawah ini sama untuk keduanya. Nonaktif otomatis bila
 * kunci API penyedia terpilih kosong (dosen menilai manual).
 */
class AiGrader
{
    private AiDriver $driver;

    public function __construct()
    {
        $this->driver = self::makeDriver(config('services.ai.provider'));
    }

    public static function makeDriver(?string $provider): AiDriver
    {
        return match (strtolower(trim((string) $provider))) {
            'openai', 'chatgpt', 'gpt' => new OpenAiDriver,
            default => new AnthropicDriver,
        };
    }

    public function enabled(): bool
    {
        return $this->driver->enabled();
    }

    /** Nama penyedia + model yang sedang aktif (untuk pesan & log). */
    public function describe(): string
    {
        return $this->driver->name().' / '.$this->driver->model();
    }

    /**
     * Nilai satu pengumpulan tugas/proyek.
     *
     * @return array{score:int, feedback:string, breakdown:array, model:string}
     */
    public function gradeAssignment(AssignmentSubmission $sub): array
    {
        $lesson = $sub->lesson;
        $maxScore = (int) $lesson->max_score;

        $rubric = trim((string) $lesson->rubric) !== ''
            ? $lesson->rubric
            : 'Nilai berdasarkan: kelengkapan menjawab instruksi (40%), ketepatan konsep/algoritma & kode (40%), kejelasan penjelasan (20%).';

        $system = implode("\n", [
            'Anda adalah asisten dosen mata kuliah Algoritma dan Pemrograman (bahasa Dart).',
            'Tugas Anda menilai pengumpulan mahasiswa secara OBJEKTIF, KONSISTEN, dan ADIL berdasarkan rubrik.',
            'Berikan umpan balik yang membangun dalam Bahasa Indonesia: sebutkan yang sudah baik dan yang perlu diperbaiki secara spesifik.',
            'Jangan menilai lebih tinggi karena kalimat sopan; nilai hanya substansi.',
            '',
            "JUDUL TUGAS: {$lesson->title}",
            'INSTRUKSI TUGAS:',
            (string) $lesson->content,
            '',
            "RUBRIK PENILAIAN (nilai maksimum {$maxScore}):",
            $rubric,
            '',
            'Kembalikan HANYA satu objek JSON valid tanpa teks lain, dengan bentuk:',
            '{"score": <bilangan bulat 0..'.$maxScore.'>,',
            ' "feedback": "<umpan balik 3-6 kalimat untuk mahasiswa>",',
            ' "breakdown": [{"aspek": "<nama aspek rubrik>", "skor": <int>, "maks": <int>, "catatan": "<1-2 kalimat>"}]}',
        ]);

        [$parts, $note] = $this->buildSubmissionContent($sub);

        try {
            $raw = $this->driver->complete($system, $parts, maxTokens: 4000);
        } catch (Throwable $e) {
            // Lampiran PDF sering jadi penyebab gagal di sebagian model.
            // Ulangi sekali tanpa PDF supaya tugas tetap ternilai.
            $withoutPdf = array_values(array_filter($parts, fn ($p) => ($p['type'] ?? '') !== 'pdf'));
            if (count($withoutPdf) === count($parts)) {
                throw $e;
            }
            $raw = $this->driver->complete($system, $withoutPdf, maxTokens: 4000);
            $note = trim(($note ? $note."\n" : '')
                .'Catatan: lampiran PDF tidak dapat dibaca AI, penilaian hanya dari jawaban teks/tautan. Mohon dosen memeriksa berkasnya.');
        }

        $json = $this->extractJson($raw);

        $score = (int) round((float) ($json['score'] ?? 0));
        $score = max(0, min($maxScore, $score));

        $feedback = trim((string) ($json['feedback'] ?? 'Tidak ada umpan balik.'));
        if ($note) {
            $feedback = $note."\n\n".$feedback;
        }

        return [
            'score' => $score,
            'feedback' => $feedback,
            'breakdown' => is_array($json['breakdown'] ?? null) ? $json['breakdown'] : [],
            'model' => $this->driver->model(),
        ];
    }

    /** Umpan balik AI untuk percobaan kuis (menjelaskan jawaban yang salah). */
    public function reviewQuizAttempt(QuizAttempt $attempt): string
    {
        $quiz = $attempt->quiz;
        $questions = $quiz->questions()->with('options')->get();
        $picked = $attempt->answers()->pluck('option_id', 'question_id');

        $lines = [];
        foreach ($questions as $i => $q) {
            $chosenId = $picked[$q->id] ?? null;
            $chosen = $q->options->firstWhere('id', $chosenId);
            $correct = $q->options->firstWhere('is_correct', true);
            $ok = $chosen && $chosen->is_correct;
            $lines[] = 'Soal '.($i + 1).': '.$q->text
                .($q->code_snippet ? "\n[kode] ".$q->code_snippet : '')
                ."\n- Jawaban mahasiswa: ".($chosen->text ?? '(kosong)')
                ."\n- Jawaban benar: ".($correct->text ?? '-')
                ."\n- Status: ".($ok ? 'BENAR' : 'SALAH');
        }

        $system = implode("\n", [
            'Anda tutor mata kuliah Algoritma dan Pemrograman (Dart).',
            'Berikan penjelasan singkat Bahasa Indonesia untuk setiap soal yang SALAH: mengapa jawaban benar itu benar,',
            'dan kesalahpahaman yang mungkin terjadi. Untuk soal yang sudah benar, cukup satu kalimat penguat.',
            'Tulis rapi per nomor. Maksimal 2 kalimat per soal. Jangan mengubah nilai.',
        ]);

        $parts = [['type' => 'text', 'text' => "Nilai: {$attempt->score}\n\n".implode("\n\n", $lines)]];

        return $this->driver->complete($system, $parts, maxTokens: 2500);
    }

    /** @return array{0: array, 1: ?string} konten user + catatan (mis. berkas tak terbaca) */
    private function buildSubmissionContent(AssignmentSubmission $sub): array
    {
        $parts = [];
        $note = null;

        $intro = "PENGUMPULAN MAHASISWA:\n";
        if (trim((string) $sub->text) !== '') {
            $intro .= "\n[Jawaban teks]\n".$sub->text;
        }
        if (trim((string) $sub->link_url) !== '') {
            $intro .= "\n\n[Tautan]\n".$sub->link_url."\n(AI tidak membuka tautan; nilai berdasarkan penjelasan mahasiswa.)";
        }

        if ($sub->file_path) {
            $ext = strtolower(pathinfo($sub->file_path, PATHINFO_EXTENSION));
            $disk = Storage::disk('public');
            $fence = str_repeat('`', 3);

            if (in_array($ext, ['dart', 'txt', 'md', 'json', 'csv'], true) && $disk->exists($sub->file_path)) {
                $code = mb_substr((string) $disk->get($sub->file_path), 0, 24000);
                $intro .= "\n\n[Isi berkas {$ext}]\n{$fence}\n{$code}\n{$fence}";
            } elseif ($ext === 'pdf' && $disk->exists($sub->file_path) && $this->driver->supportsPdf()) {
                $parts[] = [
                    'type' => 'pdf',
                    'data' => $disk->get($sub->file_path),
                    'name' => basename($sub->file_path),
                ];
            } else {
                $note = "Catatan: berkas .{$ext} terlampir tetapi tidak dapat dibaca AI; mohon dosen memeriksa berkas secara manual.";
                $intro .= "\n\n[Berkas .{$ext} terlampir — tidak dibaca AI]";
            }
        }

        array_unshift($parts, ['type' => 'text', 'text' => $intro]);

        return [$parts, $note];
    }

    private function extractJson(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);

        // ambil objek JSON pertama sampai kurung penutup terakhir
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $json = json_decode($text, true);
        if (! is_array($json)) {
            throw new RuntimeException('Respons AI bukan JSON yang bisa dibaca: '.mb_substr($text, 0, 300));
        }

        return $json;
    }
}
