<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\MetodeBayar;
use Tests\JposTestCase;

/**
 * Keterangan METODE PEMBAYARAN di struk, pesanan, dan laporan.
 *
 * Yang dijaga di sini bukan tampilan, tapi KEBENARAN UANG. Sebelum versi ini, metode
 * pembayaran cuma dipajang mentah di struk (CASH) dan tidak pernah dipakai mengelompokkan
 * apa pun. Begitu ia menjadi dasar pengelompokan uang, tiga cacat lama berubah dari
 * kosmetik menjadi mahal - dan ketiganya diuji di sini:
 *
 *   1. Ejaan tidak konsisten (cash vs tunai) memecah satu metode jadi dua ember.
 *   2. Pelunasan pesanan tidak pernah mencatat caranya, jadi DP tunai + lunas QRIS
 *      terhitung seluruhnya tunai - dan laci kurang sebesar pelunasannya tanpa jejak.
 *   3. paid_amount bukan uang yang masuk laci kalau ada kembalian.
 */
class MetodePembayaranTest extends JposTestCase
{
    private function jual(array $ubah = []): \Illuminate\Testing\TestResponse
    {
        $p = $this->makeProduct(['sell_price' => 10000]);

        return $this->actingAs($this->kasir)->postJson('/kasir', array_merge([
            'items' => [['product_id' => $p->id, 'qty' => 1]],
            'paid_amount' => 10000,
            'payment_method' => 'tunai',
        ], $ubah));
    }

    private function pesanDp(float $harga, float $dp, string $metodeDp): Sale
    {
        $p = $this->makeProduct(['sell_price' => $harga]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $p->id, 'qty' => 1]],
            'paid_amount' => $dp,
            'payment_method' => $metodeDp,
            'is_waiting_list' => true,
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    // ----------------------------------------------------------------- ejaan

    /**
     * PALING MENENTUKAN: ejaan lama tetap terbaca, tanpa data client ditulis ulang.
     *
     * Transaksi tahun lalu tersimpan cash. Menjalankan UPDATE massal terhadap database
     * toko sungguhan demi kerapian adalah risiko tanpa imbalan, jadi yang diterjemahkan
     * adalah PEMBACAANNYA.
     */
    public function test_ejaan_lama_diterjemahkan_bukan_ditulis_ulang(): void
    {
        $this->assertSame('tunai', MetodeBayar::normal('cash'));
        $this->assertSame('tunai', MetodeBayar::normal('CASH'));
        $this->assertSame('tunai', MetodeBayar::normal(' Tunai '));
        $this->assertSame('Tunai', MetodeBayar::label('cash'));
        $this->assertSame('transfer', MetodeBayar::normal('bank'));
        $this->assertSame('debit', MetodeBayar::normal('kartu'));
    }

    /**
     * Nilai tak dikenal jadi lainnya, BUKAN dipaksa jadi tunai.
     *
     * Memaksanya jadi tunai menaruh uang di ember yang salah - kesalahan yang jauh lebih
     * mahal daripada satu baris bernama Lainnya yang kelihatan aneh.
     */
    public function test_nilai_tak_dikenal_tidak_dipaksa_jadi_tunai(): void
    {
        $this->assertSame('lainnya', MetodeBayar::normal('gopay'));
        $this->assertSame('Lainnya', MetodeBayar::label('entah'));
        $this->assertSame('tunai', MetodeBayar::normal(null));
    }

    /** Baris baru selalu tersimpan kanonik, jadi kekacauan ejaan tidak bertambah. */
    public function test_baris_baru_disimpan_kanonik(): void
    {
        $this->jual(['payment_method' => 'cash'])->assertOk();

        $this->assertSame('tunai', Sale::latest('id')->first()->payment_method);
    }

    // ------------------------------------------------- uang yang benar-benar masuk

    /**
     * Uang yang dicatat adalah yang MASUK LACI, bukan yang diserahkan pembeli.
     *
     * Menyerahkan Rp 50.000 untuk belanja Rp 10.000 menambah Rp 10.000 di laci. Mencatat
     * Rp 50.000 membuat laporan uang masuk tidak akan pernah cocok dengan lacinya.
     */
    public function test_kembalian_tidak_ikut_terhitung_sebagai_uang_masuk(): void
    {
        $this->jual(['paid_amount' => 50000])->assertOk();

        $bayar = SalePayment::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(10000, (float) $bayar->amount, 0.01,
            'Kembalian ikut terhitung sebagai uang masuk.');
        $this->assertSame('bayar', $bayar->kind);
        $this->assertSame('tunai', $bayar->method);
    }

