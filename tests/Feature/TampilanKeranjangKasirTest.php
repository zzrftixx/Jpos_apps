<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * Bentuk panel keranjang di Modul Kasir.
 *
 * Ini test TAMPILAN, bukan test hitungan - dan itu disengaja. Yang dijaga di sini adalah
 * susunan yang keliru sebelumnya, dan kekeliruan susunan tidak pernah menampakkan diri
 * sebagai galat: server tetap membalas 200, seluruh angka tetap benar, dan yang rusak cuma
 * kemampuan kasir melihat apa yang sedang ia kerjakan.
 *
 * Diukur di browser sungguhan pada layar 1440x900 sebelum diperbaiki:
 *
 *   - isi tetap di bawah daftar barang memakan 511px dari 608px tinggi panel, sehingga
 *     daftar barangnya sendiri cuma kebagian 97px - kira-kira dua baris
 *   - daftar itu punya gulungan sendiri DI DALAM panel yang juga bisa tergulung; memutar
 *     roda tetikus menggerakkan yang mana tergantung di mana penunjuknya kebetulan berada
 *   - tiap baris hanya menampilkan harga satuan, tidak totalnya, jadi "2 x Rp 12.500"
 *     harus dihitung di kepala saat pelanggan bertanya
 */
class TampilanKeranjangKasirTest extends JposTestCase
{
    private function halamanKasir(): string
    {
        $this->makeProduct(['name' => 'Beras Premium', 'stock' => 50]);

        return $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();
    }

    /**
     * PALING MENENTUKAN: daftar barang tidak boleh punya gulungan sendiri.
     *
     * Dua gulungan bersarang membuat kasir menggulung yang salah, dan barang yang baru
     * dipindai tetap tidak terlihat walau sudah masuk keranjang.
     */
    public function test_daftar_barang_tidak_punya_gulungan_sendiri(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString('x-ref="daftarKeranjang"', $html);
        $this->assertStringContainsString('<div x-ref="daftarKeranjang" class="space-y-2 min-h-[200px]">', $html,
            'Daftar keranjang kembali punya batas tinggi atau gulungan sendiri.');
    }

    /** Yang menggulung adalah zona di atas kaki, dan hanya di layar lebar. */
    public function test_hanya_zona_atas_yang_menggulung_dan_hanya_di_layar_lebar(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString('class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto px-4 pt-4 pb-3"', $html);

        // Di layar sempit panelnya ikut arus halaman. Kalau potong-isi dipaksakan di sana,
        // tombol Bayar terpotong dan tidak bisa diklik sama sekali.
        $this->assertStringContainsString('flex flex-col card p-0 lg:overflow-hidden', $html);
        $this->assertStringNotContainsString('flex flex-col card p-0 overflow-hidden', $html);
    }

    /**
     * Total dan tombol Bayar berada di kaki yang tidak ikut tergulung.
     *
     * Keduanya adalah hal yang paling sering dicari kasir, dan paling mahal kalau harus
     * dicari dulu sambil pelanggan menunggu di depan meja.
     */
    public function test_total_dan_tombol_bayar_ada_di_kaki_yang_tidak_tergulung(): void
    {
        $html = $this->halamanKasir();

        // Dijangkar ke kelas kakinya, BUKAN ke komentar Blade - komentar {{-- --}} tidak
        // pernah ikut ke HTML yang dikirim, jadi menjangkar ke sana selalu gagal.
        $kaki = strstr($html, 'shrink-0 border-t border-slate-200 bg-white px-4 py-3 pb-4 space-y-2');

        $this->assertNotFalse($kaki, 'Kaki tetap panel keranjang hilang.');
        $this->assertStringContainsString("formatNumber(total())", $kaki, 'Total tidak ada di kaki tetap.');
        $this->assertStringContainsString('Bayar &amp; Cetak Struk', $kaki, 'Tombol Bayar tidak ada di kaki tetap.');
        $this->assertStringContainsString('Tahan Transaksi', $kaki);
    }

    /** Tiap baris menampilkan total barisnya, bukan cuma harga satuan. */
    public function test_tiap_baris_menampilkan_total_barisnya(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString("formatNumber(linePrice(item) * item.qty)", $html,
            'Total per baris hilang - kasir harus mengalikan sendiri di kepala.');
    }

    /**
     * Baris yang baru disentuh disorot dan digulung ke dalam pandangan.
     *
     * Tanpa ini, memindai barang ke-15 tidak mengubah apa pun yang terlihat: barisnya
     * mendarat di luar layar, kasir menyangka pindaiannya gagal, memindai lagi, dan
     * barangnya masuk dua kali.
     */
    public function test_baris_yang_baru_disentuh_disorot_dan_digulung_ke_pandangan(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString('barisTersorot: -1', $html, 'Penanda baris tersorot hilang.');
        $this->assertStringContainsString('sorotBaris(idx)', $html);
        $this->assertStringContainsString('sorotBaris(existingIdx)', $html,
            'Menambah barang yang sudah ada di keranjang tidak lagi menyorot barisnya.');
        $this->assertStringContainsString('sorotBaris(this.cart.length - 1)', $html,
            'Baris yang baru dibuat tidak lagi disorot.');
        $this->assertStringContainsString("scrollIntoView({ block: 'nearest' })", $html,
            'Daftar tidak lagi menggulung ke baris yang baru disentuh.');
        $this->assertStringContainsString(':data-baris="idx"', $html,
            'Penanda baris hilang - sorotBaris tidak akan menemukan barisnya.');
    }

    /**
     * Halaman kasir meminta <main> jadi kolom flex supaya isinya memakai TINGGI SISA.
     *
     * Tanpa ini, satu spanduk peringatan di atas halaman mendorong panel keranjang 38px
     * ke bawah lipatan layar - terukur - dan tombol Bayar ikut terdorong bersamanya.
     * Halaman lain tidak terpengaruh: bagi mereka kelas tambahan itu kosong.
     */
    public function test_halaman_kasir_memakai_tinggi_sisa_bukan_tinggi_penuh(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString('class="flex flex-col lg:flex-row gap-4 lg:flex-1 lg:min-h-0"', $html);
        $this->assertStringNotContainsString('class="flex flex-col lg:flex-row gap-4 h-full"', $html);

        // Halaman lain tetap seperti semula - <main> mereka tidak berubah jadi kolom flex.
        $lain = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();
        $this->assertStringContainsString('flex-1 overflow-y-auto p-4 lg:p-6', $lain);
        $this->assertStringNotContainsString('p-4 lg:p-6 lg:flex lg:flex-col', $lain);
    }
}
