<?php

namespace App\Jobs;

use App\Exceptions\SppkmsPostSentException;
use App\Models\SppkmsSync;
use App\Services\Integration\AlertService;
use App\Services\Integration\SppkmsDualWriteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Hantar satu baris sppkms_sync ke SPPKMS lama (Fasa 8 dual-write).
 * Idempoten: DONE/SKIPPED atau recno sedia ada → terus pulang.
 * Gagal percubaan terakhir → FAILED + amaran Telegram (AlertService).
 */
class PostDualWrite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 180; // login + POST borang SPPKMS lama

    /** @var array<int,int> backoff: 30s → 2m → 10m → 30m → 1j */
    public array $backoff = [30, 120, 600, 1800, 3600];

    public function __construct(public int $syncId)
    {
        $this->onQueue('sync');
    }

    public function handle(SppkmsDualWriteService $service): void
    {
        $sync = SppkmsSync::withoutMasjidScope()->find($this->syncId);

        // Idempoten — jangan POST dua kali untuk rekod sama
        if (!$sync || in_array($sync->status, ['DONE', 'SKIPPED'], true) || $sync->sppkms_recno) {
            return;
        }

        $sync->update(['attempts' => $sync->attempts + 1]);

        try {
            $service->hantar($sync);
        } catch (SppkmsPostSentException $e) {
            // POST sudah dihantar (rekod MUNGKIN tercipta di SPPKMS) — JANGAN retry
            // (elak pendua). Tanda FAILED + amaran semak manual; tiada re-throw.
            $sync->update([
                'status'     => 'FAILED',
                'last_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            app(AlertService::class)->hantar(
                (int) $sync->masjid_id,
                "⚠️ Dual-write: POST dihantar tetapi respons tak jelas\n"
                ."Jenis: {$sync->source_type} #{$sync->source_id}\n"
                .'SEMAK MANUAL di SPPKMS sama ada rekod sudah tercipta SEBELUM "Cuba Semula" '
                .'(elak rekod kewangan PENDUA).'
            );

            return; // tidak throw — tiada cubaan semula automatik
        } catch (Throwable $e) {
            // Ralat PRA-POST (login/kredensial/sambungan sebelum hantar) — selamat retry
            $sync->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }

    /** Selepas percubaan terakhir gagal — tanda FAILED + amaran admin. */
    public function failed(Throwable $e): void
    {
        $sync = SppkmsSync::withoutMasjidScope()->find($this->syncId);
        if (!$sync || in_array($sync->status, ['DONE', 'SKIPPED'], true)) {
            return;
        }

        $sync->update([
            'status'     => 'FAILED',
            'last_error' => mb_substr($e->getMessage(), 0, 500),
        ]);

        app(AlertService::class)->hantar(
            (int) $sync->masjid_id,
            "❌ Dual-write gagal ke SPPKMS lama\n"
            ."Jenis: {$sync->source_type} #{$sync->source_id}\n"
            ."Percubaan: {$sync->attempts}\n"
            .'Ralat: '.mb_substr($e->getMessage(), 0, 200)."\n"
            .'Tindakan: semak halaman Dual-Write SPPKMS dan "Cuba Semula".'
        );
    }
}
