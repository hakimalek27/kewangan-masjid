// Smoke-crawl menyeluruh (Chromium): log masuk SEKALI ikut peranan, lalui SETIAP
// halaman dalam satu sesi, kumpul status HTTP + ralat konsol/pageerror, dan sahkan
// pautan dalaman tak rosak. Dijalankan terhadap klon sppkms_test (bukan prod).
import { test, expect } from '@playwright/test'

test.describe.configure({ mode: 'serial' })

const ACC = {
  admin: { login: 'admin', pass: 'admin12345' },
  bendahari: { login: 'malmutaqqin', pass: 'alm12345' },
}

const HALAMAN_BENDAHARI = [
  '/dashboard', '/carian',
  '/kutipan/senarai', '/kutipan/baru', '/kutipan/harian', '/kutipan/jumaat', '/kutipan/tabung', '/kutipan/dividen',
  '/belanja', '/belanja/senarai', '/belanja/baru', '/belanja/aset', '/belanja/jurnal', '/belanja/rekupmen',
  '/aset/senarai', '/aset/daftar-lama',
  '/fd/senarai', '/fd/baru', '/fd/senarai-lama', '/fd/daftar-lama',
  '/cek/senarai', '/cek/daftar', '/cek-batal/senarai', '/cek-batal/daftar',
  '/sewa/senarai', '/sewa/daftar', '/peti-besi/senarai', '/peti-besi/daftar',
  '/bank', '/bank/baki-awal', '/bank/baki-terkini',
  '/akaun/coa', '/akaun/imbangan-duga', '/akaun/kunci-kira-kira', '/akaun/untung-rugi',
  '/akaun/lejer', '/akaun/lejer-akaun', '/akaun/jurnal', '/akaun/laporan-jurnal', '/akaun/program',
  '/penyata/bulanan', '/penyata/bank', '/penyata/tahunan', '/penyata/setting',
  '/pwr/baki', '/pwr/bayar', '/pwr/buku', '/pwr/penyata',
  '/statistik/kutipan', '/statistik/kutipan-coa', '/statistik/belanja', '/statistik/belanja-coa', '/statistik/jumaat',
  '/belanjawan', '/dana', '/rekonsiliasi', '/semak-penyata', '/susut-nilai', '/kelulusan', '/draf',
  '/tetapan/masjid', '/tetapan/resit-baucer', '/tetapan/mapping', '/tetapan/semak-kod',
  '/tetapan/kawalan', '/tetapan/tutup-tahun', '/tetapan/kata-laluan', '/tetapan/pengguna', '/tetapan/wizard',
]

const HALAMAN_ADMIN = [
  '/sistem', '/admin/pemantauan', '/admin/audit', '/admin/ralat', '/admin/keselamatan',
  '/admin/backup', '/admin/dual-write', '/admin/semak-penyata', '/tetapan/ai', '/tetapan/api', '/tetapan/api/log',
  '/tetapan/masjid-baru', '/tetapan/pengguna', '/tetapan/kata-laluan',
]

const ABAI = [/favicon/i, /manifest/i, /net::ERR/i, /Failed to load resource/i]
const penting = (list) => list.filter((e) => !ABAI.some((re) => re.test(e)))

async function login(page, akaun) {
  await page.goto('/')
  await page.fill('input[name="login"]', akaun.login)
  await page.fill('input[name="password"]', akaun.pass)
  await Promise.all([
    page.waitForURL(/\/(dashboard|sistem|penyata)/, { timeout: 15000 }),
    page.press('input[name="password"]', 'Enter'),
  ])
}

async function crawl(page, senarai) {
  const gagalStatus = []
  const gagalJs = []
  const ralatSemasa = []
  page.on('console', (m) => { if (m.type() === 'error') ralatSemasa.push(`console: ${m.text()}`) })
  page.on('pageerror', (e) => ralatSemasa.push(`pageerror: ${e.message}`))

  for (const path of senarai) {
    ralatSemasa.length = 0
    const resp = await page.goto(path, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(250) // beri masa skrip modul jalan
    if (!resp || resp.status() >= 400) gagalStatus.push(`${path} → ${resp ? resp.status() : 'no-resp'}`)
    const p = penting(ralatSemasa)
    if (p.length) gagalJs.push(`${path}: ${p.join(' | ')}`)
  }
  return { gagalStatus, gagalJs }
}

test('BENDAHARI: setiap halaman status OK + tiada ralat JS', async ({ page }) => {
  test.setTimeout(240000)
  await login(page, ACC.bendahari)
  const { gagalStatus, gagalJs } = await crawl(page, HALAMAN_BENDAHARI)
  expect(gagalStatus, `Status buruk: ${gagalStatus.join(', ')}`).toHaveLength(0)
  expect(gagalJs, `Ralat JS: ${gagalJs.join(' || ')}`).toHaveLength(0)
})

test('ADMIN: setiap halaman sistem status OK + tiada ralat JS', async ({ page }) => {
  test.setTimeout(120000)
  await login(page, ACC.admin)
  const { gagalStatus, gagalJs } = await crawl(page, HALAMAN_ADMIN)
  expect(gagalStatus, `Status buruk: ${gagalStatus.join(', ')}`).toHaveLength(0)
  expect(gagalJs, `Ralat JS: ${gagalJs.join(' || ')}`).toHaveLength(0)
})

test('Pautan dalaman (dashboard + sidebar) tidak rosak', async ({ page }) => {
  await login(page, ACC.bendahari)
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  const hrefs = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')))
  const dalaman = [...new Set(hrefs.filter((h) => h && h.startsWith('/') && !h.startsWith('//')
    && !/logout|\/cetak|\.pdf|\.xlsx|export|eksport|\?/i.test(h)))]
  const rosak = []
  for (const h of dalaman) {
    const r = await page.request.get(h)
    if (r.status() >= 400) rosak.push(`${h} → ${r.status()}`)
  }
  expect(rosak, `Pautan rosak: ${rosak.join(', ')}`).toHaveLength(0)
})

test('Butang tulis HADIR untuk bendahari (kutipan & belanja)', async ({ page }) => {
  await login(page, ACC.bendahari)
  await page.goto('/kutipan/baru')
  expect(await page.locator('button[type="submit"], input[type="submit"]').count()).toBeGreaterThan(0)
  await page.goto('/belanja/baru')
  expect(await page.locator('button[type="submit"], input[type="submit"]').count()).toBeGreaterThan(0)
})
