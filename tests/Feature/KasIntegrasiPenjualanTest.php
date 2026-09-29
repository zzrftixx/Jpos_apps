<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Support\Akuntansi;
use Tests\JposTestCase;

class KasIntegrasiPenjualanTest extends JposTestCase
{
    private function setupStore(): Product
    {
        Setting::set('store_profile', [
            'name' => 'Toko Jayla Makmur',
            'address' => 'Jl. Merdeka No. 17',
            'phone' => '0812-3456-7890',
        ]);

        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->startOfMonth()->toDateString(),
            'saldo_awal_kas' => 1000000,
            'modal_awal' => 1000000,
        ]);

        return $this->makeProduct([
            'name' => 'Kopi Robusta 250g',
            'stock' => 50,
            'cost_price' => 15000,
            'sell_price' => 25000,
        ]);
    }

    public function test_penjualan_kasir_otomatis_muncul_sebagai_kas_masuk_di_menu_kas(): void
    {
        $produk = $this->setupStore();

        // 1. Transaksi Kasir POS senilai 50.000 (2 kopi @ 25.000)
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 50000,
            'payment_method' => 'cash',
        ])->assertOk();

        // 2. Transaksi Kas Masuk Manual (misal pendapatan parkir 10.000)
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in',
            'category' => 'lain_lain',
            'amount' => 10000,
            'note' => 'Pendapatan parkir',
        ])->assertSessionHasNoErrors();

        // 3. Transaksi Kas Keluar Manual (misal beli sapu 15.000)
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out',
            'category' => 'operasional',
            'amount' => 15000,
            'note' => 'Beli sapu dan pel',
        ])->assertSessionHasNoErrors();

        // Buka halaman /kas
        $response = $this->actingAs($this->admin)->get('/kas');
        $response->assertOk();

        // Verifikasi view data
        $summary = $response->viewData('summary');
        $this->assertEquals(50000.0, (float) $summary->penjualan, 'Kas dari penjualan kasir harus 50.000');
        $this->assertEquals(10000.0, (float) $summary->manual_in, 'Kas masuk manual harus 10.000');
        $this->assertEquals(60000.0, (float) $summary->total_in, 'Total kas masuk harus 60.000 (penjualan + manual)');
        $this->assertEquals(15000.0, (float) $summary->total_out, 'Total kas keluar harus 15.000');
        $this->assertEquals(45000.0, (float) $summary->saldo_periode, 'Saldo periode harus 45.000 (60.000 - 15.000)');

        // Verifikasi teks pada HTML halaman kas
        $response->assertSee('Total Kas Masuk');
        $response->assertSee('Penjualan Kasir');
        $response->assertSee('60.000'); // total kas masuk
        $response->assertSee('50.000'); // penjualan kasir
        $response->assertSee('10.000'); // kas masuk manual
        $response->assertSee('15.000'); // total kas keluar
        $response->assertSee('Kasir POS');
        $response->assertSee('Pendapatan parkir');
        $response->assertSee('Beli sapu dan pel');
    }

    public function test_penyaring_tipe_mutasi_pada_halaman_kas(): void
    {
        $produk = $this->setupStore();

        // Penjualan kasir
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 1]],
            'paid_amount' => 25000,
            'payment_method' => 'cash',
        ])->assertOk();

        // Kas masuk manual
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in',
            'category' => 'modal_tambahan',
            'amount' => 500000,
            'note' => 'Setor modal tambahan',
        ])->assertSessionHasNoErrors();

        // Kas keluar manual
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out',
            'category' => 'gaji',
            'amount' => 100000,
            'note' => 'Gaji paruh waktu',
        ])->assertSessionHasNoErrors();

        // Filter: sale (hanya penjualan)
        $resSale = $this->actingAs($this->admin)->get('/kas?type=sale');
        $resSale->assertOk();
        $resSale->assertSee('Kasir POS');
        $resSale->assertDontSee('Setor modal tambahan');
        $resSale->assertDontSee('Gaji paruh waktu');

        // Filter: manual_in (hanya kas masuk manual)
        $resManualIn = $this->actingAs($this->admin)->get('/kas?type=manual_in');
        $resManualIn->assertOk();
        $resManualIn->assertSee('Setor modal tambahan');
        $resManualIn->assertDontSee('Kasir POS');
        $resManualIn->assertDontSee('Gaji paruh waktu');

        // Filter: out (semua kas keluar)
        $resOut = $this->actingAs($this->admin)->get('/kas?type=out');
        $resOut->assertOk();
        $resOut->assertSee('Gaji paruh waktu');
        $resOut->assertDontSee('Setor modal tambahan');
    }

    public function test_ekspor_laporan_kas_memuat_mutasi_penjualan(): void
    {
        $produk = $this->setupStore();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 1]],
            'paid_amount' => 25000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in',
            'category' => 'lain_lain',
            'amount' => 30000,
            'note' => 'Uang tip pelanggan',
        ])->assertSessionHasNoErrors();

        $responsePdf = $this->actingAs($this->admin)->get('/laporan/ekspor/kas/pdf');
        $responsePdf->assertOk();
        $this->assertStringStartsWith('%PDF', $responsePdf->streamedContent());

        $responseExcel = $this->actingAs($this->admin)->get('/laporan/ekspor/kas/xlsx');
        $responseExcel->assertOk();
        $this->assertGreaterThan(1000, strlen($responseExcel->streamedContent()));
    }
}
