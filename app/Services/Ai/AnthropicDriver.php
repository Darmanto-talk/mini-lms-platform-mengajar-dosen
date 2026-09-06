<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Penyedia Claude (Anthropic) — Messages API. */
class AnthropicDriver implements AiDriver
{
    public function name(): string
    {
        return 'Anthropic';
    }

    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model');
    }

    /** Claude bisa membaca PDF langsung sebagai blok document. */
    public function supportsPdf(): bool
    {
        return true;
    }

    public function complete(string $system, array $parts, int $maxTokens): string
    {
        $cfg = config('services.anthropic');

        $resp = Http::withHeaders([
            'x-api-key'         => $cfg['key'],
            'anthropic-version' => $cfg['version'],
            'content-type'      => 'application/json',
        ])
            ->timeout(90)
            ->retry(1, 2000)
            ->post(rtrim($cfg['base_url'], '/').'/v1/messages', [
                'model'      => $cfg['model'],
                'max_tokens' => $maxTokens,
                'system'     => [[
                    'type' => 'text',
                    'text' => $system,
                    'cache_control' => ['type' => 'ephemeral'],   // hemat biaya: prompt sistem di-cache
                ]],
                'messages' => [
                    ['role' => 'user', 'content' => $this->toBlocks($parts)],
                ],
            ]);

        if ($resp->failed()) {
            throw new RuntimeException('Anthropic API '.$resp->status().': '.$resp->body());
        }

        $text = '';
        foreach ($resp->json()['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        return trim($text);
    }

    /** Ubah bagian netral menjadi blok konten Anthropic. */
    private function toBlocks(array $parts): array
    {
        $out = [];
        foreach ($parts as $p) {
            if (($p['type'] ?? '') === 'pdf') {
                $out[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => base64_encode($p['data']),
                    ],
                ];
            } else {
                $out[] = ['type' => 'text', 'text' => (string) ($p['text'] ?? '')];
            }
        }

        return $out;
    }
}
