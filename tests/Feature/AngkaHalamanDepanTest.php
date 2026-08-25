<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * Angka omset di Dashboard dan peringkat Produk Terlaris.
 *
 * DUA HALAMAN INI TIDAK PERNAH DIUJI ANGKANYA - dan itu persis kenapa cacatnya bertahan.
 * Yang menyinggung keduanya cuma `EksporLaporanTest` dan `KecepatanHalamanTest`, dan keduanya
 * menguji hal lain (bentuk berkas ekspor, dan jumlah query).
 *
 * Yang salah sebelum perbaikan:
 *
 *   - Dashboard menyaring `status != 'returned'`, BUKAN `order_status = 'completed'`. Pesanan
 *     DP yang belum diserahkan dan transaksi yang sudah DIBATALKAN ikut terhitung sebagai
 *     omset. `monthRevenue` dan grafiknya bahkan tidak menyaring apa pun.
 *   - Dashboard memakai `SUM(total)` yang MEMUAT PAJAK TITIPAN - uang yang harus disetorkan,
 *     bukan hak toko. Laporan Omset sudah memakai `subtotal - discount` sejak 2.2.0.
 *   - `ReportController::terlaris()` tidak menyaring `order_status` sama sekali.
 *
 * Yang membuat temuan terakhir itu menyakitkan: versi EKSPOR laporan yang sama
 * (`LaporanEksporController::laporanTerlaris`) sudah menyaringnya sejak awal. Jadi laporan
 * yang sama memberi angka BERBEDA antara layar dan PDF, dan tidak ada yang membandingkannya.
 */
