<?php

namespace App\Services\Ai;

/**
 * Kontrak penyedia AI (Anthropic / OpenAI).
 *
 * AiGrader menyusun prompt-nya sendiri lalu menyerahkan pemanggilan API ke driver,
 * sehingga menambah penyedia baru cukup dengan membuat satu kelas driver.
 *
 * Bagian `$parts` memakai bentuk netral (bukan format salah satu penyedia):
 *   ['type' => 'text', 'text' => '...']
 *   ['type' => 'pdf',  'data' => <isi berkas mentah>, 'name' => 'tugas.pdf']
 */
interface AiDriver
{
    /** Nama penyedia untuk pesan galat/log. */
    public function name(): string;

    /** Siap dipakai? (kunci API terisi) */
    public function enabled(): bool;

    /** Nama model yang sedang dipakai — disimpan di kolom ai_model. */
    public function model(): string;

    /** Apakah penyedia ini bisa membaca lampiran PDF secara langsung? */
    public function supportsPdf(): bool;

    /**
     * Kirim satu permintaan dan kembalikan teks jawabannya.
     *
     * @param  string  $system  instruksi sistem (teks polos)
     * @param  array<int, array<string, mixed>>  $parts  konten dari mahasiswa
     */
    public function complete(string $system, array $parts, int $maxTokens): string;
}
