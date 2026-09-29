<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use Tests\JposTestCase;

class LaporanTransaksiShiftTest extends JposTestCase
{
    public function test_tab_transaksi_per_shift_muncul_di_halaman_laporan(): void
    {
        $response = $this->actingAs($this->admin)->get('/laporan/penjualan');
        $response->assertOk();
        $response->assertSee('Transaksi per Shift');
    }

    public function test_halaman_laporan_shift_dapat_diakses_oleh_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/laporan/shift');
        $response->assertOk();
        $response->assertSee('Laporan Transaksi per Shift');
        $response->assertSee('Total Shift');
        $response->assertSee('Total Penjualan Shift');
    }

    public function test_laporan_shift_menampilkan_shift_dan_transaksi_terkait(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'expected_cash' => 150000,
            'cash_sales' => 50000,
            'non_cash_sales' => 0,
            'actual_cash' => 150000,
            'difference' => 0,
            'status' => 'closed',
            'closed_at' => now()->addHours(4),
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-SHIFT-001',
            'user_id' => $this->kasir->id,
            'cashier_shift_id' => $shift->id,
            'subtotal' => 50000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 50000,
            'paid_amount' => 50000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get('/laporan/shift');
        $response->assertOk();
        $response->assertSee("Shift #{$shift->id}");
        $response->assertSee('INV-SHIFT-001');
        $response->assertSee('Rp 50.000');
    }

    public function test_transaksi_batal_tidak_muncul_dan_tidak_dihitung_pada_laporan_shift(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        // Transaksi aktif
        Sale::create([
            'invoice_no' => 'INV-AKTIF-999',
            'user_id' => $this->kasir->id,
            'cashier_shift_id' => $shift->id,
            'subtotal' => 75000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 75000,
            'paid_amount' => 75000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        // Transaksi batal
        Sale::create([
            'invoice_no' => 'INV-BATAL-999',
            'user_id' => $this->kasir->id,
            'cashier_shift_id' => $shift->id,
            'subtotal' => 300000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 300000,
            'paid_amount' => 300000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->admin)->get('/laporan/shift');
        $response->assertOk();

        $content = $response->getContent();

        // Invoice aktif harus muncul
        $this->assertStringContainsString('INV-AKTIF-999', $content);
        // Invoice batal TIDAK boleh muncul
        $this->assertStringNotContainsString('INV-BATAL-999', $content);

        // Total penjualan harus Rp 75.000, bukan Rp 375.000
        $this->assertStringContainsString('Rp 75.000', $content);
        $this->assertStringNotContainsString('Rp 375.000', $content);
        $this->assertStringNotContainsString('Rp 300.000', $content);
    }

    public function test_filter_laporan_shift_berdasarkan_shift_id(): void
    {
        $shift1 = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        $shift2 = CashierShift::create([
            'user_id' => $this->admin->id,
            'opened_at' => now(),
            'starting_cash' => 200000,
            'status' => 'open',
        ]);

        $res1 = $this->actingAs($this->admin)->get("/laporan/shift?shift_id={$shift1->id}");
        $res1->assertOk();
        $res1->assertSee("Shift #{$shift1->id}");
        $res1->assertDontSee("Shift #{$shift2->id}");
    }

    public function test_ekspor_laporan_shift_ke_pdf_dan_excel(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'cash_sales' => 25000,
            'non_cash_sales' => 0,
            'expected_cash' => 125000,
            'actual_cash' => 125000,
            'difference' => 0,
            'status' => 'closed',
            'closed_at' => now()->addHours(2),
        ]);

        Sale::create([
            'invoice_no' => 'INV-EKSPOR-001',
            'user_id' => $this->kasir->id,
            'cashier_shift_id' => $shift->id,
            'subtotal' => 25000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 25000,
            'paid_amount' => 25000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        // Unduh PDF
        $resPdf = $this->actingAs($this->admin)->get('/laporan/ekspor/shift/pdf');
        $resPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $resPdf->streamedContent());

        // Unduh Excel
        $resXlsx = $this->actingAs($this->admin)->get('/laporan/ekspor/shift/xlsx');
        $resXlsx->assertOk();
        $this->assertStringStartsWith('PK', $resXlsx->streamedContent());
    }

    public function test_tombol_transaksi_tersedia_di_tabel_shift_kasir(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->get('/shift');
        $response->assertOk();
        $response->assertSee('Transaksi');
        $response->assertSee("laporan/shift?shift_id={$shift->id}");
    }

    public function test_tabel_rekapitulasi_shift_tampil_di_layar(): void
    {
        CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 150000,
            'cash_sales' => 80000,
            'non_cash_sales' => 20000,
            'actual_cash' => 230000,
            'difference' => 0,
            'status' => 'closed',
            'closed_at' => now()->addHours(5),
        ]);

        $response = $this->actingAs($this->admin)->get('/laporan/shift');
        $response->assertOk();
        $response->assertSee('Tabel Rekapitulasi Shift Kasir');
        $response->assertSee('TOTAL SELURUH SHIFT');
        $response->assertSee('Rekap Penerimaan Metode Bayar');
        $response->assertSee('Rekap Aliran Kas &amp; Rekonsiliasi Laci', false);
    }

    public function test_rincian_item_produk_terjual_tampil_pada_transaksi_shift(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        $product = $this->makeProduct(['name' => 'Kopi Robusta Super', 'sell_price' => 25000]);

        $sale = Sale::create([
            'invoice_no' => 'INV-ITEM-001',
            'user_id' => $this->kasir->id,
            'cashier_shift_id' => $shift->id,
            'subtotal' => 50000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 50000,
            'paid_amount' => 50000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'status' => 'completed',
            'order_status' => 'completed',
        ]);

        \App\Models\SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => 'Kopi Robusta Super',
            'price' => 25000,
            'qty' => 2,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'subtotal' => 50000,
        ]);

        $response = $this->actingAs($this->admin)->get('/laporan/shift');
        $response->assertOk();
        $response->assertSee('Kopi Robusta Super');
        $response->assertSee('Buka Semua Item Barang');
        $response->assertSee('Tutup Semua Item');
    }
}
