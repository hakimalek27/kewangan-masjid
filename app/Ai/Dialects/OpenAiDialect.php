<?php

namespace App\Ai\Dialects;

use App\Ai\AiException;
use App\Ai\Contracts\StatementExtractorInterface;
use App\Ai\Contracts\VisionExtractorInterface;
use App\Ai\DTO\ExtractionResult;
use App\Ai\DTO\StatementResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
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
        $hasil->promptTokens = $response->json('usage.prompt_tokens');
        $hasil->completionTokens = $response->json('usage.completion_tokens');

        return $hasil;
    }

    /**
     * OCR SELARI — hantar banyak imej serentak (Http::pool). Setiap item gagal →
     * null (pemanggil report + langkau); tiada AiException dilempar untuk satu
     * kegagalan supaya muka lain kekal diproses.
     *
     * @param  string[]  $itemsBytes
     * @return array<int, StatementResult|null>
     */
    public function extractStatementBatch(array $itemsBytes, string $mime, string $prompt): array
    {
        $url = rtrim($this->baseUrl ?: 'https://api.openai.com', '/').'/v1/chat/completions';
        $items = array_values($itemsBytes);

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($bytes) => $pool->withToken($this->apiKey)->timeout(120)->post($url, [
                'model' => $this->model,
                'max_tokens' => 16384,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        $this->blokKandungan($bytes, $mime),
                    ],
                ]],
            ]),
            $items
        ));

        $hasil = [];
        foreach ($items as $i => $_) {
            $resp = $responses[$i] ?? null;
            if (!$resp instanceof Response || $resp->failed()) {
                $hasil[$i] = null; // ConnectionException / HTTP gagal → langkau

                continue;
            }
            $r = StatementResult::fromJson((string) $resp->json('choices.0.message.content', ''));
            $r->tokensUsed = $resp->json('usage.total_tokens');
            $r->promptTokens = $resp->json('usage.prompt_tokens');
            $r->completionTokens = $resp->json('usage.completion_tokens');
            $hasil[$i] = $r;
        }

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
     * teks (PDF digital diekstrak) dihantar sebagai blok text biasa — jauh lebih
     * murah/pantas & tiada ralat OCR; imej kekal blok image_url data-URI.
     */
    private function blokKandungan(string $bytes, string $mime): array
    {
        if ($mime === 'application/pdf') {
            return ['type' => 'file', 'file' => [
                'filename' => 'dokumen.pdf',
                'file_data' => 'data:application/pdf;base64,'.base64_encode($bytes),
            ]];
        }

        if (str_starts_with($mime, 'text/')) {
            return ['type' => 'text', 'text' => "PENYATA (teks diekstrak dari PDF):\n".$bytes];
        }

        return ['type' => 'image_url', 'image_url' => [
            'url' => 'data:'.$mime.';base64,'.base64_encode($bytes),
        ]];
    }
}
