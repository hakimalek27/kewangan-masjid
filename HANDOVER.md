# HANDOVER — SPKM (folder masih `sppkms-v2` — lihat R2 di bawah)
> Nota serah tugas ringkas. Sejarah PENUH: `../spm-explore/SEJARAH-KERJA.md` (§7o–§7r terkini).
> Laporan audit: `LAPORAN-AUDIT-MENYELURUH-20260702.md` · Pelan: `PELAN-PEMBAIKAN-AUDIT-20260702.md`.

## Keadaan semasa (8 Jul 2026)
- **Cabang aktif:** `fix/audit-20260702` (belum merge ke main). Remote: github hakimalek27/kewangan-masjid.
- **Ujian:** 279/279 PHPUnit + 4/4 Playwright E2E LULUS. DB `spkm` verify-balance 3778 + audit-chain 9 OK.
- **Audit end-to-end + pembaikan F0–F7 SIAP** (6 sektor). **+ 3 kerja baharu (8 Jul):** penjenamaan SPKM, superadmin akses penuh, ciri Semak Penyata (AI).

## ⭐ Kerja baharu 8 Jul (penjenamaan + superadmin + Semak Penyata AI)
- **Penjenamaan SPPKMS/SPAKM → SPKM (Sistem Pengurusan Kewangan Masjid):** `config/spkm.php`, `config('spkm.*')`, perintah `spkm:*`, `SPKM_MASJID_ID`, APP_NAME/manifest. **KEKAL** rujukan sistem legasi V1 (dual-write SPPKMS). DB kini `spkm`/`spkm_test`.
- **Superadmin (role admin) akses PENUH baca+tulis semua tenant:** `RoleMiddleware` pintasan admin + `security_event ADMIN_OVERRIDE` (migrasi `2026_07_08_000001`); `AppUser::bolehTulis*` termasuk admin; menu penuh untuk admin.
- **Ciri "Semak Penyata (AI)":** bendahari upload penyata bank (PDF/imej) → AI (kunci OpenAI PUSAT, kawalan superadmin) → 2 jadual (belum/sudah rekod) → klik Rekod = catat kutipan/belanja. Kuota 3/bulan/tenant (superadmin ubah/top-up/ON-OFF di `/admin/semak-penyata`). Jadual `penyata_semakan` + kolum AI pada `bank_statement_line` (migrasi `2026_07_08_000002`). Job `ProsesPenyataAi` (queue `ai`).

## Apa yang dibaiki (ringkas)
- B1 rantai audit silang-masjid · B2 penyata ikut bank + rekupmen · B3 P&L period 13/00 · B4 idempotency race · B5 kebocoran viewer masjid_ids
- C1–C6 concurrency+validasi · **C8 re-point 102 entri header-COA** (cipta 600-15030; P&L tak berubah)
- E1–E13 hardening (throttle token, fillable, envelope ralat v1, uji-pulih backup, dual-write anti-pendua, SSRF guard, view)
- Go-live: buang residu masjid 50, `.env.production.example`, `SENARAI-SEMAK-GOLIVE.md`, paksa-tukar-kata-laluan + command reset

## DB prod disentuh (backup di ../spm-explore/shots/pra-*)
buang masjid 50 · re-point header-COA · kolum `must_change_password` · view `v_program_report`. Migrasi `2026_07_02_000001/000002`.

## ⚠️ BAKI SEBELUM GO-LIVE (belum buat)
1. **Merge cabang `fix/audit-20260702`** ke main.
2. **Regen `install.sql`** untuk fresh-install: tambah `must_change_password`, COA `600-15030`, view `v_program_report`, **jadual `penyata_semakan` + kolum baharu `bank_statement_line` (batch_id, cadangan_jenis, cadangan_coa_id, ai_confidence) + enum `security_event` ADMIN_OVERRIDE**.
3. **Frontend:** `npm run build` + **pastikan `public/hot` TIADA** (jika ada → carta rosak `window.Chart is not a constructor`). Lihat SENARAI-SEMAK-GOLIVE.md §6.
4. **Tukar kata laluan lalai sebenar:** `php artisan spkm:reset-default-passwords` (admin/malmutaqqin masih guna lalai).
5. **Config go-live:** API key AI, bot Telegram + webhook secret, service account GDrive, kredensial dual-write; `.env` production (APP_DEBUG=false, SESSION_SECURE_COOKIE=true).
6. **Kunci OpenAI PUSAT Semak Penyata (AI):** set di `/admin/semak-penyata` (superadmin) + hidupkan toggle global; kuota lalai 3/bulan/tenant.
7. **R2 — tukar nama folder `sppkms-v2` → `spkm`:** DB sudah `spkm`/`spkm_test` (DB lama `sppkms`/`sppkms_test` KEKAL sebagai sandaran). Rename folder = langkah PALING akhir, dari LUAR folder (tutup semua sesi/proses dahulu); selepas itu buka semula projek di laluan baharu.

## Didokumen — sengaja TIDAK diubah (ada rasional)
- C7 dashboard rekupmen = V1-faithful buku-tunai (tukar akan pecah tally 899,569.50)
- D1–D4 SoD: pengguna pilih kekal bendahari/pentadbir kawal kawalan/tutup-tahun; bendahari kekal urus pengguna
- E10 prompt-injection (manusia sahkan) · E11 lejer tarikh vs penyata period_ym · E12 baucer_no pendua warisan (angka betul)

## Nota teknikal
- Ujian guna `DatabaseTransactions` atas klon `spkm_test` (JANGAN RefreshDatabase). Parallel TIDAK disokong (perlu klon DB per-worker).
- E2E: `DB_DATABASE=spkm_test php artisan serve --port=8123` + `npx playwright test`. Login lalai: admin/admin12345, malmutaqqin/alm12345.
- Path mysql XAMPP: `C:\Users\hakim\xampp\mysql\bin\` (hidupkan mysqld dahulu).
