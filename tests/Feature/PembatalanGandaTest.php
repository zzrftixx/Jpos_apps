<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\StockMovement;
use Tests\JposTestCase;

/**
 * Menjaga agar pembatalan/pelunasan yang terpanggil dua kali tidak menggandakan efeknya.
 *
 * Pemeriksaan status dulu berada di LUAR transaksi dan tanpa lock, sehingga dua permintaan
 * yang hampir bersamaan (mis. kasir menekan tombol dua kali) bisa sama-sama lolos dan
 * mengembalikan stok atau menambah pembayaran dua kali. Sekarang status dikunci dan
 * diperiksa ulang di dalam transaksi; permintaan kedua tidak melakukan apa-apa.
 *
 * Test ini memakai SQLite in-memory yang tidak benar-benar paralel, jadi ia menguji
 * perilaku IDEMPOTEN: memanggil aksi dua kali berturut-turut harus sama hasilnya dengan
 * sekali. Itulah jaring yang sama yang melindungi dari klik ganda.
 */
class PembatalanGandaTest extends JposTestCase
{
    private function buatPesananWaiting(int $qty = 5): Sale
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => $qty]],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        return Sale::where('order_status', 'waiting')->latest('id')->firstOrFail();
    }

    public function test_batal_pesanan_dua_kali_hanya_mengembalikan_stok_sekali(): void
    {
        $sale = $this->buatPesananWaiting(5);
        $produk = $sale->items->first()->product;

        $this->assertSame(95.0, (float) $produk->fresh()->stock, 'Prasyarat: 100 - 5 direservasi.');

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel")->assertRedirect();
        $stokSetelahBatalPertama = $produk->fresh()->stock;

        // Panggilan kedua (klik ganda) tidak boleh menambah stok lagi.
        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel");

        $this->assertSame(100.0, (float) $stokSetelahBatalPertama, 'Batal pertama mengembalikan 5.');
        $this->assertSame(100.0, (float) $produk->fresh()->stock, 'Batal kedua TIDAK boleh menambah stok lagi.');

        $this->assertSame('cancelled', $sale->fresh()->order_status);

        // Hanya satu gerakan stok "return" untuk pesanan ini.
        $gerakan = StockMovement::where('note', 'like', 'Pembatalan pesanan%')->count();
        $this->assertSame(1, $gerakan, 'Pembatalan hanya boleh mencatat satu gerakan stok.');
    }

    public function test_batal_transaksi_lunas_dua_kali_hanya_mengembalikan_stok_sekali(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 5000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 8]],
            'paid_amount' => 40000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertSame(92.0, (float) $produk->fresh()->stock);

        $this->actingAs($this->admin)->post("/retur/{$sale->id}/cancel")->assertRedirect();
        $this->actingAs($this->admin)->post("/retur/{$sale->id}/cancel");

        $this->assertSame(100.0, (float) $produk->fresh()->stock, 'Stok tidak boleh dikembalikan dua kali.');
        $this->assertSame('cancelled', $sale->fresh()->order_status);
    }

    public function test_lunasi_pesanan_dua_kali_tidak_menggandakan_pembayaran(): void
    {
        $sale = $this->buatPesananWaiting(5);   // total 50.000, sudah bayar 20.000
        $this->assertSame(50000.0, (float) $sale->total);
        $this->assertSame(20000.0, (float) $sale->paid_amount);

        // Pelunasan 30.000 membuat pesanan lunas; klik kedua tidak boleh menambah lagi.
        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/pay", ['amount' => 30000])->assertRedirect();
        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/pay", ['amount' => 30000]);

        $sesudah = $sale->fresh();
        $this->assertSame('completed', $sesudah->order_status);
        $this->assertSame(50000.0, (float) $sesudah->paid_amount, 'Pembayaran kedua ditolak karena pesanan sudah lunas.');
    }
}
