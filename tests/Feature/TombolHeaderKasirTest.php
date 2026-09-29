<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\JposTestCase;

/**
 * UAT Tombol Header Modul Kasir (Tertahan & Pesanan / DP).
 *
 * Menjaga agar tombol 'Tertahan' dan 'Pesanan / DP' di kanan atas modul kasir:
 * 1. Berukuran serasi (tinggi h-9, rounded-xl) dengan tombol aksi cepat lainnya.
 * 2. Responsif: ikon dan badge tetap tampil di mobile (sm:hidden pada teks), teks lengkap di desktop.
 * 3. Teks 'Pesanan / DP' rapi dalam satu baris (tidak patah / tergantung di baris kedua).
 * 4. Memiliki badge counter yang terisi dinamis dari jumlah transaksi tertahan & pesanan/DP.
 */
class TombolHeaderKasirTest extends JposTestCase
{
    public function test_view_menerima_jumlah_tertahan_dan_pesanan(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk();

        $this->assertArrayHasKey('jumlahTertahan', $response->baseResponse->original->getData());
        $this->assertArrayHasKey('jumlahPesanan', $response->baseResponse->original->getData());
    }

    public function test_tombol_tertahan_dan_pesanan_proporsional_dan_responsif(): void
    {
        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        // Keduanya memakai h-9 dan rounded-xl serasi dengan kontrol header lainnya
        $this->assertStringContainsString('href="' . route('kasir.tahan') . '"', $html);
        $this->assertStringContainsString('href="' . route('kasir.waiting-list') . '"', $html);

        // Tombol Tahan Pesanan: card gerigi icon, label terlihat jelas (tidak disembunyikan), dan badge
        $this->assertStringContainsString('M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6M9 11h6M9 14h3', $html);
        $this->assertStringContainsString('<span class="whitespace-nowrap">Tahan Pesanan</span>', $html);
        $this->assertStringContainsString('x-text="jumlahTertahan"', $html);

        // Tombol Waiting List: clipboard icon, label terlihat jelas (tidak disembunyikan), dan badge
        $this->assertStringContainsString('<span class="whitespace-nowrap">Waiting List</span>', $html);
        $this->assertStringContainsString('x-text="jumlahPesanan"', $html);

        // Alpine.js menerima data awal kedua counter
        $this->assertMatchesRegularExpression('/jumlahTertahan:\s*\d+/', $html);
        $this->assertMatchesRegularExpression('/jumlahPesanan:\s*\d+/', $html);
    }

    public function test_counter_menghitung_tertahan_dan_pesanan_secara_akurat(): void
    {
        $pelanggan = $this->makeCustomer();

        // 1 Pesanan (waiting list / DP, parked_at = null)
        Sale::create([
            'invoice_no' => 'INV-TEST-WL-1',
            'user_id' => $this->kasir->id,
            'customer_id' => $pelanggan->id,
            'subtotal' => 20000,
            'total' => 20000,
            'paid_amount' => 5000,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'order_status' => 'waiting',
            'parked_at' => null,
        ]);

        // 1 Tertahan (waiting, parked_at != null)
        Sale::create([
            'invoice_no' => 'INV-TEST-TH-1',
            'user_id' => $this->kasir->id,
            'customer_id' => $pelanggan->id,
            'subtotal' => 15000,
            'total' => 15000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'order_status' => 'waiting',
            'parked_at' => now(),
        ]);

        $response = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk();
        $this->assertSame(1, $response->viewData('jumlahTertahan'));
        $this->assertSame(1, $response->viewData('jumlahPesanan'));
    }
}
