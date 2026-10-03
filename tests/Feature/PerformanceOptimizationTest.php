<?php

namespace Tests\Feature;

use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    /**
     * Memastikan konfigurasi PRAGMA SQLite sudah disetel untuk performa tinggi.
     */
    public function test_sqlite_pragmas_configured_correctly(): void
    {
        $pragmas = config('database.connections.sqlite.pragmas');

        $this->assertIsArray($pragmas, 'Konfigurasi pragmas SQLite harus berupa array.');
        $this->assertEquals(-64000, $pragmas['cache_size'] ?? null, 'cache_size harus 64MB (-64000).');
        $this->assertEquals(268435456, $pragmas['mmap_size'] ?? null, 'mmap_size harus 256MB (268435456).');
        $this->assertEquals('MEMORY', $pragmas['temp_store'] ?? null, 'temp_store harus MEMORY.');
    }

    /**
     * Memastikan skrip jpos-instant.js termuat di head-assets.
     */
    public function test_head_assets_contains_jpos_instant(): void
    {
        $konten = file_get_contents(resource_path('views/partials/head-assets.blade.php'));

        $this->assertStringContainsString('vendor/jpos-instant.js', $konten);
        $this->assertStringContainsString('vendor/jpos-sesi.js', $konten);
    }

    /**
     * Memastikan view transition styling terpasang di layout utama.
     */
    public function test_layouts_contains_view_transition_css(): void
    {
        $konten = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('@view-transition', $konten);
    }

    /**
     * Memastikan berkas public/vendor/jpos-instant.js ada dan memiliki logika yang benar.
     */
    public function test_jpos_instant_file_exists_and_valid(): void
    {
        $path = public_path('vendor/jpos-instant.js');

        $this->assertFileExists($path);
        $konten = file_get_contents($path);

        $this->assertStringContainsString('speculationrules', $konten);
        $this->assertStringContainsString('prefetchUrl', $konten);
        $this->assertStringContainsString('pointerenter', $konten);
        $this->assertStringContainsString('prewarmSidebar', $konten);
    }
}
