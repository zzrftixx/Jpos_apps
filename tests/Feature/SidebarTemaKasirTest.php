<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * UAT Tampilan Sidebar & Flat Icon Tema Kasir JPOS.
 *
 * Menjaga agar sidebar:
 * 1. Selaras dengan tema Kasir JPOS (bg-white, border-slate-200, aksen brand blue).
 * 2. Menggunakan flat icon (solid SVG dengan fill="currentColor").
 * 3. Memiliki active state yang jelas (.nav-active dengan background lembut #eef7ff dan teks #1c6ff0).
 * 4. Berfungsi optimal sebagai drawer saat dibuka dari Modul Kasir.
 */
class SidebarTemaKasirTest extends JposTestCase
{
    public function test_sidebar_memakai_tema_terang_selaras_kasir(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        // Aside berlatar putih dengan border slate, bukan slate-900 gelap
        $this->assertStringContainsString('bg-white border-r border-slate-200', $html);
        $this->assertStringNotContainsString('bg-slate-900 text-slate-200', $html);

        // Header sidebar memiliki branding toko yang elegan
        $this->assertStringContainsString('bg-brand-600 text-white', $html);

        // Tombol tutup drawer untuk mobile dan modul kasir
        $this->assertStringContainsString('sidebarOpen = false', $html);

        // Styling nav-link dan nav-active bernuansa kasir jpos
        $this->assertStringContainsString('.nav-link { display:flex; align-items:center;', $html);
        $this->assertStringContainsString('.nav-active { background:#eef7ff !important; color:#1c6ff0 !important;', $html);
    }

    public function test_icon_menggunakan_flat_icon(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        // Memastikan icon-icon sidebar menggunakan fill="currentColor" (flat icon)
        $this->assertStringContainsString('fill="currentColor"', $html);

        // Tidak lagi didominasi oleh stroke outline 1.8 bawaan lama
        $this->assertStringNotContainsString('stroke-width="1.8"', $html);
    }

    public function test_sidebar_dapat_dibuka_dari_halaman_kasir(): void
    {
        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        // Tombol toggle menu di header kasir mengontrol sidebarOpen
        $this->assertStringContainsString('@click="sidebarOpen = !sidebarOpen"', $html);

        // Drawer aside ada di halaman kasir dan siap dibuka
        $this->assertStringContainsString('sidebarOpen ? \'translate-x-0\' : \'-translate-x-full\'', $html);
    }
}
