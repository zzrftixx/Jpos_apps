<?php

namespace Tests\Feature;

use App\Models\Setting;
use Tests\JposTestCase;

class ModulKasirKlasikTest extends JposTestCase
{
    /**
     * Pengaturan tampilan modul kasir dapat menyimpan layout modern dan klasik.
     */
    public function test_pengaturan_tampilan_modul_kasir_dapat_menyimpan_layout(): void
    {
        // Simpan ke klasik
        $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'layout' => 'klasik',
            'default_view' => 'gambar',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('klasik', Setting::kasirLayout());

        // Simpan ke modern
        $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'layout' => 'modern',
            'default_view' => 'gambar',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('modern', Setting::kasirLayout());
    }

    /**
     * Halaman kasir dengan layout klasik merender elemen kasir klasik dan tombol bantuan cepat.
     */
    public function test_halaman_kasir_klasik_merender_elemen_dan_tombol_bantuan_cepat(): void
    {
        $this->makeProduct(['name' => 'Minyak Bimoli 1L', 'sku' => 'B02', 'sell_price' => 19500, 'stock' => 50]);

        Setting::set('kasir_display', ['layout' => 'klasik', 'default_view' => 'gambar']);

        $res = $this->actingAs($this->kasir)->get(route('kasir.index', ['layout' => 'klasik']));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('Aplikasi Penjualan', $html);
        $this->assertStringContainsString('Modul Kasir', $html);
        $this->assertStringContainsString('F2', $html);
        $this->assertStringContainsString('Cari', $html);
        $this->assertStringContainsString('F7', $html);
        $this->assertStringContainsString('Clear', $html);
        $this->assertStringContainsString('F8', $html);
        $this->assertStringContainsString('Bayar', $html);
        $this->assertStringContainsString('klasik-total-box-jadul', $html);
        $this->assertStringContainsString('modalFilteredProducts', $html);
        $this->assertStringContainsString('focusQtyRow', $html);
        $this->assertStringContainsString('selesaiEditQty', $html);
        $this->assertStringContainsString('cart-qty-', $html);

        // Navigasi produk di tabel dengan panah atas / bawah dan hapus item terpilih dengan Del
        $this->assertStringContainsString('selectedCartIndex', $html);
        $this->assertStringContainsString('pilihBarisCart', $html);
        $this->assertStringContainsString('navigasiCartPrev', $html);
        $this->assertStringContainsString('navigasiCartNext', $html);
        $this->assertStringContainsString('hapusItemTerpilih', $html);
        $this->assertStringContainsString('Geser Item', $html);
        $this->assertStringContainsString('Hapus Item', $html);
        $this->assertStringContainsString('Del', $html);

        // Auto scroll dan fit container tabel transaksi saat banyak item (> 9 produk)
        $this->assertStringContainsString('cartTableContainer', $html);
        $this->assertStringContainsString('klasik-cart-scroll', $html);
        $this->assertStringContainsString('scrollCartRowIntoView', $html);
        $this->assertStringContainsString('navigasiCartJump', $html);
        $this->assertStringContainsString('cart-row-', $html);
        $this->assertStringContainsString('Daftar Belanja Transaksi', $html);

        // Highlight full baris biru muda solid tebal (bukan hanya di qty) dan viewport static
        $this->assertStringContainsString('row-selected', $html);
        $this->assertStringContainsString('.klasik-table-jadul tbody tr.row-selected,', $html);
        $this->assertStringContainsString('#bae6fd', $html);
        $this->assertStringContainsString('max-h-screen', $html);

        // Pop-up Pembayaran Klasik sesuai gambar
        $this->assertStringContainsString('TOTAL BELANJA (Rp)', $html);
        $this->assertStringContainsString('JUMLAH BAYAR', $html);
        $this->assertStringContainsString('JUMLAH KEMBALI', $html);
        $this->assertStringContainsString('JUMLAH QTY', $html);
        $this->assertStringContainsString('Uang Pas', $html);
        $this->assertStringContainsString('selesaiBayar', $html);
        $this->assertStringContainsString('pasUang', $html);
        $this->assertStringContainsString('tutupModalBayar', $html);

        // Pilihan Metode Pembayaran di Pop-up Bayar [F8]
        $this->assertStringContainsString('METODE PEMBAYARAN', $html);
        $this->assertStringContainsString('pop-bayar-metode-wrap', $html);
        $this->assertStringContainsString('setPaymentMethod', $html);
        $this->assertStringContainsString('Tunai', $html);
        $this->assertStringContainsString('Debit', $html);
        $this->assertStringContainsString('QRIS', $html);
        $this->assertStringContainsString('Transfer', $html);

        // Logo JPOS di pojok kiri atas modul kasir klasik menggantikan info toko (agak besar)
        $this->assertStringContainsString('logo-jpos.png', $html);
        $this->assertStringContainsString('klasik-main-logo', $html);
        $this->assertStringContainsString('klasik-main-logo-wrap', $html);
        $this->assertStringContainsString('Logo JPOS', $html);
        $this->assertStringNotContainsString('INFO TOKO', $html);
    }

    /**
     * Rute /transaksi berhasil mengalihkan (redirect) langsung ke /kasir.
     */
    public function test_rute_transaksi_redirect_ke_kasir(): void
    {
        $res = $this->actingAs($this->kasir)->get('/transaksi');
        $res->assertRedirect('/kasir');
    }

