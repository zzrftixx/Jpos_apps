<?php

namespace Tests\Feature;

use App\Models\FixedAsset;
use App\Models\NeracaSnapshot;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Setting;
use App\Support\Akuntansi;
use Tests\JposTestCase;

/**
 * UAT Neraca.
 *
 * SATU HAL YANG DIUJI DI SINI LEBIH PENTING DARIPADA SEMUA YANG LAIN:
 *
 *      ASET = KEWAJIBAN + MODAL
 *
 * Itu bukan konvensi yang bisa ditawar. Setiap rupiah barang di toko asalnya cuma dua -
 * uang orang lain (hutang) atau uang sendiri (modal); tidak ada kemungkinan ketiga. Kalau
 * kiri dan kanan tidak sama, pasti ada transaksi yang menggerakkan satu sisi saja, dan
 * neracanya tidak boleh dipercaya sampai selisihnya nol.
 *
 * Karena itu hampir semua test di berkas ini berakhir dengan pemeriksaan selisih nol,
 * masing-masing setelah satu jenis transaksi yang berbeda.
 */
class NeracaTest extends JposTestCase
{
    private function mulaiPembukuan(float $modalAwal = 1000000, float $saldoKas = 1000000): void
    {
        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->toDateString(),
            'saldo_awal_kas' => $saldoKas,
            'modal_awal' => $modalAwal,
        ]);
    }

    /**
     * Untuk skenario yang stoknya sudah ada sebelum pembukuan dimulai.
     *
     * Stok yang sudah ada di rak saat mulai membukukan HARUS ikut dihitung sebagai modal awal.
     * Kalau tidak, neraca akan melaporkan selisih persis sebesar nilai stok itu - dan itu
     * memang perilaku yang benar: barang yang tidak jelas asal uangnya wajib ketahuan.
     * Ini juga yang akan dilakukan pemilik toko sungguhan saat mengisi modal awal.
     */
    private function mulaiPembukuanDenganStokYangSudahAda(float $saldoKas = 1000000): void
    {
        $nilaiStok = (float) Product::where('type', 'barang')
            ->selectRaw('COALESCE(SUM(stock * cost_price), 0) as nilai')->value('nilai');

        $this->mulaiPembukuan($saldoKas + $nilaiStok, $saldoKas);
    }

    private function seimbang(string $kenapa): object
    {
        $posisi = Akuntansi::posisiPada(now()->toDateString());

        $this->assertSame(
            0.0,
            $posisi->selisih,
            sprintf(
                "%s\nAset %s = Kewajiban %s + Modal %s, selisih %s",
                $kenapa,
                number_format($posisi->total_aset),
                number_format($posisi->total_kewajiban),
                number_format($posisi->total_modal),
                number_format($posisi->selisih)
            )
        );

        return $posisi;
    }

    private function beliTunai(Product $produk, float $qty, float $harga): void
    {
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => $qty, 'unit_type' => 'base', 'price' => $harga]],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();
    }

    /* ------------------------------------------------------------------ titik nol */

    public function test_toko_baru_hanya_berisi_modal_awal(): void
    {
        $this->mulaiPembukuan(1000000, 1000000);

        $posisi = $this->seimbang('Toko yang belum bertransaksi sekali pun sudah tidak seimbang.');

        $this->assertSame(1000000.0, $posisi->kas);
        $this->assertSame(1000000.0, $posisi->total_modal);
        $this->assertSame(0.0, $posisi->total_kewajiban);
    }

    /* ------------------------------------------------- satu per satu jenis transaksi */

    /** Uang berubah wujud jadi stok. Total aset tidak berubah, cuma pindah pos. */
    public function test_beli_tunai_memindahkan_kas_menjadi_persediaan(): void
    {
        $this->mulaiPembukuan();
        $produk = $this->makeProduct(['stock' => 0, 'cost_price' => 0]);

        $this->beliTunai($produk, 10, 5000);

        $posisi = $this->seimbang('Pembelian tunai membuat neraca timpang.');

        $this->assertSame(950000.0, $posisi->kas, 'Kas berkurang 50.000.');
        $this->assertSame(50000.0, $posisi->persediaan, 'Persediaan bertambah 50.000.');
        $this->assertSame(1000000.0, $posisi->total_aset, 'Total aset tidak boleh berubah - uangnya hanya pindah pos.');
        $this->assertSame(1000000.0, $posisi->total_modal, 'Membeli barang bukan untung dan bukan rugi.');
    }

    /** Beli hutang menambah aset DAN kewajiban sekaligus - inilah yang dulu hilang. */
    public function test_beli_hutang_menambah_aset_dan_kewajiban_bersamaan(): void
    {
        $this->mulaiPembukuan();
        $produk = $this->makeProduct(['stock' => 0, 'cost_price' => 0]);

        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => 10, 'unit_type' => 'base', 'price' => 5000]],
            'bayar' => 'hutang',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Pembelian kredit membuat neraca timpang.');

        $this->assertSame(1000000.0, $posisi->kas, 'Belum ada uang keluar.');
        $this->assertSame(50000.0, $posisi->persediaan);
        $this->assertSame(50000.0, $posisi->hutang_usaha);
        $this->assertSame(1050000.0, $posisi->total_aset);
        $this->assertSame(1000000.0, $posisi->total_modal, 'Berhutang tidak menambah kekayaan pemilik.');
    }

    public function test_jual_tunai_menambah_modal_sebesar_labanya(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'cost_price' => 5000, 'sell_price' => 8000]);
        $this->mulaiPembukuanDenganStokYangSudahAda();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 100000,
            'payment_method' => 'cash',
        ])->assertOk();

        $posisi = $this->seimbang('Penjualan tunai membuat neraca timpang.');

        // Terima 80.000, kembalian 20.000 -> kas bertambah 80.000.
        $this->assertSame(1080000.0, $posisi->kas);
        // 100 pcs awal senilai 500.000, terjual 10 -> tersisa 450.000.
        $this->assertSame(450000.0, $posisi->persediaan);
        // Modal awal 1.500.000 (kas 1 juta + stok 500 ribu) + laba (80.000 - 50.000).
        $this->assertSame(1530000.0, $posisi->total_modal);
    }

    /**
     * INI YANG PALING MUDAH SALAH.
     *
     * Stok pesanan DP sudah dipotong saat pesanan dibuat supaya tidak terjual dua kali, tapi
     * penjualannya belum boleh diakui karena barangnya belum diserahkan. Kalau dibiarkan
     * begitu saja, aset berkurang tanpa pengurangan di sisi mana pun dan neraca langsung
     * timpang sebesar nilai barangnya.
     *
     * Perlakuan yang benar: barangnya masih milik toko (tetap dihitung persediaan), dan uang
     * mukanya adalah kewajiban - toko masih berhutang barang kepada pembelinya.
     */
    public function test_pesanan_dp_mencatat_uang_muka_sebagai_kewajiban(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'cost_price' => 5000, 'sell_price' => 8000]);
        $this->mulaiPembukuanDenganStokYangSudahAda();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 30000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $posisi = $this->seimbang('Pesanan DP membuat neraca timpang.');

        $this->assertSame(1030000.0, $posisi->kas, 'Uang mukanya diterima.');
        $this->assertSame(500000.0, $posisi->persediaan, 'Barang pesanan masih milik toko, nilainya tetap di persediaan.');
        $this->assertSame(30000.0, $posisi->uang_muka, 'Uang muka adalah hutang barang kepada pembeli.');
        $this->assertSame(1500000.0, $posisi->total_modal, 'Belum ada laba - barangnya belum diserahkan.');
        $this->assertSame(0.0, $posisi->laba_ditahan, 'Uang muka bukan pendapatan.');
        $this->assertSame(50000.0, $posisi->sisa_tagihan_pesanan, 'Sisa tagihan 80.000 - 30.000.');
    }

    public function test_pesanan_dp_yang_dilunasi_berubah_jadi_laba(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'cost_price' => 5000, 'sell_price' => 8000]);
        $this->mulaiPembukuanDenganStokYangSudahAda();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 30000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $penjualan = \App\Models\Sale::firstOrFail();

        $this->actingAs($this->admin)->post("/kasir/waiting-list/{$penjualan->id}/pay", [
            'amount' => 50000,
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Pelunasan pesanan DP membuat neraca timpang.');

        $this->assertSame(0.0, $posisi->uang_muka, 'Kewajibannya lunas - barangnya sudah diserahkan.');
        $this->assertSame(450000.0, $posisi->persediaan, 'Barang keluar dari persediaan sekarang.');
        $this->assertSame(30000.0, $posisi->laba_ditahan, 'Labanya baru diakui di sini: 80.000 - 50.000.');
    }

    public function test_retur_mengembalikan_barang_dan_uangnya(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'cost_price' => 5000, 'sell_price' => 8000]);
        $this->mulaiPembukuanDenganStokYangSudahAda();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 80000,
            'payment_method' => 'cash',
        ])->assertOk();

        $penjualan = \App\Models\Sale::firstOrFail();
        $baris = $penjualan->items()->firstOrFail();

        $this->actingAs($this->admin)->post('/retur', [
            'sale_id' => $penjualan->id,
            'items' => [['sale_item_id' => $baris->id, 'qty' => 4]],
            'reason' => 'Barang rusak',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Retur membuat neraca timpang.');

        $this->assertSame(1048000.0, $posisi->kas, 'Uang 32.000 dikembalikan ke pembeli.');
        $this->assertSame(470000.0, $posisi->persediaan, '4 pcs kembali ke rak.');
        $this->assertSame(18000.0, $posisi->laba_ditahan, 'Laba tinggal 6 pcs x 3.000.');
    }

    public function test_bayar_hutang_mengurangi_kas_dan_kewajiban_bersamaan(): void
    {
        $this->mulaiPembukuan();
        $produk = $this->makeProduct(['stock' => 0, 'cost_price' => 0]);

        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => 10, 'unit_type' => 'base', 'price' => 5000]],
            'bayar' => 'hutang',
        ])->assertSessionHasNoErrors();

        $nota = Purchase::firstOrFail();

        $this->actingAs($this->admin)->post("/pembelian/{$nota->id}/bayar", [
            'amount' => 50000,
            'paid_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Pelunasan hutang membuat neraca timpang.');

        $this->assertSame(950000.0, $posisi->kas);
        $this->assertSame(0.0, $posisi->hutang_usaha);
        $this->assertSame(1000000.0, $posisi->total_modal, 'Membayar hutang bukan rugi - dua-duanya berkurang.');
    }

    /**
     * Prive mengurangi MODAL, bukan laba. Uang yang diambil pemilik untuk keperluan pribadi
     * bukan beban toko; toko tidak mendapat apa-apa sebagai gantinya.
     */
    public function test_prive_mengurangi_modal_bukan_laba(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out',
            'category' => 'ambil_pribadi',
            'amount' => 200000,
            'description' => 'Ambil untuk belanja rumah',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Prive membuat neraca timpang.');

        $this->assertSame(800000.0, $posisi->kas);
        $this->assertSame(200000.0, $posisi->prive);
        $this->assertSame(800000.0, $posisi->total_modal);
        $this->assertSame(0.0, $posisi->laba_ditahan, 'Prive tidak boleh muncul sebagai kerugian.');
    }

    public function test_setoran_modal_menambah_modal_bukan_laba(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in',
            'category' => 'modal_tambahan',
            'amount' => 500000,
            'description' => 'Tambah modal',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Setoran modal membuat neraca timpang.');

        $this->assertSame(1500000.0, $posisi->kas);
        $this->assertSame(500000.0, $posisi->tambahan_modal);
        $this->assertSame(0.0, $posisi->laba_ditahan, 'Menyetor uang sendiri bukan keuntungan.');
    }

    public function test_beban_operasional_mengurangi_kas_dan_laba(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out',
            'category' => 'sewa',
            'amount' => 300000,
            'description' => 'Sewa kios',
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Beban membuat neraca timpang.');

        $this->assertSame(700000.0, $posisi->kas);
        $this->assertSame(-300000.0, $posisi->laba_ditahan, 'Sewa adalah beban - benar-benar mengurangi laba.');
        $this->assertSame(700000.0, $posisi->total_modal);
    }

    /* ---------------------------------------------------------------- aset tetap */

    public function test_aset_tetap_masuk_neraca_dan_menyusut(): void
    {
        $this->mulaiPembukuan();

        FixedAsset::create([
            'name' => 'Komputer kasir',
            'acquired_at' => now()->subMonths(12)->toDateString(),
            'acquisition_cost' => 6000000,
            'useful_life_months' => 36,
        ]);

        $posisi = Akuntansi::posisiPada(now()->toDateString());

        // 6.000.000 / 36 bulan = 166.666,67 per bulan, 12 bulan berjalan.
        $this->assertEqualsWithDelta(4000000, $posisi->aset_tetap, 1, 'Nilai buku setelah 1 tahun dari 3 tahun umur.');
    }




    /**
     * CATATAN: pembelian peralatan tidak lagi diuji di berkas ini.
     *
     * Sejak 2.3.0 aset tetap dicatat lewat menu Kas (kategori `aset_tetap`), bukan lewat
     * formulir di halaman Neraca - satu jenis transaksi, satu jalan masuk. Pengujiannya
     * pindah ke KasAsetModalTest, termasuk pemeriksaan neraca tetap seimbang sesudahnya.
     */

    public function test_aset_tetap_tanpa_umur_manfaat_tidak_disusutkan(): void
    {
        $aset = FixedAsset::create([
            'name' => 'Etalase kaca',
            'acquired_at' => now()->subYears(5)->toDateString(),
            'acquisition_cost' => 2000000,
            'useful_life_months' => null,
        ]);

        $this->assertSame(0.0, $aset->penyusutanSampai(now()));
        $this->assertSame(2000000.0, $aset->nilaiBukuSampai(now()));
    }

    public function test_penyusutan_berhenti_setelah_umur_manfaat_habis(): void
    {
        $aset = FixedAsset::create([
            'name' => 'Printer',
            'acquired_at' => now()->subYears(10)->toDateString(),
            'acquisition_cost' => 1200000,
            'useful_life_months' => 24,
        ]);

        $this->assertSame(0.0, $aset->nilaiBukuSampai(now()), 'Nilai buku tidak boleh menjadi negatif.');
    }

    /* ------------------------------------------------------- skenario penuh, akhir */

    /**
     * Semua jenis transaksi dijalankan berurutan di satu toko, lalu neracanya diperiksa.
     *
     * Test-test di atas menguji satu transaksi sendiri-sendiri; yang ini menangkap kesalahan
     * yang baru muncul saat transaksinya bercampur.
     */
    public function test_skenario_lengkap_tetap_seimbang(): void
    {
        $this->mulaiPembukuan(2000000, 2000000);

        $kopi = $this->makeProduct(['name' => 'Kopi', 'stock' => 0, 'cost_price' => 0, 'sell_price' => 15000]);
        $gula = $this->makeProduct(['name' => 'Gula', 'stock' => 0, 'cost_price' => 0, 'sell_price' => 18000]);

        // 1. Kulakan tunai
        $this->beliTunai($kopi, 50, 10000);

        // 2. Kulakan kredit
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $gula->id, 'qty' => 40, 'unit_type' => 'base', 'price' => 12000]],
            'bayar' => 'hutang',
        ])->assertSessionHasNoErrors();

        // 3. Jual tunai
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [
                ['product_id' => $kopi->id, 'qty' => 20],
                ['product_id' => $gula->id, 'qty' => 10],
            ],
            'paid_amount' => 500000,
            'payment_method' => 'cash',
        ])->assertOk();

        // 4. Pesanan DP
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $kopi->id, 'qty' => 5]],
            'paid_amount' => 25000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        // 5. Retur sebagian dari penjualan tunai
        $tunai = \App\Models\Sale::where('order_status', 'completed')->firstOrFail();
        $this->actingAs($this->admin)->post('/retur', [
            'sale_id' => $tunai->id,
            'items' => [['sale_item_id' => $tunai->items()->first()->id, 'qty' => 3]],
            'reason' => 'Kemasan penyok',
        ])->assertSessionHasNoErrors();

        // 6. Cicil hutang
        $nota = Purchase::where('sisa_hutang', '>', 0)->firstOrFail();
        $this->actingAs($this->admin)->post("/pembelian/{$nota->id}/bayar", [
            'amount' => 200000,
            'paid_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        // 7. Bayar beban
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out', 'category' => 'gaji', 'amount' => 150000, 'description' => 'Gaji harian',
        ])->assertSessionHasNoErrors();

        // 8. Setor modal
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in', 'category' => 'modal_tambahan', 'amount' => 300000, 'description' => 'Tambah modal',
        ])->assertSessionHasNoErrors();

        // 9. Ambil prive
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out', 'category' => 'ambil_pribadi', 'amount' => 100000, 'description' => 'Keperluan pribadi',
        ])->assertSessionHasNoErrors();

        // 10. Beli peralatan pakai uang toko
        $this->actingAs($this->admin)->post('/laporan/neraca/aset', [
            'name' => 'Timbangan digital',
            'acquired_at' => now()->toDateString(),
            'acquisition_cost' => 400000,
            'useful_life_months' => 60,
            'dibayar_dari_kas' => 1,
        ])->assertSessionHasNoErrors();

        $posisi = $this->seimbang('Sembilan jenis transaksi bercampur membuat neraca timpang.');

        $this->assertGreaterThan(0, $posisi->total_aset);
        $this->assertGreaterThan(0, $posisi->hutang_usaha, 'Masih ada sisa hutang yang belum dicicil.');
        $this->assertSame(25000.0, $posisi->uang_muka);
    }


    /* --------------------------------------------------------------- tutup buku */

    /**
     * MEMBEKUKAN NERACA DUA KALI PADA TANGGAL YANG SAMA HARUS BERHASIL.
     *
     * Sebelum diperbaiki, penekanan kedua SELALU berujung galat 500:
     * "UNIQUE constraint failed: neraca_snapshots.tanggal".
     *
     * Sebabnya halus. Kolom `tanggal` di-cast 'date', dan Eloquent menyimpannya memakai
     * format tanggal bawaan model - 'Y-m-d H:i:s'. Jadi isi barisnya '2026-08-13 00:00:00',
     * sementara updateOrCreate mencarinya dengan '2026-08-13' apa adanya dari formulir.
     * Pencariannya tidak pernah cocok, Eloquent menyimpulkan barisnya belum ada, lalu INSERT
     * - dan menabrak batasan UNIQUE.
     *
     * Membekukan ulang adalah hal yang WAJAR dilakukan: angkanya berubah setiap ada transaksi
     * baru pada tanggal itu. Jadi cacat ini menutup fiturnya sama sekali setelah pemakaian
     * pertama.
     */
    public function test_membekukan_neraca_dua_kali_pada_tanggal_sama_memperbarui_bukan_menggandakan(): void
    {
        $this->mulaiPembukuan();
        $tanggal = now()->toDateString();

        $this->actingAs($this->admin)->post('/laporan/neraca/tutup-buku', [
            'tanggal' => $tanggal, 'note' => 'Pembekuan pertama',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, NeracaSnapshot::count());

        // Transaksi baru sesudah pembekuan pertama - inilah alasan orang membekukan ulang.
        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'in', 'category' => 'modal_tambahan', 'amount' => 500000, 'description' => 'Tambah modal',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/laporan/neraca/tutup-buku', [
            'tanggal' => $tanggal, 'note' => 'Pembekuan kedua',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, NeracaSnapshot::count(), 'Pembekuan kedua membuat baris baru, bukan memperbarui.');

        $snapshot = NeracaSnapshot::firstOrFail();
        $this->assertSame('Pembekuan kedua', $snapshot->note, 'Angkanya tidak ikut diperbarui.');
        $this->assertSame(1500000.0, $snapshot->total_modal, 'Setoran modal sesudah pembekuan pertama tidak ikut terekam.');
    }

    public function test_membekukan_neraca_menyimpan_seluruh_pos(): void
    {
        $this->mulaiPembukuan();
        $produk = $this->makeProduct(['stock' => 0, 'cost_price' => 0]);
        $this->beliTunai($produk, 10, 5000);

        $this->actingAs($this->admin)->post('/laporan/neraca/tutup-buku', [
            'tanggal' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $snapshot = NeracaSnapshot::firstOrFail();
        $posisi = Akuntansi::posisiPada(now()->toDateString());

        foreach (['kas', 'persediaan', 'aset_tetap', 'total_aset', 'hutang_usaha',
                  'total_kewajiban', 'modal_awal', 'tambahan_modal', 'prive',
                  'laba_ditahan', 'total_modal', 'selisih'] as $pos) {
            $this->assertSame(
                (float) $posisi->{$pos},
                (float) $snapshot->{$pos},
                "Pos {$pos} tidak tersimpan sama dengan neraca yang sedang ditampilkan."
            );
        }
    }

    /** Snapshot lama tetap terbaca di tabel riwayat sesudah pembekuan berikutnya. */
    public function test_riwayat_tutup_buku_tampil_di_halaman(): void
    {
        $this->mulaiPembukuan();

        foreach ([now()->subDays(2), now()->subDay(), now()] as $t) {
            $this->actingAs($this->admin)->post('/laporan/neraca/tutup-buku', [
                'tanggal' => $t->toDateString(),
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, NeracaSnapshot::count());

        $this->actingAs($this->admin)->get('/laporan/neraca')
            ->assertOk()
            ->assertSee(now()->format('d/m/Y'))
            ->assertSee(now()->subDays(2)->format('d/m/Y'));
    }

    public function test_kasir_tidak_bisa_membekukan_neraca(): void
    {
        $this->actingAs($this->kasir)->post('/laporan/neraca/tutup-buku', [
            'tanggal' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, NeracaSnapshot::count());
    }

    /* --------------------------------------------------------------------- halaman */


    /**
     * Aplikasi menghitungkan modal awal yang benar, karena pemilik toko tidak akan tahu.
     *
     * Diturunkan dari data toko sungguhan berisi 142 produk dan 43 transaksi: selisihnya
     * 173.219.500, dan itu persis nilai stok awal yang tidak pernah tercatat asal uangnya -
     * persediaan sekarang 145.894.100 ditambah HPP seluruh riwayat 27.325.400. Salah mengisi
     * angka ini berarti neracanya timpang selamanya tanpa ada yang bisa menjelaskan kenapa.
     */
    public function test_saran_modal_awal_membuat_neraca_seimbang(): void
    {
        // Stok yang sudah ada tanpa asal-usul - persis keadaan toko yang baru mulai membukukan.
        $this->makeProduct(['stock' => 100, 'cost_price' => 5000, 'sell_price' => 8000]);
        $this->mulaiPembukuan(0, 0);

        $sebelum = Akuntansi::posisiPada(now()->toDateString());
        $this->assertSame(500000.0, $sebelum->selisih, 'Stok tanpa asal-usul harus muncul sebagai selisih.');

        $saran = Akuntansi::saranModalAwal(now()->toDateString());
        $this->assertSame(500000.0, $saran);

        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->toDateString(),
            'saldo_awal_kas' => 0,
            'modal_awal' => $saran,
        ]);

        $this->seimbang('Angka yang disarankan aplikasi sendiri ternyata tidak menyeimbangkan neraca.');
    }

    /** Sarannya tetap benar walau modal awal sudah terisi sebagian. */
    public function test_saran_modal_awal_memperhitungkan_yang_sudah_terisi(): void
    {
        $this->makeProduct(['stock' => 100, 'cost_price' => 5000]);
        $this->mulaiPembukuan(200000, 200000);

        $saran = Akuntansi::saranModalAwal(now()->toDateString());

        $this->assertSame(700000.0, $saran, 'Modal 200.000 yang sudah terisi + stok 500.000.');

        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->toDateString(),
            'saldo_awal_kas' => 200000,
            'modal_awal' => $saran,
        ]);

        $this->seimbang('Saran meleset saat modal awal sudah terisi sebagian.');
    }

    public function test_halaman_neraca_menawarkan_angka_modal_awal(): void
    {
        $this->makeProduct(['stock' => 100, 'cost_price' => 5000]);
        $this->mulaiPembukuan(0, 0);

        $this->actingAs($this->admin)->get('/laporan/neraca')
            ->assertOk()
            ->assertSee('Pakai angka yang membuat neraca seimbang')
            ->assertSee('500.000');
    }

    public function test_halaman_neraca_terbuka_dan_menampilkan_persamaan(): void
    {
        $this->mulaiPembukuan();

        $this->actingAs($this->admin)->get('/laporan/neraca')
            ->assertOk()
            ->assertSee('Neraca')
            ->assertSee('Total Aset')
            ->assertSee('Total Kewajiban dan Modal');
    }

    public function test_kasir_tidak_bisa_membuka_neraca(): void
    {
        $this->actingAs($this->kasir)->get('/laporan/neraca')->assertForbidden();
    }
}
