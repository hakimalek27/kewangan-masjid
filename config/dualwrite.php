<?php

/**
 * Tetapan dual-write ke SPPKMS lama (Fasa 8).
 */
return [
    /*
     | Pengesahan sijil SSL untuk panggilan HTTP ke SPPKMS lama.
     |   true  (lalai)  → sahkan sijil guna CA sistem (SELAMAT, untuk produksi)
     |   false          → langkau pengesahan (TIDAK SELAMAT — elak)
     |   "/laluan/ca.pem" → sahkan guna CA bundle eksplisit (cth pelayan tanpa
     |                       CA sistem terkonfigurasi)
     | Tetap melalui env DUALWRITE_VERIFY.
     */
    'verify' => (function () {
        $v = env('DUALWRITE_VERIFY', true);
        if (is_bool($v)) {
            return $v;
        }
        return match (strtolower((string) $v)) {
            'false', '0', 'off', 'no' => false,
            'true', '1', 'on', 'yes', '' => true,
            default => $v, // laluan ke CA bundle
        };
    })(),
];
