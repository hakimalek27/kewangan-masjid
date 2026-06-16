# -*- coding: utf-8 -*-
import io, json, re, glob, os

BASE = r"C:\Projek Coding\Sistem Kewangan Masjid\sppkms-v2"

files = []
for pat in [
    r"resources\views\layouts\app.blade.php",
    r"resources\views\auth\login.blade.php",
    r"resources\views\placeholder.blade.php",
    r"resources\views\tetapan\*.blade.php",
    r"resources\views\admin\*.blade.php",
    r"resources\views\ai\*.blade.php",
    r"resources\views\lanjutan\*.blade.php",
]:
    files.extend(glob.glob(os.path.join(BASE, pat)))

keys = set()
rx1 = re.compile(r"__\('((?:[^'\\]|\\.)*)'\)")
for f in files:
    s = io.open(f, encoding="utf-8").read()
    for m in rx1.finditer(s):
        k = m.group(1).replace("\\'", "'")
        keys.add(k)

# kunci dinamik dari controller / array PHP yang dipaparkan melalui __($var)
keys.update([
    # wizard (SetupWizardController)
    "Daftar Akaun Bank",
    "Tetapkan akaun bank masjid (slot 1–3) dan petakan ke COA 250-050x0.",
    "Masukkan baki awal tahun (bank/aset/liabiliti) dan muktamadkan.",
    "Petakan label tempatan masjid kepada COA piawai.",
    "Set No. Resit/Baucer",
    "Tetapkan turutan nombor resit, PV, PWR dan jurnal.",
    "Muat Naik Logo Masjid",
    "Logo dipaparkan pada resit dan penyata kewangan.",
    # resit (ResitBaucerController::SIRI)
    "Resit Kutipan", "Baucer Bayaran (PV)", "Baucer PWR", "Jurnal (JNL)",
    # mapping jenisLabel
    "Penerimaan", "Perbelanjaan", "Kedua-dua",
    # backup mode labels
    "Per transaksi (setiap kutipan/bayaran/jurnal)", "Dump DB harian (02:00)", "Log audit & keselamatan (setiap jam)",
    # draf-lihat kaedah labels
    "EFT / Pindahan Bank", "QR", "Cek", "Tunai",
    # baki-terkini namaBulan
    "Januari","Februari","Mac","April","Mei","Jun","Julai","Ogos","September","Oktober","November","Disember",
])

en = json.load(io.open(os.path.join(BASE, r"lang\en.json"), encoding="utf-8"))
missing = sorted(k for k in keys if k not in en)
print("JUMLAH KUNCI DIGUNAKAN:", len(keys))
print("KUNCI BELUM ADA:", len(missing))
with io.open(os.path.join(BASE, "_missing.json"), "w", encoding="utf-8") as f:
    json.dump(missing, f, ensure_ascii=False, indent=0)
