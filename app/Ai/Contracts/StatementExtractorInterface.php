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
}
