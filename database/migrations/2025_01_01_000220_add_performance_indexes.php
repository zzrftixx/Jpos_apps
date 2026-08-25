<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan indeks pada kolom yang sering dipakai untuk JOIN, filter, dan urutan.
 *
 * Toko yang berjalan bertahun-tahun mengumpulkan puluhan ribu baris di sales, sale_items,
 * dan stock_movements. Tanpa indeks ini, laporan dan riwayat stok memindai seluruh tabel.
 * Aman untuk data yang sudah ada: menambah indeks tidak mengubah satu baris pun, dan
 * migrasi ini idempoten kalau dijalankan ulang.
 *
 * SQLite mengunci database sesaat saat membuat indeks; untuk aplikasi kasir satu pemakai
 * ini tidak terasa, tapi backup pengaman tetap dibuat lebih dulu oleh jpos:prepare.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->indeks('sale_items', 'sale_id');
        $this->indeks('sale_items', 'product_id');

        $this->indeks('stock_movements', 'product_id');
        $this->indeks('stock_movements', 'created_at');

        // Laporan hampir selalu menyaring rentang tanggal lalu status.
        $this->indeks('sales', ['created_at', 'order_status']);
        $this->indeks('sales', 'customer_id');

        $this->indeks('cash_transactions', 'created_at');

        $this->indeks('sale_returns', 'sale_id');
        $this->indeks('sale_return_items', 'sale_return_id');
        $this->indeks('sale_return_items', 'sale_item_id');
    }

    public function down(): void
    {
        $this->hapusIndeks('sale_items', 'sale_id');
        $this->hapusIndeks('sale_items', 'product_id');
        $this->hapusIndeks('stock_movements', 'product_id');
        $this->hapusIndeks('stock_movements', 'created_at');
        $this->hapusIndeks('sales', ['created_at', 'order_status']);
        $this->hapusIndeks('sales', 'customer_id');
        $this->hapusIndeks('cash_transactions', 'created_at');
        $this->hapusIndeks('sale_returns', 'sale_id');
        $this->hapusIndeks('sale_return_items', 'sale_return_id');
        $this->hapusIndeks('sale_return_items', 'sale_item_id');
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

        // Beberapa baris master data lama mungkin sudah pernah diberi indeks manual;
        // pemeriksaan ini membuat migrasi aman dijalankan ulang.
        if ($this->indeksAda($tabel, $nama)) {
            return;
        }

        Schema::table($tabel, function (Blueprint $table) use ($kolom, $nama) {
            $table->index((array) $kolom, $nama);
        });
    }

    private function hapusIndeks(string $tabel, array|string $kolom): void
    {
        if (! Schema::hasTable($tabel)) {
            return;
        }

        $nama = $this->nama($tabel, $kolom);

        if (! $this->indeksAda($tabel, $nama)) {
            return;
        }

        Schema::table($tabel, function (Blueprint $table) use ($nama) {
            $table->dropIndex($nama);
        });
    }

    private function indeksAda(string $tabel, string $nama): bool
    {
        foreach (\DB::select('PRAGMA index_list(' . $tabel . ')') as $idx) {
            if (($idx->name ?? null) === $nama) {
                return true;
            }
        }

        return false;
    }
};
