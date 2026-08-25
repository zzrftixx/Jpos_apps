<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Tests\JposTestCase;

/**
 * Kotak input angka menampilkan pemisah ribuan sambil diketik, jadi nilai yang terkirim ke
 * server berformat Indonesia ("1.500.000"). Middleware NormalizeNumericInput yang
 * menormalkannya sebelum validasi.
 *
 * Ini jalur uang. Salah baca satu pemisah berarti harga produk salah ribuan kali.
 */
class NormalizeNumericInputTest extends JposTestCase
{
    private function payloadProduk(array $override = []): array
    {
        return array_merge([
            'name' => 'Produk Uji Angka',
            'type' => 'barang',
            'cost_price' => '5.000',
            'sell_price' => '9.000',
            'stock' => '10',
        ], $override);
    }

    public function test_harga_berformat_ribuan_tersimpan_sebagai_angka_benar(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk([
                'cost_price' => '1.250.000',
                'sell_price' => '1.500.000',
                'stock' => '1.200',
            ]))
            ->assertSessionHasNoErrors();

        $produk = Product::firstOrFail();

        $this->assertSame(1250000.0, (float) $produk->cost_price);
        $this->assertSame(1500000.0, (float) $produk->sell_price);
        $this->assertSame(1200.0, (float) $produk->stock);
    }

    public function test_angka_tanpa_pemisah_tetap_benar(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk([
                'cost_price' => '1250000',
                'sell_price' => '1500000',
            ]))
            ->assertSessionHasNoErrors();

        $produk = Product::firstOrFail();
        $this->assertSame(1250000.0, (float) $produk->cost_price);
        $this->assertSame(1500000.0, (float) $produk->sell_price);
    }

    public function test_desimal_dengan_koma_dibaca_benar(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk([
                'cost_price' => '1.500,75',
                'sell_price' => '2.000,25',
            ]))
            ->assertSessionHasNoErrors();

        $produk = Product::firstOrFail();
        $this->assertSame(1500.75, (float) $produk->cost_price);
        $this->assertSame(2000.25, (float) $produk->sell_price);
    }

    /**
     * Kalau JavaScript mati, kasir bisa mengetik dengan kebiasaan lama "11.5" untuk 11,5.
     * Membuang titik mentah-mentah akan menghasilkan 115 - salah sepuluh kali lipat pada
     * persen pajak.
     */
    public function test_satu_titik_diikuti_satu_dua_digit_dianggap_desimal(): void
    {
        $this->actingAs($this->admin)
            ->post('/pengaturan/pajak', ['name' => 'PPN', 'percent' => '11.5', 'enabled' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(11.5, (float) Setting::get('tax')['percent']);
    }

    public function test_satu_titik_diikuti_tiga_digit_dianggap_pemisah_ribuan(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk(['sell_price' => '1.500']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1500.0, (float) Product::firstOrFail()->sell_price);
    }

    public function test_label_mata_uang_dan_spasi_diabaikan(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk(['sell_price' => 'Rp 2.750.000,-']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2750000.0, (float) Product::firstOrFail()->sell_price);
    }

    /** Field kosong harus tetap kosong supaya aturan 'nullable' bekerja seperti semula. */
    public function test_field_kosong_tidak_diubah_jadi_nol(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk([
                'wholesale_price' => '',
                'wholesale_min_qty' => '',
            ]))
            ->assertSessionHasNoErrors();

        $produk = Product::firstOrFail();
        $this->assertNull($produk->wholesale_price);
        $this->assertNull($produk->wholesale_min_qty);
    }

    /**
     * Barcode berisi digit dan BUKAN field angka. Kalau middleware menyentuhnya, barcode
     * yang mengandung titik akan berubah diam-diam.
     */
    public function test_barcode_dan_nama_tidak_disentuh(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk([
                'name' => 'Aqua 1.5L Botol',
                'barcode' => '8992761111.222',
            ]))
            ->assertSessionHasNoErrors();

        $produk = Product::firstOrFail();
        $this->assertSame('Aqua 1.5L Botol', $produk->name);
        $this->assertSame('8992761111.222', $produk->barcode);
    }

    public function test_satuan_tambahan_bersarang_ikut_dinormalkan(): void
    {
        // Migrasi 000210 sudah menanam satuan bawaan termasuk "Dus".
        $unit = \App\Models\Unit::firstOrCreate(['name' => 'Dus']);

        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk([
                'multi_unit_enabled' => '1',
                'units' => [
                    ['unit_id' => $unit->id, 'ratio_to_previous' => '1.000', 'price' => '2.400.000', 'wholesale_price' => '2.300.000', 'cost_price' => '2.100.000', 'wholesale_min_qty' => '5'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $baris = Product::firstOrFail()->units()->firstOrFail();

        $this->assertSame(1000.0, (float) $baris->ratio_to_previous);
        $this->assertSame(1000.0, (float) $baris->conversion, 'Rantai satu baris: rasio = konversi ke satuan dasar.');
        $this->assertSame(2400000.0, (float) $baris->price);
        $this->assertSame(2100000.0, (float) $baris->cost_price);
        $this->assertSame(2300000.0, (float) $baris->wholesale_price);
        $this->assertSame(5, $baris->wholesale_min_qty);
    }

    public function test_transaksi_kas_dengan_nominal_berformat(): void
    {
        $this->actingAs($this->admin)
            ->post('/kas', ['type' => 'in', 'category' => 'modal_tambahan', 'amount' => '5.000.000', 'note' => 'Setoran modal'])
            ->assertSessionHasNoErrors();

        $this->assertSame(5000000.0, (float) \App\Models\CashTransaction::firstOrFail()->amount);
    }

    /** Checkout kasir mengirim JSON dengan angka sungguhan - harus lewat tanpa disentuh. */
    public function test_json_dengan_angka_asli_tidak_rusak(): void
    {
        $produk = $this->makeProduct(['stock' => 50, 'sell_price' => 12500]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 4]],
            'discount' => 2500,
            'paid_amount' => 50000,
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = \App\Models\Sale::firstOrFail();

        $this->assertSame(50000.0, (float) $sale->subtotal);
        $this->assertSame(2500.0, (float) $sale->discount);
        $this->assertSame(47500.0, (float) $sale->total);
        $this->assertSame(46.0, (float) $produk->fresh()->stock);
    }

    /** Kalau JS mati, checkout tetap bisa terkirim form-encoded dengan nilai berformat. */
    public function test_checkout_dengan_nilai_berformat_tetap_benar(): void
    {
        $produk = $this->makeProduct(['stock' => 50, 'sell_price' => 12500]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => '4']],
            'discount' => '2.500',
            'paid_amount' => '50.000',
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = \App\Models\Sale::firstOrFail();
        $this->assertSame(2500.0, (float) $sale->discount);
        $this->assertSame(47500.0, (float) $sale->total);
    }

    public function test_nilai_bukan_angka_dibiarkan_supaya_validasi_yang_menolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/produk', $this->payloadProduk(['sell_price' => 'abcdef']))
            ->assertSessionHasErrors('sell_price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_pengaturan_printer_dengan_desimal_koma(): void
    {
        $this->actingAs($this->admin)
            ->post('/pengaturan/printer-struk', [
                'profile' => 'custom',
                'custom_width' => '57,5',
                'margin' => '1,5',
                'font_size' => '11',
            ])
            ->assertSessionHasNoErrors();

        $printer = Setting::get('printer_struk');

        $this->assertSame(57.5, (float) $printer['paper_size']);
        $this->assertSame(1.5, (float) $printer['margin']);
        $this->assertSame(11, (int) $printer['font_size']);
    }
}
