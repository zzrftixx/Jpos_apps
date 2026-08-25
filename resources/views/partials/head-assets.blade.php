{{--
    Aset UI JPOS - seluruhnya dimuat dari public/vendor, tanpa satu pun permintaan
    ke internet.

    Sebelumnya Tailwind, Alpine, Chart.js, JsBarcode, dan Iconify diambil dari CDN.
    Sebagai aplikasi web di server ber-internet itu tidak masalah, tapi begitu
    dipaketkan jadi .exe untuk komputer kasir - yang sering offline, wifi lemot,
    atau diblokir firewall - halaman jadi tanpa CSS dan seluruh tombol, modal,
    serta keranjang kasir mati total karena Alpine tidak pernah termuat.

    Lihat config/jpos.php untuk mengganti sumber Tailwind (jalur rollback).
--}}
@if (config('jpos.assets.tailwind_mode') === 'play')
    <script src="@aset('vendor/tailwind-play-3.4.17.js')"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              brand: {
                50: '#eef7ff', 100:'#d9edff',200:'#bce0ff',300:'#8ecdff',400:'#59b0ff',
                500: '#2f8fff', 600: '#1c6ff0', 700: '#1857c4', 800: '#1a499d', 900: '#1b407d'
              }
            }
          }
        }
      }
    </script>
@else
    <link rel="stylesheet" href="@aset('vendor/jpos.css')">
@endif
{{-- Dimuat SEBELUM Alpine (tanpa defer) supaya directive x-number sudah terdaftar
     lewat event alpine:init saat Alpine mulai memindai halaman. --}}
<script src="@aset('vendor/jpos-number.js')"></script>
<script defer src="@aset('vendor/jpos-live-search.js')"></script>
{{-- Penangkap alat pindai barcode. Dimuat di SELURUH halaman, bukan cuma Kasir: alat pindai
     menembakkan karakter ke mana pun fokus berada, dan yang paling berbahaya justru saat
     fokusnya sedang di kolom nominal bayar. Halaman yang tidak mendengarkan peristiwanya
     cukup mengabaikannya. --}}
<script defer src="@aset('vendor/jpos-pemindai.js')"></script>
{{-- Penanda "jendela masih terbuka" untuk JPOS.exe, supaya server ikut mati saat aplikasi
     ditutup dan pintasan bisa dibuka lagi tanpa peringatan "JPOS sudah berjalan". --}}
<script defer src="@aset('vendor/jpos-sesi.js')"></script>
<script defer src="@aset('vendor/alpine-3.15.12.min.js')"></script>
