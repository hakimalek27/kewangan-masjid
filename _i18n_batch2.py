# -*- coding: utf-8 -*-
import io

BASE = r"C:\Projek Coding\Sistem Kewangan Masjid\sppkms-v2"

def sub(path, pairs):
    full = BASE + "\\" + path.replace("/", "\\")
    with io.open(full, encoding="utf-8") as f:
        s = f.read()
    miss = 0
    for old, new in pairs:
        if old not in s:
            print("MISS:", path, "|", old[:80])
            miss += 1
        else:
            s = s.replace(old, new)
    with io.open(full, "w", encoding="utf-8", newline="") as f:
        f.write(s)
    print("OK", path, "(miss=%d)" % miss)

# ---------- lanjutan/kelulusan.blade.php ----------
sub("resources/views/lanjutan/kelulusan.blade.php", [
    ("@section('title', 'Kelulusan Maker-Checker')", "@section('title', __('Kelulusan Maker-Checker'))"),
    ("""    Had kelulusan semasa: <strong>{{ $had > 0 ? 'RM '.number_format($had, 2) : 'DIMATIKAN (0)' }}</strong> —
    bayaran bendahari melebihi had memerlukan kelulusan admin/pengerusi sebelum direkodkan ke jurnal.
    Ubah had di halaman <a href="{{ route('kawalan.index') }}">Kawalan</a>.""",
     """    {{ __('Had kelulusan semasa:') }} <strong>{{ $had > 0 ? 'RM '.number_format($had, 2) : __('DIMATIKAN (0)') }}</strong> —
    {{ __('bayaran bendahari melebihi had memerlukan kelulusan admin/pengerusi sebelum direkodkan ke jurnal.') }}
    {{ __('Ubah had di halaman') }} <a href="{{ route('kawalan.index') }}">{{ __('Kawalan') }}</a>."""),
    ('<div class="card-header fw-bold">Permohonan Menunggu <span', '<div class="card-header fw-bold">{{ __(\'Permohonan Menunggu\') }} <span'),
    ("<th>Butiran Permohonan</th>", "<th>{{ __('Butiran Permohonan') }}</th>"),
    ('<th class="text-end" style="width:130px">Jumlah (RM)</th>', '<th class="text-end" style="width:130px">{{ __(\'Jumlah (RM)\') }}</th>'),
    ('<th style="width:150px">Pemohon (Maker)</th>', '<th style="width:150px">{{ __(\'Pemohon (Maker)\') }}</th>'),
    ('<th style="width:140px">Tarikh Mohon</th>', '<th style="width:140px">{{ __(\'Tarikh Mohon\') }}</th>'),
    ('<th class="no-print" style="width:210px">Tindakan</th>', '<th class="no-print" style="width:210px">{{ __(\'Tindakan\') }}</th>'),
    ("@if (!empty($payload['pemohon'])) — penerima: {{ $payload['pemohon'] }} @endif",
     "@if (!empty($payload['pemohon'])) — {{ __('penerima:') }} {{ $payload['pemohon'] }} @endif"),
    ("onclick=\"return confirm('Lulus & rekodkan pembayaran #{{ $p->id }} (RM {{ number_format((float) $p->amaun, 2) }})?')\"",
     "onclick=\"return confirm('{{ __('Lulus & rekodkan pembayaran') }} #{{ $p->id }} (RM {{ number_format((float) $p->amaun, 2) }})?')\""),
    ('<i class="bi bi-check-lg"></i> Lulus', '<i class="bi bi-check-lg"></i> {{ __(\'Lulus\') }}'),
    ('<i class="bi bi-x-lg"></i> Tolak', '<i class="bi bi-x-lg"></i> {{ __(\'Tolak\') }}'),
    (">Tiada permohonan menunggu kelulusan. 🎉</td>", ">{{ __('Tiada permohonan menunggu kelulusan.') }} 🎉</td>"),
    ('<div class="card-header fw-bold">Sejarah Keputusan (30 terkini)</div>',
     '<div class="card-header fw-bold">{{ __(\'Sejarah Keputusan (30 terkini)\') }}</div>'),
    ("<tr><th>#</th><th>Catatan</th><th class=\"text-end\">Jumlah (RM)</th><th>Status</th><th>Checker</th><th>Diputuskan</th></tr>",
     "<tr><th>#</th><th>{{ __('Catatan') }}</th><th class=\"text-end\">{{ __('Jumlah (RM)') }}</th><th>{{ __('Status') }}</th><th>{{ __('Checker') }}</th><th>{{ __('Diputuskan') }}</th></tr>"),
    (">Tiada sejarah.</td>", ">{{ __('Tiada sejarah.') }}</td>"),
    ('<h5 class="modal-title">Tolak Permohonan #{{ $p->id }}</h5>', '<h5 class="modal-title">{{ __(\'Tolak Permohonan\') }} #{{ $p->id }}</h5>'),
    ('<label class="form-label" for="sebab-{{ $p->id }}">Sebab Penolakan</label>',
     '<label class="form-label" for="sebab-{{ $p->id }}">{{ __(\'Sebab Penolakan\') }}</label>'),
    (">Sahkan Tolak</button>", ">{{ __('Sahkan Tolak') }}</button>"),
])

