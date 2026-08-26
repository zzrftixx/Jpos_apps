<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Rack;
use App\Models\RackSlot;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

/**
 * UAT kecepatan halaman.
 *
 * Yang diukur JUMLAH QUERY, bukan milidetik. Waktu bergantung mesin dan tidak bisa
 * dijadikan patokan yang stabil di test; jumlah query bergantung pada kode, dan cacat
 * performa yang paling sering muncul - N+1 - selalu menampakkan diri di sana.
 *
 * Ini penting khusus untuk JPos karena server bawaan PHP MELAYANI SATU PERMINTAAN PADA
 * SATU WAKTU. Satu halaman yang menembakkan 300 query bukan cuma lambat sendiri: ia
 * menahan kasir yang sedang menunggu struk tercetak di tab lain.
 *
 * Batasnya diberi kelonggaran dari angka terukur, supaya test tidak rewel pada perubahan
 * wajar - tapi tetap jauh di bawah jumlah baris data, sehingga N+1 pasti menabraknya.
 *
 * Terukur pada 60 produk, SEBELUM dan SESUDAH profil toko berhenti dibaca ulang untuk tiap
 * fragmen tampilan (lihat AppServiceProvider::profilToko):
 *
 *     /laporan/neraca          90 -> 26
 *     /laporan/laba            79 -> 17
 *     /kasir                   74 -> 16
 *     /master/produk           73 -> 13
 *     /laporan/stok            73 ->  9
 *     /laporan/penjualan       73 ->  9
 *     /pembelian               73 -> 11
 *     /dashboard               72 -> 14
 *     /laporan/per-pelanggan   70 ->  6
 *     /laporan/terlaris        70 ->  6
 *     /kas                     68 ->  6
 *     /master/planogram        63 ->  5
 *
 * Penyebabnya satu dan berlaku di semua halaman: View::composer('*') berjalan untuk SETIAP
 * view yang dirender, bukan sekali per halaman.
 */
class KecepatanHalamanTest extends JposTestCase
{
    private const JUMLAH_PRODUK = 60;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = $this->makeCategory();
        $rak = Rack::create(['name' => 'Rak A', 'rows' => 8, 'cols' => 10]);

        $baris = 0;
        $kolom = 0;

        for ($i = 1; $i <= self::JUMLAH_PRODUK; $i++) {
            $produk = $this->makeProduct([
                'name' => 'Produk ' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'category_id' => $kategori->id,
                'stock' => 10 + $i,
                'cost_price' => 5000,
                'sell_price' => 8000,
            ]);

            // Separuh produk ditaruh di rak, supaya halaman yang menampilkan lokasi rak
            // benar-benar harus mengambilnya - bukan cuma melewati kolom kosong.
            if ($i % 2 === 0 && $baris < 8) {
                RackSlot::create([
                    'rack_id' => $rak->id, 'row' => $baris, 'col' => $kolom,
                    'product_id' => $produk->id,
                ]);

                if (++$kolom >= 10) {
                    $kolom = 0;
                    $baris++;
                }
            }
        }

