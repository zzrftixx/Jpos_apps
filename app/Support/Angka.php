<?php

namespace App\Support;

/**
 * Aritmetika dan tampilan kuantitas yang boleh pecahan.
 *
 * Selama ini kuantitas selalu bilangan bulat, jadi konversi ke satuan dasar cukup ditulis
 * `(int) round($qty * $conversion)` di mana-mana. Begitu satuan timbangan masuk - menjual
 * 0,4 Kg atau 400 Gram - rumus itu berubah dari penyederhanaan yang wajar menjadi kebocoran
 * uang:
 *
 *     (int) round(0.4 * 1.0)  = 0   -> barang keluar, uang tertagih, stok tidak berkurang
 *     (int) round(2.5 * 1.0)  = 3   -> toko kehilangan setengah kilo tiap transaksi
 *
 * Kelas ini menjadi satu-satunya tempat perhitungan itu berada, supaya tidak ada lagi
 * pembulatan yang tersebar dan diam-diam berbeda satu sama lain.
 *
 * SKALA: 4 desimal, mengikuti kolom `conversion` di product_units. Cukup untuk konversi
 * sekecil Gram ke Kilogram (0,001) dengan satu digit cadangan.
 */
class Angka
{
    public const SKALA = 4;

    /**
     * Membulatkan ke skala kerja.
     *
     * Dipakai setiap kali hasil aritmetika pecahan disimpan. Tanpa ini, penjumlahan dan
     * pengurangan float berulang meninggalkan sisa seperti 9.599999999999999 yang lalu
     * tampil di layar kasir dan tersimpan di database selamanya.
     */
    public static function bulat(mixed $nilai): float
    {
        return round((float) $nilai, self::SKALA);
    }

    /**
     * Mengubah kuantitas dalam satuan apa pun menjadi satuan dasar produk.
     *
     * Pengganti tunggal untuk seluruh `(int) round($qty * $conversion)` yang tersebar di
     * KasirController dan ReturnController.
     */
    public static function keSatuanDasar(mixed $qty, mixed $conversion): float
    {
        return self::bulat((float) $qty * (float) $conversion);
    }

    /**
     * Apakah stok yang tersedia cukup untuk jumlah yang diminta?
     *
     * WAJIB dipakai alih-alih `>` biasa. Pecahan desimal tidak bisa diwakili persis oleh
     * float biner: 0,1 + 0,2 menghasilkan 0,30000000000000004, sehingga menjual 0,3 Kg dari
     * stok 0,3 Kg akan ditolak dengan pesan "stok tidak cukup" - padahal cukup persis.
     * Perbandingan dilakukan setelah dibulatkan ke skala kerja supaya sisa sekecil itu
     * tidak pernah menentukan.
     */
    public static function cukup(mixed $diminta, mixed $tersedia): bool
    {
        return self::bulat((float) $diminta - (float) $tersedia) <= 0;
    }

    /**
     * Kuantitas untuk dibaca manusia, dalam format Indonesia.
     *
     * Desimal hanya muncul kalau memang ada, karena sebagian besar penjualan tetap
     * bilangan bulat dan "10,0000 Pcs" cuma membuat struk sulit dibaca:
     *
     *     10        -> "10"
     *     0.5       -> "0,5"
     *     1500.25   -> "1.500,25"
     *     0.4000    -> "0,4"
     */
    public static function qty(mixed $nilai, int $maksDesimal = self::SKALA): string
    {
        $angka = self::bulat($nilai);
        $desimal = $maksDesimal;

        // Jumlah desimal terkecil yang masih mewakili nilainya dengan tepat.
        for ($i = 0; $i <= $maksDesimal; $i++) {
            if (round($angka, $i) == $angka) {
                $desimal = $i;
                break;
            }
        }

        return number_format($angka, $desimal, ',', '.');
    }
}
