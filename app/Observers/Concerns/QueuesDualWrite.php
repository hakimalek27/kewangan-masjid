<?php

namespace App\Observers\Concerns;

use App\Jobs\PostDualWrite;
use App\Models\SppkmsSync;
use App\Services\Integration\AlertService;
use App\Support\Setting;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Logik kongsi observer dual-write SPPKMS (Fasa 8) — corak sama seperti
 * QueuesTransactionBackup: berjalan SELEPAS commit ($afterCommit pada
 * observer), best-effort, TIDAK menyentuh logik kewangan service.
 *
 * Hanya rekod yang DICIPTA BAHARU melalui sistem ini di-dual-write —
 * rekod migrasi/penulisan terus DB tidak melalui created() Eloquent,
 * jadi tiada risiko gelung atau hantaran berganda.
 */
trait QueuesDualWrite
{
    /**
     * Masukkan baris sppkms_sync PENDING + dispatch PostDualWrite jika
     * tetapan dual_write_sppkms masjid aktif. Idempoten via UNIQUE
     * (masjid_id, source_type, source_id) — firstOrCreate.
     */
    protected function masukBarisanDualWrite(Model $model, string $sourceType): void
    {
        try {
            $masjidId = (int) $model->masjid_id;

            if (!Setting::isOn('dual_write_sppkms', $masjidId)) {
                return;
            }

            $sync = SppkmsSync::withoutMasjidScope()->firstOrCreate(
                [
                    'masjid_id'   => $masjidId,
                    'source_type' => $sourceType,
                    'source_id'   => $model->getKey(),
                ],
                ['status' => 'PENDING']
            );

            if ($sync->wasRecentlyCreated) {
                PostDualWrite::dispatch($sync->id);
            }
        } catch (Throwable $e) {
            $this->logRalatDualWrite($model, $sourceType, $e);
        }
    }

    /**
     * Rekod sumber dibatal/dipadam SELEPAS sudah dihantar (DONE) ke
     * SPPKMS lama — sistem lama TIADA endpoint padam yang selamat untuk
     * automasi (keputusan: padam manual). Tanda baris sync + amaran admin.
     */
    protected function tandaPerluPadamManual(Model $model, string $sourceType): void
    {
        try {
            $sync = SppkmsSync::withoutMasjidScope()
                ->where('masjid_id', (int) $model->masjid_id)
                ->where('source_type', $sourceType)
                ->where('source_id', $model->getKey())
                ->where('status', 'DONE')
                ->first();

            if (!$sync) {
                return;
            }

            $sync->update([
                'last_error' => "PERLU PADAM MANUAL di SPPKMS (recno {$sync->sppkms_recno})",
            ]);

            app(AlertService::class)->hantar(
                (int) $model->masjid_id,
                "⚠️ DUAL-WRITE: rekod dibatalkan di sistem baharu\n"
                ."Jenis: {$sourceType} #{$model->getKey()}\n"
                ."Rekod SPPKMS lama recno {$sync->sppkms_recno} PERLU DIPADAM MANUAL\n"
                .'(SPPKMS tiada endpoint padam selamat untuk automasi).'
            );
        } catch (Throwable $e) {
            $this->logRalatDualWrite($model, $sourceType, $e);
        }
    }

    private function logRalatDualWrite(Model $model, string $sourceType, Throwable $e): void
    {
        // Dual-write TIDAK boleh mengganggu transaksi — log senyap sahaja
        try {
            \App\Models\ErrorLog::withoutMasjidScope()->create([
                'masjid_id' => $model->masjid_id ?? null,
                'level'     => 'WARNING',
                'message'   => mb_substr('Gagal barisan dual-write '.$sourceType.' #'.$model->getKey().': '.$e->getMessage(), 0, 500),
            ]);
        } catch (Throwable) {
            // senyap sepenuhnya
        }
    }
}