class AngkaHalamanDepanTest extends JposTestCase
{
    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->produk = $this->makeProduct([
            'name' => 'Beras Premium',
            'cost_price' => 8000,
            'sell_price' => 10000,
            'stock' => 1000,
        ]);
    }

    private function jualLunas(float $qty = 1): Sale
    {
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->produk->id, 'qty' => $qty]],
            'paid_amount' => 9999999,
            'payment_method' => 'cash',
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    private function pesananDp(float $qty = 1): Sale
    {
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->produk->id, 'qty' => $qty]],
            'paid_amount' => 1000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------

    /** PALING MENENTUKAN: pesanan DP belum jadi omset - barangnya belum diserahkan. */
    public function test_pesanan_dp_tidak_menaikkan_omset_dashboard(): void
    {
        $this->jualLunas(2);
        $this->pesananDp(5);

        $data = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $this->assertEqualsWithDelta(20000, $data->viewData('todayRevenue'), 0.01,
            'Pesanan DP ikut terhitung sebagai omset hari ini.');
        $this->assertSame(1, $data->viewData('todayTransactions'),
            'Pesanan DP ikut terhitung sebagai transaksi selesai.');
    }

    public function test_transaksi_dibatalkan_tidak_menaikkan_omset_dashboard(): void
    {
        $this->jualLunas(2);
        $dibatalkan = $this->jualLunas(3);

        $this->actingAs($this->admin)->post(route('retur.cancel-sale', $dibatalkan))->assertRedirect();

        $data = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $this->assertEqualsWithDelta(20000, $data->viewData('todayRevenue'), 0.01,
            'Transaksi yang dibatalkan masih terhitung sebagai omset.');
    }

    /**
     * Pajak adalah titipan yang harus disetorkan, bukan hak toko. Dashboard dulu memakai
     * SUM(total) yang memuatnya, sehingga angkanya selalu lebih besar dari laporannya sendiri.
     */
    public function test_pajak_tidak_ikut_ke_omset_dashboard(): void
    {
        \App\Models\Setting::set('tax', ['enabled' => true, 'percent' => 10, 'inclusive' => false]);

        $this->jualLunas(2);

        $data = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $this->assertEqualsWithDelta(20000, $data->viewData('todayRevenue'), 0.01,
            'Pajak titipan ikut terhitung sebagai omset.');
    }

    /** Angka di halaman depan wajib sama persis dengan Laporan Omset - satu sumber. */
    public function test_omset_dashboard_sama_dengan_laporan(): void
    {
        $this->jualLunas(3);
        $this->pesananDp(2);

        $dashboard = (float) $this->actingAs($this->admin)->get('/dashboard')->viewData('todayRevenue');
        $laporan = Akuntansi::omsetHarian(now()->toDateString(), now()->toDateString());

        $this->assertEqualsWithDelta($laporan[now()->toDateString()] ?? 0, $dashboard, 0.01,
            'Dashboard dan Laporan Omset menampilkan angka yang berbeda.');
    }

    /** Grafik memakai angka yang sama dengan kartu di atasnya, dan tidak melompati hari kosong. */
    public function test_grafik_tujuh_hari_utuh_dan_sejalan_dengan_kartu(): void
    {
        $this->jualLunas(2);

        $data = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $grafik = collect($data->viewData('salesChart'));

        $this->assertCount(7, $grafik, 'Grafik melompati hari yang tidak ada penjualannya.');

        $hariIni = $grafik->firstWhere('date', now()->toDateString());

        $this->assertNotNull($hariIni);
        $this->assertEqualsWithDelta($data->viewData('todayRevenue'), $hariIni->total, 0.01,
            'Titik hari ini di grafik berbeda dari kartu omset hari ini.');
    }

    /** Retur mengurangi omset, sama seperti di laporan. */
    public function test_retur_mengurangi_omset_dashboard(): void
    {
        $penjualan = $this->jualLunas(5);

        $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $penjualan->id,
            'items' => [['sale_item_id' => $penjualan->items->first()->id, 'qty' => 2]],
            'reason' => 'Rusak',
        ])->assertOk();

        $data = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $this->assertEqualsWithDelta(30000, $data->viewData('todayRevenue'), 0.01,
            'Retur tidak mengurangi omset di dashboard.');
    }

    // -----------------------------------------------------------------
    // Produk Terlaris
    // -----------------------------------------------------------------

    /** HUKUM 5 di halaman Terlaris - dulu tidak disaring sama sekali. */
    public function test_terlaris_hanya_menghitung_transaksi_selesai(): void
    {
        $this->jualLunas(4);
        $this->pesananDp(50);

        $baris = collect($this->actingAs($this->admin)
            ->get(route('laporan.terlaris'))
            ->assertOk()
            ->viewData('topProducts')
            ->items());

        $beras = $baris->firstWhere('product_name', 'Beras Premium');

        $this->assertNotNull($beras);
        $this->assertEqualsWithDelta(4, $beras->total_qty, 0.001,
            'Pesanan DP ikut naik ke peringkat produk terlaris.');
    }

    public function test_terlaris_di_dashboard_juga_hanya_transaksi_selesai(): void
    {
        $this->jualLunas(4);
        $this->pesananDp(50);

        $baris = collect($this->actingAs($this->admin)->get('/dashboard')->viewData('topProducts'));
        $beras = $baris->firstWhere('product_name', 'Beras Premium');

        $this->assertNotNull($beras);
        $this->assertEqualsWithDelta(4, $beras->total_qty, 0.001,
            'Pesanan DP ikut naik ke peringkat produk terlaris di dashboard.');
    }

    /**
     * Halaman layar dan versi ekspornya HARUS memberi angka yang sama. Perbedaan di antara
     * keduanya adalah cacat yang paling sulit ditemukan, karena masing-masing terlihat wajar
     * kalau dilihat sendiri-sendiri.
     */
    public function test_terlaris_di_layar_sama_dengan_versi_ekspor(): void
    {
        $this->jualLunas(4);
        $this->pesananDp(50);

        $layar = collect($this->actingAs($this->admin)
            ->get(route('laporan.terlaris'))
            ->viewData('topProducts')
            ->items())
            ->firstWhere('product_name', 'Beras Premium');

        $ekspor = \App\Models\SaleItem::selectRaw('SUM(qty * unit_conversion) as total_qty')
            ->whereHas('sale', fn ($q) => $q->where('order_status', 'completed'))
            ->where('product_name', 'Beras Premium')
            ->value('total_qty');

        $this->assertEqualsWithDelta((float) $ekspor, (float) $layar->total_qty, 0.001,
            'Angka di layar berbeda dari angka yang dipakai versi ekspor.');
    }
}
