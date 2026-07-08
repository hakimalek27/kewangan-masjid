<?php

namespace App\Ai\Contracts;

use App\Ai\DTO\StatementResult;

/**
 * Ekstraksi PENYATA BANK penuh (banyak baris) — berbeza dengan
 * VisionExtractorInterface (satu resit). Digunakan oleh saluran AI pusat
 * ciri "Semak Penyata (AI)" (dialek OpenAI sahaja buat masa ini).
 */
interface StatementExtractorInterface
{
    /**
     * @param  string  $fileBytes  bait mentah fail penyata (PDF/imej)
     * @param  string  $mime       cth application/pdf, image/jpeg, image/png
     * @param  string  $prompt     arahan penuh (AiClientFactory::buildPromptPenyata)
     *
     * @throws \App\Ai\AiException
     */
    public function extractStatement(string $fileBytes, string $mime, string $prompt): StatementResult;

    /**
     * Ekstrak BEBERAPA fail secara SELARI (satu panggilan HTTP serentak setiap
     * item) — untuk OCR imbasan pantas (potong masa penyata berbilang muka).
     *
     * @param  string[]  $itemsBytes  senarai bait mentah (cth imej setiap muka)
     * @return array<int, StatementResult|null>  sepadan indeks; null jika item gagal
     */
    public function extractStatementBatch(array $itemsBytes, string $mime, string $prompt): array;
}
