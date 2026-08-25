<?php

namespace App\Support;

/**
 * Perhitungan tata letak struk termal.
 *
 * DUA HAL YANG SELAMA INI SALAH, DAN KEDUANYA CUMA TERLIHAT DI KERTAS 58MM.
 *
 * 1. LEBAR KERTAS BUKAN LEBAR CETAK.
 *
 *    Printer termal tidak mencetak sampai tepi kertasnya. Kepala cetaknya lebih sempit dari
 *    rol, dan sisanya tidak pernah tersentuh:
 *
 *        rol 58 mm  ->  384 titik @203 dpi  =  48 mm yang benar-benar tercetak
 *        rol 80 mm  ->  576 titik @203 dpi  =  72 mm yang benar-benar tercetak
 *
 *    Struk dulu ditata selebar KERTAS. Isinya karena itu 10 mm lebih lebar dari yang bisa
 *    dicetak, dan pengandar printer menanganinya dengan salah satu dari dua cara: memotong
 *    tepi kanan, atau mengecilkan seluruh halaman supaya muat. Yang kedua itu yang membuat
 *    tulisan terlihat MIRING dan TIDAK DI TENGAH - bukan printernya rusak, dan bukan
 *    pratinjaunya salah; pratinjau di layar memang benar-benar rata tengah, tapi yang
 *    dikirim ke printer sudah diperkecil dan digeser lebih dulu.
 *
 *    Kertas 80 mm lolos dari perhatian karena selisihnya (80 -> 72) hanya 10%, dan struk
 *    80 mm isinya lapang sehingga hasilnya masih terlihat wajar. Di 58 mm selisihnya 17%
 *    pada kertas yang sudah sempit - di situ baru kelihatan.
 *
 * 2. LEBAR KOLOM DALAM PERSEN TIDAK BISA DIPAKAI DI DUA LEBAR KERTAS SEKALIGUS.
 *
 *    Tata letak nota memberi HARGA dan JUMLAH masing-masing sekian PERSEN, padahal yang
 *    sebenarnya dibutuhkan kolom itu adalah sekian KARAKTER - "1.500.000" butuh 9 karakter,
 *    berapa pun lebar kertasnya. Persen yang pas di 80 mm menjadi kelebihan di 58 mm, dan
 *    sisanya diambil dari kolom BARANG. Terukur di peramban sebelum perbaikan: kolom BARANG
 *    hanya 16,5 mm pada 72 mm, dan 13,1 mm pada 58 mm - kira-kira tujuh karakter, sehingga
 *    satu nama produk pecah jadi enam baris.
 *
 *    Karena fontnya monospace (Courier New), lebar satu karakter bisa dihitung pasti:
 *    0,6 x ukuran font. Jadi kolom dihitung dalam KARAKTER lebih dulu, baru diubah ke persen
 *    mengikuti lebar cetak yang berlaku. Dengan begitu 58 mm dan 80 mm sama-sama benar.
 */
class Struk
{
    /**
     * Lebar cetak nyata per profil printer, dalam milimeter.
     *
     * Angka ini bukan karangan: 384 dan 576 titik pada 203 dpi adalah spesifikasi kepala
     * cetak yang dipakai hampir semua printer termal 58 mm dan 80 mm.
     */
    public const LEBAR_CETAK = [
        'pos58' => 48.0,
        'pos80' => 72.0,
    ];

    /** Lebar satu karakter font monospace, sebagai kelipatan ukuran font. */
    private const RASIO_KARAKTER = 0.6;

    /** 1 milimeter dalam satuan px CSS (96 dpi). */
    private const PX_PER_MM = 96 / 25.4;

    /** Kolom BARANG tidak pernah boleh lebih sempit dari ini, dalam karakter. */
    private const MIN_KARAKTER_NAMA = 8;

    /** Kolom QTY memuat mis. "2,5 Kg". */
    private const KARAKTER_QTY = 7;

    /**
     * Lebar yang benar-benar bisa dicetak, dalam milimeter.
     *
     * Pengaturan lama hanya menyimpan `paper_size`. Untuk instalasi yang sudah berjalan,
     * nilai itu diterjemahkan lewat profilnya: toko yang memilih POS58 selama ini menata
     * struk selebar 58 mm padahal printernya hanya sanggup 48 mm.
     *
     * Profil `custom` diperlakukan berbeda dan sengaja: yang memakainya sudah menyetel
     * angkanya sendiri sampai hasilnya pas, jadi angka itu SUDAH lebar cetak dan tidak
     * boleh dikurangi lagi.
     */
    public static function lebarCetak(array $printerStruk): float
    {
        $tersimpan = (float) ($printerStruk['print_width'] ?? 0);

        if ($tersimpan > 0) {
            return $tersimpan;
        }

        $profil = (string) ($printerStruk['profile'] ?? '');

        if (isset(self::LEBAR_CETAK[$profil])) {
            return self::LEBAR_CETAK[$profil];
        }

        // Tanpa profil yang dikenal, angka yang ada dipakai apa adanya - menebak-nebak di
        // sini justru bisa mengubah hasil cetak toko yang selama ini sudah pas.
        return (float) ($printerStruk['paper_size'] ?? 80);
    }

    /** Berapa karakter monospace yang muat dalam satu baris selebar $lebarMm. */
    public static function karakterPerBaris(float $lebarMm, int $fontPx): int
    {
        $lebarKarakterPx = max(1.0, $fontPx * self::RASIO_KARAKTER);

        return max(1, (int) floor(($lebarMm * self::PX_PER_MM) / $lebarKarakterPx));
    }

    /** Font tabel nota tidak pernah diturunkan di bawah ini - di printer termal jadi tidak terbaca. */
    public const FONT_NOTA_MIN = 9;

