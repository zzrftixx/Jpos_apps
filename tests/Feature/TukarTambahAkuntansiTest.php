<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Support\Akuntansi;
use Tests\JposTestCase;

class TukarTambahAkuntansiTest extends JposTestCase
{
    private function mulaiPembukuan(float $modalAwal = 1000000, float $saldoKas = 1000000): void
    {
        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->toDateString(),
            'saldo_awal_kas' => $saldoKas,
            'modal_awal' => $modalAwal,
        ]);
    }

    public function test_tukar_tambah_neraca_dan_kas_laci(): void
    {
        // Produk A: harga modal 5000, jual 10000, stok 20 (nilai 100.000)
        $produkA = $this->makeProduct(['stock' => 20, 'cost_price' => 5000, 'sell_price' => 10000]);
        // Produk B: harga modal 8000, jual 15000, stok 20 (nilai 160.000)
        $produkB = $this->makeProduct(['stock' => 20, 'cost_price' => 8000, 'sell_price' => 15000]);

        // Modal awal = kas (1.000.000) + stok di rak (260.000) = 1.260.000
        $this->mulaiPembukuan(1260000, 1000000);

        // 1. Penjualan awal: 1 pcs Produk A tunai (10.000)
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produkA->id, 'qty' => 1]],
            'paid_amount' => 10000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::firstOrFail();
        $saleItem = $sale->items()->firstOrFail();

        // Kas awal: 1.000.000 + 10.000 = 1.010.000
        $posisi1 = Akuntansi::posisiPada(now()->toDateString());
        $this->assertSame(1010000.0, $posisi1->kas);
        $this->assertSame(0.0, $posisi1->selisih, 'Neraca awal harus seimbang');

        // 2. Tukar tambah: retur 1 pcs Produk A (10.000), tambah 1 pcs Produk B (15.000)
        // Pembeli bayar tambahan 15.000 (sesuai kontrak ReturnController saat ini)
        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'reason' => 'Tukar tambah',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'qty' => 1],
            ],
            'add_items' => [
                ['product_id' => $produkB->id, 'qty' => 1],
            ],
            'additional_payment' => 15000,
            'additional_payment_method' => 'tunai',
        ])->assertOk();

        // Posisi kas dan neraca setelah tukar tambah
        $posisi2 = Akuntansi::posisiPada(now()->toDateString());

        // Mari periksa komponen neraca:
        // ASET = Kas + Piutang + Persediaan + Aset Tetap
        // MODAL = Modal Awal + Tambahan Modal - Prive + Laba Ditahan
        $this->assertSame(0.0, $posisi2->selisih, 'Neraca setelah tukar tambah wajib seimbang (ASET = KEWAJIBAN + MODAL)');
        
        // Sekarang periksa Kas fisik laci:
        // Pembeli awalnya bayar 10.000, lalu bayar tambahan 15.000.
        // Jika kasir mengembalikan 10.000 uang tunai untuk item A, uang di laci bertambah bersih 15.000 (1.000.000 + 15.000 = 1.015.000).
        $this->assertSame(1015000.0, $posisi2->kas, 'Kas laci harus mencerminkan saldo fisik');
    }
}
