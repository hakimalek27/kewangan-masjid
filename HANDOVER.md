# HANDOVER — SPKM (folder masih `sppkms-v2` — lihat R2 di bawah)
> Nota serah tugas ringkas. Sejarah PENUH: `../spm-explore/SEJARAH-KERJA.md` (§7o–§7r terkini).
> Laporan audit: `LAPORAN-AUDIT-MENYELURUH-20260702.md` · Pelan: `PELAN-PEMBAIKAN-AUDIT-20260702.md`.

## ⭐ KEMAS KINI 8 Jul (malam) — Semak Penyata AI: pipeline PDF imbasan + lump-sum (299/299 PHPUnit)
- **Punca "PDF tak baca penuh":** penyata bank sebenar = PDF **IMBASAN 72 muka** (scan, tiada teks). Provider vision baca muka pertama sahaja → 9–11 rekod + tarikh salah.
- **Pipeline pecah-muka:** pasang **Poppler** (winget `oschwartz10612.Poppler`; config `spkm.poppler_bin`). `PdfRenderService` (pdftoppm) → `ProsesPenyataAi::ekstrakBaris()` render setiap muka JPG → OCR satu-satu → gabung (tahan-ralat per-muka; job timeout 1800s). Guna imej per-muka (image_url) → mana-mana provider vision boleh.
- **Prompt tarikh dibaiki:** guna tarikh SEBENAR penyata (bukan hari ini); DD/MM/YY→YYYY-MM-DD (24=2024); deskripsi tak campur tarikh; abai B/F.
- **Multi-provider (dropdown):** `sp_provider` (jadual) + katalog `config('spkm.ai_provider_catalog')` (OpenAI/OpenRouter/DeepSeek/Ollama/Groq/Mistral/Custom); `/admin/semak-penyata` dropdown auto-isi base_url+model. **ADMIN set Default untuk SEMUA tenant** (tenant TIDAK pilih; upload sahaja). Fallback legasi `sp_ai_*`.
- **LUMP-SUM:** `SemakPenyataService::rekodLumpSum` + route `semakpenyata.lumpsum` + checkbox/bar/modal di view. Longgok banyak baris QR kecil sama-sisi jadi 1 rekod (infaq). Sekat sisi bercampur; kuatkuasa keluarga COA.
- **Had fail 100MB** (php.ini 100M/110M/512M) + **SSL fix** (curl.cainfo/openssl.cafile → `C:/Users/hakim/cacert.pem`; punca cURL error 60). `putFileAs` streaming.
- Baki: POPPLER_BIN boleh override via env utk environment lain; produksi Linux letak poppler di PATH.

## ⭐ KEMAS KINI 8 Jul (petang lewat #2) — 3 kerja SaaS tambahan SIAP (296/296 PHPUnit)
1. **Jenama mod penyedia:** sidebar/footer superadmin (mod penyedia) papar **SPKM / "Konsol Penyedia"**, bukan nama tenant (composer `$modProvider` + `layouts/app.blade.php`).
2. **Semak Penyata AI multi-provider:** superadmin simpan beberapa **profil provider** (OpenAI/DeepSeek/Ollama/OpenRouter — semua serasi-OpenAI) di `/admin/semak-penyata`; bendahari **pilih provider dropdown semasa upload** untuk banding OCR (jadual `sp_provider` + `penyata_semakan.sp_provider_id`; dedup kini per-provider). Config lama `sp_ai_*` kekal fallback. Model mesti sokong VISION. **Timeout upload 30s dibaiki** (`set_time_limit` dlm `ProsesPenyataAi` + worker `ai` mesti hidup).
3. **Dual-write per-tenant:** dipindah dari admin → **tetapan tenant** (`/tetapan/dual-write`, `role:bendahari,pentadbir`); setiap masjid isi login SPPKMS SENDIRI + toggle (Setting per masjid_id). Admin TIDAK lagi urus dual-write. Route/controller/view/menu lama `admin.dualwrite*` DIBUANG.

## ⭐ KEMAS KINI 8 Jul (petang lewat) — model peranan diperketat + pemisahan penyedia
Superadmin bukan lagi "akses penuh tulis" — kini **PENYEDIA baca-sahaja** (tiada tulis kewangan/tetapan tenant); **urus pengguna dipindah ke superadmin sahaja**. **DAN pemisahan penyedia-vs-tenant penuh:**
- **Mod PENYEDIA** (superadmin baru login, belum "Masuk" masjid): mendarat Konsol Sistem; sidebar tunjuk fungsi SISTEM sahaja (Pentadbiran, Urus Pengguna, Tetapan AI/API); menu + halaman KEWANGAN tenant tersembunyi/dialih ke Konsol. Middleware baharu `RestrictAdminProvider` (alias `admin.provider`).
- **Mod DALAM-TENANT** (selepas klik "Masuk" masjid di Konsol → `masjid.tukar`): boleh BACA kewangan masjid itu (tulis tetap 403); banner "Mod Lihat" + butang "Kembali ke Konsol" (`masjid.keluar` padam `selected_masjid_id`). Penukar masjid topbar hanya papar dalam mod ini.
- Isyarat mod: kehadiran `session('selected_masjid_id')`. Fail: `RestrictAdminProvider.php`, `MasjidSwitchController::keluar`, route `masjid.keluar`, `layouts/app.blade.php` (sidebar), `bootstrap/app.php` (alias).
**287/287 PHPUnit LULUS** (helper ujian `MasjidContext::adminMasuk`); disahkan manual server langsung (mod penyedia /dashboard→302 /sistem; Masuk→200; Kembali→302). Akaun ujian sementara (5 peranan + Tenant B) telah DIBUANG dari DB `spkm`. e2e role-audit dikemas (belum dijalankan).

