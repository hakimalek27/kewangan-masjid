<?php

namespace App\Ai\Contracts;

use App\Ai\DTO\ExtractionResult;

/**
 * Kontrak ekstraktor visi AI — terima bait imej/PDF + prompt, pulangkan
 * data berstruktur. Setiap dialect (OpenAI/Anthropic/Gemini) melaksanakan
 * kontrak ini supaya AiClientFactory boleh menukar provider tanpa mengubah
 * kod pemanggil.
 */
interface VisionExtractorInterface
{
    /**
     * @throws \App\Ai\AiException jika panggilan API gagal
     */
    public function extract(string $imageBytes, string $mime, string $prompt): ExtractionResult;
}
