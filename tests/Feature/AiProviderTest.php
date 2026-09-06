<?php

namespace Tests\Feature;

use App\Services\Ai\AnthropicDriver;
use App\Services\Ai\OpenAiDriver;
use App\Services\AiGrader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Memastikan pemilihan penyedia AI dan bentuk permintaan ke masing-masing API
 * tetap benar — supaya berpindah antara Claude dan ChatGPT cukup lewat .env.
 */
class AiProviderTest extends TestCase
{
    public function test_provider_dipilih_dari_config(): void
    {
        $this->assertInstanceOf(AnthropicDriver::class, AiGrader::makeDriver('anthropic'));
        $this->assertInstanceOf(OpenAiDriver::class, AiGrader::makeDriver('openai'));
        $this->assertInstanceOf(OpenAiDriver::class, AiGrader::makeDriver('ChatGPT'));

        // nilai kosong / tak dikenal -> jatuh ke Anthropic, jangan sampai error
        $this->assertInstanceOf(AnthropicDriver::class, AiGrader::makeDriver(null));
        $this->assertInstanceOf(AnthropicDriver::class, AiGrader::makeDriver('salah-ketik'));
    }

    public function test_permintaan_anthropic_berbentuk_messages_api(): void
    {
        config(['services.anthropic.key' => 'kunci-uji', 'services.anthropic.model' => 'claude-sonnet-5']);

        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'halo']]])]);

        $teks = (new AnthropicDriver)->complete('instruksi sistem', [
            ['type' => 'text', 'text' => 'jawaban mahasiswa'],
            ['type' => 'pdf', 'data' => 'ISI-PDF', 'name' => 'tugas.pdf'],
        ], 500);

        $this->assertSame('halo', $teks);

        Http::assertSent(function ($req) {
            $b = $req->data();

            return str_ends_with($req->url(), '/v1/messages')
                && $req->hasHeader('x-api-key', 'kunci-uji')
                && $b['model'] === 'claude-sonnet-5'
                && $b['system'][0]['text'] === 'instruksi sistem'
                && $b['messages'][0]['content'][0]['type'] === 'text'
                && $b['messages'][0]['content'][1]['type'] === 'document'
                && $b['messages'][0]['content'][1]['source']['data'] === base64_encode('ISI-PDF');
        });
    }

    public function test_permintaan_openai_berbentuk_chat_completions(): void
    {
        config([
            'services.openai.key' => 'kunci-uji',
            'services.openai.model' => 'gpt-4o-mini',
            'services.openai.send_pdf' => true,
        ]);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'halo']]]])]);

        $teks = (new OpenAiDriver)->complete('instruksi sistem', [
            ['type' => 'text', 'text' => 'jawaban mahasiswa'],
            ['type' => 'pdf', 'data' => 'ISI-PDF', 'name' => 'tugas.pdf'],
        ], 500);

        $this->assertSame('halo', $teks);

        Http::assertSent(function ($req) {
            $b = $req->data();

            return str_ends_with($req->url(), '/v1/chat/completions')
                && $req->hasHeader('Authorization', 'Bearer kunci-uji')
                && $b['model'] === 'gpt-4o-mini'
                && $b['messages'][0]['role'] === 'system'
                && $b['messages'][0]['content'] === 'instruksi sistem'
                && $b['messages'][1]['content'][0]['type'] === 'text'
                && $b['messages'][1]['content'][1]['type'] === 'file'
                && str_starts_with($b['messages'][1]['content'][1]['file']['file_data'], 'data:application/pdf;base64,');
        });
    }

    public function test_openai_melewati_pdf_bila_send_pdf_dimatikan(): void
    {
        config(['services.openai.send_pdf' => false]);

        $this->assertFalse((new OpenAiDriver)->supportsPdf());
        $this->assertTrue((new AnthropicDriver)->supportsPdf());
    }

    public function test_enabled_mengikuti_kunci_penyedia_aktif(): void
    {
        config(['services.anthropic.key' => null, 'services.openai.key' => 'ada']);
        $this->assertFalse((new AnthropicDriver)->enabled());
        $this->assertTrue((new OpenAiDriver)->enabled());
    }
}
