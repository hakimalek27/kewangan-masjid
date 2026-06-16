<?php

namespace App\Services\Accounting;

use App\Enums\SequenceType;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Penomboran berasingan setiap siri (Resit/PV/PWR/JNL/VKUTIPAN).
 * next() MESTI dipanggil dalam transaksi DB — baris dikunci (FOR UPDATE)
 * supaya dua pengguna serentak tidak mendapat nombor sama. Jika transaksi
 * di-rollback, kaunter turut berundur (tiada jurang).
 * Nombor MANUAL tidak melalui sini — kaunter tidak bertambah (peraturan SPPKMS).
 */
class NumberSequenceService
{
    private const DEFAULT = [
        'RESIT'    => ['prefix' => '',    'digit' => 4],
        'PV'       => ['prefix' => 'PV',  'digit' => 4],
        'PWR'      => ['prefix' => 'PWR', 'digit' => 4],
        'JNL'      => ['prefix' => 'JNL', 'digit' => 5],
        'VKUTIPAN' => ['prefix' => 'V',   'digit' => 6],
    ];

    public function next(SequenceType $jenis, ?int $masjidId = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('NumberSequenceService::next() mesti dipanggil dalam transaksi DB.');
        }

        $masjidId ??= app('current.masjid_id');

        $seq = NumberSequence::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('jenis', $jenis->value)
            ->lockForUpdate()
            ->first();

        if (!$seq) {
            $d = self::DEFAULT[$jenis->value];
            $seq = NumberSequence::withoutMasjidScope()->create([
                'masjid_id' => $masjidId,
                'jenis'     => $jenis->value,
                'prefix'    => $d['prefix'],
                'digit'     => $d['digit'],
                'next_no'   => 1,
            ]);
            $seq = NumberSequence::withoutMasjidScope()->whereKey($seq->id)->lockForUpdate()->first();
        }

        $no = $seq->prefix.str_pad((string) $seq->next_no, (int) $seq->digit, '0', STR_PAD_LEFT);
        $seq->increment('next_no');

        return $no;
    }

    /** Nombor seterusnya TANPA menambah kaunter (paparan borang). */
    public function peek(SequenceType $jenis, ?int $masjidId = null): string
    {
        $masjidId ??= app('current.masjid_id');

        $seq = NumberSequence::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('jenis', $jenis->value)
            ->first();

        if (!$seq) {
            $d = self::DEFAULT[$jenis->value];
            return $d['prefix'].str_pad('1', $d['digit'], '0', STR_PAD_LEFT);
        }

        return $seq->prefix.str_pad((string) $seq->next_no, (int) $seq->digit, '0', STR_PAD_LEFT);
    }

    /** Halaman Set Resit/Baucer — tetapkan digit & nombor mula siri. */
    public function setStart(SequenceType $jenis, int $digit, int $mula, ?string $prefix = null, ?int $masjidId = null): void
    {
        $masjidId ??= app('current.masjid_id');
        $d = self::DEFAULT[$jenis->value];

        NumberSequence::withoutMasjidScope()->updateOrCreate(
            ['masjid_id' => $masjidId, 'jenis' => $jenis->value],
            ['prefix' => $prefix ?? $d['prefix'], 'digit' => $digit, 'next_no' => $mula]
        );
    }
}
