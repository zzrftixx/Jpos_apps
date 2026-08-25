<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\JposTestCase;

/**
 * Indeks performa.
 *
 * Toko yang berjalan bertahun-tahun mengumpulkan puluhan ribu baris di sales, sale_items,
 * dan stock_movements. Tanpa indeks ini laporan memindai seluruh tabel - dan karena server
 * bawaan melayani satu permintaan pada satu waktu, satu laporan lambat menahan kasir.
 *
 * Indeksnya mudah hilang tanpa jejak: satu saja migrasi yang memakai ->change() pada SQLite
 * akan membangun ulang tabelnya. Test ini yang memberi tahu kalau itu terjadi.
 */
class IndeksPerformaTest extends JposTestCase
{
    public static function indeksWajib(): array
    {
        return [
            ['sale_items', 'sale_items_sale_id_index'],
            ['sale_items', 'sale_items_product_id_index'],
            ['stock_movements', 'stock_movements_product_id_index'],
            ['stock_movements', 'stock_movements_created_at_index'],
            ['sales', 'sales_created_at_order_status_index'],
            ['sales', 'sales_customer_id_index'],
            ['cash_transactions', 'cash_transactions_created_at_index'],
            ['sale_returns', 'sale_returns_sale_id_index'],
            ['sale_return_items', 'sale_return_items_sale_return_id_index'],
            ['sale_return_items', 'sale_return_items_sale_item_id_index'],
            ['product_units', 'product_units_product_id_sort_order_index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('indeksWajib')]
    public function test_indeks_ada_setelah_seluruh_migrasi(string $tabel, string $nama): void
    {
        $adaIndeks = collect(Schema::getIndexes($tabel))->pluck('name')->contains($nama);

        $this->assertTrue($adaIndeks, "Indeks {$nama} hilang dari tabel {$tabel}.");
    }

    /**
     * CHECK constraint tidak ikut dibuat ulang oleh Laravel saat tabel SQLite dibangun ulang
     * karena ->change(). Kalau salah satunya hilang, artinya ada migrasi yang merebuild tabel
     * dan indeks pun kemungkinan besar ikut terdampak.
     */
    public function test_check_constraint_masih_utuh(): void
    {
        foreach (['products', 'stock_movements'] as $tabel) {
            $sql = \Illuminate\Support\Facades\DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name = ?",
                [$tabel]
            )?->sql ?? '';

            $this->assertStringContainsStringIgnoringCase(
                'check (',
                $sql,
                "CHECK constraint di tabel {$tabel} hilang - kemungkinan ada migrasi yang membangun ulang tabelnya."
            );
        }
    }
}
