<?php

namespace Tests\Feature;

use App\Models\Setting;
use Tests\JposTestCase;

class MarketingHookShiftTest extends JposTestCase
{
    public function test_sidebar_menampilkan_badge_pro_saat_shift_kasir_nonaktif(): void
    {
        Setting::set('shift_kasir', ['enabled' => false]);
        Setting::flushMemo();

        $this->assertFalse(Setting::shiftKasirEnabled());

        $response = $this->actingAs($this->kasir)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('showPromoShiftModal = true', false);
        $response->assertSee('PRO');
        $response->assertSee('Modul Shift Kasir &amp; Rekonsiliasi Laci', false);
    }

    public function test_sidebar_menampilkan_tautan_biasa_saat_shift_kasir_aktif(): void
    {
        Setting::set('shift_kasir', ['enabled' => true]);
        Setting::flushMemo();

        $this->assertTrue(Setting::shiftKasirEnabled());

        $response = $this->actingAs($this->kasir)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(route('shift.index'));
    }
}
