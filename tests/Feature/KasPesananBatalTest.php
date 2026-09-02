<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * UANG HANTU: DP pesanan yang dibatalkan tidak boleh terkunci di Kas selamanya.
 *
 * Ditemukan lewat audit agen AI lain, diverifikasi ulang ke kode sebelum satu baris diubah.
 *
 * Cacatnya: `Akuntansi::kasPada()` menjumlahkan `paid_amount - change_amount` dari SELURUH
 * penjualan tanpa menyaring status. Pesanan DP yang dibatalkan - barangnya sudah kembali ke
 * rak, uangnya sudah kembali ke pembeli - tetap dihitung sebagai uang di laci. Kasir yang
 * menghitung lacinya selalu terlihat kurang setor, dan tidak ada cara menelusurinya.
 *
 * Yang dijaga di sini ADA TIGA, dan ketiganya harus benar bersamaan:
 *   1. DP pesanan batal keluar dari Kas.
 *   2. `paid_amount`-nya TETAP TERSIMPAN - uang itu sungguh pernah diterima.
 *   3. Neraca tetap seimbang (H7). Sisi kewajibannya ikut dikeluarkan; kalau tidak,
 *      KEWAJIBAN jadi lebih besar dari ASET.
 */
class KasPesananBatalTest extends JposTestCase
{
    private function pesananDenganDp(float $harga, float $dp): Sale
    {
        $p = $this->makeProduct(['sell_price' => $harga, 'cost_price' => $harga / 2, 'stock' => 50]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => 1]],
            'paid_amount' => $dp,
            'payment_method' => 'tunai',
            'is_waiting_list' => true,
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    /** PALING MENENTUKAN: DP yang sudah dikembalikan tidak lagi dihitung ada di laci. */
    public function test_dp_pesanan_batal_keluar_dari_kas(): void
    {
        $sale = $this->pesananDenganDp(500000, 200000);

        $hariIni = now()->toDateString();
        $kasSebelum = Akuntansi::posisiPada($hariIni)->kas;

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel");
        $this->assertSame('cancelled', $sale->fresh()->order_status);

        $kasSesudah = Akuntansi::posisiPada($hariIni)->kas;

        $this->assertEqualsWithDelta($kasSebelum - 200000, $kasSesudah, 0.01,
            'DP pesanan yang dibatalkan masih terkunci di Kas - uang hantu.');
    }

    /**
     * Datanya TIDAK dihapus. Uang itu sungguh pernah diterima, dan struk pesanan batal
     * harus tetap bisa menunjukkannya.
     *
     * Ini yang membedakan perbaikan di PEMBACA dari perbaikan dengan menolkan kolom:
     * menolkan menghapus fakta, dan hanya berlaku untuk pesanan yang dibatalkan SESUDAH
     * tambalannya dipasang - pesanan batal yang sudah ada di database client tetap salah.
     */
    public function test_riwayat_dp_tidak_dihapus_saat_pembatalan(): void
    {
        $sale = $this->pesananDenganDp(500000, 200000);

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel");

        $sale = $sale->fresh();

        $this->assertEqualsWithDelta(200000, (float) $sale->paid_amount, 0.01,
            'paid_amount dinolkan - jejak bahwa uangnya pernah diterima hilang.');
        $this->assertCount(1, $sale->payments,
            'Baris penerimaan uangnya ikut hilang.');
    }

    /**
     * H7 — neraca WAJIB tetap seimbang sesudah pembatalan.
     *
     * DP batal dulu muncul di kedua sisi: Kas (aset) dan Uang Muka Pelanggan (kewajiban).
     * Mengeluarkannya dari sisi aset saja membuat KEWAJIBAN lebih besar dari ASET. Test ini
     * yang menahan perbaikan setengah jadi.
     */
    public function test_neraca_tetap_seimbang_sesudah_pembatalan(): void
    {
        $sale = $this->pesananDenganDp(500000, 200000);

        // Diukur PERGESERANNYA, bukan nilai mutlaknya: database test yang bersih memang
        // belum seimbang (ada persediaan tanpa modal awal), dan itu bukan urusan test ini.
        // Yang wajib benar: pembatalan tidak boleh MENGGESER keseimbangan sedikit pun.
        $sebelum = (float) Akuntansi::posisiPada(now()->toDateString())->selisih;

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel");

        $sesudah = (float) Akuntansi::posisiPada(now()->toDateString())->selisih;

        $this->assertEqualsWithDelta($sebelum, $sesudah, 0.01,
            'Pembatalan menggeser keseimbangan neraca - H7 dilanggar. DP-nya keluar dari '
            . 'satu sisi saja (Kas) tanpa ikut keluar dari Uang Muka Pelanggan.');
    }

    /** Pesanan yang MASIH menunggu tetap dihitung - hanya yang batal yang dikeluarkan. */
    public function test_pesanan_yang_masih_menunggu_tetap_masuk_kas(): void
    {
        $kasAwal = Akuntansi::posisiPada(now()->toDateString())->kas;

        $this->pesananDenganDp(500000, 200000);

        $this->assertEqualsWithDelta(
            $kasAwal + 200000,
            Akuntansi::posisiPada(now()->toDateString())->kas,
            0.01,
            'DP pesanan yang masih aktif ikut terbuang - perbaikannya terlalu luas.'
        );
    }

    /**
     * STALE READ: stok yang terjual saat form produk terbuka tidak boleh lenyap.
     *
     * `ProductController::update` dulu membaca $oldStock DI LUAR transaksi. Kalau ada
     * penjualan di sela-sela itu, selisihnya dihitung terhadap angka basi sehingga
     * pengurangan stoknya tertimpa tanpa meninggalkan StockMovement (melanggar H2).
     */
    public function test_stok_dibaca_ulang_di_dalam_transaksi(): void
    {
        $p = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        // Penjualan menyelinap: stok jadi 95, terjadi setelah form dibuka.
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => 5]],
            'paid_amount' => 50000,
            'payment_method' => 'tunai',
        ])->assertOk();

        $this->assertEqualsWithDelta(95, (float) $p->fresh()->stock, 0.001);

        // Form dikirim dengan stok yang dilihat SEBELUM penjualan itu (100).
        $this->actingAs($this->admin)->put('/master/produk/' . $p->id, [
            'name' => $p->name, 'type' => 'barang', 'unit' => $p->unit,
            'cost_price' => $p->cost_price, 'sell_price' => $p->sell_price,
            'stock' => 100, 'min_stock' => 5, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $p = $p->fresh();

        $this->assertEqualsWithDelta(100, (float) $p->stock, 0.001);

        // H2: penyesuaian dari 95 ke 100 WAJIB meninggalkan jejak sebesar +5.
        $jejak = $p->stockMovements()->where('type', 'adjustment')->latest('id')->first();

        $this->assertNotNull($jejak, 'Penyesuaian stok tidak meninggalkan StockMovement (H2).');
        $this->assertEqualsWithDelta(5, (float) $jejak->qty, 0.001,
            'Selisih dihitung terhadap stok basi - penjualan di sela-sela form terhapus diam-diam.');
    }
}
