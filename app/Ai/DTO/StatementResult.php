<?php

namespace App\Ai\DTO;

/**
 * Hasil ekstraksi penyata bank penuh — pembungkus {"lines":[...]} kerana
 * mod json_object OpenAI tidak boleh memulangkan array kosong di akar.
 * fromJson() parse selamat: pagar ```json dibuang; baris tak sah ditapis.
 */
class StatementResult
{
    /** @var StatementLine[] */
    public array $lines = [];
    public string $rawJson = '';
    public ?int $tokensUsed = null;

    public static function fromJson(string $teks): self
    {
        $hasil = new self();
        $hasil->rawJson = $teks;

        $bersih = trim(preg_replace('/```json|```/i', '', $teks) ?? $teks);
        $data = json_decode($bersih, true);

        $senarai = is_array($data) ? ($data['lines'] ?? null) : null;
        if (!is_array($senarai)) {
            return $hasil; // gagal parse — lines kosong, raw dikekalkan untuk siasatan
        }

        foreach ($senarai as $item) {
            if (!is_array($item)) {
                continue;
            }
            $baris = StatementLine::fromArray($item);
            if ($baris->sah()) {
                $hasil->lines[] = $baris;
            }
        }

        return $hasil;
    }
}