## Keadaan semasa (8 Jul 2026, selepas semakan audit 3-ejen + audit peranan sebenar)
- **Cabang aktif:** `fix/audit-20260702` (belum merge ke main). Remote: github hakimalek27/kewangan-masjid.
- **Ujian:** 284/284 PHPUnit + 15/15 Playwright (isolation 3 + smoke-crawl 4 + **role-audit 8 BAHARU**). DB `spkm` verify-balance 3778 + audit-chain 9 OK.
- **Audit peranan sebenar (browser) SIAP:** 4 akaun disahkan satu-satu — superadmin (tulis sebenar + ADMIN_OVERRIDE + tukar masjid), pentadbir (tiada kewangan), bendahari (tulis sebenar), viewer (penyata + masjid di-assign sahaja). **REKALIBRASI TALLY:** klon ujian lama BASI (void resit 1851 tiada) — klon di-reset dari prod + 9 nilai ujian dikemas ke kebenaran prod (BS 174,589.95 dsb.). ⚠️ Klon ujian WAJIB di-reset dari `spkm` selepas sebarang pembetulan data prod.
- **Model SaaS dimuktamadkan (8 Jul malam):** peranan ditawarkan = admin/bendahari/juruaudit/viewer (pentadbir/pengerusi/setiausaha = legasi, akaun lama kekal); viewer (JAWI/MAIWP) laporan penuh baca-sahaja; onboarding superadmin-cipta. 286/286 + 15/15. Rename folder: jalankan `..RENAME-SPKM.cmd` selepas tutup semua sesi.
- **Semakan audit 3-ejen (8 Jul petang) SIAP:** semua penemuan dibaiki — K1 jurnal berganda dua-klik (lockForUpdate), K2 GAGAL kunci fail (guna semula batch), K3 duplikat sah digugurkan (dedup pra-kira), S1–S9 (COA keluarga di pelayan, race kuota, retry, tarikh AI, MATCHED tak boleh abai, butang admin Tanda GAGAL, jenis ikut tanda, arahan queue go-live). +5 ujian baharu. ⚠️ E2E perlu SATU serve sahaja di port 8123 (proses bertindih = ujian tembak DB salah) + seed `bdh_browser_b` ikut tests-e2e/README.md.
- **Audit end-to-end + pembaikan F0–F7 SIAP** (6 sektor). **+ 3 kerja baharu (8 Jul):** penjenamaan SPKM, superadmin akses penuh, ciri Semak Penyata (AI).

## ⭐ Kerja baharu 8 Jul (penjenamaan + superadmin + Semak Penyata AI)
- **Penjenamaan SPPKMS/SPAKM → SPKM (Sistem Pengurusan Kewangan Masjid):** `config/spkm.php`, `config('spkm.*')`, perintah `spkm:*`, `SPKM_MASJID_ID`, APP_NAME/manifest. **KEKAL** rujukan sistem legasi V1 (dual-write SPPKMS). DB kini `spkm`/`spkm_test`.
- **Superadmin (role admin) — model DIMUKTAMADKAN semula (8 Jul petang):** PENYEDIA (provider) **baca-sahaja** terhadap kewangan/tetapan tenant. `RoleMiddleware` admin kini lepas hanya **kaedah SELAMAT (GET)** ATAU route yang eksplisit izin `admin`; tulis melalui pagar bukan-admin → **403** (dilog `PERMISSION_DENIED`, bukan lagi `ADMIN_OVERRIDE`). `AppUser::bolehTulis/bolehUrusMasjid/bolehTulisDaftar` **TIDAK** lagi termasuk admin. Admin masih boleh: baca semua tenant, tukar-masjid, konsol/`/admin/*`, onboarding, tetapan API/AI, **urus pengguna**. (Enum `ADMIN_OVERRIDE` kekal dlm DB tetapi tak lagi ditulis.)
- **Urus pengguna = SUPERADMIN sahaja** (dulu admin+bendahari+pentadbir): route `role:admin`, menu `tetapan.pengguna`→`['admin']`. Bendahari/pentadbir tenant kini 403 (pengasingan tugas — maker tak cipta checker). Ujian dikemas: RoleMatrix/PenggunaScope/PengasinganData + e2e role-audit.
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
