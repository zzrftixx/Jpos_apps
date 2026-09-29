<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * UAT pembelian barang dan hutang ke pemasok.
 *
 * Ini menutup lubang paling mendasar di pembukuan JPos: sebelumnya stok bertambah tanpa
 * jejak uang sama sekali - barang muncul dari udara. Akibatnya bukan cuma pembukuan tidak
 * rapi, tapi NERACA TIDAK AKAN PERNAH SEIMBANG, karena sisi aset bertambah tanpa ada
 * penambahan di sisi kewajiban maupun modal.
 *
 * Setiap pembelian harus menggerakkan tiga hal sekaligus: stok, harga modal, dan uang.
 */
class PembelianTest extends JposTestCase
{
    private function beli(Product $produk, array $tambahan = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/pembelian', array_merge([
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $produk->id,
                'qty' => 10,
                'unit_type' => 'base',
                'price' => 5000,
            ]],
            'bayar' => 'tunai',
        ], $tambahan));
    }

    public function test_pembelian_tunai_menambah_stok_dan_mengeluarkan_kas(): void
    {
        $produk = $this->makeProduct(['stock' => 0, 'cost_price' => 0]);

        $this->beli($produk)->assertSessionHasNoErrors();

        $this->assertSame(10.0, (float) $produk->fresh()->stock, 'Stok harus bertambah 10.');
        $this->assertSame(5000.0, (float) $produk->fresh()->cost_price, 'Harga modal diperbarui dari harga beli.');

        $nota = Purchase::firstOrFail();
        $this->assertSame(50000.0, (float) $nota->total);
        $this->assertSame(0.0, (float) $nota->sisa_hutang, 'Pembelian tunai tidak meninggalkan hutang.');

        $kas = CashTransaction::where('category', 'pembelian')->firstOrFail();
        $this->assertSame('out', $kas->type);
        $this->assertSame(50000.0, (float) $kas->amount);

        $gerakan = StockMovement::where('type', 'in')->where('note', 'like', 'Pembelian%')->firstOrFail();
        $this->assertSame(10.0, (float) $gerakan->qty);
    }

    /**
     * INI YANG PALING SERING DISALAHPAHAMI saat baru belajar pembukuan: membeli barang
     * dagangan BUKAN beban. Uangnya tidak hilang, ia berubah wujud jadi stok di rak. Ia baru
     * menjadi beban saat barangnya terjual, dan pada saat itu dihitung sebagai HPP.
     */
    public function test_pembelian_tidak_mengurangi_laba(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'cost_price' => 5000, 'sell_price' => 8000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 80000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sebelum = Akuntansi::ringkasanLabaRugi(now()->toDateString(), now()->toDateString())->laba_bersih;

        $this->beli($produk)->assertSessionHasNoErrors();

        $this->assertSame(
            $sebelum,
            Akuntansi::ringkasanLabaRugi(now()->toDateString(), now()->toDateString())->laba_bersih,
            'Membeli barang menurunkan laba - padahal uangnya cuma berubah jadi stok.'
        );
    }

    public function test_pembelian_hutang_mencatat_sisa_dan_tidak_mengeluarkan_kas(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);
        $pemasok = Supplier::create(['name' => 'PT Pemasok Jaya']);

        $this->beli($produk, [
            'bayar' => 'hutang',
            'supplier_id' => $pemasok->id,
            'due_date' => now()->addDays(30)->toDateString(),
        ])->assertSessionHasNoErrors();

        $nota = Purchase::firstOrFail();

        $this->assertSame(50000.0, (float) $nota->sisa_hutang);
        $this->assertSame(0.0, (float) $nota->paid_amount);
        $this->assertSame(10.0, (float) $produk->fresh()->stock, 'Barang tetap masuk walau belum dibayar.');
        $this->assertSame(0, CashTransaction::count(), 'Belum ada uang keluar.');
    }

    public function test_hutang_bisa_dicicil_sampai_lunas(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);
        $this->beli($produk, ['bayar' => 'hutang'])->assertSessionHasNoErrors();

        $nota = Purchase::firstOrFail();

        $this->actingAs($this->admin)->post("/pembelian/{$nota->id}/bayar", [
            'amount' => 20000,
            'paid_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(30000.0, (float) $nota->fresh()->sisa_hutang);
        $this->assertSame(20000.0, (float) CashTransaction::where('category', 'pembelian')->sum('amount'));

        $this->actingAs($this->admin)->post("/pembelian/{$nota->id}/bayar", [
            'amount' => 30000,
            'paid_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $nota->fresh()->sisa_hutang);
        $this->assertTrue($nota->fresh()->sudahLunas());
    }

    public function test_bayar_melebihi_sisa_hutang_ditolak(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);
        $this->beli($produk, ['bayar' => 'hutang'])->assertSessionHasNoErrors();
        $nota = Purchase::firstOrFail();

        $this->actingAs($this->admin)->post("/pembelian/{$nota->id}/bayar", [
            'amount' => 999999,
            'paid_at' => now()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(50000.0, (float) $nota->fresh()->sisa_hutang, 'Pembayaran gagal tidak boleh menyentuh hutang.');
    }

    /**
     * Server bawaan melayani satu permintaan pada satu waktu, jadi ini bukan uji konkurensi
     * sungguhan - yang dijaga adalah pemeriksaan sisa hutang benar-benar dilakukan ulang di
     * dalam transaksi, sehingga klik kedua tidak bisa membayar hutang yang sudah lunas.
     */
    public function test_membayar_dua_kali_tidak_melebihi_hutang(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);
        $this->beli($produk, ['bayar' => 'hutang'])->assertSessionHasNoErrors();
        $nota = Purchase::firstOrFail();

        for ($ke = 1; $ke <= 2; $ke++) {
            $this->actingAs($this->admin)->post("/pembelian/{$nota->id}/bayar", [
                'amount' => 50000,
                'paid_at' => now()->toDateString(),
            ]);
        }

        $this->assertSame(0.0, (float) $nota->fresh()->sisa_hutang);
        $this->assertSame(
            50000.0,
            (float) CashTransaction::where('category', 'pembelian')->sum('amount'),
            'Kas keluar dua kali untuk hutang yang sama.'
        );
    }

    /** Membeli per Dus tapi menjual per Pcs - stok dan harga modal harus dalam satuan dasar. */
    public function test_membeli_dalam_satuan_besar_dikonversi_ke_satuan_dasar(): void
    {
        $produk = $this->makeProduct(['stock' => 0, 'unit' => 'Pcs', 'cost_price' => 0]);
        $dus = $this->makeProductUnit($produk, 'Dus', 40, 200000);

        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $produk->id,
                'qty' => 2,
                'unit_type' => 'unit_' . $dus->id,
                'price' => 100000,
            ]],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->assertSame(80.0, (float) $produk->fresh()->stock, '2 Dus x 40 = 80 Pcs.');
        $this->assertSame(2500.0, (float) $produk->fresh()->cost_price, '200.000 untuk 80 Pcs = 2.500 per Pcs.');
    }

    /**
     * Ongkir dibagi SEBANDING NILAI barang, bukan rata per baris. Membebankan ongkir yang
     * sama ke barang mahal dan barang murah membuat margin barang murah terlihat jauh lebih
     * buruk daripada kenyataannya.
     */
    public function test_biaya_kirim_dibagi_sebanding_nilai_barang(): void
    {
        $mahal = $this->makeProduct(['name' => 'Barang Mahal', 'stock' => 0, 'unit' => 'Pcs']);
        $murah = $this->makeProduct(['name' => 'Barang Murah', 'stock' => 0, 'unit' => 'Pcs']);

        // Mahal: 10 x 9.000 = 90.000. Murah: 10 x 1.000 = 10.000. Subtotal 100.000.
        // Ongkir 10.000 -> 9.000 ke yang mahal, 1.000 ke yang murah.
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $mahal->id, 'qty' => 10, 'unit_type' => 'base', 'price' => 9000],
                ['product_id' => $murah->id, 'qty' => 10, 'unit_type' => 'base', 'price' => 1000],
            ],
            'other_cost' => 10000,
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->assertSame(9900.0, (float) $mahal->fresh()->cost_price, '(90.000 + 9.000) / 10');
        $this->assertSame(1100.0, (float) $murah->fresh()->cost_price, '(10.000 + 1.000) / 10');
    }

    public function test_membatalkan_pembelian_mengembalikan_stok_dan_kas(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);
        $this->beli($produk)->assertSessionHasNoErrors();
        $nota = Purchase::firstOrFail();

        $this->assertSame(10.0, (float) $produk->fresh()->stock);

        $this->actingAs($this->admin)->delete("/pembelian/{$nota->id}")->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $produk->fresh()->stock, 'Stok harus keluar lagi.');
        $this->assertSame(0, Purchase::count());
        // Kas keluar 50.000 lalu masuk lagi 50.000 - bersihnya nol.
        $this->assertSame(
            0.0,
            (float) CashTransaction::where('type', 'out')->sum('amount') - (float) CashTransaction::where('type', 'in')->sum('amount')
        );
    }

    /** Barang yang sudah terjual tidak bisa ditarik kembali dari stok. */
    public function test_pembelian_yang_barangnya_sudah_terjual_tidak_bisa_dibatalkan(): void
    {
        $produk = $this->makeProduct(['stock' => 0, 'sell_price' => 8000]);
        $this->beli($produk)->assertSessionHasNoErrors();
        $nota = Purchase::firstOrFail();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 6]],
            'paid_amount' => 48000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->actingAs($this->admin)->delete("/pembelian/{$nota->id}")->assertStatus(422);

        $this->assertSame(4.0, (float) $produk->fresh()->stock, 'Stok tidak boleh berubah saat pembatalan ditolak.');
        $this->assertSame(1, Purchase::count());
    }

    /**
     * Nota bertanggal masa depan ditolak - dan ini bukan sekadar kerapian.
     *
     * Stok bertambah SEKARANG apa pun tanggal notanya, tapi hutangnya baru ikut dihitung
     * Neraca setelah tanggal itu tiba. Diukur sebelum diperbaiki: nota bertanggal tahun depan
     * menaikkan persediaan 50.000 sementara hutang usaha tetap 0, sehingga neraca timpang
     * persis 50.000 - dan pemilik toko tidak punya cara menebak penyebabnya.
     */
    public function test_nota_bertanggal_masa_depan_ditolak(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);

        $this->beli($produk, ['purchase_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('purchase_date');

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0.0, (float) $produk->fresh()->stock, 'Stok bertambah walau notanya ditolak.');
    }

    /** Nota hari ini dan nota mundur tetap boleh - toko sering mencatat belakangan. */
    public function test_nota_hari_ini_dan_mundur_tetap_diterima(): void
    {
        $produk = $this->makeProduct(['stock' => 0]);

        $this->beli($produk, ['purchase_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->beli($produk, ['purchase_date' => now()->subDays(30)->toDateString()])->assertSessionHasNoErrors();

        $this->assertSame(2, Purchase::count());
    }

    public function test_kasir_tidak_bisa_membuka_halaman_pembelian(): void
    {
        $this->actingAs($this->kasir)->get('/pembelian')->assertForbidden();
    }
}
