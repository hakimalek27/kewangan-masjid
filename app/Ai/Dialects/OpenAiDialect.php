<?php

namespace App\Ai\Dialects;

use App\Ai\AiException;
use App\Ai\Contracts\VisionExtractorInterface;
use App\Ai\DTO\ExtractionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Dialect OpenAI Chat Completions — turut digunakan untuk DeepSeek/Qwen/
 * provider serasi-OpenAI melalui base_url tersuai.
 */
class OpenAiDialect implements VisionExtractorInterface
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private ?string $baseUrl = null,
    ) {
    }

    public function extract(string $imageBytes, string $mime, string $prompt): ExtractionResult
    {
        $url = rtrim($this->baseUrl ?: 'https://api.openai.com', '/').'/v1/chat/completions';

        try {
            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->post($url, [
                    'model' => $this->model,
                    'max_tokens' => 800,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => [
                                'url' => 'data:'.$mime.';base64,'.base64_encode($imageBytes),
                            ]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException $e) {
            throw new AiException('Sambungan ke OpenAI gagal: '.$e->getMessage(), 'openai');
        }

        if ($response->failed()) {
            throw new AiException(
                'OpenAI HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300),
                'openai',
                $response->status()
            );
        }

        $hasil = ExtractionResult::fromJson((string) $response->json('choices.0.message.content', ''));
        $hasil->tokensUsed = $response->json('usage.total_tokens');

        return $hasil;
    }
}
