<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\JposTestCase;

/**
 * Pembersih keranjang TERTAHAN yang ditinggal kasir.
 *
 * Yang paling dijaga di sini BUKAN bahwa keranjangnya terbersihkan, tapi bahwa PESANAN DP
 * TIDAK IKUT TERSAPU. Keduanya sama-sama `order_status = 'waiting'`; yang membedakan cuma
 * `parked_at`. Versi pertama perintah ini menyapu seluruh `waiting` berdasarkan umur - dan
 * di database client itu berarti 24 pesanan senilai Rp 109 juta dengan piutang Rp 40 juta
 * dibatalkan otomatis pada nyala pertama.
 */
class BersihkanKeranjangCommandTest extends JposTestCase
{
    private function buatKeranjang(bool $tertahan, int $jamLalu, float $qty = 3): Sale
    {
        $p = $this->makeProduct(['sell_price' => 10000, 'stock' => 10]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => $qty]],
            'paid_amount' => $tertahan ? 0 : 5000,
            'payment_method' => 'tunai',
            $tertahan ? 'is_parked' : 'is_waiting_list' => true,
        ])->assertOk();

        $sale = Sale::latest('id')->firstOrFail();

        // Dimundurkan langsung di database supaya tidak bergantung pada Carbon::setTestNow,
        // yang akan ikut memundurkan created_at seluruh baris lain.
        $sale->forceFill([
            'parked_at' => $tertahan ? now()->subHours($jamLalu) : null,
            'created_at' => now()->subHours($jamLalu),
        ])->save();

        return $sale->fresh();
    }

    /** PALING MENENTUKAN: pesanan DP TIDAK BOLEH tersapu, seberapa pun tuanya. */
    public function test_pesanan_dp_tidak_pernah_ikut_dibersihkan(): void
    {
        $pesanan = $this->buatKeranjang(tertahan: false, jamLalu: 24 * 30);

        $this->artisan('jpos:bersihkan-keranjang', ['--jam' => 1])->assertSuccessful();

        $this->assertSame('waiting', $pesanan->fresh()->order_status,
            'Pesanan DP ikut dibatalkan - piutang toko lenyap. Penyaring parked_at hilang.');
    }

    /** Keranjang tertahan yang ditinggalkan dilepas, stoknya kembali ke rak. */
    public function test_keranjang_tertahan_yang_ditinggalkan_dilepas(): void
    {
        $keranjang = $this->buatKeranjang(tertahan: true, jamLalu: 24);
        $produk = $keranjang->items->first()->product;

        $this->assertEqualsWithDelta(7, (float) $produk->fresh()->stock, 0.001,
            'Stok belum terpotong saat ditahan - prasyarat test tidak terpenuhi.');

        $this->artisan('jpos:bersihkan-keranjang', ['--jam' => 12])->assertSuccessful();

        $this->assertSame('cancelled', $keranjang->fresh()->order_status);
        $this->assertEqualsWithDelta(10, (float) $produk->fresh()->stock, 0.001,
            'Stok tidak dikembalikan ke rak.');
    }

    /** H2: pelepasan stok WAJIB meninggalkan jejak StockMovement. */
    public function test_pelepasan_stok_meninggalkan_jejak(): void
    {
        $keranjang = $this->buatKeranjang(tertahan: true, jamLalu: 24);
        $produk = $keranjang->items->first()->product;

        $this->artisan('jpos:bersihkan-keranjang', ['--jam' => 12])->assertSuccessful();

        $jejak = $produk->stockMovements()->where('type', 'return')->latest('id')->first();

        $this->assertNotNull($jejak, 'Stok kembali tanpa jejak - melanggar H2.');
        $this->assertEqualsWithDelta(3, (float) $jejak->qty, 0.001);
    }

    /** Keranjang yang masih baru dibiarkan - kasirnya mungkin sedang melayani. */
    public function test_keranjang_yang_masih_baru_dibiarkan(): void
    {
        $keranjang = $this->buatKeranjang(tertahan: true, jamLalu: 1);

        $this->artisan('jpos:bersihkan-keranjang', ['--jam' => 12])->assertSuccessful();

        $this->assertSame('waiting', $keranjang->fresh()->order_status,
            'Keranjang yang baru ditahan sejam ikut dilepas - kasirnya bisa sedang melayani.');
    }

    /** --dry-run tidak mengubah apa pun. */
    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        $keranjang = $this->buatKeranjang(tertahan: true, jamLalu: 24);

        $this->artisan('jpos:bersihkan-keranjang', ['--jam' => 12, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('waiting', $keranjang->fresh()->order_status);
    }
}
