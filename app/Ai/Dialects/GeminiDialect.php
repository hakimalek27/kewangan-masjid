<?php

namespace App\Ai\Dialects;

use App\Ai\AiException;
use App\Ai\Contracts\VisionExtractorInterface;
use App\Ai\DTO\ExtractionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Dialect Google Gemini generateContent — fail sebagai inline_data base64. */
class GeminiDialect implements VisionExtractorInterface
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private ?string $baseUrl = null,
    ) {
    }

    public function extract(string $imageBytes, string $mime, string $prompt): ExtractionResult
    {
        $url = rtrim($this->baseUrl ?: 'https://generativelanguage.googleapis.com', '/')
            .'/v1beta/models/'.$this->model.':generateContent?key='.$this->apiKey;

        try {
            $response = Http::timeout(120)->post($url, [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($imageBytes)]],
                        ['text' => $prompt],
                    ],
                ]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ]);
        } catch (ConnectionException $e) {
            throw new AiException('Sambungan ke Gemini gagal: '.$e->getMessage(), 'gemini');
        }

        if ($response->failed()) {
            throw new AiException(
                'Gemini HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300),
                'gemini',
                $response->status()
            );
        }

        $hasil = ExtractionResult::fromJson((string) $response->json('candidates.0.content.parts.0.text', ''));
        $hasil->tokensUsed = $response->json('usageMetadata.totalTokenCount');

        return $hasil;
    }
}
