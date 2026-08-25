<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Tests\JposTestCase;

/**
 * UAT stok pecahan - penjaga terhadap kebocoran uang yang paling mahal di aplikasi ini.
 *
 * Selama kuantitas selalu bilangan bulat, menulis `(int) round($qty * $conversion)` adalah
 * penyederhanaan yang wajar. Begitu satuan kecil masuk - Gram terhadap Kilogram, atau
 * menjual 0,4 Kg - rumus itu berubah jadi lubang:
 *
 *     (int) round(400 * 0.001) = (int) round(0.4) = 0
 *
 * Barang keluar dari toko, uang tertagih, stok tidak berkurang sepeser pun, dan bisa
 * diulang tanpa batas. Yang lebih halus: pemeriksaan "stok cukup?" menjumlahkan nilai yang
 * SUDAH dibulatkan, sehingga dua baris 0,4 Kg terbaca 0 + 0 = 0 dan lolos walau stok kosong.
 *
 * Test di berkas ini sengaja ditulis pada tingkat HTTP, bukan unit, supaya seluruh jalur -
 * validasi, konversi satuan, penguncian, mutasi stok - ikut terlindungi. Kalau suatu saat
 * ada yang mengembalikan pembulatan integer, test pertama di bawah ini yang jatuh duluan.
 */
class StokPecahanTest extends JposTestCase
{
    /**
     * Kasus yang paling merugikan: satuan kecil yang konversinya di bawah 1.
     *
     * Ini bisa terjadi TANPA fitur timbangan sama sekali - cukup produk ber-satuan dasar Kg
     * yang juga dijual per Gram.
     */
    public function test_menjual_satuan_kecil_memotong_stok_pecahan(): void
    {
        $produk = $this->makeProduct(['stock' => 10, 'unit' => 'Kg', 'sell_price' => 20000]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 400, 'unit_type' => 'unit_' . $gram->id]],
            'paid_amount' => 8000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertSame(
            9.6,
            (float) $produk->fresh()->stock,
            '400 Gram = 0,4 Kg. Kalau ini 10, stok tidak berkurang sama sekali dan barang keluar gratis.'
        );

        $gerakan = StockMovement::where('type', 'sale')->firstOrFail();
        $this->assertSame(-0.4, (float) $gerakan->qty, 'Mutasi stok harus mencatat pecahan apa adanya.');
    }

    /**
     * Pembulatan ke atas sama merugikannya, hanya arahnya terbalik: toko kehilangan stok
     * yang sebenarnya masih ada di rak.
     */
    public function test_menjual_setengah_satuan_tidak_dibulatkan_ke_atas(): void
    {
        $produk = $this->makeProduct(['stock' => 10, 'unit' => 'Kg', 'sell_price' => 20000]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2500, 'unit_type' => 'unit_' . $gram->id]],
            'paid_amount' => 50000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertSame(7.5, (float) $produk->fresh()->stock);
    }

    /**
     * Guard stok harus menjumlahkan nilai PECAHAN, bukan hasil pembulatan tiap baris.
     * Kalau dibulatkan dulu, dua baris 0,4 Kg terbaca 0 + 0 = 0 dan lolos.
     */
    public function test_dua_baris_pecahan_diakumulasi_sebelum_diperiksa(): void
    {
        $produk = $this->makeProduct(['stock' => 0.5, 'unit' => 'Kg', 'sell_price' => 20000]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [
                ['product_id' => $produk->id, 'qty' => 400, 'unit_type' => 'unit_' . $gram->id],
                ['product_id' => $produk->id, 'qty' => 400, 'unit_type' => 'unit_' . $gram->id],
            ],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(0.5, (float) $produk->fresh()->stock, 'Transaksi gagal tidak boleh menyentuh stok.');
        $this->assertSame(0, Sale::count());
    }

    /**
     * Pecahan desimal tidak terwakili persis oleh float biner: 0,1 + 0,2 = 0,30000000000000004.
     * Tanpa perbandingan berepsilon, menghabiskan stok tepat pas justru ditolak.
     */
    public function test_pecahan_yang_tepat_menghabiskan_stok_tidak_ditolak(): void
    {
        $produk = $this->makeProduct(['stock' => 0.3, 'unit' => 'Kg', 'sell_price' => 20000]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        for ($ke = 1; $ke <= 3; $ke++) {
            $this->actingAs($this->admin)->postJson('/kasir', [
                'items' => [['product_id' => $produk->id, 'qty' => 100, 'unit_type' => 'unit_' . $gram->id]],
                'paid_amount' => 2000,
                'payment_method' => 'cash',
            ])->assertOk("Penjualan ke-{$ke} dari 3 x 0,1 Kg atas stok 0,3 Kg harus diterima.");
        }

        $this->assertSame(0.0, (float) $produk->fresh()->stock, 'Sisa stok harus nol bulat, bukan 0,0000000001.');
    }

    public function test_pembatalan_pesanan_mengembalikan_pecahan_utuh(): void
    {
        $produk = $this->makeProduct(['stock' => 10, 'unit' => 'Kg', 'sell_price' => 20000]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 400, 'unit_type' => 'unit_' . $gram->id]],
            'paid_amount' => 3000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $this->assertSame(9.6, (float) $produk->fresh()->stock);

        $sale = Sale::firstOrFail();
        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel")->assertRedirect();

        $this->assertSame(10.0, (float) $produk->fresh()->stock, 'Stok harus kembali persis, bukan 9,9999 atau 10,0001.');
    }

    public function test_retur_sebagian_mengembalikan_pecahan(): void
    {
        $produk = $this->makeProduct(['stock' => 10, 'unit' => 'Kg', 'sell_price' => 20000]);
        $gram = $this->makeProductUnit($produk, 'Gram', 0.001, 20);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 400, 'unit_type' => 'unit_' . $gram->id]],
            'paid_amount' => 8000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::firstOrFail();
        $item = $sale->items()->firstOrFail();

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'items' => [['sale_item_id' => $item->id, 'qty' => 200]],
            'reason' => 'Rusak',
        ])->assertOk();

        $this->assertSame(9.8, (float) $produk->fresh()->stock, 'Retur 200 Gram = 0,2 Kg.');
        $this->assertSame(200.0, (float) $item->fresh()->returned_qty);
    }

    /**
     * Penjaga terhadap perubahan skema atau driver.
     *
     * Kolom stok dideklarasikan integer di instalasi lama, dan itu TIDAK menghalangi
     * penyimpanan pecahan karena INTEGER affinity di SQLite menyimpan nilai yang tidak bisa
     * dikonversi tanpa kehilangan presisi apa adanya sebagai REAL. Seluruh perbaikan di
     * berkas ini bersandar pada perilaku itu, jadi kalau suatu saat berubah, test inilah
     * yang jatuh lebih dulu dan menjelaskan sebabnya.
     */
    public function test_kolom_stok_benar_benar_menyimpan_pecahan(): void
    {
        $produk = $this->makeProduct(['stock' => 10]);

        $produk->stock = 9.6;
        $produk->save();

        $this->assertSame(9.6, (float) Product::findOrFail($produk->id)->stock);
    }
}
