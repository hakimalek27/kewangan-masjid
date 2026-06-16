<?php

namespace App\Ai\DTO;

/**
 * Hasil ekstrakan AI (DTO) — dipetakan terus ke kolum ai_extraction.
 * fromJson() parse selamat: buang pagar ```json; jika gagal parse,
 * confidence = 0 dan teks mentah dikekalkan dalam rawJson untuk siasatan.
 */
class ExtractionResult
{
    public ?string $doc_type = null;
    public ?string $tarikh = null;
    public ?string $penerima = null;
    public ?string $jumlah = null;
    public ?string $no_rujukan = null;
    public ?string $no_akaun = null;
    public ?string $bank = null;
    public ?string $kaedah = null;
    public ?string $cadangan_coa = null;
    public ?string $keterangan = null;
    public int $confidence = 0;
    public string $rawJson = '';
    public ?int $tokensUsed = null;
    public ?float $costUsd = null;

    public static function fromJson(string $teks): self
    {
        $hasil = new self();
        $hasil->rawJson = $teks;

        $bersih = trim(preg_replace('/```json|```/i', '', $teks) ?? $teks);
        $data = json_decode($bersih, true);

        if (!is_array($data)) {
            $hasil->confidence = 0; // gagal parse — simpan raw sahaja

            return $hasil;
        }

        $teksAtauNull = fn ($v) => ($v === null || $v === '' || is_array($v)) ? null : (string) $v;

        $hasil->doc_type = $teksAtauNull($data['doc_type'] ?? null);
        $hasil->tarikh = self::tarikhSah($teksAtauNull($data['tarikh'] ?? null));
        $hasil->penerima = $teksAtauNull($data['penerima'] ?? null);
        $hasil->no_rujukan = $teksAtauNull($data['no_rujukan'] ?? null);
        $hasil->no_akaun = $teksAtauNull($data['no_akaun'] ?? null);
        $hasil->bank = $teksAtauNull($data['bank'] ?? null);
        $hasil->kaedah = $teksAtauNull($data['kaedah'] ?? null);
        $hasil->cadangan_coa = $teksAtauNull($data['cadangan_coa'] ?? null);
        $hasil->keterangan = $teksAtauNull($data['keterangan'] ?? null);

        $jumlah = $data['jumlah'] ?? null;
        if (is_numeric($jumlah)) {
            $hasil->jumlah = number_format((float) $jumlah, 2, '.', '');
        }

        $conf = $data['confidence'] ?? 0;
        $hasil->confidence = is_numeric($conf) ? max(0, min(100, (int) $conf)) : 0;

        return $hasil;
    }

    /** Terima YYYY-MM-DD sahaja — tarikh tidak sah dibuang (fallback hari ini di hilir). */
    private static function tarikhSah(?string $tarikh): ?string
    {
        if ($tarikh === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarikh)) {
            return null;
        }

        return checkdate((int) substr($tarikh, 5, 2), (int) substr($tarikh, 8, 2), (int) substr($tarikh, 0, 4))
            ? $tarikh : null;
    }
}
