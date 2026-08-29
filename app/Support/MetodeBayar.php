<?php

namespace App\Support;

/**
 * Satu tempat untuk seluruh pengetahuan tentang METODE PEMBAYARAN.
 *
 * Sebelum berkas ini ada, metode pembayaran hidup sebagai teks bebas di tiga tempat yang
 * tidak pernah sepakat:
 *
 *   - layar kasir mengirim  : cash | debit | qris | transfer   (Inggris)
 *   - pembuat data uji      : tunai | qris | transfer          (Indonesia)
 *   - migrasi 000080        : default 'cash'
 *   - struk                 : strtoupper() apa adanya -> "CASH" tercetak ke pelanggan
 *
 * Validasi di KasirController::store hanya ['required','string'], jadi nilai apa pun bisa
 * masuk. Selama metode pembayaran cuma dipajang, kekacauan itu tidak terasa. Begitu ia jadi
 * DASAR PENGELOMPOKAN UANG, satu toko bisa punya dua ember untuk hal yang sama - "cash"
 * Rp 3 juta dan "tunai" Rp 5 juta - dan tidak ada yang tahu mana yang benar.
 *
 * KENAPA DATA LAMA TIDAK DITULIS ULANG: database client berisi ribuan transaksi sungguhan.
 * Menjalankan UPDATE massal terhadapnya demi kerapian adalah risiko tanpa imbalan. Yang
 * dilakukan di sini justru sebaliknya - ejaan lama DITERJEMAHKAN saat dibaca. Transaksi
 * tahun lalu yang tersimpan 'cash' tetap 'cash' di database, dan tetap terbaca "Tunai" di
 * layar, di struk, dan di laporan.
 */
final class MetodeBayar
{
    /** Kunci kanonik -> label yang dilihat manusia. Urutannya = urutan tampil. */
    public const DAFTAR = [
        'tunai' => 'Tunai',
        'qris' => 'QRIS',
        'debit' => 'Kartu Debit',
        'transfer' => 'Transfer',
    ];

    /**
     * Ejaan lain yang pernah - atau masih - tersimpan di database client.
     *
     * Sengaja publik: migrasi 000350 menyusun ekspresi CASE untuk pengisian awal tabel
     * sale_payments dari peta ini, supaya terjemahan ejaan hanya ditulis di SATU tempat.
     */
    public const SINONIM = [
        'cash' => 'tunai',
        'tunai' => 'tunai',
        'kas' => 'tunai',
        'qr' => 'qris',
        'qris' => 'qris',
        'debit' => 'debit',
        'kartu' => 'debit',
        'card' => 'debit',
        'debit_card' => 'debit',
        'kartu_debit' => 'debit',
        'transfer' => 'transfer',
        'tf' => 'transfer',
        'bank' => 'transfer',
    ];

    /**
     * Kunci kanonik untuk sebuah nilai mentah dari database.
     *
     * Nilai yang tidak dikenali TIDAK dibuang dan TIDAK dipaksa jadi 'tunai' - ia menjadi
     * 'lainnya'. Memaksanya jadi tunai akan menaruh uang di ember yang salah, dan itu
     * kesalahan yang jauh lebih mahal daripada satu baris bernama "Lainnya".
     */
    public static function normal(?string $nilai): string
    {
        $kunci = strtolower(trim((string) $nilai));

        return self::SINONIM[$kunci] ?? ($kunci === '' ? 'tunai' : 'lainnya');
    }

    public static function label(?string $nilai): string
    {
        $kunci = self::normal($nilai);

        return self::DAFTAR[$kunci] ?? 'Lainnya';
    }

    /**
     * Kelas warna lencana.
     *
     * HANYA memakai kelas yang SUDAH ada di public/vendor/jpos.css. Tailwind di proyek ini
     * dikompilasi statis dari isi berkas Blade dan tidak ada internet untuk membangunnya
     * ulang di tempat client; kelas yang belum pernah dikompilasi akan tampil TANPA warna
     * sama sekali - rusak diam-diam, tanpa galat. Sudah diperiksa satu per satu.
     */
    public static function kelas(?string $nilai): string
    {
        return match (self::normal($nilai)) {
            'tunai' => 'bg-green-100 text-green-700 border-green-200',
            'qris' => 'bg-blue-100 text-blue-700 border-blue-200',
            'debit' => 'bg-amber-100 text-amber-700 border-amber-200',
            'transfer' => 'bg-brand-50 text-brand-600 border-brand-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    /** Semua kunci yang boleh muncul di penyaring, termasuk 'lainnya' untuk data lama. */
    public static function kunci(): array
    {
        return [...array_keys(self::DAFTAR), 'lainnya'];
    }

    /** Untuk penyaring: kunci -> label, plus Lainnya di ujung. */
    public static function pilihan(): array
    {
        return self::DAFTAR + ['lainnya' => 'Lainnya'];
    }
}
