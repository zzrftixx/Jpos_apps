<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\JposTestCase;

/**
 * UAT penjagaan kunci keamanan aplikasi (APP_KEY) pada jpos:prepare.
 *
 * Memastikan bahwa jpos:prepare dan launcher tidak akan pernah gagal mem-boot
 * aplikasi meskipun berkas .env dalam keadaan kosong, APP_KEY belum diisi,
 * atau terdapat cache konfigurasi lama yang tersisa dari lingkungan build.
 */
class PrepareAppKeyTest extends JposTestCase
{
    private string $envPath;
    private ?string $originalEnvContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = base_path('.env');
        if (File::exists($this->envPath)) {
            $this->originalEnvContent = File::get($this->envPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalEnvContent !== null) {
            File::put($this->envPath, $this->originalEnvContent);
        }

        parent::tearDown();
    }

    public function test_jpos_prepare_membuat_app_key_jika_kosong_atau_kunci_bawaan(): void
    {
        $baseContent = $this->originalEnvContent ?? "APP_NAME=JPOS\nAPP_KEY=\nDB_CONNECTION=sqlite\n";
        $testEnv = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=', $baseContent);
        if (! str_contains($testEnv, 'APP_KEY=')) {
            $testEnv .= "\nAPP_KEY=\n";
        }
        File::put($this->envPath, $testEnv);
        config(['app.key' => '']);

        $this->artisan('jpos:prepare', ['--skip-cache' => true])
            ->assertSuccessful();

        $envUpdated = File::get($this->envPath);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=/m', $envUpdated);
        $this->assertNotEmpty(config('app.key'));
        $this->assertStringStartsWith('base64:', config('app.key'));
    }

    public function test_jpos_prepare_mengganti_kunci_bawaan_lama(): void
    {
        $kunciBawaanLama = 'base64:orz8CxdFwOz5eUhykwT0Y67rgw+31Y9atp3z4anDZLU=';
        $baseContent = $this->originalEnvContent ?? "APP_NAME=JPOS\nAPP_KEY=\nDB_CONNECTION=sqlite\n";
        $testEnv = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $kunciBawaanLama, $baseContent);
        if (! str_contains($testEnv, 'APP_KEY=')) {
            $testEnv .= "\nAPP_KEY=" . $kunciBawaanLama . "\n";
        }
        File::put($this->envPath, $testEnv);
        config(['app.key' => $kunciBawaanLama]);

        $this->artisan('jpos:prepare', ['--skip-cache' => true])
            ->assertSuccessful();

        $envUpdated = File::get($this->envPath);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=/m', $envUpdated);
        $this->assertNotSame($kunciBawaanLama, config('app.key'));
    }
}