    /** Transaksi yang cuma ditahan di meja kasir tidak membawa uang sama sekali. */
    public function test_transaksi_tertahan_tidak_mencatat_uang_masuk(): void
    {
        $this->jual(['is_parked' => true, 'paid_amount' => 0])->assertOk();

        $this->assertSame(0, SalePayment::count(),
            'Keranjang yang cuma ditahan tercatat sebagai uang masuk.');
    }

    // ----------------------------------------------------- DP dan pelunasan

    /**
     * INI CACAT UTAMA YANG DIPERBAIKI: DP tunai, dilunasi QRIS, keduanya tercatat.
     *
     * Sebelum ini pesanan seperti ini tercatat seluruhnya tunai, dan pemilik toko yang
     * menghitung lacinya malam itu menemukan selisih sebesar pelunasannya tanpa cara
     * menelusurinya - caranya memang tidak pernah disimpan di mana pun.
     */
    public function test_dp_dan_pelunasan_mencatat_metodenya_masing_masing(): void
    {
        $sale = $this->pesanDp(400000, 100000, 'tunai');

        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 300000,
            'method' => 'qris',
        ]);

        $bayar = $sale->fresh()->payments()->orderBy('id')->get();

        $this->assertCount(2, $bayar, 'Pelunasan tidak tercatat sebagai penerimaan uang tersendiri.');

        $this->assertSame('tunai', $bayar[0]->method);
        $this->assertSame('dp', $bayar[0]->kind);
        $this->assertEqualsWithDelta(100000, (float) $bayar[0]->amount, 0.01);

        $this->assertSame('qris', $bayar[1]->method);
        $this->assertSame('pelunasan', $bayar[1]->kind);
        $this->assertEqualsWithDelta(300000, (float) $bayar[1]->amount, 0.01);

        $this->assertSame('completed', $sale->fresh()->order_status);
    }

    /** Pelunasan berlebih: yang masuk laci tetap sebesar sisanya, bukan yang diserahkan. */
    public function test_pelunasan_berlebih_hanya_mencatat_sisa_tagihan(): void
    {
        $sale = $this->pesanDp(400000, 100000, 'tunai');

        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 350000,
            'method' => 'tunai',
        ]);

        $pelunasan = $sale->fresh()->payments()->where('kind', 'pelunasan')->firstOrFail();

        $this->assertEqualsWithDelta(300000, (float) $pelunasan->amount, 0.01,
            'Kembalian pelunasan ikut terhitung sebagai uang masuk.');
    }

    /** Permintaan tanpa method tetap dilayani - memakai cara yang sama dengan DP-nya. */
    public function test_pelunasan_tanpa_metode_mengikuti_cara_dp(): void
    {
        $sale = $this->pesanDp(400000, 100000, 'qris');

        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", ['amount' => 300000]);

        $this->assertSame('qris', $sale->fresh()->payments()->where('kind', 'pelunasan')->firstOrFail()->method);
    }

    // ------------------------------------------------------------- penyaring

    /**
     * Pesanan muncul di penyaring SETIAP metode yang benar-benar dipakai membayarnya.
     *
     * Menyaring dari kolom sales.payment_method hanya akan menemukannya di Tunai dan
     * menyembunyikan Rp 300.000 yang sungguh-sungguh masuk lewat QRIS.
     */
    public function test_penyaring_menemukan_pesanan_lewat_kedua_metodenya(): void
    {
        $sale = $this->pesanDp(400000, 100000, 'tunai');

        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 300000,
            'method' => 'qris',
        ]);

        foreach (['tunai', 'qris'] as $metode) {
            $this->actingAs($this->admin)
                ->get('/kasir/waiting-list?status=completed&metode=' . $metode)
                ->assertOk()
                ->assertSee($sale->invoice_no);
        }

        $this->actingAs($this->admin)
            ->get('/kasir/waiting-list?status=completed&metode=transfer')
            ->assertOk()
            ->assertDontSee($sale->invoice_no);
    }

    /** Penyaring yang dikarang di URL diabaikan, bukan diteruskan mentah ke query. */
    public function test_penyaring_karangan_diabaikan(): void
    {
        $this->actingAs($this->admin)
            ->get('/kasir/waiting-list?status=waiting&metode=gopay-karangan')
            ->assertOk();
    }

    // ----------------------------------------------------------------- struk

    /** Struk mencetak nama yang dimengerti manusia, bukan kunci mentah. */
    public function test_struk_mencetak_label_bukan_kunci_mentah(): void
    {
        $this->jual(['payment_method' => 'cash'])->assertOk();
        $sale = Sale::latest('id')->firstOrFail();

        $html = $this->actingAs($this->kasir)->get("/kasir/receipt/{$sale->id}")->assertOk()->getContent();

        $this->assertStringContainsString('Bayar (Tunai)', $html);
        $this->assertStringNotContainsString('Bayar (CASH)', $html);
    }

    /** Struk pesanan bertahap merinci kedua penerimaan uangnya. */
    public function test_struk_merinci_dp_dan_pelunasan(): void
    {
        $sale = $this->pesanDp(400000, 100000, 'tunai');

        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 300000,
            'method' => 'qris',
        ]);

        $html = $this->actingAs($this->kasir)->get("/kasir/receipt/{$sale->id}")->assertOk()->getContent();

        $this->assertStringContainsString('DP (Tunai)', $html);
        $this->assertStringContainsString('Lunas (QRIS)', $html);
    }

    // --------------------------------------------------------------- laporan

    /**
     * PALING MENENTUKAN UNTUK LAPORAN: angka lama tidak bergeser sedikit pun.
     *
     * Seluruh tambahan ini hanya membaca tabel baru. Kalau ringkasan omset ikut berubah,
     * berarti ada yang tersambung ke tempat yang salah.
     */
    public function test_angka_laporan_lama_tidak_berubah(): void
    {
        $this->jual(['paid_amount' => 50000])->assertOk();

        $html = $this->actingAs($this->admin)->get('/laporan/penjualan')->assertOk()->getContent();

        // Nilai transaksinya Rp 10.000 - bukan Rp 50.000 yang diserahkan pembeli.
        $this->assertStringContainsString('Total Pendapatan', $html);
        $this->assertStringContainsString('Rp 10.000', $html);
    }

    /** Uang masuk per metode tampil dan mengelompokkan dengan benar. */
    public function test_laporan_menampilkan_uang_masuk_per_metode(): void
    {
        $this->jual(['payment_method' => 'tunai', 'paid_amount' => 10000])->assertOk();
        $this->jual(['payment_method' => 'qris', 'paid_amount' => 10000])->assertOk();

        $html = $this->actingAs($this->admin)->get('/laporan/penjualan')->assertOk()->getContent();

        $this->assertStringContainsString('Uang Masuk per Metode', $html);
        $this->assertStringContainsString('QRIS', $html);
    }

    /** Transaksi batal tidak dihitung sebagai uang masuk - uangnya sudah kembali. */
    public function test_transaksi_batal_tidak_dihitung_sebagai_uang_masuk(): void
    {
        $sale = $this->pesanDp(400000, 100000, 'tunai');

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$sale->id}/cancel");

        $this->assertSame('cancelled', $sale->fresh()->order_status);

        $html = $this->actingAs($this->admin)->get('/laporan/penjualan')->assertOk()->getContent();

        $this->assertStringNotContainsString('Rp 100.000', $html,
            'Uang pesanan yang dibatalkan masih terhitung sebagai uang masuk.');
    }

    /** Penyaring metode ikut terbawa ke berkas yang diunduh. */
    public function test_penyaring_metode_ikut_ke_ekspor(): void
    {
        $this->jual(['payment_method' => 'qris'])->assertOk();
        $this->jual(['payment_method' => 'tunai'])->assertOk();

        $html = $this->actingAs($this->admin)
            ->get('/laporan/penjualan?metode=qris')->assertOk()->getContent();

        $this->assertStringContainsString('metode=qris', $html,
            'Tombol unduh kehilangan penyaring - berkasnya akan berisi seluruh transaksi.');
    }

    /** Warna lencana hanya memakai kelas yang sudah ada di CSS terkompilasi (B2). */
    public function test_kelas_lencana_sudah_ada_di_css_terkompilasi(): void
    {
        $css = file_get_contents(public_path('vendor/jpos.css'));

        foreach (MetodeBayar::kunci() as $kunci) {
            foreach (explode(' ', MetodeBayar::kelas($kunci)) as $kelas) {
                $this->assertStringContainsString('.' . $kelas, $css,
                    "Kelas {$kelas} belum pernah dikompilasi - lencananya tampil tanpa warna, tanpa galat.");
            }
        }
    }
}
