<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * Tampilan di layar sempit.
 *
 * Dua hal dijaga di sini, dan keduanya adalah cacat yang tidak pernah menampakkan diri
 * sebagai galat: server tetap membalas 200, seluruh angka tetap benar, dan yang rusak cuma
 * kemampuan orang menjangkau menunya.
 */
class TampilanLayarSempitTest extends JposTestCase
{
    /**
     * PALING MENENTUKAN: sidebar harus jadi kolom flex di SEMUA ukuran layar.
     *
     * Dulu tertulis `lg:flex flex-col`. Di bawah 1024px, <aside> itu display:block, jadi
     * `flex-1` pada <nav> di dalamnya tidak berarti apa-apa - navnya setinggi isinya,
     * `overflow-y-auto` tidak pernah aktif, dan menu di bawah lipatan TIDAK BISA DIJANGKAU.
     *
     * Terukur di browser pada 375x812 sebelum diperbaiki: kotak nav 1326px sama persis
     * dengan isinya 1326px (jadi nol gulungan), dan menu terakhir "Tentang Aplikasi"
     * berakhir 566px di luar layar. Sesudah: kotak 689px, isi 1326px, bisa digulung.
     *
     * Satu kata `lg:` yang salah tempat mengunci 27 menu jadi separuh.
     */
    public function test_sidebar_jadi_kolom_flex_di_semua_ukuran(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('transform transition-transform flex flex-col lg:translate-x-0 lg:static', $html);
        $this->assertStringNotContainsString('lg:translate-x-0 lg:static lg:flex flex-col', $html,
            'Sidebar kembali hanya flex di layar lebar - menunya tidak akan bisa digulir di telepon.');

        // Yang membuat gulungannya ada sama sekali.
        $this->assertStringContainsString('data-sidebar-nav class="flex-1 overflow-y-auto', $html);
    }

    /** Navigasi bawah ada di layar sempit, dan tidak pernah muncul di layar lebar. */
    public function test_navigasi_bawah_hanya_untuk_layar_sempit(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('lg:hidden fixed bottom-0 inset-x-0', $html,
            'Bilah navigasi bawah hilang.');
    }

    /**
     * Isi halaman diberi ruang supaya tidak tertimbun bilah bawah.
     *
     * Tanpa ini, tombol terakhir tiap halaman - termasuk Bayar di Modul Kasir - berada
     * persis di belakang bilah navigasi dan tidak bisa disentuh.
     */
    public function test_isi_halaman_diberi_ruang_untuk_bilah_bawah(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('p-4 lg:p-6 pb-24 lg:pb-6', $html);
    }

    /**
     * Navigasi bawah mengikuti hak akses yang sama dengan sidebar.
     *
     * Kalau tidak, ia jadi jalan pintas yang melewati izin - dan pintu belakang yang
     * dibuat demi kenyamanan adalah cara paling umum izin menjadi tidak ada artinya.
     */
    public function test_navigasi_bawah_mengikuti_hak_akses(): void
    {
        $peranKasir = $this->kasir->role;
        $peranKasir->update(['permissions' => ['kasir']]);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $bilah = strstr($html, 'lg:hidden fixed bottom-0 inset-x-0');

        $this->assertNotFalse($bilah);
        $this->assertStringContainsString(route('kasir.index'), $bilah);
        $this->assertStringNotContainsString(route('laporan.penjualan'), $bilah,
            'Kasir tanpa izin Laporan tetap melihat tombolnya di bilah bawah.');
        $this->assertStringNotContainsString(route('produk.index'), $bilah);
    }

    /** Tombol "Menu" tetap ada supaya sisa 27 menu tidak jadi tidak terjangkau. */
    public function test_bilah_bawah_menyediakan_pintu_ke_sisa_menu(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $bilah = strstr($html, 'lg:hidden fixed bottom-0 inset-x-0');

        $this->assertStringContainsString('sidebarOpen = true', $bilah);
        $this->assertStringContainsString('>Menu<', $bilah);
    }
}
