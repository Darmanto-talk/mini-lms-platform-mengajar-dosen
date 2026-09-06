<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Penyedia ChatGPT (OpenAI) — Chat Completions API. */
class OpenAiDriver implements AiDriver
{
    public function name(): string
    {
        return 'OpenAI';
    }

    public function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function model(): string
    {
        return (string) config('services.openai.model');
    }

    /**
     * Lampiran PDF dikirim sebagai bagian "file" base64. Tidak semua model
     * mendukungnya, jadi AiGrader akan mengulang tanpa PDF bila permintaan gagal.
     */
    public function supportsPdf(): bool
    {
        return (bool) config('services.openai.send_pdf');
    }

    public function complete(string $system, array $parts, int $maxTokens): string
    {
        $cfg = config('services.openai');

        $resp = Http::withToken($cfg['key'])
            ->timeout(90)
            ->retry(1, 2000)
            ->post(rtrim($cfg['base_url'], '/').'/v1/chat/completions', [
                'model' => $cfg['model'],
                'max_completion_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $this->toParts($parts)],
                ],
            ]);

        if ($resp->failed()) {
            throw new RuntimeException('OpenAI API '.$resp->status().': '.$resp->body());
        }

        return trim((string) ($resp->json()['choices'][0]['message']['content'] ?? ''));
    }

    /** Ubah bagian netral menjadi bagian konten OpenAI. */
    private function toParts(array $parts): array
    {
        $out = [];
        foreach ($parts as $p) {
            if (($p['type'] ?? '') === 'pdf') {
                $out[] = [
                    'type' => 'file',
                    'file' => [
                        'filename'  => (string) ($p['name'] ?? 'lampiran.pdf'),
                        'file_data' => 'data:application/pdf;base64,'.base64_encode($p['data']),
                    ],
                ];
            } else {
                $out[] = ['type' => 'text', 'text' => (string) ($p['text'] ?? '')];
            }
        }

        return $out;
    }
}
