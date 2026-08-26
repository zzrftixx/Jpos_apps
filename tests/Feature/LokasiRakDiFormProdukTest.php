<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rack;
use App\Models\RackSlot;
use App\Support\ProductCatalog;
use Tests\JposTestCase;

/**
 * UAT: memilih lokasi rak langsung dari form Tambah/Edit Produk.
 *
 * KENAPA ADA DI FORM PRODUK, padahal Planogram punya halamannya sendiri. Saat memasukkan
 * barang baru, pemilik toko sedang memegang barangnya dan tahu persis mau ditaruh di mana.
 * Memaksanya membuka halaman lain berarti langkah itu ditunda - dan peta rak yang setengah
 * terisi lebih menyesatkan daripada tidak ada peta sama sekali: karyawan yang mencari akan
 * menemukan rak kosong.
 *
 * Aturannya SAMA PERSIS dengan halaman Planogram, karena batasannya memang di database:
 * satu produk hanya menempati satu kotak. Dua jalan masuk untuk satu aturan adalah cara
 * paling umum aturan itu jadi berbeda di salah satunya.
 */
class LokasiRakDiFormProdukTest extends JposTestCase
{
    private Rack $rak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rak = Rack::create(['name' => 'Rak A', 'rows' => 2, 'cols' => 3]);
    }

    private function simpanProduk(array $ubah = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/master/produk', array_merge([
            'name' => 'Kopi Sachet',
            'type' => 'barang',
            'unit' => 'Pcs',
            'cost_price' => 5000,
            'sell_price' => 8000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => 1,
            'rack_id' => $this->rak->id,
            'rack_slot' => '0-1',
        ], $ubah));
    }

    /**
     * "Jumlah muka" dicabut dari layar - di form produk MAUPUN di halaman Planogram.
     *
     * Yang dibutuhkan pemilik toko cuma satu: barang ini ada di rak mana, bagian mana.
     * Berapa banyak yang menghadap ke depan tidak menjawab pertanyaan siapa pun - kalau
     * raknya terlihat kosong, stoknya diambil dari gudang, dan jumlah yang sebenarnya
     * sudah dijaga Master Produk. Satu kolom isian yang tidak dipakai tetap menagih
     * perhatian setiap kali barang baru dimasukkan.
     *
     * Kolomnya masih berdiri di database dan itu disengaja: menghapus kolom di SQLite
     * berpotensi membangun ulang tabel di database toko yang sedang beroperasi.
     */
    public function test_jumlah_muka_tidak_ada_lagi_di_form_produk_maupun_planogram(): void
    {
        $form = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="facings"', $form, 'Jumlah muka kembali di form produk.');
        $this->assertStringNotContainsString('Jumlah Muka', $form);

        // Yang dicabut hanya jumlahnya - letaknya tetap bisa diisi dari sini.
        $this->assertStringContainsString('name="rack_id"', $form);
        $this->assertStringContainsString('name="rack_slot"', $form);

        $peta = $this->actingAs($this->admin)
            ->get(route('planogram.edit', $this->rak))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="facings"', $peta, 'Jumlah muka kembali di halaman Planogram.');
        $this->assertStringNotContainsString('Jumlah Muka', $peta);
    }

    /* ------------------------------------------------------------ menaruh produk */

    public function test_produk_baru_bisa_langsung_ditaruh_di_rak(): void
    {
        $this->simpanProduk()->assertSessionHasNoErrors();

        $slot = RackSlot::firstOrFail();
        $produk = Product::where('name', 'Kopi Sachet')->firstOrFail();

        $this->assertSame($produk->id, $slot->product_id);
        $this->assertSame($this->rak->id, $slot->rack_id);
        $this->assertSame(0, $slot->row);
        $this->assertSame(1, $slot->col);
        $this->assertSame('Rak A 1-2', $slot->label());
    }

    public function test_produk_tanpa_rak_tidak_menempati_kotak_apa_pun(): void
    {
        $this->simpanProduk(['rack_id' => '', 'rack_slot' => ''])->assertSessionHasNoErrors();

        $this->assertSame(0, RackSlot::count());
        $this->assertSame(1, Product::where('name', 'Kopi Sachet')->count(), 'Produknya ikut gagal tersimpan.');
    }

    /** Rak dipilih tapi kotaknya belum - jangan menempatkan asal-asalan. */
    public function test_rak_dipilih_tanpa_kotak_tidak_menempatkan_apa_pun(): void
    {
        $this->simpanProduk(['rack_slot' => ''])->assertSessionHasNoErrors();

        $this->assertSame(0, RackSlot::count());
    }

    /* -------------------------------------------------------------- memindahkan */

    /** Mengedit produk ke kotak lain MEMINDAHKAN, bukan menggandakan. */
    public function test_mengedit_produk_ke_kotak_lain_memindahkannya(): void
    {
        $this->simpanProduk()->assertSessionHasNoErrors();
        $produk = Product::where('name', 'Kopi Sachet')->firstOrFail();

        $this->actingAs($this->admin)->put("/master/produk/{$produk->id}", [
            'name' => 'Kopi Sachet', 'type' => 'barang', 'unit' => 'Pcs',
            'cost_price' => 5000, 'sell_price' => 8000, 'stock' => 10, 'min_stock' => 2, 'is_active' => 1,
            'rack_id' => $this->rak->id, 'rack_slot' => '1-2',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, RackSlot::count(), 'Produk yang sama menempati dua kotak sekaligus.');

        $slot = RackSlot::firstOrFail();
        $this->assertSame(1, $slot->row);
        $this->assertSame(2, $slot->col);
    }

    /** Mengosongkan pilihan rak saat edit melepas produknya dari rak. */
    public function test_mengosongkan_rak_saat_edit_melepas_produk_dari_rak(): void
    {
        $this->simpanProduk()->assertSessionHasNoErrors();
        $produk = Product::where('name', 'Kopi Sachet')->firstOrFail();

        $this->actingAs($this->admin)->put("/master/produk/{$produk->id}", [
            'name' => 'Kopi Sachet', 'type' => 'barang', 'unit' => 'Pcs',
            'cost_price' => 5000, 'sell_price' => 8000, 'stock' => 10, 'min_stock' => 2, 'is_active' => 1,
            'rack_id' => '', 'rack_slot' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, RackSlot::count());
    }

    /**
     * Menaruh produk di kotak yang sudah ditempati produk lain akan MENGGANTIKAN isinya.
     *
     * Dropdown di form memang tidak menawarkan kotak terisi, tapi pengiriman langsung bisa
     * saja terjadi - dan yang menolak tidak boleh batasan UNIQUE database, karena pesannya
     * tidak bisa dipahami siapa pun.
     */
    public function test_menaruh_di_kotak_yang_sudah_terisi_menggantikan_isinya(): void
    {
        $this->simpanProduk()->assertSessionHasNoErrors();
        $lama = Product::where('name', 'Kopi Sachet')->firstOrFail();

        $this->simpanProduk(['name' => 'Gula Pasir'])->assertSessionHasNoErrors();
        $baru = Product::where('name', 'Gula Pasir')->firstOrFail();

        $this->assertSame(1, RackSlot::count());
        $this->assertSame($baru->id, RackSlot::firstOrFail()->product_id);
        $this->assertSame(0, RackSlot::where('product_id', $lama->id)->count());
    }

    /* ------------------------------------------------------------------ kaitannya */

    /**
     * Katalog kasir membawa label lokasi rak. Kalau cachenya tidak dibangun ulang, kasir
     * masih melihat lokasi lama - tanpa error apa pun.
     */
    public function test_menaruh_produk_dari_form_langsung_terlihat_di_katalog_kasir(): void
    {
        $this->simpanProduk()->assertSessionHasNoErrors();
        $produk = Product::where('name', 'Kopi Sachet')->firstOrFail();

        $baris = collect(app(ProductCatalog::class)->forCart())->firstWhere('id', $produk->id);

        $this->assertSame('Rak A 1-2', $baris['rack_location'], 'Kasir masih melihat katalog yang basi.');
    }

    public function test_form_produk_menampilkan_pilihan_rak(): void
    {
        $this->actingAs($this->admin)->get('/master/produk')
            ->assertOk()
            ->assertSee('Lokasi Rak')
            ->assertSee('Rak A');
    }

    /** Halaman Planogram tetap bekerja - dua jalan masuk, satu aturan. */
    public function test_halaman_planogram_tetap_bisa_memindahkan_produk_yang_ditaruh_dari_form(): void
    {
        $this->simpanProduk()->assertSessionHasNoErrors();
        $produk = Product::where('name', 'Kopi Sachet')->firstOrFail();

        $this->actingAs($this->admin)->post("/master/planogram/{$this->rak->id}/slot", [
            'row' => 1, 'col' => 0, 'product_id' => $produk->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, RackSlot::count());

        $slot = RackSlot::firstOrFail();
        $this->assertSame(1, $slot->row);
        $this->assertSame(0, $slot->col);
    }

    /** Produk jasa tidak menempati rak - tidak punya wujud fisik. */
    public function test_produk_jasa_tidak_menampilkan_pilihan_rak(): void
    {
        $html = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();

        $this->assertStringContainsString("type === 'barang' && daftarRak.length > 0", $html,
            'Bagian Lokasi Rak tidak dibatasi ke produk barang.');
    }
}
