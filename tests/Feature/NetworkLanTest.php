<?php

namespace Tests\Feature;

use App\Support\NetworkInfo;
use Tests\JposTestCase;

class NetworkLanTest extends JposTestCase
{
    public function test_network_info_validasi_lan_ip_dengan_benar(): void
    {
        $this->assertTrue(NetworkInfo::isValidLanIp('192.168.1.10'));
        $this->assertTrue(NetworkInfo::isValidLanIp('10.0.0.5'));
        $this->assertTrue(NetworkInfo::isValidLanIp('172.16.0.1'));

        $this->assertFalse(NetworkInfo::isValidLanIp('127.0.0.1'));
        $this->assertFalse(NetworkInfo::isValidLanIp('169.254.1.1'));
        $this->assertFalse(NetworkInfo::isValidLanIp('bukan-ip'));
    }

    public function test_network_info_menghasilkan_url_akses_valid(): void
    {
        $urls = NetworkInfo::getAccessUrls();

        $this->assertIsArray($urls);
        $this->assertNotEmpty($urls);

        foreach ($urls as $u) {
            $this->assertArrayHasKey('ip', $u);
            $this->assertArrayHasKey('url', $u);
            $this->assertArrayHasKey('is_recommended', $u);
            $this->assertStringStartsWith('http://', $u['url']);
        }
    }

    public function test_admin_dapat_mengakses_halaman_pengaturan_jaringan_lan(): void
    {
        $response = $this->actingAs($this->admin)->get('/pengaturan/jaringan');

        $response->assertOk();
        $response->assertSee('Akses Jaringan');
        $response->assertSee('Alamat URL Akses');
        $response->assertSee('Windows Firewall');
    }

    public function test_kasir_tidak_dapat_mengakses_pengaturan_jaringan_lan(): void
    {
        $response = $this->actingAs($this->kasir)->get('/pengaturan/jaringan');

        $response->assertForbidden();
    }

    public function test_admin_dapat_menyimpan_pengaturan_mode_lan(): void
    {
        $response = $this->actingAs($this->admin)->post('/pengaturan/jaringan', [
            'mode_lan_enabled' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    public function test_artisan_command_jpos_lan_berhasil_dijalankan(): void
    {
        $this->artisan('jpos:lan')
            ->expectsOutputToContain('JPOS MULTI-DEVICE')
            ->expectsOutputToContain('Alamat URL')
            ->assertSuccessful();
    }
}
