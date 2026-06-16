<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Skop global masjid_id — setiap query model ini ditapis kepada masjid sesi
 * (multi-tenant). Guna Model::withoutMasjidScope() untuk akses merentas masjid
 * (cth kerja admin sistem / kerja latar).
 */
trait BelongsToMasjid
{
    public static function bootBelongsToMasjid(): void
    {
        static::addGlobalScope('masjid', function (Builder $builder) {
            $masjidId = app()->bound('current.masjid_id') ? app('current.masjid_id') : null;
            if ($masjidId !== null) {
                $builder->where($builder->getModel()->getTable().'.masjid_id', $masjidId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->masjid_id) && app()->bound('current.masjid_id')) {
                $model->masjid_id = app('current.masjid_id');
            }
        });
    }

    public static function withoutMasjidScope(): Builder
    {
        return static::withoutGlobalScope('masjid');
    }
}
