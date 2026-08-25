# public/vendor — Aset UI Lokal

Semua aset UI JPOS berada di folder ini. Aplikasi **tidak melakukan satu pun
permintaan ke internet** saat dijalankan — sudah diverifikasi di seluruh 36 halaman
lewat `performance.getEntriesByType('resource')`: 0 permintaan ke host luar.

Sebelumnya kelima library ini dimuat dari CDN. Sebagai web app di server ber-internet
itu wajar, tapi begitu dipaketkan jadi `.exe` untuk komputer kasir — yang sering
offline, wifi lemot, atau diblokir firewall — halaman jadi tanpa CSS dan seluruh
tombol, modal, serta keranjang kasir mati total karena Alpine tidak pernah termuat.

## Isi

| File | Asal | Versi | Dipakai di |
|---|---|---|---|
| `jpos.css` | hasil kompilasi Tailwind (lihat di bawah) | 3.4.17 | semua halaman |
| `tailwind-play-3.4.17.js` | `https://cdn.tailwindcss.com` | 3.4.17 | hanya mode rollback |
| `alpine-3.15.12.min.js` | `https://cdn.jsdelivr.net/npm/alpinejs@3.15.12/dist/cdn.min.js` | 3.15.12 | semua halaman |
| `chart-4.5.1.umd.min.js` | `https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js` | 4.5.1 | Dashboard |
| `jsbarcode-3.11.6.all.min.js` | `https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js` | 3.11.6 | Template Barcode, Cetak Label |
| `iconify-icon-2.1.0.min.js` | `https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js` | 2.1.0 | Tentang |
| `iconify-icons-jpos.js` | dibuat dari `api.iconify.design` | — | Tentang |

### Kenapa ada `iconify-icons-jpos.js`

Menyalin script Iconify saja tidak cukup. Komponen `<iconify-icon>` tetap mengunduh
SVG tiap ikon dari `api.iconify.design` saat halaman dibuka, jadi di komputer offline
ikonnya kosong. File ini mendaftarkan data ke-7 ikon yang dipakai halaman Tentang
lewat `IconifyIcon.addCollection()` sebelum komponen sempat meminta ke jaringan.

## Membangun ulang `jpos.css`

```bash
npx tailwindcss@3.4.17 -c tailwind.config.cjs -i resources/css/jpos.css -o public/vendor/jpos.css --minify
```

Wajib dijalankan ulang setiap kali ada class Tailwind **baru** dipakai di file Blade.
Palet warna `brand` di `tailwind.config.cjs` harus tetap sama persis dengan
konfigurasi inline di `resources/views/partials/head-assets.blade.php` (cabang mode
`play`), supaya kedua mode menghasilkan tampilan yang sama.

## Jalur rollback

`config/jpos.php` → `assets.tailwind_mode`:

- `css` (default) — memakai `jpos.css`, 26 KB. Ini yang dipakai normal.
- `play` — memakai compiler Tailwind yang berjalan di browser (407 KB), persis
  seperti versi CDN lama.

Kalau suatu saat ada tampilan yang terlihat berbeda di komputer client, ubah satu
baris itu (atau set `JPOS_TAILWIND_MODE=play` di `.env`) dan tampilan kembali
identik dengan versi sebelumnya tanpa perlu build ulang apa pun.

## Verifikasi yang sudah dilakukan

Perbandingan computed style seluruh elemen di 36 halaman, mode `play` vs `css`:
**36/36 identik**. Halaman Tentang punya 1 elemen `<script>` tambahan (data ikon)
yang tidak memengaruhi tampilan — sidik jari visualnya sama persis.

## Integritas berkas

```
57b37d7cae9a27d965fdae4adcc844245dfdc407e655aee85dcfff3a08036a3f  alpine-3.15.12.min.js
48444a82d4edcb5bec0f1965faacdde18d9c17db3063d042abada2f705c9f54a  chart-4.5.1.umd.min.js
758d94838db0cafdeb97eb0b54a120de36cfb3c7fe862eed989f37e80c550f02  iconify-icon-2.1.0.min.js
52e032534c3f98976ad95cb8c20baf80ed0cc83d42590602a8cf1db16e2e22ed  jsbarcode-3.11.6.all.min.js
176e894661aa9cdc9a5cba6c720044cbbf7b8bd80d1c9a142a7c24b1b6c50d15  tailwind-play-3.4.17.js
```
