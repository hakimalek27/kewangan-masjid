<?php

namespace App\Ai\DTO;

/**
 * Satu baris transaksi penyata bank yang diekstrak AI.
 * Amaun sentiasa string 2 titik perpuluhan; tarikh YYYY-MM-DD sah sahaja.
 */
class StatementLine
{
    public ?string $tarikh = null;
    public ?string $deskripsi = null;
    public string $debit = '0.00';
    public string $kredit = '0.00';
    public ?string $baki = null;
    public ?string $cadanganJenis = null;   // KUTIPAN | BAYARAN | null
    public ?string $cadanganCoa = null;     // cth "400-01010"
    public int $confidence = 0;

    public static function fromArray(array $data): self
    {
        $baris = new self();

        $teks = fn ($v) => ($v === null || $v === '' || is_array($v)) ? null : (string) $v;

        $baris->tarikh = self::tarikhSah($teks($data['tarikh'] ?? null));
        $baris->deskripsi = $teks($data['deskripsi'] ?? null);
        $baris->cadanganCoa = $teks($data['cadangan_coa'] ?? null);

        $jenis = strtoupper((string) ($data['cadangan_jenis'] ?? ''));
        $baris->cadanganJenis = in_array($jenis, ['KUTIPAN', 'BAYARAN'], true) ? $jenis : null;

        foreach (['debit', 'kredit'] as $medan) {
            $nilai = $data[$medan] ?? 0;
            if (is_string($nilai)) {
                $nilai = str_replace([',', 'RM', ' '], '', $nilai);
            }
            $baris->{$medan} = is_numeric($nilai) && (float) $nilai > 0
                ? number_format((float) $nilai, 2, '.', '')
                : '0.00';
        }

        $baki = $data['baki'] ?? null;
        if (is_string($baki)) {
            $baki = str_replace([',', 'RM', ' '], '', $baki);
        }
        $baris->baki = is_numeric($baki) ? number_format((float) $baki, 2, '.', '') : null;

        $conf = $data['confidence'] ?? 0;
        $baris->confidence = is_numeric($conf) ? max(0, min(100, (int) $conf)) : 0;

        return $baris;
    }

    /** Baris sah = ada amaun satu sisi sahaja (debit XOR kredit) — baris kosong dibuang. */
    public function sah(): bool
    {
        return ((float) $this->debit > 0) xor ((float) $this->kredit > 0);
    }

    private static function tarikhSah(?string $tarikh): ?string
    {
        if ($tarikh === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarikh)) {
            return null;
        }

        return checkdate((int) substr($tarikh, 5, 2), (int) substr($tarikh, 8, 2), (int) substr($tarikh, 0, 4))
            ? $tarikh : null;
    }
}
