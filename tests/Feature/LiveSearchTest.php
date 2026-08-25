<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * Menjaga pencarian langsung (live search) di seluruh daftar berhalaman.
 *
 * Mekanismenya menukar isi blok [data-live-results] dengan hasil fetch ke URL yang sama.
 * Artinya ada dua hal yang harus selalu ada di setiap halaman: form ditandai
 * data-live-search, dan blok hasil ditandai data-live-results. Kalau salah satu hilang
 * karena penyuntingan berikutnya, pencarian diam-diam kembali menuntut tombol "Cari"
 * tanpa ada yang error - persis jenis kemunduran yang sulit disadari.
 *
 * Perilaku penyaringannya sendiri (server-side) juga diuji di sini supaya penanda dan
 * hasilnya dijamin sejalan.
 */
class LiveSearchTest extends JposTestCase
{
    /** @return array<string, array{0: string}> */
    public static function halamanPencarian(): array
    {
        return [
            'produk' => ['/master/produk'],
            'kategori' => ['/master/kategori'],
            'satuan' => ['/master/satuan'],
            'supplier' => ['/master/supplier'],
            'pelanggan' => ['/master/pelanggan'],
            'user' => ['/manajemen/user'],
            'cetak barcode' => ['/barcode'],
            'waiting list' => ['/kasir/waiting-list'],
            'laporan stok' => ['/laporan/stok'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('halamanPencarian')]
    public function test_halaman_punya_penanda_pencarian_langsung(string $url): void
    {
        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('data-live-search', $html,
            "Form pencarian di {$url} kehilangan penanda data-live-search.");
        $this->assertStringContainsString('data-live-results', $html,
            "Blok hasil di {$url} kehilangan penanda data-live-results.");
    }

    public function test_modul_pencarian_langsung_dimuat_di_semua_halaman(): void
    {
        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('jpos-live-search.js', $html);
    }

    public function test_pencarian_produk_menyaring_hasil(): void
    {
        $this->makeProduct(['name' => 'Sabun Mandi Lifebuoy']);
        $this->makeProduct(['name' => 'Sampo Clear']);

        $html = $this->actingAs($this->admin)->get('/master/produk?q=Sabun')->assertOk()->getContent();

        $this->assertStringContainsString('Sabun Mandi Lifebuoy', $html);
        $this->assertStringNotContainsString('Sampo Clear', $html);
    }

    public function test_pencarian_produk_juga_mencari_sku_dan_barcode(): void
    {
        $this->makeProduct(['name' => 'Kopi Hitam', 'sku' => 'SKU-KHUSUS1', 'barcode' => '8991234567890']);
        $this->makeProduct(['name' => 'Teh Manis']);

        $lewatSku = $this->actingAs($this->admin)->get('/master/produk?q=KHUSUS1')->getContent();
        $this->assertStringContainsString('Kopi Hitam', $lewatSku);
        $this->assertStringNotContainsString('Teh Manis', $lewatSku);

        $lewatBarcode = $this->actingAs($this->admin)->get('/master/produk?q=8991234567890')->getContent();
        $this->assertStringContainsString('Kopi Hitam', $lewatBarcode);
    }

    public function test_pencarian_kosong_menampilkan_semua(): void
    {
        $this->makeProduct(['name' => 'Produk Satu']);
        $this->makeProduct(['name' => 'Produk Dua']);

        $html = $this->actingAs($this->admin)->get('/master/produk?q=')->assertOk()->getContent();

        $this->assertStringContainsString('Produk Satu', $html);
        $this->assertStringContainsString('Produk Dua', $html);
    }

    public function test_pencarian_tanpa_hasil_tidak_error(): void
    {
        $this->makeProduct(['name' => 'Produk Ada']);

        $this->actingAs($this->admin)
            ->get('/master/produk?q=tidakadaproduksepertiini')
            ->assertOk()
            ->assertSee('Belum ada produk.');
    }

    /**
     * Pencarian dikirim apa adanya ke query LIKE. Karakter khusus tidak boleh membuat
     * halaman error, dan tidak boleh disisipkan mentah ke HTML.
     */
    public function test_pencarian_dengan_karakter_khusus_aman(): void
    {
        $this->makeProduct(['name' => 'Produk Normal']);

        foreach (['%', '_', "'", '"', '<script>alert(1)</script>', '100%', 'a\\b'] as $aneh) {
            $res = $this->actingAs($this->admin)->get('/master/produk?q=' . urlencode($aneh));
            $res->assertOk();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $res->getContent(),
                'Masukan pencarian tidak boleh muncul mentah sebagai HTML.');
        }
    }

    public function test_filter_gabungan_pencarian_dan_kategori(): void
    {
        $kategori = $this->makeCategory('Minuman');
        $this->makeProduct(['name' => 'Kopi Susu', 'category_id' => $kategori->id]);
        $this->makeProduct(['name' => 'Kopi Bubuk']);   // tanpa kategori

        $html = $this->actingAs($this->admin)
            ->get('/master/produk?q=Kopi&category_id=' . $kategori->id)
            ->assertOk()->getContent();

        $this->assertStringContainsString('Kopi Susu', $html);
        $this->assertStringNotContainsString('Kopi Bubuk', $html);
    }

    public function test_pencarian_tetap_berlaku_saat_pindah_halaman(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->makeProduct(['name' => 'Barang Uji ' . $i]);
        }
        $this->makeProduct(['name' => 'Lain Sendiri']);

        $halaman2 = $this->actingAs($this->admin)
            ->get('/master/produk?q=Barang+Uji&page=2')
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Lain Sendiri', $halaman2,
            'Filter pencarian harus ikut terbawa ke halaman berikutnya.');
    }
}
