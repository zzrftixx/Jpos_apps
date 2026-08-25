<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Tests\JposTestCase;

/**
 * UAT alur transaksi: menelusuri perjalanan uang dan stok dari ujung ke ujung.
 *
 * Fokusnya bukan "apakah halaman terbuka", tapi "apakah angkanya benar setelah
 * serangkaian aksi yang biasa dilakukan kasir sehari-hari" - termasuk urutan yang
 * jarang tapi mungkin, karena di situlah kesalahan data biasanya bersembunyi.
 */
class AlurTransaksiTest extends JposTestCase
{
    public function test_penjualan_satuan_besar_memotong_stok_dalam_satuan_dasar(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 1000, 'unit' => 'pcs']);
        $dus = $this->makeProductUnit($produk, 'Dus', 12, 11000);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2, 'unit_type' => 'unit_' . $dus->id]],
            'paid_amount' => 22000,
            'payment_method' => 'cash',
        ])->assertOk();

        // 2 dus x 12 = 24 pcs
        $this->assertSame(76.0, (float) $produk->fresh()->stock);

        $sale = Sale::firstOrFail();
        $this->assertSame(22000.0, (float) $sale->total, 'Harga per dus, bukan per pcs.');

        $gerakan = StockMovement::where('type', 'sale')->firstOrFail();
        $this->assertSame(-24.0, (float) $gerakan->qty, 'Mutasi stok harus dalam satuan dasar.');
    }

    /** Produk yang sama dibeli dalam dua satuan sekaligus - stok harus dijumlahkan. */
    public function test_dua_baris_satuan_berbeda_dihitung_terhadap_stok_yang_sama(): void
    {
        $produk = $this->makeProduct(['stock' => 30, 'sell_price' => 1000]);
        $dus = $this->makeProductUnit($produk, 'Dus', 12, 11000);

        // 2 dus (24 pcs) + 10 pcs = 34 pcs, melebihi stok 30 -> harus ditolak
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [
                ['product_id' => $produk->id, 'qty' => 2, 'unit_type' => 'unit_' . $dus->id],
                ['product_id' => $produk->id, 'qty' => 10],
            ],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(30.0, (float) $produk->fresh()->stock, 'Transaksi gagal tidak boleh menyentuh stok.');
        $this->assertSame(0, Sale::count());
    }

    public function test_harga_grosir_berlaku_saat_qty_mencapai_minimum(): void
    {
        $produk = $this->makeProduct([
            'stock' => 100, 'sell_price' => 10000,
            'wholesale_price' => 8000, 'wholesale_min_qty' => 12,
        ]);

        // 11 pcs -> masih harga normal
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 11]],
            'paid_amount' => 110000, 'payment_method' => 'cash',
        ])->assertOk();
        $this->assertSame(110000.0, (float) Sale::latest('id')->first()->total);

        // 12 pcs -> harga grosir
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 12]],
            'paid_amount' => 96000, 'payment_method' => 'cash',
        ])->assertOk();
        $this->assertSame(96000.0, (float) Sale::latest('id')->first()->total);
    }

    public function test_pajak_hanya_dari_produk_kena_pajak_setelah_diskon(): void
    {
        $this->enableTax(10);
        $kena = $this->makeProduct(['sell_price' => 10000, 'is_taxable' => true, 'stock' => 10]);
        $bebas = $this->makeProduct(['sell_price' => 10000, 'is_taxable' => false, 'stock' => 10]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [
                ['product_id' => $kena->id, 'qty' => 1],
                ['product_id' => $bebas->id, 'qty' => 1],
            ],
            'discount' => 4000,
            'paid_amount' => 100000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::firstOrFail();

        // subtotal 20.000, diskon 4.000. Basis pajak = 10.000 - (4.000 x 10.000/20.000) = 8.000
        // Pajak 10% = 800. Total = 20.000 - 4.000 + 800 = 16.800
        $this->assertSame(20000.0, (float) $sale->subtotal);
        $this->assertSame(800.0, (float) $sale->tax_amount);
        $this->assertSame(16800.0, (float) $sale->total);
    }

    /** Rangkaian panjang: jual -> retur sebagian -> batalkan sisanya. */
    public function test_jual_retur_sebagian_lalu_batal_mengembalikan_stok_tepat(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 5000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 50000, 'payment_method' => 'cash',
        ])->assertOk();
        $this->assertSame(90.0, (float) $produk->fresh()->stock);

        $sale = Sale::firstOrFail();
        $item = $sale->items->first();

        // Retur 4 dari 10
        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'items' => [['sale_item_id' => $item->id, 'qty' => 4]],
        ])->assertOk();
        $this->assertSame(94.0, (float) $produk->fresh()->stock);

        // Batalkan transaksi: hanya 6 sisa yang belum diretur yang dikembalikan
        $this->actingAs($this->admin)->post("/retur/{$sale->id}/cancel")->assertRedirect();
        $this->assertSame(100.0, (float) $produk->fresh()->stock, 'Tidak boleh ada pengembalian ganda.');
    }

    public function test_retur_melebihi_qty_terjual_ditolak(): void
    {
        $produk = $this->makeProduct(['stock' => 50, 'sell_price' => 5000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 3]],
            'paid_amount' => 15000, 'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::firstOrFail();

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'items' => [['sale_item_id' => $sale->items->first()->id, 'qty' => 5]],
        ])->assertStatus(422);

        $this->assertSame(47.0, (float) $produk->fresh()->stock, 'Retur gagal tidak boleh mengubah stok.');
    }

    /** Edit pesanan waiting list: stok lama dikembalikan, stok baru dipotong. */
    public function test_edit_pesanan_menghitung_ulang_stok_secara_neto(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 5000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 20000, 'payment_method' => 'cash', 'is_waiting_list' => true,
        ])->assertOk();
        $this->assertSame(90.0, (float) $produk->fresh()->stock);

        $sale = Sale::where('order_status', 'waiting')->firstOrFail();

        // Ubah dari 10 menjadi 3
        $this->actingAs($this->admin)->putJson("/kasir/waiting-list/{$sale->id}", [
            'items' => [['product_id' => $produk->id, 'qty' => 3]],
        ])->assertOk();

        $this->assertSame(97.0, (float) $produk->fresh()->stock, 'Stok harus 100 - 3, bukan hasil akumulasi.');
        $this->assertSame(15000.0, (float) $sale->fresh()->subtotal);
    }

    public function test_pelunasan_bertahap_menjadi_lunas_saat_cukup(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'paid_amount' => 10000, 'payment_method' => 'cash', 'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::where('order_status', 'waiting')->firstOrFail();
        $this->assertSame(50000.0, (float) $sale->total);

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/pay", ['amount' => 20000]);
        $this->assertSame('waiting', $sale->fresh()->order_status);
        $this->assertSame(30000.0, (float) $sale->fresh()->paid_amount);

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/pay", ['amount' => 20000]);
        $this->assertSame('completed', $sale->fresh()->order_status);
        $this->assertSame(50000.0, (float) $sale->fresh()->paid_amount);
    }

    public function test_dp_tidak_boleh_sama_atau_melebihi_total(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 20000, 'payment_method' => 'cash', 'is_waiting_list' => true,
        ])->assertStatus(422);

        $this->assertSame(100.0, (float) $produk->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_bayar_kurang_dari_total_ditolak(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 19999, 'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(100.0, (float) $produk->fresh()->stock);
    }

    /** Produk jasa tidak punya stok fisik - tidak pernah dipotong atau divalidasi. */
    public function test_produk_jasa_tidak_memotong_stok(): void
    {
        $jasa = $this->makeProduct(['type' => 'jasa', 'stock' => 0, 'sell_price' => 25000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $jasa->id, 'qty' => 3]],
            'paid_amount' => 75000, 'payment_method' => 'cash',
        ])->assertOk();

        $this->assertSame(0.0, (float) $jasa->fresh()->stock);
        $this->assertSame(0, StockMovement::where('type', 'sale')->count(), 'Jasa tidak mencatat mutasi stok.');
        $this->assertSame(75000.0, (float) Sale::firstOrFail()->total);
    }

    public function test_laporan_omset_hanya_menghitung_transaksi_lunas(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        // Lunas
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 20000, 'payment_method' => 'cash',
        ])->assertOk();

        // Masih DP
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'paid_amount' => 10000, 'payment_method' => 'cash', 'is_waiting_list' => true,
        ])->assertOk();

        $html = $this->actingAs($this->admin)->get('/laporan/omset')->assertOk()->getContent();

        // Omset harus 20.000 saja, bukan 70.000
        $this->assertStringContainsString('20.000', $html);
        $this->assertStringNotContainsString('70.000', $html);
    }

    public function test_stok_tidak_pernah_menjadi_negatif(): void
    {
        $produk = $this->makeProduct(['stock' => 1, 'sell_price' => 5000]);

        // Beli 1 -> habis
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 1]],
            'paid_amount' => 5000, 'payment_method' => 'cash',
        ])->assertOk();
        $this->assertSame(0.0, (float) $produk->fresh()->stock);

        // Beli lagi -> ditolak
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 1]],
            'paid_amount' => 5000, 'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(0.0, (float) $produk->fresh()->stock);
    }
}
