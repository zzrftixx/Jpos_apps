<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\JposTestCase;

/**
 * UAT migrasi terhadap database yang datang dari BASELINE LAMA.
 *
 * Toko yang sudah berjalan tidak selalu berangkat dari skema JPos. Sebagian berasal dari
 * turunan lain (petshop, vitur) yang menambahkan kolom yang sama lewat migrasi BERNAMA BEDA.
 * Bagi Laravel, migrasi bernama beda itu belum pernah dijalankan - jadi ia menjalankannya,
 * dan `alter table add column` menabrak kolom yang sudah ada.
 *
 * Ini kejadian nyata, bukan kekhawatiran teoretis. Database client petshop gagal diperbarui:
 *
 *     SQLSTATE[HY000]: General error: 1 duplicate column name: multi_unit_enabled
 *
 * Migrasi berhenti di tengah, database tertinggal SETENGAH TERMIGRASI, dan aplikasi tidak
 * bisa membacanya sama sekali. Pemilik toko melihatnya sebagai "database tidak terbaca" -
 * tanpa satu pun petunjuk penyebabnya.
 *
 * Yang dijaga di sini: migrasi penambah-kolom harus IDEMPOTEN. Kolom yang sudah ada dilewati,
 * bukan ditambahkan ulang.
 */
class MigrasiBaselineLamaTest extends JposTestCase
{
    private string $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = sys_get_temp_dir() . '/jpos-baseline-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        DB::purge('baseline');
        @unlink($this->db);

