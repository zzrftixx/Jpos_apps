<?php

/**
 * Router untuk server bawaan PHP (`artisan serve`).
 *
 * KENAPA JPOS PUNYA VERSINYA SENDIRI. Router bawaan Laravel mengembalikan `false` untuk
 * berkas yang ada di public/, artinya server bawaan PHP yang melayaninya langsung - dan
 * server itu TIDAK MENGIRIM SATU PUN header cache. Diukur: permintaan dengan
 * If-Modified-Since dibalas 200 beserta seluruh isinya, bukan 304.
 *
 * Akibatnya peramban mengunduh ulang seluruh aset di SETIAP perpindahan halaman:
 *
 *     jpos.css + alpine + jpos-number + live-search + sesi  =  110 KB, 5 permintaan
 *     11,5 ms dari 51 ms satu perpindahan halaman - 22%-nya habis di situ
 *
 * Dan itu bukan cuma soal 11 ms. Server bawaan melayani SATU permintaan pada satu waktu,
 * jadi lima permintaan aset itu antre di depan halaman berikutnya yang sedang dibuka kasir.
 *
 * Dengan header cache, peramban tidak mengirim permintaannya sama sekali.
 *
 * YANG MEMBUAT INI AMAN: seluruh URL aset membawa ?v=<versi aplikasi> (lihat direktif
 * @aset). Versi naik setiap paket update, jadi alamatnya berubah dan peramban wajib
 * mengambil yang baru. Tanpa itu, cache panjang berarti kasir melihat tampilan versi lama
 * setelah aplikasi diperbarui - lebih buruk daripada lambat.
 *
 * Berkas yang TIDAK berversi (gambar produk, logo toko yang diunggah pemilik) diberi masa
 * simpan pendek dan tetap divalidasi lewat ETag, supaya mengganti logo langsung terlihat.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$publik = __DIR__ . '/public';
$berkas = $publik . $uri;

// Hanya berkas biasa di dalam public/ yang boleh dilayani di sini. realpath() menutup
// jalur ../ - localhost pun tidak pantas dibiarkan bisa membaca berkas di luar.
$asli = $uri !== '/' && $uri !== '' ? realpath($berkas) : false;
$akarPublik = realpath($publik);

$bolehDilayani = $asli !== false
    && $akarPublik !== false
    && is_file($asli)
    && str_starts_with($asli, $akarPublik . DIRECTORY_SEPARATOR);

if (! $bolehDilayani) {
    require_once $publik . '/index.php';

    return;
}

$tipe = [
    'css' => 'text/css',
    'js' => 'application/javascript',
    'mjs' => 'application/javascript',
    'json' => 'application/json',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',
    'map' => 'application/json',
    'txt' => 'text/plain',
    'pdf' => 'application/pdf',
];

$ekstensi = strtolower(pathinfo($asli, PATHINFO_EXTENSION));

// Ekstensi yang tidak dikenal diserahkan ke server bawaan. Menebak tipe berkas yang salah
// jauh lebih berbahaya daripada kehilangan header cache untuk satu berkas.
if (! isset($tipe[$ekstensi])) {
    return false;
}

$waktuUbah = filemtime($asli);
$ukuran = filesize($asli);
$etag = '"' . dechex($waktuUbah) . '-' . dechex($ukuran) . '"';

// Aset yang alamatnya membawa ?v= boleh disimpan lama: versinya naik tiap paket update,
// jadi alamatnya ikut berubah. Sisanya divalidasi ulang tiap kali.
$berversi = isset($_GET['v']) && $_GET['v'] !== '';
$masaSimpan = $berversi ? 31536000 : 0;

header('Content-Type: ' . $tipe[$ekstensi]);
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $waktuUbah) . ' GMT');
header($berversi
    ? 'Cache-Control: public, max-age=' . $masaSimpan . ', immutable'
    : 'Cache-Control: public, must-revalidate, max-age=0');

$etagPeramban = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$waktuPeramban = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');

$masihSegar = $etagPeramban === $etag
    || ($etagPeramban === '' && $waktuPeramban !== '' && @strtotime($waktuPeramban) >= $waktuUbah);

if ($masihSegar) {
    http_response_code(304);

    return;
}

header('Content-Length: ' . $ukuran);

// HEAD tidak boleh membawa isi, tapi header-nya harus sama persis dengan GET.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    readfile($asli);
}