        for ($i = 1; $i <= 10; $i++) {
            Customer::create(['name' => 'Pelanggan ' . $i]);
        }

        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->startOfMonth()->toDateString(),
            'saldo_awal_kas' => 1000000,
            'modal_awal' => 1000000,
        ]);

        // Transaksi supaya laporan punya isi yang harus benar-benar diolah.
        $produkUntukJual = Product::orderBy('id')->take(8)->get();

        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($this->admin)->postJson('/kasir', [
                'items' => [['product_id' => $produkUntukJual[$i]->id, 'qty' => 2]],
                'paid_amount' => 20000,
                'payment_method' => 'cash',
                'customer_id' => Customer::inRandomOrder()->value('id'),
            ])->assertOk();
        }
    }

    /**
     * @return array{jumlah:int,daftar:array<int,string>}
     */
    private function hitungQuery(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get($url)->assertOk();

        $log = DB::getRawQueryLog();
        DB::disableQueryLog();

        return [
            'jumlah' => count($log),
            'daftar' => array_map(fn ($q) => $q['raw_query'] ?? '', $log),
        ];
    }

    private function periksa(string $url, int $batas): void
    {
        $hasil = $this->hitungQuery($url);

        $this->assertLessThanOrEqual(
            $batas,
            $hasil['jumlah'],
            sprintf(
                "Halaman %s menembakkan %d query (batas %d) untuk %d produk.\n"
                . "Server bawaan melayani satu permintaan pada satu waktu, jadi ini menahan kasir.\n"
                . "Sepuluh query pertama:\n  %s",
                $url,
                $hasil['jumlah'],
                $batas,
                self::JUMLAH_PRODUK,
                implode("\n  ", array_slice($hasil['daftar'], 0, 10))
            )
        );
    }

    /* --------------------------------------------------------- beban bersama */

    /**
     * INI PENJAGA YANG PALING MENENTUKAN.
     *
     * Profil toko dibagikan ke semua tampilan lewat View::composer('*'), dan composer itu
     * berjalan untuk SETIAP view yang dirender - tiap partial, komponen, dan @include.
     * Satu halaman JPos terdiri dari puluhan fragmen, jadi membaca profil di dalamnya
     * berarti puluhan query yang sama persis, DI SETIAP HALAMAN.
     *
     * Terukur sebelum diperbaiki: 30x pemeriksaan keberadaan tabel + 30x baca settings,
     * untuk menampilkan satu daftar rak yang cuma butuh 4 query.
     */
    public function test_profil_toko_dibaca_sekali_saja_per_halaman(): void
    {
        $hasil = $this->hitungQuery('/dashboard');

        $bacaSettings = count(array_filter(
            $hasil['daftar'],
            fn (string $sql) => str_contains($sql, 'from "settings"')
        ));

        $cekTabel = count(array_filter(
            $hasil['daftar'],
            fn (string $sql) => str_contains($sql, 'sqlite_master')
        ));

        $this->assertLessThanOrEqual(3, $bacaSettings,
            "Tabel settings dibaca {$bacaSettings} kali dalam satu halaman - profil toko dibaca ulang untuk tiap fragmen tampilan.");

        $this->assertLessThanOrEqual(2, $cekTabel,
            "Keberadaan tabel diperiksa {$cekTabel} kali dalam satu halaman.");
    }

    /**
     * Halaman yang paling sederhana harus benar-benar murah. Kalau yang ini membengkak,
     * penyebabnya pasti di lapisan bersama - bukan di halamannya.
     */
    public function test_halaman_paling_sederhana_tetap_murah(): void
    {
        $hasil = $this->hitungQuery('/master/planogram');

        $this->assertLessThanOrEqual(
            10,
            $hasil['jumlah'],
            sprintf(
                "Halaman paling sederhana menembakkan %d query. Beban ini ada di SEMUA halaman.\n  %s",
                $hasil['jumlah'],
                implode("\n  ", array_slice($hasil['daftar'], 0, 12))
            )
        );
    }

    /* -------------------------------------------------------------- per halaman */

    public function test_dashboard(): void
    {
        $this->periksa('/dashboard', 20);
    }

    public function test_kasir(): void
    {
        $this->periksa('/kasir', 22);
    }

    public function test_master_produk(): void
    {
        $this->periksa('/master/produk', 20);
    }

    /** Lokasi rak ditampilkan per baris - kalau diambil satu per satu, ini yang menangkapnya. */
    public function test_laporan_stok(): void
    {
        $this->periksa('/laporan/stok', 15);
    }

    public function test_laporan_laba(): void
    {
        $this->periksa('/laporan/laba', 25);
    }

    /**
     * Neraca sempat menghitung posisi keuangan DUA KALI dalam satu tampilan: sekali untuk
     * angkanya, sekali lagi lewat saran modal awal. Batas ini yang menjaganya tidak kembali.
     */
    public function test_laporan_neraca(): void
    {
        $this->periksa('/laporan/neraca', 35);
    }

    public function test_laporan_penjualan(): void
    {
        $this->periksa('/laporan/penjualan', 15);
    }

    public function test_laporan_per_pelanggan(): void
    {
        $this->periksa('/laporan/per-pelanggan', 12);
    }

    public function test_laporan_terlaris(): void
    {
        $this->periksa('/laporan/terlaris', 12);
    }

    public function test_pembelian(): void
    {
        $this->periksa('/pembelian', 18);
    }

    public function test_planogram_daftar(): void
    {
        $this->periksa('/master/planogram', 10);
    }

    /** Kisi rak menampilkan nama & stok tiap kotak - 80 kotak tidak boleh jadi 80 query. */
    public function test_planogram_penyunting_kisi(): void
    {
        $rak = Rack::firstOrFail();

        $this->periksa("/master/planogram/{$rak->id}", 15);
    }

    public function test_planogram_cetak_peta(): void
    {
        $rak = Rack::firstOrFail();

        $this->periksa("/master/planogram/{$rak->id}/cetak", 10);
    }

    public function test_kas(): void
    {
        $this->periksa('/kas', 12);
    }
}
