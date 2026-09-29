<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Support\Akuntansi;
use Tests\JposTestCase;

class MutasiKasPelunasanTest extends JposTestCase
{
    /**
     * Pelunasan waiting list pada hari berikutnya harus muncul di Mutasi Kas pada tanggal pelunasan,
     * bukan menggelembungkan tanggal pemesanan awal atau hilang dari tanggal pelunasan.
     */
    public function test_pelunasan_waiting_list_muncul_di_mutasi_kas_pada_tanggal_pelunasan(): void
    {
        $product = $this->makeProduct(['stock' => 20, 'cost_price' => 5000, 'sell_price' => 50000]);

        // Hari 1: Kasir melayani pesanan waiting list dengan DP Rp 20.000 tunai
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_type' => 'base']],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);

        // Pasang tanggal Hari 1 (kemarin)
        $hari1 = now()->subDays(1)->toDateString();
        $waktuHari1 = now()->subDays(1)->setTime(10, 0, 0);
        \Illuminate\Support\Facades\DB::table('sales')->where('id', $sale->id)->update(['created_at' => $waktuHari1]);
        $sale->payments()->update(['created_at' => $waktuHari1]);

        // Hari 2 (hari ini): Pelanggan datang melunasi sisa Rp 30.000 tunai
        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 30000,
            'method' => 'tunai',
        ])->assertRedirect();

        $hari2 = now()->toDateString();

        // 1. Verifikasi Mutasi Kas Hari 1
        $mutasiHari1 = Akuntansi::queryMutasiKas($hari1, $hari1)->get();
        $saleMutasiHari1 = $mutasiHari1->where('source_type', 'sale');
        $this->assertCount(1, $saleMutasiHari1, 'Hari 1 harus mencatat 1 mutasi penjualan (DP)');
        $this->assertSame(20000.0, (float) $saleMutasiHari1->first()->amount, 'Jumlah mutasi kas hari 1 harus DP Rp 20.000, bukan Rp 50.000');

        $ringkasanHari1 = Akuntansi::ringkasanKas($hari1, $hari1);
        $this->assertSame(20000.0, (float) $ringkasanHari1->penjualan, 'Ringkasan kas penjualan hari 1 harus Rp 20.000');
        $this->assertSame((float) $ringkasanHari1->penjualan, (float) $saleMutasiHari1->sum('amount'), 'Ringkasan kas dan tabel mutasi kas harus konsisten pada hari 1');

        // 2. Verifikasi Mutasi Kas Hari 2 (Pelunasan)
        $mutasiHari2 = Akuntansi::queryMutasiKas($hari2, $hari2)->get();
        $saleMutasiHari2 = $mutasiHari2->where('source_type', 'sale');
        $this->assertCount(1, $saleMutasiHari2, 'Hari 2 harus mencatat 1 mutasi penjualan (Pelunasan)');
        $this->assertSame(30000.0, (float) $saleMutasiHari2->first()->amount, 'Jumlah mutasi kas hari 2 harus Rp 30.000');

        $ringkasanHari2 = Akuntansi::ringkasanKas($hari2, $hari2);
        $this->assertSame(30000.0, (float) $ringkasanHari2->penjualan, 'Ringkasan kas penjualan hari 2 harus Rp 30.000');
        $this->assertSame((float) $ringkasanHari2->penjualan, (float) $saleMutasiHari2->sum('amount'), 'Ringkasan kas dan tabel mutasi kas harus konsisten pada hari 2');

        // 3. Akses halaman web /kas untuk hari 2
        $response = $this->actingAs($this->admin)->get("/kas?from={$hari2}&to={$hari2}");
        $response->assertOk();
        $response->assertSee('30.000');
        $response->assertSee($sale->invoice_no);
    }
}
