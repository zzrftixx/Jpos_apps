<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Tests\JposTestCase;

class LaporanMutasiStokTest extends JposTestCase
{
    public function test_halaman_mutasi_stok_dapat_diakses(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok'));
        $response->assertOk();
        $response->assertSee('Rekap Mutasi Stok');
    }

    public function test_akumulasi_barang_masuk_dan_keluar_terhitung_akurat(): void
    {
        $product = $this->makeProduct([
            'name' => 'Royal Canin Maxi Adult 15kg',
            'stock' => 0,
            'cost_price' => 700000,
            'sell_price' => 850000,
            'unit' => 'Karung',
        ]);

        // 1. Pembelian (Kulakan Masuk) 20 karung
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'qty' => 20, 'unit_type' => 'base', 'price' => 700000]
            ],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        // 2. Penjualan Selesai (Kasir Keluar) 5 karung
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 5, 'unit_type' => 'base']
            ],
            'paid_amount' => 4250000,
            'payment_method' => 'cash',
        ])->assertOk();

        // 3. Akses laporan mutasi stok
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok'));
        $response->assertOk();
        $response->assertSee('Royal Canin Maxi Adult 15kg');
        $response->assertSee('20'); // Total Masuk
        $response->assertSee('5');  // Total Keluar
    }

    public function test_pesanan_belum_selesai_dan_batal_tidak_dihitung_sebagai_barang_keluar(): void
    {
        $product = $this->makeProduct([
            'name' => 'Bolt Tuna 20kg',
            'stock' => 50,
            'cost_price' => 350000,
            'sell_price' => 420000,
            'unit' => 'Sak',
        ]);

        // Pesanan Waiting List (DP/tertahan) - HUKUM 5: belum diserahkan, bukan omset & bukan mutasi keluar selesai
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 3, 'unit_type' => 'base']
            ],
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
            'mode_pesanan' => 'dp',
            'customer_name' => 'Pelanggan DP',
        ])->assertOk();

        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok', ['q' => 'Bolt Tuna']));
        $response->assertOk();
        $response->assertSee('Bolt Tuna 20kg');
        // Total keluar harus tetap 0 karena pesanan belum selesai
        $content = $response->getContent();
        $this->assertStringContainsString('Bolt Tuna 20kg', $content);
    }
}
