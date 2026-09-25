<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use Tests\JposTestCase;

class CashierShiftTest extends JposTestCase
{
    public function test_kasir_dapat_membuka_shift_dengan_modal_awal(): void
    {
        $response = $this->actingAs($this->kasir)->post('/shift', [
            'starting_cash' => 150000,
            'notes' => 'Shift Pagi',
        ]);

        $response->assertSessionHasNoErrors();

        $shift = CashierShift::getActiveShift($this->kasir->id);
        $this->assertNotNull($shift);
        $this->assertSame(150000.0, (float) $shift->starting_cash);
        $this->assertSame('open', $shift->status);
        $this->assertSame('Shift Pagi', $shift->notes);
    }

    public function test_tidak_bisa_membuka_shift_ganda_jika_masih_ada_shift_aktif(): void
    {
        CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->kasir)->postJson('/shift', [
            'starting_cash' => 200000,
        ]);

        $response->assertStatus(422);
    }

    public function test_penjualan_kasir_otomatis_terkait_ke_shift_aktif(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 10000]);

        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_type' => 'base']],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
        ]);

        $response->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame($shift->id, $sale->cashier_shift_id);
    }

    public function test_kalkulasi_summary_shift_menghitung_tunai_dan_non_tunai(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 10000]);

        // 1. Penjualan tunai Rp 20.000
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_type' => 'base']],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
        ])->assertOk();

        // 2. Penjualan QRIS Rp 30.000
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 3, 'unit_type' => 'base']],
            'paid_amount' => 30000,
            'payment_method' => 'qris',
        ])->assertOk();

        $summary = $shift->calculateSummary();

        $this->assertSame(100000.0, $summary['starting_cash']);
        $this->assertSame(20000.0, $summary['cash_sales']);
        $this->assertSame(30000.0, $summary['non_cash_sales']);
        $this->assertSame(50000.0, $summary['total_sales']);
        // Expected cash = starting_cash (100k) + cash_sales (20k) = 120k
        $this->assertSame(120000.0, $summary['expected_cash']);
    }

    public function test_tutup_shift_mencatat_uang_fisik_dan_selisih(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 10000]);

        // Penjualan tunai 50.000
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 5, 'unit_type' => 'base']],
            'paid_amount' => 50000,
            'payment_method' => 'cash',
        ])->assertOk();

        // Expected cash = 100.000 + 50.000 = 150.000
        // Kasir hitung fisik = 148.000 (selisih kurang 2.000)
        $response = $this->actingAs($this->kasir)->postJson("/shift/{$shift->id}/close", [
            'actual_cash' => 148000,
            'notes' => 'Selisih 2000 untuk plastik belanja',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertNotNull($shift->closed_at);
        $this->assertSame(50000.0, (float) $shift->cash_sales);
        $this->assertSame(150000.0, (float) $shift->expected_cash);
        $this->assertSame(148000.0, (float) $shift->actual_cash);
        $this->assertSame(-2000.0, (float) $shift->difference);
    }

    public function test_cetak_slip_shift_dapat_diakses(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'closed_at' => now(),
            'starting_cash' => 100000,
            'cash_sales' => 50000,
            'expected_cash' => 150000,
            'actual_cash' => 150000,
            'difference' => 0,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($this->kasir)->get("/shift/{$shift->id}/print");
        $response->assertOk();
        $response->assertSee('REKAPITULASI SHIFT KASIR');
        $response->assertSee('100.000');
        $response->assertSee('150.000');
    }

    public function test_halaman_riwayat_shift_dapat_dibuka(): void
    {
        $response = $this->actingAs($this->admin)->get('/shift');
        $response->assertOk();
        $response->assertSee('Manajemen Shift Kasir');
    }

    public function test_menu_pengaturan_shift_kasir_dapat_diakses_dan_diubah(): void
    {
        // 1. Buka halaman pengaturan
        $response = $this->actingAs($this->admin)->get('/pengaturan/shift-kasir');
        $response->assertOk();
        $response->assertSee('Pengaturan Shift Kasir');

        // 2. Ubah status menjadi nonaktif
        $updateResponse = $this->actingAs($this->admin)->post('/pengaturan/shift-kasir', [
            'enabled' => '0',
            'require_shift_for_sales' => '0',
            'show_expected_cash_on_close' => '1',
        ]);
        $updateResponse->assertRedirect();
        $this->assertFalse(\App\Models\Setting::shiftKasirEnabled());

        // 3. Ubah kembali menjadi aktif
        $this->actingAs($this->admin)->post('/pengaturan/shift-kasir', [
            'enabled' => '1',
            'require_shift_for_sales' => '1',
            'show_expected_cash_on_close' => '1',
        ]);
        $this->assertTrue(\App\Models\Setting::shiftKasirEnabled());
        $this->assertTrue(\App\Models\Setting::shiftKasir()['require_shift_for_sales']);
    }

    public function test_buka_shift_ditolak_jika_fitur_shift_dinonaktifkan(): void
    {
        \App\Models\Setting::set('shift_kasir', ['enabled' => false]);

        $response = $this->actingAs($this->kasir)->postJson('/shift', [
            'starting_cash' => 100000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Fitur Shift Kasir sedang dinonaktifkan di menu Pengaturan.',
        ]);
    }

    public function test_transaksi_kasir_tanpa_shift_jika_fitur_shift_dinonaktifkan(): void
    {
        \App\Models\Setting::set('shift_kasir', ['enabled' => false]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 10000]);

        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 10000,
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertNull($sale->cashier_shift_id);
    }

    public function test_transaksi_dicegah_jika_wajib_buka_shift_diaktifkan_dan_belum_ada_shift(): void
    {
        \App\Models\Setting::set('shift_kasir', [
            'enabled' => true,
            'require_shift_for_sales' => true,
        ]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 10000]);

        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 10000,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
    }

    public function test_popup_buka_shift_memiliki_opsi_batal_dan_logout(): void
    {
        \App\Models\Setting::set('shift_kasir', ['enabled' => true]);

        $response = $this->actingAs($this->kasir)->get('/kasir');
        $response->assertOk();
        $response->assertSee('id="logout-form-pos"', false);
        $response->assertSee('batalBukaShift()');
        $response->assertSee('Batal / Logout');
    }

    public function test_pengaturan_kas_laci_dan_waktu_shift_dapat_disimpan(): void
    {
        $response = $this->actingAs($this->admin)->post('/pengaturan/shift-kasir', [
            'enabled' => '1',
            'require_shift_for_sales' => '1',
            'show_expected_cash_on_close' => '1',
            'starting_cash_mode' => 'fixed',
            'default_starting_cash' => '150000',
            'require_positive_starting_cash' => '1',
            'time_mode' => 'manual',
        ]);

        $response->assertRedirect();
        $settings = \App\Models\Setting::shiftKasir();
        $this->assertSame(150000.0, (float) $settings['default_starting_cash']);
        $this->assertSame('fixed', $settings['starting_cash_mode']);
        $this->assertTrue($settings['require_positive_starting_cash']);
        $this->assertSame('manual', $settings['time_mode']);
    }

    public function test_modal_kas_laci_mengikuti_sisa_kas_shift_terakhir(): void
    {
        CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay()->addHours(8),
            'starting_cash' => 100000,
            'expected_cash' => 350000,
            'actual_cash' => 350000,
            'difference' => 0,
            'status' => 'closed',
        ]);

        \App\Models\Setting::set('shift_kasir', [
            'enabled' => true,
            'starting_cash_mode' => 'last_closing',
        ]);

        $this->assertSame(350000.0, \App\Models\Setting::defaultStartingCash());
    }

    public function test_buka_shift_gagal_jika_wajib_modal_positif_dan_input_nol(): void
    {
        \App\Models\Setting::set('shift_kasir', [
            'enabled' => true,
            'require_positive_starting_cash' => true,
        ]);

        $response = $this->actingAs($this->kasir)->postJson('/shift', [
            'starting_cash' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_buka_dan_tutup_shift_dengan_waktu_manual(): void
    {
        \App\Models\Setting::set('shift_kasir', [
            'enabled' => true,
            'time_mode' => 'manual',
        ]);

        // 1. Buka shift dengan opened_at manual
        $responseOpen = $this->actingAs($this->kasir)->postJson('/shift', [
            'starting_cash' => 100000,
            'opened_at' => '2026-09-08 08:00:00',
            'notes' => 'Shift Manual Kemarin',
        ]);

        $responseOpen->assertOk();
        $shift = CashierShift::getActiveShift($this->kasir->id);
        $this->assertNotNull($shift);
        $this->assertSame('2026-09-08 08:00:00', $shift->opened_at->format('Y-m-d H:i:s'));

        // 2. Tutup shift dengan closed_at manual
        $responseClose = $this->actingAs($this->kasir)->postJson("/shift/{$shift->id}/close", [
            'actual_cash' => 100000,
            'closed_at' => '2026-09-08 17:00:00',
            'notes' => 'Tutup Jam 5 Sore',
        ]);

        $responseClose->assertOk();
        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertSame('2026-09-08 17:00:00', $shift->closed_at->format('Y-m-d H:i:s'));
    }

    public function test_pengaturan_mode_kas_laci_dapat_dinonaktifkan(): void
    {
        $response = $this->actingAs($this->admin)->post('/pengaturan/shift-kasir', [
            'enabled' => '1',
            'starting_cash_mode' => 'disabled',
            'time_mode' => 'auto',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse(\App\Models\Setting::cashDrawerEnabled());
        $this->assertSame(0.0, \App\Models\Setting::defaultStartingCash());
    }

    public function test_buka_shift_tanpa_modal_ketika_mode_kas_laci_dinonaktifkan(): void
    {
        \App\Models\Setting::set('shift_kasir', [
            'enabled' => true,
            'starting_cash_mode' => 'disabled',
        ]);

        $response = $this->actingAs($this->kasir)->postJson('/shift', [
            'notes' => 'Shift Tanpa Kas Laci',
        ]);

        $response->assertOk();
        $shift = CashierShift::getActiveShift($this->kasir->id);
        $this->assertNotNull($shift);
        $this->assertSame(0.0, (float) $shift->starting_cash);
        $this->assertSame('open', $shift->status);
    }

    public function test_tutup_shift_tanpa_uang_fisik_ketika_mode_kas_laci_dinonaktifkan(): void
    {
        \App\Models\Setting::set('shift_kasir', [
            'enabled' => true,
            'starting_cash_mode' => 'disabled',
        ]);

        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->kasir)->postJson("/shift/{$shift->id}/close", [
            'notes' => 'Tutup Tanpa Hitung Fisik Laci',
        ]);

        $response->assertOk();
        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertNull($shift->actual_cash);
        $this->assertNull($shift->difference);
    }

    public function test_laporan_shift_dapat_diakses_dan_menampilkan_data(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 50000,
            'expected_cash' => 50000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->get('/laporan/shift');
        $response->assertOk();
        $response->assertSee('Laporan Transaksi per Shift');
        $response->assertSee('#' . $shift->id);
    }

    public function test_ekspor_laporan_shift_pdf_dan_excel(): void
    {
        CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 50000,
            'expected_cash' => 50000,
            'status' => 'open',
        ]);

        $pdf = $this->actingAs($this->admin)->get('/laporan/ekspor/shift/pdf');
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');

        $xlsx = $this->actingAs($this->admin)->get('/laporan/ekspor/shift/xlsx');
        $xlsx->assertOk();
        $xlsx->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
