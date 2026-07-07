# Ujian Pelayar (Playwright) — peranan, pengasingan & audit menyeluruh

3 spec:

- **`isolation.spec.js`** — pengasingan merentas-masjid (anti-IDOR) + landing peranan.
- **`smoke-crawl.spec.js`** — lawati SETIAP halaman (bendahari + admin), status OK + sifar ralat JS + pautan dalaman.
- **`role-audit.spec.js`** — audit peranan MENYELURUH 4 akaun: SUPERADMIN (akses penuh + tulis sebenar + tukar masjid + kawalan Semak Penyata AI), PENTADBIR (tetapan sahaja, tiada kewangan/sistem), BENDAHARI (kewangan penuh + tulis sebenar, tiada sistem), VIEWER (penyata sahaja + hanya masjid di-assign).

## Jangan sentuh pangkalan data produksi

Ujian ini dijalankan terhadap **klon `spkm_test`**, bukan `spkm` (produksi).
⚠️ `role-audit.spec.js` MENULIS rekod kutipan sebenar ke dalam klon — **reset klon selepas selesai** (langkah 5).
⚠️ Pastikan **HANYA SATU** pelayan di port 8123 — proses bertindih menyebabkan ujian menembak DB salah
(`netstat -ano | findstr :8123` → `taskkill /F /PID <pid>`).

## Cara jalan

1. **Semai akaun ujian** (sekali) ke dalam klon:

   ```bash
   DB_DATABASE=spkm_test php artisan tinker --execute="\$m=App\Models\Masjid::firstOrCreate(['nama'=>'UJIAN BROWSER MASJID B']); if (\App\Models\Coa::withoutMasjidScope()->where('masjid_id',\$m->id)->count()<1) { app(\App\Services\Tetapan\CoaTemplateService::class)->sediaUntukMasjid(\$m->id); } \$mid=(int)config('spkm.masjid_id'); \$h=Illuminate\Support\Facades\Hash::make('uji12345'); App\Models\AppUser::updateOrCreate(['login'=>'bdh_browser_b'],['masjid_id'=>\$m->id,'nama_penuh'=>'Bendahari B Ujian','role'=>'bendahari','password_hash'=>Illuminate\Support\Facades\Hash::make('ujianB12345'),'is_active'=>1,'must_change_password'=>0]); App\Models\AppUser::updateOrCreate(['login'=>'pentadbir_uji'],['masjid_id'=>\$mid,'nama_penuh'=>'Pentadbir Uji','role'=>'pentadbir','password_hash'=>\$h,'is_active'=>1,'must_change_password'=>0]); \$v=App\Models\AppUser::updateOrCreate(['login'=>'viewer_uji'],['masjid_id'=>\$mid,'nama_penuh'=>'Viewer Uji','role'=>'viewer','password_hash'=>\$h,'is_active'=>1,'must_change_password'=>0]); \$v->masjids()->sync([\$m->id]);"
   ```

2. **Hidupkan pelayan** menunjuk ke klon (port 8123):

   ```bash
   php artisan config:clear
   DB_DATABASE=spkm_test php artisan serve --host=127.0.0.1 --port=8123
   ```

3. **Jalankan ujian** (terminal lain):

   ```bash
   npx playwright test
   ```

4. **Selepas selesai** — reset klon (buang rekod ujian pelayar + akaun semaian sekali gus):

   ```powershell
   & "C:\Users\hakim\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS spkm_test; CREATE DATABASE spkm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   & "C:\Users\hakim\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction --routines --triggers spkm | & "C:\Users\hakim\xampp\mysql\bin\mysql.exe" -u root spkm_test
   ```

Akaun sedia ada dalam klon: `admin/admin12345` (superadmin), `malmutaqqin/alm12345` (bendahari) — kedua-dua masjid 49.
Akaun semaian: `pentadbir_uji/uji12345`, `viewer_uji/uji12345` (di-assign masjid B), `bdh_browser_b/ujianB12345` (masjid B).