        parent::tearDown();
    }

    /**
     * Membangun database yang meniru baseline lama: skema JPos penuh, tapi catatan migrasinya
     * memakai NAMA-NAMA dari turunan petshop/vitur. Kolomnya sudah ada; migrasi JPos yang
     * setara belum tercatat pernah jalan.
     */
    private function siapkanBaselineLama(): void
    {
        File::put($this->db, '');

        config(['database.connections.baseline' => [
            'driver' => 'sqlite',
            'database' => $this->db,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('baseline');

        $this->artisan('migrate', ['--database' => 'baseline', '--force' => true])->assertSuccessful();

        // Nama migrasi JPos dihapus dari catatan, lalu diganti nama versi lama. Kolomnya tetap
        // ada di tabel - persis keadaan database client.
        DB::connection('baseline')->table('migrations')
            ->whereIn('migration', [
                '2025_01_01_000230_add_multi_unit_chain_columns',
                '2025_01_01_000240_add_weighable_and_unit_cost_price',
            ])
            ->delete();

        DB::connection('baseline')->table('migrations')->insert([
            ['migration' => '2025_01_01_000220_add_multi_unit_chain_columns', 'batch' => 1],
            ['migration' => '2025_01_01_000230_add_weighable_and_cost_price_columns', 'batch' => 1],
        ]);
    }

    /** INI YANG PALING MENENTUKAN: memperbarui database baseline lama tidak boleh gagal. */
    public function test_migrasi_atas_baseline_lama_berjalan_sampai_selesai(): void
    {
        $this->siapkanBaselineLama();

        $this->artisan('migrate', ['--database' => 'baseline', '--force' => true])
            ->assertSuccessful();

        $tercatat = DB::connection('baseline')->table('migrations')
            ->where('migration', '2025_01_01_000230_add_multi_unit_chain_columns')->exists();

        $this->assertTrue($tercatat, 'Migrasi JPos tidak tercatat pernah dijalankan.');
    }

    /** Kolom yang sudah ada tidak boleh digandakan, dan tabel barunya tetap terbentuk. */
    public function test_skema_akhir_lengkap_dan_tidak_ada_kolom_ganda(): void
    {
        $this->siapkanBaselineLama();
        $this->artisan('migrate', ['--database' => 'baseline', '--force' => true])->assertSuccessful();

        $skema = Schema::connection('baseline');

        $wajibAda = [
            'products' => 'multi_unit_enabled',
            'product_units' => 'ratio_to_previous',
            'units' => 'is_weighable',
            'sale_items' => 'cost_price_snapshot',
        ];

        foreach ($wajibAda as $tabel => $kolom) {
            $this->assertTrue($skema->hasColumn($tabel, $kolom), "Kolom {$tabel}.{$kolom} hilang.");

            $jumlah = collect(DB::connection('baseline')->select('PRAGMA table_info("' . $tabel . '")'))
                ->where('name', $kolom)->count();

            $this->assertSame(1, $jumlah, "Kolom {$tabel}.{$kolom} muncul {$jumlah} kali.");
        }

        foreach (['purchases', 'purchase_items', 'purchase_payments',
            'fixed_assets', 'neraca_snapshots', 'racks', 'rack_slots'] as $tabel) {
            $this->assertTrue($skema->hasTable($tabel), "Tabel {$tabel} tidak terbentuk.");
        }
    }

    /**
     * Backfill rantai satuan TIDAK boleh dijalankan ulang di atas data yang sudah terisi.
     *
     * Pelipatan kumulatif yang dijalankan dua kali menggandakan konversinya: produk dengan
     * Lusin=12 berubah jadi 288, dan sejak saat itu harga, potongan stok, serta seluruh
     * laporan salah tanpa ada yang menyadarinya.
     */
    public function test_rantai_satuan_tidak_tergandakan_saat_migrasi_diulang(): void
    {
        $this->siapkanBaselineLama();

        $produkId = DB::connection('baseline')->table('products')->insertGetId([
            'name' => 'Produk Rantai', 'sku' => 'RANTAI-1', 'unit' => 'Pcs',
            'cost_price' => 1000, 'sell_price' => 1500, 'stock' => 10, 'min_stock' => 1,
            'is_taxable' => 0, 'is_active' => 1, 'type' => 'barang',
            'multi_unit_enabled' => 1, 'hpp_calc_enabled' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $unitId = DB::connection('baseline')->table('units')->insertGetId([
            'name' => 'Lusin Baseline', 'is_weighable' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::connection('baseline')->table('product_units')->insert([
            'product_id' => $produkId, 'unit_id' => $unitId,
            'conversion' => 12, 'ratio_to_previous' => 12, 'sort_order' => 0,
            'price' => 15000, 'allow_decimal' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('migrate', ['--database' => 'baseline', '--force' => true])->assertSuccessful();

        $baris = DB::connection('baseline')->table('product_units')
            ->where('product_id', $produkId)->first();

        $this->assertEquals(12, (float) $baris->conversion, 'Konversi berubah setelah migrasi.');
        $this->assertEquals(12, (float) $baris->ratio_to_previous,
            'Rasio rantai tergandakan - harga dan potongan stok produk ini akan salah selamanya.');
    }

    /** Data toko harus utuh setelah migrasi - tidak ada baris maupun nilai yang hilang. */
    public function test_data_toko_utuh_setelah_migrasi(): void
    {
        $this->siapkanBaselineLama();

        for ($i = 1; $i <= 5; $i++) {
            DB::connection('baseline')->table('products')->insert([
                'name' => 'Barang ' . $i, 'sku' => 'BRG-' . $i, 'unit' => 'Pcs',
                'cost_price' => 1000 * $i, 'sell_price' => 1500 * $i, 'stock' => 10 + $i,
                'min_stock' => 1, 'is_taxable' => 0, 'is_active' => 1, 'type' => 'barang',
                'multi_unit_enabled' => 0, 'hpp_calc_enabled' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $sebelum = DB::connection('baseline')->table('products')->count();
        $nilaiSebelum = (float) DB::connection('baseline')->table('products')->sum('cost_price');

        $this->artisan('migrate', ['--database' => 'baseline', '--force' => true])->assertSuccessful();

        $this->assertSame($sebelum, DB::connection('baseline')->table('products')->count(),
            'Jumlah produk berubah setelah migrasi.');
        $this->assertEqualsWithDelta($nilaiSebelum,
            (float) DB::connection('baseline')->table('products')->sum('cost_price'), 0.01,
            'Nilai harga modal berubah setelah migrasi.');
    }
}
