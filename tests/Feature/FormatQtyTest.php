<?php

namespace Tests\Feature;

use App\Support\Angka;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\JposTestCase;

/**
 * Tampilan kuantitas pecahan.
 *
 * Struk dicetak oleh server (PHP) sedangkan keranjang digambar di browser (JavaScript).
 * Angka yang sama TIDAK BOLEH tampil berbeda di keduanya - kasir yang melihat "0,5" di
 * layar lalu mencetak struk bertuliskan "1" akan kehilangan kepercayaan pada aplikasinya,
 * dan pelanggan berhak atas struk yang cocok dengan yang dibayar.
 *
 * Daftar kasus di sini sengaja dibuat kembar dengan blok "Kuantitas pecahan" di
 * tests/js/jpos-number.test.cjs.
 */
class FormatQtyTest extends JposTestCase
{
    public static function kasusQty(): array
    {
        return [
            'bulat' => [10, '10'],
            'setengah' => [0.5, '0,5'],
            'empat persepuluh' => [0.4, '0,4'],
            'dua setengah' => [2.5, '2,5'],
            'sisa timbangan' => [9.6, '9,6'],
            'satu gram dalam kg' => [0.001, '0,001'],
            'ribuan' => [12500, '12.500'],
            'ribuan berdesimal' => [1500.25, '1.500,25'],
            'nol' => [0, '0'],
        ];
    }

    #[DataProvider('kasusQty')]
    public function test_format_qty(mixed $nilai, string $harapan): void
    {
        $this->assertSame($harapan, Angka::qty($nilai));
    }

    /**
     * Nilai dari database datang sebagai STRING desimal bertitik. Titik di situ berarti
     * desimal, bukan ribuan - kalau tertukar, 0,4 Kg tampil sebagai 4.000 Kg.
     */
    public function test_nilai_desimal_dari_database_tidak_terbaca_sebagai_ribuan(): void
    {
        $this->assertSame('0,4', Angka::qty('0.4000'));
        $this->assertSame('9,6', Angka::qty('9.60'));
        $this->assertSame('10', Angka::qty('10.0000'));
    }

    public function test_direktif_blade_qty_tersedia(): void
    {
        $terkompilasi = Blade::compileString('@qty(0.5)');

        $this->assertStringContainsString('Angka::qty', $terkompilasi);
        $this->assertSame('0,5', Angka::qty(0.5));
    }

    /**
     * Pembandingan stok harus tahan terhadap galat float biner, kalau tidak menjual 0,3 Kg
     * dari stok 0,3 Kg justru ditolak dengan pesan "stok tidak cukup".
     */
    public function test_perbandingan_stok_tahan_galat_float(): void
    {
        $this->assertTrue(Angka::cukup(0.1 + 0.2, 0.3), '0,1 + 0,2 harus dianggap cukup untuk stok 0,3.');
        $this->assertTrue(Angka::cukup(0.3, 0.3));
        $this->assertFalse(Angka::cukup(0.8, 0.5));
    }

    public function test_konversi_ke_satuan_dasar_tidak_dibulatkan_ke_integer(): void
    {
        $this->assertSame(0.4, Angka::keSatuanDasar(400, 0.001), '400 Gram = 0,4 Kg, bukan 0.');
        $this->assertSame(2.5, Angka::keSatuanDasar(2500, 0.001));
        $this->assertSame(24.0, Angka::keSatuanDasar(2, 12), 'Satuan hitung tetap bekerja seperti sebelumnya.');
    }
}
