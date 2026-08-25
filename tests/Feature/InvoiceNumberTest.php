<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\JposTestCase;

/**
 * Regresi T6: nomor invoice rusak begitu transaksi harian menembus 9999.
 *
 * Versi lama membaca 4 karakter terakhir nomor invoice. Setelah nomor ke-10000
 * menjadi INV<tgl>10000 (5 digit), pembacaan berikutnya mengambil "0000" sehingga
 * urutan kembali ke 1 dan menabrak kolom invoice_no yang unique - kasir mendapat
 * HTTP 500 tepat saat menekan tombol bayar.
 */
class InvoiceNumberTest extends JposTestCase
{
    private function prefix(): string
    {
        return 'INV' . now()->format('ymd');
    }

    private function seedSale(string $invoiceNo): Sale
    {
        return Sale::create([
            'invoice_no' => $invoiceNo,
            'user_id' => $this->admin->id,
            'subtotal' => 1000,
            'total' => 1000,
            'paid_amount' => 1000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);
    }

    public function test_urutan_normal_dari_awal_hari(): void
    {
        $this->assertSame($this->prefix() . '0001', Sale::generateInvoiceNo());

        $this->seedSale($this->prefix() . '0001');
        $this->assertSame($this->prefix() . '0002', Sale::generateInvoiceNo());

        $this->seedSale($this->prefix() . '0002');
        $this->assertSame($this->prefix() . '0003', Sale::generateInvoiceNo());
    }

    public function test_transaksi_ke_10000_tidak_mengulang_nomor(): void
    {
        $this->seedSale($this->prefix() . '9999');

        $ke10000 = Sale::generateInvoiceNo();
        $this->assertSame($this->prefix() . '10000', $ke10000);

        $this->seedSale($ke10000);

        // Inilah titik rusaknya versi lama: substr(-4) membaca "0000" lalu menghasilkan
        // 0001, yang sudah dipakai transaksi pertama hari itu.
        $ke10001 = Sale::generateInvoiceNo();
        $this->assertSame($this->prefix() . '10001', $ke10001);
        $this->assertNotSame($this->prefix() . '0001', $ke10001);
    }

    public function test_nomor_tetap_unik_melewati_batas_lima_digit(): void
    {
        $this->seedSale($this->prefix() . '0001');
        $this->seedSale($this->prefix() . '9999');

        $terpakai = [$this->prefix() . '0001', $this->prefix() . '9999'];

        for ($i = 0; $i < 25; $i++) {
            $nomor = Sale::generateInvoiceNo();
            $this->assertNotContains($nomor, $terpakai, "Nomor {$nomor} sudah dipakai sebelumnya.");
            $terpakai[] = $nomor;
            $this->seedSale($nomor);
        }

        $this->assertCount(count(array_unique($terpakai)), $terpakai);
    }

    public function test_nomor_hari_lain_tidak_mempengaruhi_urutan_hari_ini(): void
    {
        $kemarin = 'INV' . now()->subDay()->format('ymd');
        $this->seedSale($kemarin . '8888');

        $this->assertSame($this->prefix() . '0001', Sale::generateInvoiceNo());
    }

    public function test_checkout_beruntun_menghasilkan_nomor_berbeda(): void
    {
        $produk = $this->makeProduct(['stock' => 50, 'sell_price' => 1000]);

        $nomor = [];
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin)->postJson('/kasir', [
                'items' => [['product_id' => $produk->id, 'qty' => 1]],
                'paid_amount' => 1000,
                'payment_method' => 'cash',
            ])->assertOk();
        }

        $nomor = Sale::pluck('invoice_no')->all();
        $this->assertCount(5, $nomor);
        $this->assertCount(5, array_unique($nomor));
    }
}