# ---------- lanjutan/rekonsiliasi.blade.php ----------
sub("resources/views/lanjutan/rekonsiliasi.blade.php", [
    ("@section('title', 'Rekonsiliasi Bank')", "@section('title', __('Rekonsiliasi Bank'))"),
    ('<div class="card-header fw-bold">Pilih Bank</div>', '<div class="card-header fw-bold">{{ __(\'Pilih Bank\') }}</div>'),
    ("                                    Slot {{ $b->slot }} : ", "                                    {{ __('Slot') }} {{ $b->slot }} : "),
    (">Papar</button>", ">{{ __('Papar') }}</button>"),
    ('<div class="card-header fw-bold">Muat Naik Penyata Bank (CSV)</div>',
     '<div class="card-header fw-bold">{{ __(\'Muat Naik Penyata Bank (CSV)\') }}</div>'),
    ('<div class="form-text">Lajur: tarikh (Y-m-d atau d/m/Y), deskripsi, debit, kredit, baki — baris pertama header.</div>',
     '<div class="form-text">{{ __(\'Lajur: tarikh (Y-m-d atau d/m/Y), deskripsi, debit, kredit, baki — baris pertama header.\') }}</div>'),
    ('<i class="bi bi-upload me-1"></i>Import &amp; Padan Auto', '<i class="bi bi-upload me-1"></i>{{ __(\'Import & Padan Auto\') }}'),
    ("<div class=\"small text-muted\">Baris Penyata ({{ $laporan['dari'] }} → {{ $laporan['hingga'] }})</div>",
     "<div class=\"small text-muted\">{{ __('Baris Penyata') }} ({{ $laporan['dari'] }} → {{ $laporan['hingga'] }})</div>"),
    ('<div class="small text-muted">Dipadan / Belum</div>', '<div class="small text-muted">{{ __(\'Dipadan / Belum\') }}</div>'),
    ('<div class="small text-muted">Bersih Penyata lwn Buku (RM)</div>', '<div class="small text-muted">{{ __(\'Bersih Penyata lwn Buku (RM)\') }}</div>'),
    ("{{ number_format((float) $laporan['bersih_penyata'], 2) }} lwn {{ number_format((float) $laporan['bersih_buku'], 2) }}",
     "{{ number_format((float) $laporan['bersih_penyata'], 2) }} {{ __('lwn') }} {{ number_format((float) $laporan['bersih_buku'], 2) }}"),
    ('<div class="small text-muted">Beza (RM)</div>', '<div class="small text-muted">{{ __(\'Beza (RM)\') }}</div>'),
    ('<div class="card-header fw-bold">Baris Penyata BELUM DIPADAN <span', '<div class="card-header fw-bold">{{ __(\'Baris Penyata BELUM DIPADAN\') }} <span'),
    ("<th>Tarikh</th><th>Deskripsi Penyata</th>", "<th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi Penyata') }}</th>"),
    ('<th class="text-end">Keluar/Dr (RM)</th><th class="text-end">Masuk/Cr (RM)</th>',
     '<th class="text-end">{{ __(\'Keluar/Dr (RM)\') }}</th><th class="text-end">{{ __(\'Masuk/Cr (RM)\') }}</th>'),
    ('<th class="no-print" style="width:230px">Padanan Manual</th>', '<th class="no-print" style="width:230px">{{ __(\'Padanan Manual\') }}</th>'),
    ('<i class="bi bi-search"></i> Cari Voucher</a>', '<i class="bi bi-search"></i> {{ __(\'Cari Voucher\') }}</a>'),
    ("onclick=\"return confirm('Abaikan baris penyata ini?')\">Abai</button>",
     "onclick=\"return confirm('{{ __('Abaikan baris penyata ini?') }}')\">{{ __('Abai') }}</button>"),
    (">Tiada baris belum dipadan. 🎉</td>", ">{{ __('Tiada baris belum dipadan.') }} 🎉</td>"),
    ('<div class="card-header fw-bold">Padanan Manual — Baris Penyata #{{ $lineCari }}</div>',
     '<div class="card-header fw-bold">{{ __(\'Padanan Manual — Baris Penyata\') }} #{{ $lineCari }}</div>'),
    ('placeholder="Cari ref voucher / deskripsi / amaun..."', 'placeholder="{{ __(\'Cari ref voucher / deskripsi / amaun...\') }}"'),
    (">Cari</button>", ">{{ __('Cari') }}</button>"),
    ("<tr><th>Voucher</th><th>Tarikh</th><th>Deskripsi</th><th class=\"text-end\">Dr Bank</th><th class=\"text-end\">Cr Bank</th><th></th></tr>",
     "<tr><th>{{ __('Voucher') }}</th><th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th><th class=\"text-end\">{{ __('Dr Bank') }}</th><th class=\"text-end\">{{ __('Cr Bank') }}</th><th></th></tr>"),
    ('<i class="bi bi-link-45deg"></i> Padan</button>', '<i class="bi bi-link-45deg"></i> {{ __(\'Padan\') }}</button>'),
    (">Tiada voucher calon — cuba carian lain.</td>", ">{{ __('Tiada voucher calon — cuba carian lain.') }}</td>"),
    ('<div class="card-header fw-bold">Baris DIPADAN terkini <span', '<div class="card-header fw-bold">{{ __(\'Baris DIPADAN terkini\') }} <span'),
    ("<tr><th>Tarikh</th><th>Deskripsi</th><th class=\"text-end\">Dr</th><th class=\"text-end\">Cr</th><th>Voucher #</th></tr>",
     "<tr><th>{{ __('Tarikh') }}</th><th>{{ __('Deskripsi') }}</th><th class=\"text-end\">Dr</th><th class=\"text-end\">Cr</th><th>{{ __('Voucher') }} #</th></tr>"),
    (">Belum ada padanan.</td>", ">{{ __('Belum ada padanan.') }}</td>"),
])

