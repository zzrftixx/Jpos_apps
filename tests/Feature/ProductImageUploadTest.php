<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\JposTestCase;

/**
 * Regresi untuk penyebab langsung HTTP 500 di build portable.
 *
 * Log produksi: "Allowed memory size of 536870912 bytes exhausted (tried to allocate
 * 447897600 bytes) at ProductController.php:197" - yaitu imagecreatefromstring().
 * Gambar beresolusi ekstrem harus ditolak validasi SEBELUM menyentuh GD.
 */
class ProductImageUploadTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function productPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Produk Uji Gambar',
            'type' => 'barang',
            'cost_price' => 5000,
            'sell_price' => 9000,
            'stock' => 10,
        ], $override);
    }

    public function test_gambar_beresolusi_ekstrem_ditolak_validasi_bukan_500(): void
    {
        // 12000 x 12000 px = 144 megapiksel. Sebagai bitmap truecolor GD butuh ~549 MB,
        // persis kelas gambar yang dulu menjatuhkan server (log: minta 447 MB, limit 512 MB).
        $bomb = $this->hugeDimensionPng(12000, 12000, 'foto-raksasa.png');

        $response = $this->actingAs($this->admin)
            ->from('/master/produk')
            ->post('/master/produk', $this->productPayload(['image' => $bomb]));

        $response->assertRedirect('/master/produk');
        $response->assertSessionHasErrors('image');

        $this->assertStringContainsString(
            'Resolusi Gambar produk terlalu besar',
            session('errors')->first('image'),
            'Pesan penolakan harus berbahasa Indonesia dan menjelaskan apa yang harus dilakukan.'
        );

        $this->assertDatabaseCount('products', 0);
    }

    public function test_gambar_wajar_tetap_tersimpan_dan_diperkecil(): void
    {
        // Lebih besar dari batas 1000 px supaya jalur resize benar-benar dieksekusi.
        $photo = UploadedFile::fake()->image('produk.jpg', 2400, 1600);

        $response = $this->actingAs($this->admin)
            ->post('/master/produk', $this->productPayload(['image' => $photo]));

        $response->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertNotNull($product->image, 'Path gambar harus tersimpan di kolom image.');

        $fullPath = storage_path('app/public/' . $product->image);
        $this->assertFileExists($fullPath);

        [$width, $height] = getimagesize($fullPath);
        $this->assertSame(1000, $width, 'Sisi terpanjang harus diperkecil ke 1000 px.');
        $this->assertSame(667, $height, 'Rasio aspek harus dipertahankan.');
    }

    public function test_gambar_lebih_kecil_dari_batas_tidak_diperbesar(): void
    {
        $photo = UploadedFile::fake()->image('kecil.jpg', 300, 200);

        $this->actingAs($this->admin)
            ->post('/master/produk', $this->productPayload(['image' => $photo]))
            ->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        [$width, $height] = getimagesize(storage_path('app/public/' . $product->image));

        $this->assertSame(300, $width);
        $this->assertSame(200, $height);
    }

    public function test_png_transparan_tetap_png_dan_mempertahankan_alpha(): void
    {
        $png = UploadedFile::fake()->image('transparan.png', 1600, 1200);

        $this->actingAs($this->admin)
            ->post('/master/produk', $this->productPayload(['image' => $png]))
            ->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertStringEndsWith('.png', $product->image);

        $info = getimagesize(storage_path('app/public/' . $product->image));
        $this->assertSame(IMAGETYPE_PNG, $info[2], 'File .png harus benar-benar berisi data PNG.');
    }

    /**
     * Versi lama memilih ekstensi .webp tapi tetap memanggil imagejpeg(), sehingga
     * menghasilkan file .webp yang isinya sebenarnya data JPEG.
     */
    public function test_webp_menghasilkan_data_webp_bukan_jpeg_bersamaran_ekstensi(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD tanpa dukungan WebP.');
        }

        $webp = UploadedFile::fake()->image('gambar.webp', 1400, 1400);

        $this->actingAs($this->admin)
            ->post('/master/produk', $this->productPayload(['image' => $webp]))
            ->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertStringEndsWith('.webp', $product->image);

        $info = getimagesize(storage_path('app/public/' . $product->image));
        $this->assertSame(IMAGETYPE_WEBP, $info[2], 'File .webp harus benar-benar berisi data WebP.');
    }

    public function test_file_bukan_gambar_ditolak(): void
    {
        $pdf = UploadedFile::fake()->create('dokumen.pdf', 200, 'application/pdf');

        $this->actingAs($this->admin)
            ->post('/master/produk', $this->productPayload(['image' => $pdf]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_logo_toko_memakai_perlindungan_yang_sama(): void
    {
        $bomb = $this->hugeDimensionPng(12000, 9000, 'logo-raksasa.png');

        $this->actingAs($this->admin)
            ->from('/pengaturan/profil-toko')
            ->post('/pengaturan/profil-toko', [
                'name' => 'Toko Uji',
                'logo' => $bomb,
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_logo_toko_wajar_tersimpan(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 1200, 1200);

        $this->actingAs($this->admin)
            ->post('/pengaturan/profil-toko', [
                'name' => 'Toko Uji',
                'logo' => $logo,
            ])
            ->assertSessionHasNoErrors();

        $profile = \App\Models\Setting::get('store_profile', []);
        $this->assertNotEmpty($profile['logo'] ?? null);
        $this->assertFileExists(storage_path('app/public/' . $profile['logo']));
    }
}
