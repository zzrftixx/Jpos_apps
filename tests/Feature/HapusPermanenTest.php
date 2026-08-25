<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Tests\JposTestCase;

/**
 * Hapus permanen pesanan/transaksi.
 *
 * Berbeda dari "Batalkan" yang hanya mengubah status: baris Sale benar-benar dibuang dari
 * database. Dipakai untuk membersihkan catatan salah input yang tidak boleh ikut muncul di
 * laporan penjualan.
 *
 * Dua hal yang dijaga di sini: stok tidak boleh hilang diam-diam saat catatan dibuang, dan
 * stok tidak boleh dikembalikan dua kali kalau tombolnya terklik dua kali.
 */
class HapusPermanenTest extends JposTestCase
{
    private function buatPesanan(float $qty = 5, bool $lunas = false): Sale
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => $qty]],
            'paid_amount' => $lunas ? 999999 : 10000,
            'payment_method' => 'cash',
            'is_waiting_list' => ! $lunas,
        ])->assertOk();

        return Sale::firstOrFail();
    }

    public function test_menghapus_pesanan_mengembalikan_stok_yang_belum_diambil(): void
    {
        $sale = $this->buatPesanan(5);
        $produk = Product::firstOrFail();

        $this->assertSame(95.0, (float) $produk->fresh()->stock, 'Prasyarat: 5 direservasi.');

        $this->actingAs($this->admin)
            ->delete("/kasir/waiting-list/{$sale->id}")
            ->assertRedirect();

        $this->assertSame(100.0, (float) $produk->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count(), 'Baris item harus ikut terhapus lewat cascade.');
    }

    /**
     * Server bawaan melayani satu permintaan pada satu waktu, jadi ini bukan uji konkurensi
     * sungguhan - yang diuji adalah pemeriksaan ulang di dalam transaksi benar-benar ada,
     * sehingga klik kedua tidak menemukan barisnya lagi dan tidak menambah stok.
     */
    public function test_menghapus_dua_kali_tidak_mengembalikan_stok_dua_kali(): void
    {
        $sale = $this->buatPesanan(5);
        $produk = Product::firstOrFail();

        $this->actingAs($this->admin)->delete("/kasir/waiting-list/{$sale->id}");
        $stokSetelahHapusPertama = (float) $produk->fresh()->stock;

        $this->actingAs($this->admin)->delete("/kasir/waiting-list/{$sale->id}");

        $this->assertSame(100.0, $stokSetelahHapusPertama);
        $this->assertSame(100.0, (float) $produk->fresh()->stock, 'Stok tidak boleh dikembalikan dua kali.');
        $this->assertSame(
            1,
            StockMovement::where('note', 'like', 'Hapus %')->count(),
            'Hanya boleh ada satu gerakan stok untuk penghapusan ini.'
        );
    }

    /** Pesanan yang sudah dibatalkan stoknya sudah kembali - jangan ditambah lagi. */
    public function test_menghapus_pesanan_yang_sudah_dibatalkan_tidak_menambah_stok(): void
    {
        $sale = $this->buatPesanan(5);
        $produk = Product::firstOrFail();

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel")->assertRedirect();
        $this->assertSame(100.0, (float) $produk->fresh()->stock);

        $this->actingAs($this->admin)->delete("/kasir/waiting-list/{$sale->id}")->assertRedirect();

        $this->assertSame(100.0, (float) $produk->fresh()->stock, 'Stok sudah kembali saat dibatalkan.');
        $this->assertSame(0, Sale::count());
    }

    /** Bagian yang sudah diretur tidak boleh dikembalikan lagi saat transaksinya dihapus. */
    public function test_hanya_sisa_yang_belum_diretur_yang_dikembalikan(): void
    {
        $sale = $this->buatPesanan(5, lunas: true);
        $produk = Product::firstOrFail();
        $item = $sale->items()->firstOrFail();

        $this->assertSame(95.0, (float) $produk->fresh()->stock);

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'items' => [['sale_item_id' => $item->id, 'qty' => 2]],
            'reason' => 'Rusak',
        ])->assertOk();

        $this->assertSame(97.0, (float) $produk->fresh()->stock, '2 dari 5 sudah diretur.');

        $this->actingAs($this->admin)->delete("/kasir/waiting-list/{$sale->id}")->assertRedirect();

        $this->assertSame(100.0, (float) $produk->fresh()->stock, 'Sisa 3 dikembalikan, bukan 5.');
    }

    public function test_qty_pecahan_dikembalikan_utuh_saat_dihapus(): void
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

        $this->actingAs($this->admin)->delete('/kasir/waiting-list/' . Sale::firstOrFail()->id)->assertRedirect();

        $this->assertSame(10.0, (float) $produk->fresh()->stock);
    }

    /**
     * Transaksi lunas sudah masuk Laporan Penjualan. Menghapusnya mengubah angka omzet
     * secara permanen - itu keputusan pemilik toko, bukan kasir yang sedang melayani antrean.
     */
    public function test_kasir_tidak_bisa_menghapus_transaksi_yang_sudah_lunas(): void
    {
        $sale = $this->buatPesanan(5, lunas: true);

        $this->actingAs($this->kasir)
            ->delete("/kasir/waiting-list/{$sale->id}")
            ->assertRedirect();

        $this->assertSame(1, Sale::count(), 'Transaksi lunas tidak boleh terhapus oleh kasir.');
    }

    public function test_kasir_tetap_bisa_menghapus_pesanan_yang_belum_lunas(): void
    {
        $sale = $this->buatPesanan(5);

        $this->actingAs($this->kasir)
            ->delete("/kasir/waiting-list/{$sale->id}")
            ->assertRedirect();

        $this->assertSame(0, Sale::count());
    }

    public function test_pengguna_berakses_laporan_bisa_menghapus_transaksi_lunas(): void
    {
        $sale = $this->buatPesanan(5, lunas: true);

        $this->actingAs($this->admin)
            ->delete("/kasir/waiting-list/{$sale->id}")
            ->assertRedirect();

        $this->assertSame(0, Sale::count());
    }

    public function test_tombol_hapus_muncul_dengan_peringatan_sesuai_status(): void
    {
        $this->buatPesanan(5, lunas: true);

        $this->actingAs($this->admin)->get('/kasir/waiting-list?status=completed')
            ->assertOk()
            ->assertSee('HILANG DARI LAPORAN PENJUALAN', false);
    }
}