    /**
     * Halaman kasir klasik merender dropdown rekomendasi instan dan mendukung harga grosir minimal 3.
     */
    public function test_kasir_klasik_mendukung_rekomendasi_instan_dan_harga_grosir(): void
    {
        $this->makeProduct([
            'name' => 'Minyak Bimoli 1L',
            'sku' => 'B02',
            'sell_price' => 19500,
            'wholesale_price' => 18000,
            'wholesale_min_qty' => 3,
            'stock' => 100,
        ]);

        Setting::set('kasir_display', ['layout' => 'klasik', 'default_view' => 'gambar']);

        $res = $this->actingAs($this->kasir)->get(route('kasir.index', ['layout' => 'klasik']));
        $res->assertStatus(200);

        $html = $res->getContent();

        // Rekomendasi instan saat mengetik di search bar
        $this->assertStringContainsString('quickRecommendations', $html);
        $this->assertStringContainsString('onSearchInput', $html);
        $this->assertStringContainsString('highlightQuickNext', $html);
        $this->assertStringContainsString('highlightQuickPrev', $html);
        $this->assertStringContainsString('navigasiQuickDown', $html);
        $this->assertStringContainsString('navigasiQuickUp', $html);
        $this->assertStringContainsString('scrollQuickIntoView', $html);
        $this->assertStringContainsString('onItemMouseMove', $html);
        $this->assertStringContainsString('quickDropdownList', $html);
        $this->assertStringContainsString('pilihRekomendasi', $html);
        $this->assertStringContainsString('handleSearchEnter', $html);
        $this->assertStringContainsString('klasik-quick-dropdown', $html);

        // Produk dengan harga grosir minimal 3 ada dalam katalog
        $this->assertStringContainsString('Minyak Bimoli 1L', $html);
        $this->assertStringContainsString('18000', $html);
        // Pop-up modal pencarian produk [F2] menampilkan Harga Grosir & Min. Pembelian dengan highlight biru muda
        $this->assertStringContainsString('Harga Grosir', $html);
        $this->assertStringContainsString('Min. Pembelian', $html);
        $this->assertStringContainsString('klasik-f2-table', $html);
        $this->assertStringContainsString('.klasik-f2-table tbody tr.row-selected,', $html);
        $this->assertStringContainsString('modalTableContainer', $html);
        $this->assertStringContainsString('klasik-f2-table-container', $html);
        $this->assertStringContainsString('klasik-f2-search-wrap', $html);
        $this->assertStringContainsString('klasik-f2-search-icon', $html);
        $this->assertStringContainsString('onF2MouseMove', $html);
        $this->assertStringContainsString('f2-btn-pilih', $html);

        // Running text toko default
        $this->assertStringContainsString('JPOS by JaylaTech', $html);
    }

    /**
     * Running text pada kasir klasik dinamis mengambil nama dan alamat toko dari setting store_profile.
     */
    public function test_running_text_kasir_klasik_mengambil_nama_dan_alamat_toko_dari_setting(): void
    {
        Setting::set('store_profile', [
            'name' => 'TOKO JIREH',
            'address' => 'Wonorejo - Balak',
            'phone' => '08123456789',
        ]);
        Setting::set('kasir_display', ['layout' => 'klasik', 'default_view' => 'gambar']);

        $res = $this->actingAs($this->kasir)->get(route('kasir.index', ['layout' => 'klasik']));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('TOKO JIREH', $html);
        $this->assertStringContainsString('(Wonorejo - Balak)', $html);
        $this->assertStringContainsString('08123456789', $html);
        $this->assertStringContainsString('klasik-running-brand-jadul">TOKO JIREH</strong>', $html);
    }

    /**
     * Modal bayar kasir klasik menggunakan shortcut F1, F2, F3, F4 saja agar tidak bentrok dengan pengetikan angka nominal.
     */
    public function test_modal_bayar_metode_pembayaran_menggunakan_f1_f2_f3_f4(): void
    {
        Setting::set('kasir_display', ['layout' => 'klasik', 'default_view' => 'gambar']);

        $res = $this->actingAs($this->kasir)->get(route('kasir.index', ['layout' => 'klasik']));
        $res->assertStatus(200);

        $html = $res->getContent();

        // 4 Tombol metode pembayaran dengan badge F1, F2, F3, F4
        $this->assertStringContainsString('F1</span>', $html);
        $this->assertStringContainsString('F2</span>', $html);
        $this->assertStringContainsString('F3</span>', $html);
        $this->assertStringContainsString('F4</span>', $html);
        $this->assertStringContainsString('Uang Cash</span>', $html);
        $this->assertStringContainsString('Kartu EDC</span>', $html);
        $this->assertStringContainsString('Scan QR</span>', $html);
        $this->assertStringContainsString('Bank / VA</span>', $html);

        // Directive shortcut pada input bayar
        $this->assertStringContainsString('@keydown.f1.prevent="setPaymentMethod(\'tunai\')"', $html);
        $this->assertStringContainsString('@keydown.f2.prevent="setPaymentMethod(\'debit\')"', $html);
        $this->assertStringContainsString('@keydown.f3.prevent="setPaymentMethod(\'qris\')"', $html);
        $this->assertStringContainsString('@keydown.f4.prevent="setPaymentMethod(\'transfer\')"', $html);

        // Info shortcut di footer modal bayar
        $this->assertStringContainsString('[F1]</strong> Tunai', $html);
        $this->assertStringContainsString('[F2]</strong> Debit', $html);
        $this->assertStringContainsString('[F3]</strong> QRIS', $html);
        $this->assertStringContainsString('[F4]</strong> Transfer', $html);
    }
}


