<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * Pesanan / waiting list TANPA uang muka.
 *
 * Pelanggan memesan barang tapi belum membayar apa pun. Sebelumnya kasir dipaksa mengisi DP
 * lebih dari 0, jadi pesanan tanpa DP tidak bisa dicatat sama sekali - padahal itu kejadian
 * paling biasa di toko ("titip dulu, besok saya ambil").
 *
 * Tidak ada kolom, status, atau halaman baru: pesanan tanpa DP hanyalah pesanan dengan
 * `paid_amount = 0`. Yang dijaga di sini justru itu - bahwa pembukuannya tetap benar tanpa
 * satu pun perlakuan khusus.
 */
class PesananTanpaDpTest extends JposTestCase
{
    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->produk = $this->makeProduct([
            'name' => 'Beras Premium', 'cost_price' => 8000, 'sell_price' => 10000, 'stock' => 100,
        ]);
    }

    private function pesan(float $bayar): Sale
    {
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->produk->id, 'qty' => 3]],
            'paid_amount' => $bayar,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    public function test_pesanan_tanpa_dp_bisa_disimpan(): void
    {
        $pesanan = $this->pesan(0);

        $this->assertSame('waiting', $pesanan->order_status);
        $this->assertEqualsWithDelta(0, $pesanan->paid_amount, 0.01);
        $this->assertEqualsWithDelta(30000, $pesanan->remaining, 0.01, 'Sisa bayar bukan total penuh.');
    }

    /** Barangnya tetap direservasi - itu inti pesanan, dengan atau tanpa DP. */
    public function test_stok_tetap_dipotong(): void
    {
        $this->pesan(0);

        $this->assertEqualsWithDelta(97, (float) $this->produk->fresh()->stock, 0.001);
    }

    /** Belum diserahkan, belum jadi omset - sama seperti pesanan ber-DP. */
    public function test_tidak_menaikkan_omset(): void
    {
        $this->pesan(0);

        $omset = Akuntansi::omsetHarian(now()->toDateString(), now()->toDateString());

        $this->assertEqualsWithDelta(0, $omset[now()->toDateString()] ?? 0, 0.01);
    }

    /**
     * PALING MENENTUKAN untuk pembukuan: uang muka Rp 0 tidak menambah kewajiban apa pun,
     * dan neraca tetap seimbang. Kalau ini meleset, seluruh neraca ikut timpang.
     */
    public function test_neraca_tetap_seimbang_dan_tanpa_uang_muka(): void
    {
        // Dibandingkan SEBELUM vs SESUDAH, bukan terhadap nol: data awal test sudah punya
        // stok tanpa modal awal, jadi selisihnya memang bukan nol sejak awal. Yang diuji di
        // sini apakah PESANANNYA menggeser neraca - dan seharusnya tidak sama sekali.
        $sebelum = Akuntansi::posisiPada(now()->toDateString());

        $this->pesan(0);

        $sesudah = Akuntansi::posisiPada(now()->toDateString());

        $this->assertEqualsWithDelta(0, $sesudah->uang_muka, 0.01,
            'Pesanan tanpa DP tercatat punya uang muka.');
        $this->assertEqualsWithDelta($sebelum->selisih, $sesudah->selisih, 0.01,
            'Pesanan tanpa DP menggeser keseimbangan neraca.');
    }

    /** Pesanan ber-DP tetap berjalan seperti semula - perubahan ini tidak boleh menggeser apa pun. */
    public function test_pesanan_dengan_dp_tidak_berubah(): void
    {
        $pesanan = $this->pesan(10000);

        $this->assertSame('waiting', $pesanan->order_status);
        $this->assertEqualsWithDelta(10000, $pesanan->paid_amount, 0.01);
        $this->assertEqualsWithDelta(20000, $pesanan->remaining, 0.01);
        $this->assertEqualsWithDelta(10000,
            Akuntansi::posisiPada(now()->toDateString())->uang_muka, 0.01);
    }

    /**
     * Pilihannya EKSPLISIT di layar, bukan ditebak dari nominal.
     *
     * Sebelum ada radio ini, "tanpa DP" berarti kasir harus tahu bahwa mengetik angka 0
     * punya arti khusus - aturan tersembunyi yang tidak tertulis di mana pun. Radionya
     * hanya menentukan nominal; statusnya tetap `waiting` di kedua mode, jadi tidak ada
     * kolom maupun status baru yang perlu dijaga di sisi server.
     */
    public function test_kasir_menawarkan_dua_pilihan_yang_terlihat(): void
    {
        $isi = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringContainsString('value="tanpa_dp"', $isi, 'Pilihan Tanpa DP tidak ada di layar.');
        $this->assertStringContainsString('value="dp"', $isi, 'Pilihan Bayar DP tidak ada di layar.');
        $this->assertStringContainsString('modePesanan', $isi);
        $this->assertStringContainsString('Tanpa DP', $isi);
    }

    /** Dan pelunasannya tetap bisa dijalankan sampai selesai. */
    public function test_pesanan_tanpa_dp_bisa_dilunasi(): void
    {
        $pesanan = $this->pesan(0);

        $this->actingAs($this->kasir)
            ->post(route('kasir.waiting-list.pay', $pesanan), ['amount' => 30000])
            ->assertRedirect();

        $this->assertSame('completed', $pesanan->fresh()->order_status);
        $this->assertEqualsWithDelta(30000,
            Akuntansi::omsetHarian(now()->toDateString(), now()->toDateString())[now()->toDateString()] ?? 0, 0.01);
    }
}
