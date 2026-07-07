# HANDOVER — SPAKM (sppkms-v2)
> Nota serah tugas ringkas. Sejarah PENUH: `../spm-explore/SEJARAH-KERJA.md` (§7o–§7q terkini).
> Laporan audit: `LAPORAN-AUDIT-MENYELURUH-20260702.md` · Pelan: `PELAN-PEMBAIKAN-AUDIT-20260702.md`.

## Keadaan semasa (7 Jul 2026)
- **Cabang aktif:** `fix/audit-20260702` (belum merge ke main). Remote: github hakimalek27/kewangan-masjid.
- **Ujian:** 262/262 PHPUnit + 7/7 Playwright E2E LULUS. Prod `sppkms` verify-balance 3778 + audit-chain 9 OK.
- **Audit end-to-end + pembaikan F0–F7 SIAP** (6 sektor: teras perakaunan, isolasi penyewa, peranan/SoD, keselamatan, API/AI/backup/dual-write, laporan/tally).

## Apa yang dibaiki (ringkas)
- B1 rantai audit silang-masjid · B2 penyata ikut bank + rekupmen · B3 P&L period 13/00 · B4 idempotency race · B5 kebocoran viewer masjid_ids
- C1–C6 concurrency+validasi · **C8 re-point 102 entri header-COA** (cipta 600-15030; P&L tak berubah)
- E1–E13 hardening (throttle token, fillable, envelope ralat v1, uji-pulih backup, dual-write anti-pendua, SSRF guard, view)
- Go-live: buang residu masjid 50, `.env.production.example`, `SENARAI-SEMAK-GOLIVE.md`, paksa-tukar-kata-laluan + command reset

## DB prod disentuh (backup di ../spm-explore/shots/pra-*)
buang masjid 50 · re-point header-COA · kolum `must_change_password` · view `v_program_report`. Migrasi `2026_07_02_000001/000002`.

## ⚠️ BAKI SEBELUM GO-LIVE (belum buat)
1. **Merge cabang `fix/audit-20260702`** ke main.
2. **Regen `install.sql`** untuk fresh-install: tambah `must_change_password`, COA `600-15030`, view `v_program_report` terkini.
3. **Frontend:** `npm run build` + **pastikan `public/hot` TIADA** (jika ada → carta rosak `window.Chart is not a constructor`). Lihat SENARAI-SEMAK-GOLIVE.md §6.
4. **Tukar kata laluan lalai sebenar:** `php artisan sppkms:reset-default-passwords` (admin/malmutaqqin masih guna lalai).
5. **Config go-live:** API key AI, bot Telegram + webhook secret, service account GDrive, kredensial dual-write; `.env` production (APP_DEBUG=false, SESSION_SECURE_COOKIE=true).

## Didokumen — sengaja TIDAK diubah (ada rasional)
- C7 dashboard rekupmen = V1-faithful buku-tunai (tukar akan pecah tally 899,569.50)
- D1–D4 SoD: pengguna pilih kekal bendahari/pentadbir kawal kawalan/tutup-tahun; bendahari kekal urus pengguna
- E10 prompt-injection (manusia sahkan) · E11 lejer tarikh vs penyata period_ym · E12 baucer_no pendua warisan (angka betul)

## Nota teknikal
- Ujian guna `DatabaseTransactions` atas klon `sppkms_test` (JANGAN RefreshDatabase). Parallel TIDAK disokong (perlu klon DB per-worker).
- E2E: `DB_DATABASE=sppkms_test php artisan serve --port=8123` + `npx playwright test`. Login lalai: admin/admin12345, malmutaqqin/alm12345.
- Path mysql XAMPP: `C:\Users\hakim\xampp\mysql\bin\` (hidupkan mysqld dahulu).