    public const FONT_NOTA_MAX = 12;

    /**
     * Ukuran font tabel nota yang membuat seluruh kolom muat tanpa terpotong.
     *
     * Kertas 48 mm hanya memuat ±25 karakter pada font 12px, sementara "1.500.000" sendiri
     * sudah 9 karakter - dua kolom angka saja sudah menghabiskan hampir seluruh baris. Yang
     * mengalah di sini fontnya, bukan kolomnya: nominal yang membungkus ke baris berikutnya
     * membuat struk salah dibaca, dan itu jauh lebih mahal daripada huruf yang sedikit
     * lebih kecil.
     *
     * Diturunkan paling jauh sampai 9px. Di bawah itu printer termal 203 dpi mulai
     * menghasilkan huruf yang tidak terbaca, jadi lebih baik tata letaknya yang diganti -
     * lihat muatNota().
     */
    public static function fontNota(float $lebarCetakMm, int $karakterAngka): int
    {
        for ($font = self::FONT_NOTA_MAX; $font > self::FONT_NOTA_MIN; $font--) {
            if (self::muatNota($lebarCetakMm, $font, $karakterAngka)) {
                return $font;
            }
        }

        return self::FONT_NOTA_MIN;
    }

    /**
     * Apakah tabel nota 4 kolom benar-benar muat di lebar ini?
     *
     * Dipakai untuk memutuskan kapan tata letak nota harus mengalah ke tata letak bertumpuk.
     * Tabel 4 kolom pada dasarnya tata letak kertas 80 mm; memaksakannya di kertas sempit
     * menghasilkan struk yang setiap barisnya membungkus tiga kali - secara teknis "muat",
     * tapi tidak ada pelanggan yang bisa membacanya.
     */
    public static function muatNota(float $lebarCetakMm, int $fontPx, int $karakterAngka): bool
    {
        $total = self::karakterPerBaris($lebarCetakMm, $fontPx);
        $butuh = 4 + self::MIN_KARAKTER_NAMA + (2 * (max(5, $karakterAngka) + 1));

        return $total >= $butuh;
    }

    /**
     * Lebar cetak paling kecil yang masih memuat tabel nota 4 kolom, dibulatkan ke mm.
     *
     * Dipakai halaman Pengaturan untuk memberi tahu angka yang perlu dicapai, alih-alih
     * hanya mengatakan "terlalu sempit" tanpa menyebut sempit dibanding apa.
     */
    public static function lebarMinimalNota(int $karakterAngka): int
    {
        $butuh = 4 + self::MIN_KARAKTER_NAMA + (2 * (max(5, $karakterAngka) + 1));
        $lebarKarakterPx = self::FONT_NOTA_MIN * self::RASIO_KARAKTER;

        return (int) ceil(($butuh * $lebarKarakterPx) / self::PX_PER_MM);
    }

    /**
     * Lebar kolom nota (QTY / BARANG / HARGA / JUMLAH) dalam PERSEN.
     *
     * Dihitung dari kebutuhan sesungguhnya tiap kolom dalam karakter, lalu diubah ke persen
     * mengikuti lebar cetak yang berlaku. Kolom angka mendapat tepat sebanyak yang ia
     * butuhkan; sisanya seluruhnya jatuh ke BARANG, karena nama produk yang terbaca adalah
     * satu-satunya bagian struk yang tidak bisa ditebak pelanggan dari ingatan.
     *
     * @param  int  $karakterAngka  Panjang nominal terpanjang yang akan dicetak, mis. 9 untuk "1.500.000".
     * @return array{qty:float,nama:float,harga:float,jumlah:float}
     */
    public static function kolomNota(float $lebarCetakMm, int $fontPx, int $karakterAngka): array
    {
        $totalKarakter = self::karakterPerBaris($lebarCetakMm, $fontPx);

        // +1 untuk jarak antar kolom supaya angka tidak menempel ke garis tabel.
        $angka = max(5, $karakterAngka) + 1;
        $qty = self::KARAKTER_QTY;

        $nama = $totalKarakter - $qty - (2 * $angka);

        // Kertas yang terlalu sempit untuk memuat semuanya: kolom angka dipersempit
        // bertahap, bukan kolom nama - nominal boleh rapat, nama produk tidak boleh hilang.
        while ($nama < self::MIN_KARAKTER_NAMA && $angka > 6) {
            $angka--;
            $nama = $totalKarakter - $qty - (2 * $angka);
        }

        // Masih tidak cukup juga: QTY yang mengalah, karena isinya paling pendek.
        while ($nama < self::MIN_KARAKTER_NAMA && $qty > 4) {
            $qty--;
            $nama = $totalKarakter - $qty - (2 * $angka);
        }

        $nama = max(1, $nama);
        $jumlahKarakter = $qty + $nama + (2 * $angka);

        return [
            'qty' => round($qty / $jumlahKarakter * 100, 2),
            'nama' => round($nama / $jumlahKarakter * 100, 2),
            'harga' => round($angka / $jumlahKarakter * 100, 2),
            'jumlah' => round($angka / $jumlahKarakter * 100, 2),
        ];
    }

    /**
     * Berapa karakter nama produk yang muat dalam satu baris di kolom BARANG.
     *
     * Dipakai untuk memutuskan apakah baris itu perlu diturunkan ukuran fontnya supaya
     * wrap-nya lebih rapi.
     */
    public static function karakterKolomNama(float $lebarCetakMm, int $fontPx, float $persenNama): int
    {
        return self::karakterPerBaris($lebarCetakMm * ($persenNama / 100), $fontPx);
    }
}
