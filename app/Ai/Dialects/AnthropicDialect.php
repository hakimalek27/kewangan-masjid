<?php

namespace App\Ai\Dialects;

use App\Ai\AiException;
use App\Ai\Contracts\VisionExtractorInterface;
use App\Ai\DTO\ExtractionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Dialect Anthropic Messages API (Claude) — imej sebagai source base64. */
class AnthropicDialect implements VisionExtractorInterface
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private ?string $baseUrl = null,
    ) {
    }

    public function extract(string $imageBytes, string $mime, string $prompt): ExtractionResult
    {
        $url = rtrim($this->baseUrl ?: 'https://api.anthropic.com', '/').'/v1/messages';

        $blokFail = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => [
                'type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($imageBytes)]]
            : ['type' => 'image', 'source' => [
                'type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($imageBytes)]];

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post($url, [
                    'model' => $this->model,
                    'max_tokens' => 800,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [$blokFail, ['type' => 'text', 'text' => $prompt]],
                    ]],
                ]);
        } catch (ConnectionException $e) {
            throw new AiException('Sambungan ke Anthropic gagal: '.$e->getMessage(), 'anthropic');
        }

        if ($response->failed()) {
            throw new AiException(
                'Anthropic HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300),
                'anthropic',
                $response->status()
            );
        }

        $hasil = ExtractionResult::fromJson((string) $response->json('content.0.text', ''));
        $masuk = (int) $response->json('usage.input_tokens', 0);
        $keluar = (int) $response->json('usage.output_tokens', 0);
        $hasil->tokensUsed = ($masuk + $keluar) ?: null;

        return $hasil;
    }
}
