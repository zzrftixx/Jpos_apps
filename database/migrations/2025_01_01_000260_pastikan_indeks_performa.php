<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memastikan seluruh indeks performa benar-benar ada, dan menambahkan yang baru.
 *
 * Migrasi indeks yang lama (000220) tetap dibiarkan apa adanya - namanya sudah tercatat di
 * tabel migrations milik toko yang sedang berjalan, dan mengganti nama berkas yang sudah
 * dijalankan hanya meninggalkan baris yatim yang menyandung `migrate:rollback` saat
 * diagnosis darurat.
 *
 * Berkas ini berjalan PALING AKHIR, jadi apa pun yang terjadi pada migrasi sebelumnya,
 * indeksnya dijamin ada. Idempoten: aman dijalankan berulang kali, dan aman untuk instalasi
 * yang indeksnya sudah lengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sama persis dengan 000220 - diulang di sini sebagai jaring pengaman untuk
        // instalasi yang indeksnya pernah hilang karena sebab lain.
        $this->indeks('sale_items', 'sale_id');
        $this->indeks('sale_items', 'product_id');
        $this->indeks('stock_movements', 'product_id');
        $this->indeks('stock_movements', 'created_at');
        $this->indeks('sales', ['created_at', 'order_status']);
        $this->indeks('sales', 'customer_id');
        $this->indeks('cash_transactions', 'created_at');
        $this->indeks('sale_returns', 'sale_id');
        $this->indeks('sale_return_items', 'sale_return_id');
        $this->indeks('sale_return_items', 'sale_item_id');

        // Baru: rantai satuan dibaca berurutan setiap kali katalog kasir dibangun dan
        // setiap kali halaman produk dibuka.
        $this->indeks('product_units', ['product_id', 'sort_order']);
    }

    /**
     * Sengaja tidak melakukan apa-apa.
     *
     * Membuang indeks saat rollback hanya membuat toko berjalan lambat tanpa alasan, dan
     * migrasi 000220 sudah memegang tanggung jawab itu.
     */
    public function down(): void
    {
        //
    }

    private function nama(string $tabel, array|string $kolom): string
    {
        return $tabel . '_' . implode('_', (array) $kolom) . '_index';
    }

    private function indeks(string $tabel, array|string $kolom): void
    {
        if (! Schema::hasTable($tabel)) {
            return;
        }

        $nama = $this->nama($tabel, $kolom);

        if ($this->indeksAda($tabel, $nama)) {
            return;
        }

        Schema::table($tabel, function (Blueprint $table) use ($kolom, $nama) {
            $table->index((array) $kolom, $nama);
        });
    }

    private function indeksAda(string $tabel, string $nama): bool
    {
        try {
            foreach (Schema::getIndexes($tabel) as $indeks) {
                if (($indeks['name'] ?? '') === $nama) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Kalau daftar indeks tidak bisa dibaca, biarkan pembuatannya dicoba -
            // kegagalannya akan terlihat jelas, bukan tersembunyi.
        }

        return false;
    }
};