# ---------- lanjutan/susut-nilai.blade.php ----------
sub("resources/views/lanjutan/susut-nilai.blade.php", [
    ("@section('title', 'Susut Nilai Aset')", "@section('title', __('Susut Nilai Aset'))"),
    ('<label class="form-label" for="bulan">Bulan</label>', '<label class="form-label" for="bulan">{{ __(\'Bulan\') }}</label>'),
    ('<label class="form-label" for="tahun">Tahun</label>', '<label class="form-label" for="tahun">{{ __(\'Tahun\') }}</label>'),
    ("onclick=\"return confirm('Jana & pos jurnal susut nilai bulan ini untuk semua aset aktif? Aset yang sudah diposkan bulan ini akan dilangkau (idempoten).')\"",
     "onclick=\"return confirm('{{ __('Jana & pos jurnal susut nilai bulan ini untuk semua aset aktif? Aset yang sudah diposkan bulan ini akan dilangkau (idempoten).') }}')\""),
    ('<i class="bi bi-calculator me-1"></i>Jana &amp; Pos Bulan Ini', '<i class="bi bi-calculator me-1"></i>{{ __(\'Jana & Pos Bulan Ini\') }}'),
    ("""                Garis lurus: <code>kos × kadar% / 12</code> sebulan (atau <code>kos / usia guna / 12</code> jika kadar tiada).
                Jurnal: Dr 650-10000 / Cr akaun SNT aset. Berhenti automatik apabila susut terkumpul = kos.""",
     """                {{ __('Garis lurus:') }} <code>kos × kadar% / 12</code> {{ __('sebulan (atau') }} <code>kos / usia guna / 12</code> {{ __('jika kadar tiada).') }}
                {{ __('Jurnal: Dr 650-10000 / Cr akaun SNT aset. Berhenti automatik apabila susut terkumpul = kos.') }}"""),
    ('<div class="card-header fw-bold">Senarai Aset &amp; Jadual Susut Nilai</div>',
     '<div class="card-header fw-bold">{{ __(\'Senarai Aset & Jadual Susut Nilai\') }}</div>'),
    ("<th>Kod / Nama Aset</th>", "<th>{{ __('Kod / Nama Aset') }}</th>"),
    ("<th>Akaun / SNT</th>", "<th>{{ __('Akaun / SNT') }}</th>"),
    ('<th class="text-end">Kos (RM)</th>', '<th class="text-end">{{ __(\'Kos (RM)\') }}</th>'),
    ('<th class="text-end">Kadar</th>', '<th class="text-end">{{ __(\'Kadar\') }}</th>'),
    ('<th class="text-end">Susut/Bulan (RM)</th>', '<th class="text-end">{{ __(\'Susut/Bulan (RM)\') }}</th>'),
    ('<th class="text-end">SNT Terkumpul (RM)</th>', '<th class="text-end">{{ __(\'SNT Terkumpul (RM)\') }}</th>'),
    ('<th class="text-end">Nilai Buku (RM)</th>', '<th class="text-end">{{ __(\'Nilai Buku (RM)\') }}</th>'),
    ("<th>Status</th>", "<th>{{ __('Status') }}</th>"),
    ('<th class="no-print" style="width:120px">Tindakan</th>', '<th class="no-print" style="width:120px">{{ __(\'Tindakan\') }}</th>'),
    ("{{ $a->useful_life_years }} thn", "{{ $a->useful_life_years }} {{ __('thn') }}"),
    ('<span class="badge text-bg-info">Susut penuh</span>', '<span class="badge text-bg-info">{{ __(\'Susut penuh\') }}</span>'),
    ('<i class="bi bi-trash3 me-1"></i>Lupus', '<i class="bi bi-trash3 me-1"></i>{{ __(\'Lupus\') }}'),
    ('<i class="bi bi-clock-history me-1"></i><strong>Jadual susut nilai:</strong>',
     '<i class="bi bi-clock-history me-1"></i><strong>{{ __(\'Jadual susut nilai:\') }}</strong>'),
    (">Tiada aset didaftarkan.</td>", ">{{ __('Tiada aset didaftarkan.') }}</td>"),
    ('<h5 class="modal-title">Pelupusan Aset: {{ $a->kod_aset }}</h5>', '<h5 class="modal-title">{{ __(\'Pelupusan Aset:\') }} {{ $a->kod_aset }}</h5>'),
    ("""                        <strong>{{ $a->nama }}</strong> — kos RM{{ number_format((float) $a->kos, 2) }},
                        SNT terkumpul RM{{ number_format((float) $a->accumulated_depn, 2) }},
                        nilai buku RM{{ number_format((float) $a->kos - (float) $a->accumulated_depn, 2) }}.""",
     """                        <strong>{{ $a->nama }}</strong> — {{ __('kos') }} RM{{ number_format((float) $a->kos, 2) }},
                        {{ __('SNT terkumpul') }} RM{{ number_format((float) $a->accumulated_depn, 2) }},
                        {{ __('nilai buku') }} RM{{ number_format((float) $a->kos - (float) $a->accumulated_depn, 2) }}."""),
    ("""                        Jurnal pelupusan: Dr SNT (susut terkumpul) + Dr 600-99990 (baki nilai buku) /
                        Cr akaun aset (kos penuh). Status aset menjadi DILUPUSKAN.""",
     """                        {{ __('Jurnal pelupusan: Dr SNT (susut terkumpul) + Dr 600-99990 (baki nilai buku) /') }}
                        {{ __('Cr akaun aset (kos penuh). Status aset menjadi DILUPUSKAN.') }}"""),
    ('Sebab Pelupusan <span', '{{ __(\'Sebab Pelupusan\') }} <span'),
    (">Sahkan Pelupusan</button>", ">{{ __('Sahkan Pelupusan') }}</button>"),
])

