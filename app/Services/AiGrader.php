<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pemeriksaan otomatis oleh Claude (Anthropic) — memberi nilai objektif + umpan balik.
 * Nonaktif otomatis bila ANTHROPIC_API_KEY kosong (dosen menilai manual).
 */
class AiGrader
{
    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
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
            : "Nilai berdasarkan: kelengkapan menjawab instruksi (40%), ketepatan konsep/algoritma & kode (40%), kejelasan penjelasan (20%).";

        $system = [
            [
                'type' => 'text',
                'text' => implode("\n", [
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
                    '{"score": <bilangan bulat 0..' . $maxScore . '>,',
                    ' "feedback": "<umpan balik 3-6 kalimat untuk mahasiswa>",',
                    ' "breakdown": [{"aspek": "<nama aspek rubrik>", "skor": <int>, "maks": <int>, "catatan": "<1-2 kalimat>"}]}',
                ]),
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        [$userContent, $note] = $this->buildSubmissionContent($sub);

        $data = $this->call($system, $userContent, maxTokens: 4000);
        $json = $this->extractJson($data);

        $score = (int) round((float) ($json['score'] ?? 0));
        $score = max(0, min($maxScore, $score));

        $feedback = trim((string) ($json['feedback'] ?? 'Tidak ada umpan balik.'));
        if ($note) {
            $feedback = $note . "\n\n" . $feedback;
        }

        return [
            'score'     => $score,
            'feedback'  => $feedback,
            'breakdown' => is_array($json['breakdown'] ?? null) ? $json['breakdown'] : [],
            'model'     => config('services.anthropic.model'),
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
            $lines[] = 'Soal ' . ($i + 1) . ': ' . $q->text
                . ($q->code_snippet ? "\n[kode] " . $q->code_snippet : '')
                . "\n- Jawaban mahasiswa: " . ($chosen->text ?? '(kosong)')
                . "\n- Jawaban benar: " . ($correct->text ?? '-')
                . "\n- Status: " . ($ok ? 'BENAR' : 'SALAH');
        }

        $system = [[
            'type' => 'text',
            'text' => implode("\n", [
                'Anda tutor mata kuliah Algoritma dan Pemrograman (Dart).',
                'Berikan penjelasan singkat Bahasa Indonesia untuk setiap soal yang SALAH: mengapa jawaban benar itu benar,',
                'dan kesalahpahaman yang mungkin terjadi. Untuk soal yang sudah benar, cukup satu kalimat penguat.',
                'Tulis rapi per nomor. Maksimal 2 kalimat per soal. Jangan mengubah nilai.',
            ]),
            'cache_control' => ['type' => 'ephemeral'],
        ]];

        $data = $this->call($system, "Nilai: {$attempt->score}\n\n" . implode("\n\n", $lines), maxTokens: 2500);

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return trim($block['text']);
            }
        }

        return '';
    }

    /** @return array{0: array, 1: ?string} konten user + catatan (mis. berkas tak terbaca) */
    private function buildSubmissionContent(AssignmentSubmission $sub): array
    {
        $blocks = [];
        $note = null;

        $intro = "PENGUMPULAN MAHASISWA:\n";
        if (trim((string) $sub->text) !== '') {
            $intro .= "\n[Jawaban teks]\n" . $sub->text;
        }
        if (trim((string) $sub->link_url) !== '') {
            $intro .= "\n\n[Tautan]\n" . $sub->link_url . "\n(AI tidak membuka tautan; nilai berdasarkan penjelasan mahasiswa.)";
        }

        if ($sub->file_path) {
            $ext = strtolower(pathinfo($sub->file_path, PATHINFO_EXTENSION));
            $disk = Storage::disk('public');
            if (in_array($ext, ['dart', 'txt', 'md', 'json', 'csv'], true) && $disk->exists($sub->file_path)) {
                $code = mb_substr((string) $disk->get($sub->file_path), 0, 24000);
                $intro .= "\n\n[Isi berkas {$ext}]\n```\n{$code}\n```";
            } elseif ($ext === 'pdf' && $disk->exists($sub->file_path)) {
                $blocks[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => base64_encode($disk->get($sub->file_path)),
                    ],
                ];
            } else {
                $note = "Catatan: berkas .{$ext} terlampir tetapi tidak dapat dibaca AI; mohon dosen memeriksa berkas secara manual.";
                $intro .= "\n\n[Berkas .{$ext} terlampir — tidak dibaca AI]";
            }
        }

        array_unshift($blocks, ['type' => 'text', 'text' => $intro]);

        return [$blocks, $note];
    }

    private function call(array $system, array|string $userContent, int $maxTokens): array
    {
        $cfg = config('services.anthropic');

        $resp = Http::withHeaders([
            'x-api-key'         => $cfg['key'],
            'anthropic-version' => $cfg['version'],
            'content-type'      => 'application/json',
        ])
            ->timeout(90)
            ->retry(1, 2000)
            ->post(rtrim($cfg['base_url'], '/') . '/v1/messages', [
                'model'      => $cfg['model'],
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if ($resp->failed()) {
            throw new RuntimeException('Anthropic API ' . $resp->status() . ': ' . $resp->body());
        }

        return $resp->json();
    }

    private function extractJson(array $data): array
    {
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
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
            throw new RuntimeException('Respons AI bukan JSON yang bisa dibaca: ' . mb_substr($text, 0, 300));
        }

        return $json;
    }
}
