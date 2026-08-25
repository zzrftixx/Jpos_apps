<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\Route;
use Tests\JposTestCase;

/**
 * Penjaga terhadap pengerasan yang hilang tanpa suara.
 *
 * Fitur-fitur terbaru diambil dari salinan JPOS yang dibangun dari baseline LEBIH TUA, dan
 * baseline itu belum punya sebagian besar perbaikan yang sudah kita kerjakan. Menyalin satu
 * berkas utuh dari sana akan membawa serta baseline lamanya - dan yang berbahaya, sebagian
 * besar kerusakannya TIDAK menimbulkan error apa pun. Aplikasi tetap jalan, cuma diam-diam
 * kembali rusak.
 *
 * Berkas ini memeriksa hal-hal yang seperti itu.
 */
class RegresiPengerasanTest extends JposTestCase
{
    /**
     * Aplikasi ini dipaketkan jadi .exe untuk komputer kasir yang sering offline. Kalau ada
     * satu saja aset yang diambil dari internet, halaman kehilangan CSS dan SELURUH tombol,
     * modal, serta keranjang kasir mati total karena Alpine tidak pernah termuat.
     */
    public function test_tidak_ada_halaman_yang_memuat_aset_dari_internet(): void
    {
        $this->makeProduct();

        $halaman = [
            '/dashboard',
            '/master/produk',
            '/master/satuan',
            '/kasir',
            '/barcode',
            '/pengaturan/template-struk',
            '/pengaturan/template-barcode',
            '/pengaturan/tentang',
            '/laporan/stok', '/pembelian', '/laporan/neraca', '/master/planogram',
        ];

        foreach ($halaman as $url) {
            $isi = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            foreach (['cdn.tailwindcss.com', 'cdn.jsdelivr.net', 'unpkg.com', 'code.iconify.design'] as $cdn) {
                $this->assertStringNotContainsString(
                    $cdn,
                    $isi,
                    "Halaman {$url} memuat aset dari {$cdn} - aplikasi akan mati total saat komputer kasir offline."
                );
            }
        }
    }

    /** Halaman login pun harus bisa dibuka tanpa internet. */
    public function test_halaman_login_tidak_memuat_aset_dari_internet(): void
    {
        $isi = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('cdn.', $isi);
        $this->assertStringContainsString('vendor/jpos.css', $isi);
    }

    /**
     * Gambar dilayani MediaController, bukan symlink public/storage. Paket .exe tidak punya
     * symlink itu, dan pernah membuat logo toko hilang di halaman login, pratinjau template
     * struk, DAN struk cetak - tanpa satu pun pesan error.
     */
    public function test_rute_media_masih_terdaftar(): void
    {
        $this->assertTrue(Route::has('media'), 'Rute media hilang - gambar produk & logo toko akan 404 di paket .exe.');

        $produk = $this->makeProduct();
        $produk->forceFill(['image' => 'products/x.png'])->save();

        $this->assertStringContainsString('/media/products/x.png', $produk->fresh()->image_url);
    }

    /**
     * Katalog kasir disimpan di cache dalam bentuk terserialisasi. Kalau satuan tambahannya
     * masih berupa Collection (bukan array biasa), isinya berubah jadi __PHP_Incomplete_Class
     * saat dibaca kembali - dan kasir melihat produk tanpa satuan sama sekali.
     */
    public function test_katalog_selamat_melewati_serialisasi_cache(): void
    {
        $produk = $this->makeProduct();
        $this->makeProductUnit($produk, 'Dus', 12, 24000);

        $katalog = app(ProductCatalog::class)->forCart();
        $dibacaUlang = unserialize(serialize($katalog));

        $baris = collect($dibacaUlang)->firstWhere('id', $produk->id);

        $this->assertIsArray($baris['additional_units']);
        $this->assertIsArray($baris['additional_units'][0]);
        $this->assertSame('Dus', $baris['additional_units'][0]['unit_name']);
    }

    /**
     * Nomor nota memakai MAX+CAST, bukan membaca 4 karakter terakhir. Versi lama menghasilkan
     * HTTP 500 tepat saat kasir menekan Bayar pada transaksi ke-10.000 dalam satu hari.
     */
    public function test_nomor_nota_tidak_kembali_ke_awal_setelah_9999(): void
    {
        $produk = $this->makeProduct(['stock' => 100000]);

        $prefix = 'INV' . now()->format('ymd');

        \App\Models\Sale::create([
            'invoice_no' => $prefix . '9999',
            'user_id' => $this->admin->id,
            'subtotal' => 1000, 'total' => 1000, 'paid_amount' => 1000,
            'payment_method' => 'cash', 'status' => 'completed', 'order_status' => 'completed',
        ]);

        $this->assertSame($prefix . '10000', \App\Models\Sale::generateInvoiceNo());
    }

    /** Middleware penormal angka harus tetap terpasang - jaring pengaman kalau JS mati. */
    public function test_middleware_penormal_angka_masih_terpasang(): void
    {
        $this->actingAs($this->admin)->post('/master/produk', [
            'name' => 'Uji Normalisasi',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => '1.250.000',
            'sell_price' => '1.500.000',
            'stock' => '1.200',
            'is_active' => '1',
            'is_taxable' => '1',
        ])->assertSessionHasNoErrors();

        $produk = Product::where('name', 'Uji Normalisasi')->firstOrFail();

        $this->assertSame(1250000.0, (float) $produk->cost_price);
        $this->assertSame(1200.0, (float) $produk->stock);
    }

    /** Header keamanan dan penanda sesi untuk JPOS.exe harus tetap ada. */
    public function test_header_keamanan_dan_penanda_sesi_masih_ada(): void
    {
        $respons = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $this->assertNotNull($respons->headers->get('X-Content-Type-Options'));

        $this->get('/jpos-detak')->assertOk();
        $this->get('/jpos-tutup')->assertOk();
    }

    /** Produk yang masih ada di pesanan aktif tidak boleh bisa dihapus. */
    public function test_produk_yang_masih_dipesan_tidak_bisa_dihapus(): void
    {
        $produk = $this->makeProduct(['stock' => 100, 'sell_price' => 10000]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 5000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        $this->actingAs($this->admin)->delete("/master/produk/{$produk->id}");

        $this->assertNotNull(Product::find($produk->id), 'Produk yang masih dipesan tidak boleh terhapus.');
    }

    /**
     * Versi yang dilihat client harus sama dengan versi paket yang benar-benar dipasang.
     *
     * Angkanya pernah ditulis di tiga tempat dan ketiganya tidak cocok: berkas VERSION 2.1.0,
     * config/jpos.php 2.0.0, halaman Tentang 1.0. Akibatnya tidak ada cara memastikan versi
     * mana yang sedang berjalan - padahal itu hal pertama yang ditanyakan saat ada laporan
     * masalah dari client.
     */
    public function test_versi_yang_ditampilkan_sama_dengan_berkas_version(): void
    {
        $berkas = trim(file_get_contents(base_path('VERSION')));

        $this->assertNotSame('', $berkas, 'Berkas VERSION kosong.');
        $this->assertSame($berkas, config('jpos.version'), 'config/jpos.php tidak mengikuti berkas VERSION.');

        $this->actingAs($this->admin)->get('/pengaturan/tentang')
            ->assertOk()
            ->assertSee('Versi ' . $berkas);
    }
}
