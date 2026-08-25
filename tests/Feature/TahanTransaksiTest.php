<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * Transaksi TERTAHAN di meja kasir - jalur terpisah dari pesanan DP.
 *
 * Kejadiannya: pelanggan sudah di depan kasir dengan keranjang penuh, lalu bilang "sebentar,
 * saya ambil satu lagi". Antrean di belakangnya tidak bisa menunggu. Keranjangnya ditahan,
 * pelanggan berikutnya dilayani, lalu keranjang tadi diambil kembali.
 *
 * Secara mesin ini sama persis dengan pesanan waiting (`order_status = 'waiting'`, stok
 * direservasi). Yang membedakan cuma NIAT-nya, dan niat itu disimpan di `parked_at` -
 * bukan ditebak dari nominal atau dari ada-tidaknya tanggal jatuh tempo.
 *
 * Yang dijaga di sini terutama SATU hal: kedua jalur itu benar-benar terpisah di layar,
 * tapi tetap satu di pembukuan.
 */
class TahanTransaksiTest extends JposTestCase
{
    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->produk = $this->makeProduct([
            'name' => 'Beras Premium', 'cost_price' => 8000, 'sell_price' => 10000, 'stock' => 100,
        ]);
    }

    private function tahan(float $qty = 3): Sale
    {
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->produk->id, 'qty' => $qty]],
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'is_parked' => true,
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Menahan
    // -----------------------------------------------------------------

    public function test_transaksi_bisa_ditahan(): void
    {
        $t = $this->tahan();

        $this->assertSame('waiting', $t->order_status);
        $this->assertNotNull($t->parked_at, 'Penanda tertahan tidak tersimpan.');
        $this->assertEqualsWithDelta(0, $t->paid_amount, 0.01);
        $this->assertNull($t->due_date, 'Transaksi tertahan tidak boleh punya jatuh tempo.');
    }

    /** Stok direservasi selama ditahan - barangnya sudah di tangan pelanggan di depan kasir. */
    public function test_stok_direservasi_selama_ditahan(): void
    {
        $this->tahan(3);

        $this->assertEqualsWithDelta(97, (float) $this->produk->fresh()->stock, 0.001);
    }

    /**
     * Dipaksa di SERVER, bukan cuma disembunyikan di layar: permintaan yang datang
     * langsung ke endpoint pun tidak boleh bisa menahan transaksi sambil membayar.
     */
    public function test_nominal_dan_jatuh_tempo_dipaksa_kosong(): void
    {
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->produk->id, 'qty' => 2]],
            'paid_amount' => 15000,
            'payment_method' => 'cash',
            'is_parked' => true,
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertOk();

        $t = Sale::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(0, $t->paid_amount, 0.01, 'Transaksi tertahan menerima pembayaran.');
        $this->assertNull($t->due_date);
    }

    // -----------------------------------------------------------------
    // Dua jalur yang benar-benar terpisah
    // -----------------------------------------------------------------

    /** PALING MENENTUKAN: yang tertahan tidak boleh mengotori daftar pesanan DP. */
    public function test_tertahan_tidak_muncul_di_daftar_pesanan(): void
    {
        $this->tahan();

        $daftar = $this->actingAs($this->kasir)
            ->get(route('kasir.waiting-list'))->assertOk()->viewData('orders');

        $this->assertCount(0, $daftar->items(), 'Transaksi tertahan bocor ke daftar pesanan DP.');
    }

    /** Dan sebaliknya: pesanan DP tidak boleh muncul di daftar tertahan. */
    public function test_pesanan_dp_tidak_muncul_di_daftar_tertahan(): void
    {
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->produk->id, 'qty' => 2]],
            'paid_amount' => 5000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertOk();

        $tertahan = $this->actingAs($this->kasir)
            ->get(route('kasir.tahan'))->assertOk()->viewData('tertahan');

        $this->assertCount(0, $tertahan, 'Pesanan DP bocor ke daftar transaksi tertahan.');
    }

    public function test_daftar_tertahan_menampilkan_yang_ditahan(): void
    {
        $t = $this->tahan();

        $this->actingAs($this->kasir)
            ->get(route('kasir.tahan'))
            ->assertOk()
            ->assertSee($t->invoice_no);
    }

    // -----------------------------------------------------------------
    // Mengambil kembali
    // -----------------------------------------------------------------

    /** Diambil kembali: stok dilepas, dan isinya dititipkan untuk dimuat ke keranjang. */
    public function test_mengambil_kembali_melepas_stok_dan_mengembalikan_isi(): void
    {
        $t = $this->tahan(3);
        $this->assertEqualsWithDelta(97, (float) $this->produk->fresh()->stock, 0.001);

        $response = $this->actingAs($this->kasir)
            ->post(route('kasir.tahan.ambil', $t))
            ->assertRedirect(route('kasir.index'));

        $this->assertEqualsWithDelta(100, (float) $this->produk->fresh()->stock, 0.001,
            'Stok tidak dilepas saat transaksi diambil kembali.');

        $keranjang = $response->getSession()->get('kasir_keranjang_diambil');

        $this->assertIsArray($keranjang);
        $this->assertCount(1, $keranjang);
        $this->assertSame($this->produk->id, $keranjang[0]['product_id']);
        $this->assertEqualsWithDelta(3, $keranjang[0]['qty'], 0.001);
    }

    /** Sesudah diambil, ia hilang dari daftar tertahan - tidak boleh bisa diambil dua kali. */
    public function test_tidak_bisa_diambil_dua_kali(): void
    {
        $t = $this->tahan(3);

        $this->actingAs($this->kasir)->post(route('kasir.tahan.ambil', $t))->assertRedirect();
        $this->actingAs($this->kasir)->post(route('kasir.tahan.ambil', $t))
            ->assertRedirect(route('kasir.tahan'));

        $this->assertEqualsWithDelta(100, (float) $this->produk->fresh()->stock, 0.001,
            'Stok dikembalikan dua kali.');
        $this->assertCount(0, Sale::tertahan()->get());
    }

    // -----------------------------------------------------------------
    // Pembukuan tidak boleh bergeser
    // -----------------------------------------------------------------

    /** Transaksi tertahan belum jadi omset - sama seperti pesanan waiting mana pun. */
    public function test_tidak_menaikkan_omset(): void
    {
        $this->tahan();

        $omset = Akuntansi::omsetHarian(now()->toDateString(), now()->toDateString());

        $this->assertEqualsWithDelta(0, $omset[now()->toDateString()] ?? 0, 0.01);
    }

    /**
     * Dan neraca tidak bergeser sama sekali. Kolom `parked_at` sengaja dibuat terpisah dari
     * `order_status` justru supaya seluruh pembukuan yang sudah benar tidak perlu disentuh -
     * test ini yang membuktikan itu benar.
     */
    public function test_neraca_tidak_bergeser(): void
    {
        $sebelum = Akuntansi::posisiPada(now()->toDateString());

        $this->tahan();

        $sesudah = Akuntansi::posisiPada(now()->toDateString());

        $this->assertEqualsWithDelta($sebelum->selisih, $sesudah->selisih, 0.01,
            'Menahan transaksi menggeser keseimbangan neraca.');
        $this->assertEqualsWithDelta(0, $sesudah->uang_muka, 0.01,
            'Transaksi tertahan tercatat punya uang muka.');
    }

    /** Modul Kasir menawarkan jalurnya, dan menunjukkan berapa yang sedang tertahan. */
    public function test_kasir_menawarkan_jalur_tahan(): void
    {
        $this->tahan();

        $response = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk();

        $this->assertSame(1, $response->viewData('jumlahTertahan'));
        $response->assertSee('Tahan Transaksi', false);
        $response->assertSee('Tertahan', false);
    }
}
