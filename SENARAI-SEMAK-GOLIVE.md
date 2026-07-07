# SENARAI SEMAK GO-LIVE — SPKM

Ikut turutan ini sebelum sistem digunakan sebenar.

## 1. Konfigurasi pelayan
- [ ] Salin `.env.production.example` → `.env`, isi nilai sebenar.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`.
- [ ] `APP_URL=https://…` + sijil HTTPS sah; `SESSION_SECURE_COOKIE=true`.
- [ ] Pengguna DB khusus (bukan `root`) + kata laluan kukuh.
- [ ] `php artisan key:generate` (jika APP_KEY kosong) — **jangan tukar APP_KEY selepas ada rahsia dalam secret_vault** (akan rosak dekripsi).
- [ ] `php artisan config:cache route:cache view:cache` selepas set config.

## 2. Kredensial & akaun
- [ ] Jalankan `php artisan spkm:reset-default-passwords` (set kata laluan rawak + paksa tukar) ATAU tukar manual kata laluan `admin` & `malmutaqqin`.
- [ ] Sahkan tiada akaun ujian aktif (`test` sudah dibuang).
- [ ] Log masuk pertama setiap pengguna → dipaksa tukar kata laluan.

## 3. Integrasi (jika digunakan)
- [ ] `TELEGRAM_WEBHOOK_SECRET` diisi + `setWebhook` ke Telegram + chat allowlist.
- [ ] Kunci API AI dimasukkan via /tetapan/ai (disimpan tersulit).
- [ ] Service account Google Drive (`GDRIVE_SA_JSON_PATH`) untuk backup.
- [ ] Kredensial dual-write dalam vault (jika toggle ON — lalai OFF).

## 4. Penjadual & queue
- [ ] Cron: `* * * * * php artisan schedule:run` (backup 02:00, prune 03:30, sapu-lampiran 04:00, integriti 06:30, FD 07:00, defisit 07:30, baki-rendah 08:00, susut-nilai 1hb 01:00).
- [ ] Worker queue berjalan: `php artisan queue:work --queue=default,ai,webhook,backup,sync` (TANPA `--queue=` hanya queue `default` diproses — AI/webhook/backup/sync akan gagal senyap).

## 5. Pengesahan akhir
- [ ] `php artisan spkm:verify-balance` → semua voucher seimbang.
- [ ] `php artisan spkm:verify-audit-chain` → rantai utuh.
- [ ] `php artisan test` → semua LULUS.
- [ ] Backup pertama berjaya + `ujiPulih` (restore-test) lulus.

## 6. Aset frontend (Vite) — WAJIB, elak carta/JS rosak
- [ ] `npm run build` (hasilkan `public/build/` terkini).
- [ ] **Pastikan `public/hot` TIADA** — jika wujud, `@vite` cuba muat dari pelayan dev
      (`:5174`) yang tak wujud di prod → `window.Chart is not a constructor` & JS rosak
      pada dashboard/statistik. Padam: `rm -f public/hot`.

## 7. Selepas kemas kini kod (elak UI/tingkah laku lama)
- [ ] `php artisan optimize:clear` + RESTART pelayan web (buang OPcache).

## 8. Ujian pra-go-live
- [ ] `php artisan test` → semua LULUS (262+).
- [ ] E2E pelayar: hidupkan serve ke klon (`DB_DATABASE=spkm_test php artisan serve --port=8123`)
      + `npx playwright test` → isolasi peranan + smoke-crawl semua halaman LULUS.