# ---------- lanjutan/tutup-tahun.blade.php ----------
sub("resources/views/lanjutan/tutup-tahun.blade.php", [
    ("@section('title', 'Penutupan Tahun Kewangan')", "@section('title', __('Penutupan Tahun Kewangan'))"),
    ('<label class="form-label" for="tahun">Tahun Kewangan</label>', '<label class="form-label" for="tahun">{{ __(\'Tahun Kewangan\') }}</label>'),
    (">Pratonton</button>", ">{{ __('Pratonton') }}</button>"),
    ('<i class="bi bi-lock-fill me-1"></i>Tempoh dikunci sehingga {{ $pratonton[\'locked_until\'] }}',
     '<i class="bi bi-lock-fill me-1"></i>{{ __(\'Tempoh dikunci sehingga\') }} {{ $pratonton[\'locked_until\'] }}'),
    ("""        Tahun <strong>{{ $tahun }}</strong> telah pun ditutup (voucher <strong>YE-{{ $tahun }}</strong> wujud).
        Penutupan kedua tidak dibenarkan.""",
     """        {{ __('Tahun') }} <strong>{{ $tahun }}</strong> {{ __('telah pun ditutup (voucher') }} <strong>YE-{{ $tahun }}</strong> {{ __('wujud).') }}
        {{ __('Penutupan kedua tidak dibenarkan.') }}"""),
    ("""        <strong>AMARAN KUAT:</strong> Akaun Sementara <strong>300-99990</strong> masih berbaki
        <strong>RM {{ number_format((float) $pratonton['suspense'], 2) }}</strong> pada {{ $tahun }}-12.
        <u>Jelaskan (reclass) suspense dahulu</u> sebelum menutup tahun — atau tandakan kotak pengesahan
        di bawah untuk meneruskan juga (tidak digalakkan).""",
     """        <strong>{{ __('AMARAN KUAT:') }}</strong> {{ __('Akaun Sementara') }} <strong>300-99990</strong> {{ __('masih berbaki') }}
        <strong>RM {{ number_format((float) $pratonton['suspense'], 2) }}</strong> {{ __('pada') }} {{ $tahun }}-12.
        <u>{{ __('Jelaskan (reclass) suspense dahulu') }}</u> {{ __('sebelum menutup tahun — atau tandakan kotak pengesahan') }}
        {{ __('di bawah untuk meneruskan juga (tidak digalakkan).') }}"""),
    ('<div class="card-header fw-bold">Pratonton Untung Rugi {{ $tahun }} (akan dipindah ke 100-10000 DANA TERKUMPUL)</div>',
     '<div class="card-header fw-bold">{{ __(\'Pratonton Untung Rugi\') }} {{ $tahun }} {{ __(\'(akan dipindah ke 100-10000 DANA TERKUMPUL)\') }}</div>'),
    ("<tr><th>Kod</th><th>Akaun</th><th class=\"text-end\">Amaun (RM)</th></tr>",
     "<tr><th>{{ __('Kod') }}</th><th>{{ __('Akaun') }}</th><th class=\"text-end\">{{ __('Amaun (RM)') }}</th></tr>"),
    ('<tr class="table-light"><td colspan="3" class="fw-bold">HASIL</td></tr>',
     '<tr class="table-light"><td colspan="3" class="fw-bold">{{ __(\'HASIL\') }}</td></tr>'),
    ('<tr class="fw-bold"><td colspan="2">JUMLAH HASIL</td>', '<tr class="fw-bold"><td colspan="2">{{ __(\'JUMLAH HASIL\') }}</td>'),
    ('<tr class="table-light"><td colspan="3" class="fw-bold">BELANJA</td></tr>',
     '<tr class="table-light"><td colspan="3" class="fw-bold">{{ __(\'BELANJA\') }}</td></tr>'),
    ('<tr class="fw-bold"><td colspan="2">JUMLAH BELANJA</td>', '<tr class="fw-bold"><td colspan="2">{{ __(\'JUMLAH BELANJA\') }}</td>'),
    ('<td colspan="2">LEBIHAN / (KURANGAN) {{ $tahun }}</td>', '<td colspan="2">{{ __(\'LEBIHAN / (KURANGAN)\') }} {{ $tahun }}</td>'),
    ('<div class="card-header fw-bold">Tutup Tahun {{ $tahun }}</div>', '<div class="card-header fw-bold">{{ __(\'Tutup Tahun\') }} {{ $tahun }}</div>'),
    ("""                    <li>Voucher penutupan <strong>YE-{{ $tahun }}</strong> diposkan pada tempoh maya
                        <strong>{{ $tahun }}-13</strong> — laporan P&amp;L bulanan/tahunan sejarah <u>tidak berubah</u>.</li>""",
     """                    <li>{{ __('Voucher penutupan') }} <strong>YE-{{ $tahun }}</strong> {{ __('diposkan pada tempoh maya') }}
                        <strong>{{ $tahun }}-13</strong> — {{ __('laporan P&L bulanan/tahunan sejarah') }} <u>{{ __('tidak berubah') }}</u>.</li>"""),
    ('<li>Setiap akaun Hasil/Belanja disifarkan; baki bersih dipindah ke <strong>100-10000 DANA TERKUMPUL</strong>.</li>',
     '<li>{{ __(\'Setiap akaun Hasil/Belanja disifarkan; baki bersih dipindah ke\') }} <strong>100-10000 DANA TERKUMPUL</strong>.</li>'),
    ("""                    <li>Selepas tutup, semua tempoh sehingga <strong>{{ $tahun }}-12</strong> DIKUNCI —
                        tiada transaksi/pembatalan baharu dibenarkan.</li>""",
     """                    <li>{{ __('Selepas tutup, semua tempoh sehingga') }} <strong>{{ $tahun }}-12</strong> {{ __('DIKUNCI —') }}
                        {{ __('tiada transaksi/pembatalan baharu dibenarkan.') }}</li>"""),
    ("""                    <li>Akaun Sementara 300-99990: baki
                        <strong>RM {{ number_format((float) $pratonton['suspense'], 2) }}</strong>.</li>""",
     """                    <li>{{ __('Akaun Sementara 300-99990: baki') }}
                        <strong>RM {{ number_format((float) $pratonton['suspense'], 2) }}</strong>.</li>"""),
    ("""                                Saya faham Akaun Sementara masih berbaki dan <strong>mengesahkan untuk meneruskan</strong> penutupan tahun.""",
     """                                {{ __('Saya faham Akaun Sementara masih berbaki dan') }} <strong>{{ __('mengesahkan untuk meneruskan') }}</strong> {{ __('penutupan tahun.') }}"""),
    ("onclick=\"return confirm('TUTUP TAHUN {{ $tahun }}? Tindakan ini mengunci semua tempoh sehingga {{ $tahun }}-12 dan tidak boleh diundur dari halaman ini.')\"",
     "onclick=\"return confirm('{{ __('TUTUP TAHUN') }} {{ $tahun }}? {{ __('Tindakan ini mengunci semua tempoh sehingga') }} {{ $tahun }}-12 {{ __('dan tidak boleh diundur dari halaman ini.') }}')\""),
    ('<i class="bi bi-lock-fill me-1"></i>Tutup Tahun {{ $tahun }}', '<i class="bi bi-lock-fill me-1"></i>{{ __(\'Tutup Tahun\') }} {{ $tahun }}'),
])

print("DONE")
