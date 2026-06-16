<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Masjid extends Model
{
    protected $table = 'masjid';
    const UPDATED_AT = null;
    protected $guarded = [];

    /**
     * Masjid sesi semasa — dirujuk di SEMUA tempat yang memerlukan butiran
     * masjid (kepala resit/baucer/penyata, logo dashboard & sidebar). Dimemo
     * pada peringkat permintaan (container) supaya tidak query berulang walaupun
     * dipanggil oleh berbilang view/partial. Pulang null jika tiada (DB belum
     * dimigrasi / konteks tiada) — semua pemanggil mesti guna null-safe.
     */
    public static function semasa(): ?self
    {
        $id = app()->bound('current.masjid_id')
            ? (int) app('current.masjid_id')
            : (int) config('sppkms.masjid_id');

        $key = 'masjid.semasa#'.$id;
        if (app()->bound($key)) {
            return app($key);
        }

        try {
            $masjid = static::find($id);
        } catch (\Throwable) {
            $masjid = null;
        }

        app()->instance($key, $masjid);

        return $masjid;
    }

    /** Buang memo masjid semasa — panggil selepas kemaskini info/logo masjid. */
    public static function lupakanSemasa(): void
    {
        $id = app()->bound('current.masjid_id')
            ? (int) app('current.masjid_id')
            : (int) config('sppkms.masjid_id');

        app()->forgetInstance('masjid.semasa#'.$id);
    }

    /** URL logo (disk public) atau null jika tiada logo ditetapkan. */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    /** Laluan fail mutlak logo (untuk dompdf base64) atau null. */
    public function logoPath(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        $path = storage_path('app/public/'.$this->logo_path);

        return is_file($path) ? $path : null;
    }

    /** Alamat penuh satu baris: "alamat, poskod bandar, negeri". */
    public function alamatPenuh(): string
    {
        $bandar = trim(implode(' ', array_filter([$this->poskod, $this->bandar])));

        return implode(', ', array_filter([$this->alamat, $bandar, $this->negeri]));
    }
}
