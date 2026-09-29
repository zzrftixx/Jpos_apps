<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelolaShiftCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_dapat_mengunci_dan_membuka_shift_kasir(): void
    {
        // Kunci / Lock Shift Kasir
        $this->artisan('jpos:kelola-shift', ['aksi' => 'lock'])
            ->assertExitCode(0);

        $this->assertFalse(Setting::shiftKasirEnabled());

        // Buka / Unlock Shift Kasir
        $this->artisan('jpos:kelola-shift', ['aksi' => 'unlock'])
            ->assertExitCode(0);

        $this->assertTrue(Setting::shiftKasirEnabled());
    }

    public function test_perintah_interaktif_dapat_dijalankan(): void
    {
        $this->artisan('jpos:kelola-shift')
            ->expectsQuestion('  Pilihan Anda (1/2/0)', '2')
            ->assertExitCode(0);

        $this->assertFalse(Setting::shiftKasirEnabled());

        $this->artisan('jpos:kelola-shift')
            ->expectsQuestion('  Pilihan Anda (1/2/0)', '1')
            ->assertExitCode(0);

        $this->assertTrue(Setting::shiftKasirEnabled());
    }
}
