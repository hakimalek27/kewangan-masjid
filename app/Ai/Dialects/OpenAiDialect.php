<?php

namespace App\Ai\Dialects;

use App\Ai\AiException;
use App\Ai\Contracts\StatementExtractorInterface;
use App\Ai\Contracts\VisionExtractorInterface;
use App\Ai\DTO\ExtractionResult;
use App\Ai\DTO\StatementResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dialect OpenAI Chat Completions — turut digunakan untuk DeepSeek/Qwen/
 * provider serasi-OpenAI melalui base_url tersuai.
 * Menyokong dua mod: extract() satu resit, extractStatement() penyata penuh.
 */
class OpenAiDialect implements StatementExtractorInterface, VisionExtractorInterface
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private ?string $baseUrl = null,
    ) {
    }

    public function extract(string $imageBytes, string $mime, string $prompt): ExtractionResult
    {
        $response = $this->panggil($imageBytes, $mime, $prompt, 800);

        $hasil = ExtractionResult::fromJson((string) $response->json('choices.0.message.content', ''));
        $hasil->tokensUsed = $response->json('usage.total_tokens');

        return $hasil;
    }

    public function extractStatement(string $fileBytes, string $mime, string $prompt): StatementResult
    {
        // Penyata bank: 30–200+ baris → had output jauh lebih besar daripada resit.
        $response = $this->panggil($fileBytes, $mime, $prompt, 16384);

        if ($response->json('choices.0.finish_reason') === 'length') {
            throw new AiException(
                'Penyata terlalu panjang untuk diproses sekali gus — sila pisahkan penyata (contoh: satu bulan satu fail).',
                'openai'
            );
        }

        $hasil = StatementResult::fromJson((string) $response->json('choices.0.message.content', ''));
        $hasil->tokensUsed = $response->json('usage.total_tokens');

        return $hasil;
    }

    private function panggil(string $bytes, string $mime, string $prompt, int $maxTokens): Response
    {
        $url = rtrim($this->baseUrl ?: 'https://api.openai.com', '/').'/v1/chat/completions';

        try {
            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->post($url, [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            $this->blokKandungan($bytes, $mime),
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

        return $response;
    }

    /**
     * PDF perlu blok `file` (image_url TIDAK menerima PDF di OpenAI);
     * imej kekal blok image_url data-URI (payload sama seperti sebelum ini).
     */
    private function blokKandungan(string $bytes, string $mime): array
    {
        if ($mime === 'application/pdf') {
            return ['type' => 'file', 'file' => [
                'filename' => 'dokumen.pdf',
                'file_data' => 'data:application/pdf;base64,'.base64_encode($bytes),
            ]];
        }

        return ['type' => 'image_url', 'image_url' => [
            'url' => 'data:'.$mime.';base64,'.base64_encode($bytes),
        ]];
    }
}
