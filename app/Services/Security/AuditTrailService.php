<?php

namespace App\Services\Security;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Jejak audit append-only dengan hash-chain:
 * row_hash = sha256(prev_hash | masjid | user | action | entity | entity_id | before | after | masa)
 * Sebarang pengubahan/pemadaman baris lama memutuskan rantai — dikesan oleh
 * command sppkms:verify-audit-chain.
 */
class AuditTrailService
{
    public function log(
        string $action,
        string $entity,
        ?array $before = null,
        ?array $after = null,
        ?int $entityId = null,
        ?int $userId = null,
        ?int $masjidId = null,
    ): AuditTrail {
        $masjidId ??= app()->bound('current.masjid_id') ? app('current.masjid_id') : null;
        $userId   ??= app()->bound('current.user_id') ? app('current.user_id') : null;

        $req = request();

        return DB::transaction(function () use ($action, $entity, $before, $after, $entityId, $userId, $masjidId, $req) {
            $prev = AuditTrail::query()
                ->when($masjidId, fn ($q) => $q->where('masjid_id', $masjidId))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('row_hash');

            $now = now()->format('Y-m-d H:i:s');
            /*
             | PENTING: cast 'array' pada model akan menyimpan json_encode($nilai)
             | (flag lalai). Hash MESTI dikira atas rentetan TEPAT yang disimpan
             | dalam DB — jika tidak, verify-audit-chain (yang membaca nilai raw)
             | akan gagal. Justeru: kira hash dengan json_encode flag lalai dan
             | hantar ARRAY kepada create() supaya cast menghasilkan rentetan sama.
             */
            $beforeJson = $before ? json_encode($before) : null;
            $afterJson  = $after ? json_encode($after) : null;

            $rowHash = hash('sha256', implode('|', [
                $prev ?? '', $masjidId ?? '', $userId ?? '', $action, $entity,
                $entityId ?? '', $beforeJson ?? '', $afterJson ?? '', $now,
            ]));

            return AuditTrail::create([
                'masjid_id'   => $masjidId,
                'user_id'     => $userId,
                'action'      => $action,
                'entity'      => $entity,
                'entity_id'   => $entityId,
                'before_json' => $before,
                'after_json'  => $after,
                'ip_address'  => $req?->ip(),
                'user_agent'  => substr((string) $req?->userAgent(), 0, 255),
                'prev_hash'   => $prev,
                'row_hash'    => $rowHash,
                'created_at'  => $now,
            ]);
        });
    }
}
