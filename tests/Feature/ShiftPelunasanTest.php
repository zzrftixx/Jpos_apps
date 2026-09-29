<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use Tests\JposTestCase;

class ShiftPelunasanTest extends JposTestCase
{
    /**
     * Pelunasan pesanan waiting-list yang dibuat pada shift sebelumnya
     * wajib dihitung pada shift kasir yang sedang menerima pelunasan saat ini.
     */
    public function test_pelunasan_waiting_list_masuk_ke_shift_yang_menerima_uang(): void
    {
        // 1. Shift 1 dibuka
        $shift1 = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now()->subHours(5),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 50000]);

        // Pesanan DP Rp 20.000 (total Rp 50.000)
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertSame('waiting', $sale->order_status);

        // Pasang waktu pembayaran DP di dalam rentang shift 1
        $waktuShift1 = now()->subHours(4);
        \Illuminate\Support\Facades\DB::table('sales')->where('id', $sale->id)->update(['created_at' => $waktuShift1]);
        $sale->payments()->update(['created_at' => $waktuShift1]);

        // Tutup shift 1 pada 3 jam yang lalu
        $shift1->closed_at = now()->subHours(3);
        $shift1->syncCalculations();
        $shift1->status = 'closed';
        $shift1->actual_cash = 120000;
        $shift1->save();

        $shift1->refresh();
        $this->assertSame(20000.0, (float) $shift1->cash_sales);
        $this->assertSame(120000.0, (float) $shift1->expected_cash);

        // 2. Shift 2 dibuka oleh kasir
        $shift2 = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now()->subHours(1),
            'starting_cash' => 120000,
            'expected_cash' => 120000,
            'status' => 'open',
        ]);

        // Pelanggan datang melunasi sisa Rp 30.000 tunai
        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 30000,
            'method' => 'tunai',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame('completed', $sale->order_status);

        // Periksa summary shift 2
        $summary2 = $shift2->calculateSummary();
        $this->assertSame(30000.0, $summary2['cash_sales'], 'Uang pelunasan harus masuk ke shift 2');
        $this->assertSame(150000.0, $summary2['expected_cash'], 'Expected cash shift 2 harus mencakup pelunasan');

        // Pastikan shift 1 yang sudah ditutup tidak bertambah uangnya
        $summary1 = $shift1->calculateSummary();
        $this->assertSame(20000.0, $summary1['cash_sales'], 'Shift 1 tidak boleh ketambahan uang pelunasan yang terjadi di shift 2');
    }

    /**
     * Pelunasan oleh kasir lain pada shift yang sedang berjalan bersamaan
     * TIDAK BOLEH bocor atau dihitung ganda pada shift kasir pembuat pesanan.
     */
    public function test_pelunasan_oleh_kasir_lain_pada_shift_simultan_tidak_bocor_ke_kasir_pembuat_pesanan(): void
    {
        $kasir1 = $this->kasir;
        $kasir2 = \App\Models\User::create([
            'name' => 'Kasir Dua',
            'username' => 'kasir2',
            'email' => 'kasir2@jpos.local',
            'password' => \Illuminate\Support\Facades\Hash::make('rahasia123'),
            'role_id' => $this->kasirRole->id,
            'is_active' => true,
        ]);

        // Shift 1 dibuka oleh Kasir 1
        $shift1 = CashierShift::create([
            'user_id' => $kasir1->id,
            'opened_at' => now()->subHours(2),
            'starting_cash' => 50000,
            'status' => 'open',
        ]);

        // Shift 2 dibuka oleh Kasir 2 (berjalan simultan)
        $shift2 = CashierShift::create([
            'user_id' => $kasir2->id,
            'opened_at' => now()->subHours(2),
            'starting_cash' => 50000,
            'status' => 'open',
        ]);

        $product = $this->makeProduct(['stock' => 50, 'cost_price' => 5000, 'sell_price' => 50000]);

        // Kasir 1 melayani pesanan waiting list dengan DP Rp 20.000 tunai
        $this->actingAs($kasir1)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertSame($shift1->id, $sale->cashier_shift_id);

        // Pelanggan datang ke Kasir 2 melunasi sisa Rp 30.000 tunai
        $this->actingAs($kasir2)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 30000,
            'method' => 'tunai',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame('completed', $sale->order_status);

        // Periksa summary Kasir 2: harus mencatat penerimaan pelunasan Rp 30.000
        $summary2 = $shift2->calculateSummary();
        $this->assertSame(30000.0, $summary2['cash_sales'], 'Uang pelunasan Rp 30.000 harus masuk ke laci Kasir 2');
        $this->assertSame(80000.0, $summary2['expected_cash'], 'Expected cash Kasir 2 harus 50.000 + 30.000 = 80.000');

        // Periksa summary Kasir 1: HANYA DP Rp 20.000 yang diterima Kasir 1, TIDAK boleh ketambahan pelunasan Kasir 2
        $summary1 = $shift1->calculateSummary();
        $this->assertSame(20000.0, $summary1['cash_sales'], 'Laci Kasir 1 HANYA boleh berisi DP Rp 20.000 miliknya, bukan pelunasan Kasir 2');
        $this->assertSame(70000.0, $summary1['expected_cash'], 'Expected cash Kasir 1 harus 50.000 + 20.000 = 70.000');
    }
}
