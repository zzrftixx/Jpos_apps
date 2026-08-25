<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * Smoke test dasar aplikasi.
 *
 * Menggantikan test bawaan Laravel yang mengharapkan '/' membalas 200; di JPOS route
 * itu memang selalu mengalihkan ke dashboard, jadi test bawaannya gagal sejak awal
 * dan tidak menguji apa pun yang berguna.
 */
class ExampleTest extends JposTestCase
{
    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_halaman_login_bisa_dibuka(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_health_check_untuk_launcher_menjawab(): void
    {
        // JPOS.exe memakai endpoint ini untuk tahu kapan server benar-benar siap
        // sebelum membuka browser.
        $this->get('/up')->assertOk();
    }

    public function test_user_yang_sudah_login_bisa_membuka_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    }
}
