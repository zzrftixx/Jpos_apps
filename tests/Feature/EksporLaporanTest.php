<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Setting;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\JposTestCase;

/**
 * UAT ekspor laporan ke PDF dan Excel.
 *
 * Yang diuji bukan "berkasnya terunduh", tapi berkasnya BENAR-BENAR bisa dibuka. Respons
 * berstatus 200 berisi HTML galat juga akan lolos kalau yang diperiksa cuma statusnya -
 * dan pemilik toko baru tahu saat berkasnya ditolak Excel di depan orang lain.
 *
 * Karena itu isinya diperiksa sampai ke tanda tangan berkas: PDF wajib berawalan %PDF, dan
 * XLSX wajib berupa arsip ZIP yang memuat xl/workbook.xml.
 */
class EksporLaporanTest extends JposTestCase
{
    private function siapkanData(): void
    {
        Setting::set('store_profile', [
            'name' => 'Toko Jayla Makmur',
            'address' => 'Jl. Merdeka No. 17, Semarang',
            'phone' => '0812-3456-7890',
        ]);

        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->startOfMonth()->toDateString(),
            'saldo_awal_kas' => 1000000,
            'modal_awal' => 1000000,
        ]);

        $produk = $this->makeProduct(['name' => 'Kopi Sachet', 'stock' => 0, 'cost_price' => 0, 'sell_price' => 8000]);

        // Pembelian tunai + kredit, penjualan, kas, pesanan DP - supaya kesepuluh laporan
        // punya isi. Laporan kosong tidak membuktikan apa pun tentang tata letaknya.
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => 100, 'unit_type' => 'base', 'price' => 5000]],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => 20, 'unit_type' => 'base', 'price' => 5000]],
            'bayar' => 'hutang',
            'due_date' => now()->addDays(30)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 10]],
            'paid_amount' => 80000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertOk();

        $this->actingAs($this->admin)->post('/kas', [
            'type' => 'out', 'category' => 'sewa', 'amount' => 150000, 'description' => 'Sewa kios',
        ])->assertSessionHasNoErrors();
    }

    public static function daftarLaporan(): array
    {
        return [
            'laba rugi' => ['laba-rugi'],
            'neraca' => ['neraca'],
            'penjualan' => ['penjualan'],
            'omset' => ['omset'],
            'stok' => ['stok'],
            'terlaris' => ['terlaris'],
            'per pelanggan' => ['per-pelanggan'],
            'kas' => ['kas'],
            'hutang' => ['hutang'],
            'piutang' => ['piutang'],
        ];
    }

    #[DataProvider('daftarLaporan')]
    public function test_pdf_benar_benar_berkas_pdf(string $jenis): void
    {
        $this->siapkanData();

        $isi = $this->unduh($jenis, 'pdf');

        $this->assertStringStartsWith('%PDF', $isi, "Laporan {$jenis} bukan berkas PDF yang sah.");
        $this->assertGreaterThan(1000, strlen($isi), "Laporan {$jenis} terlalu kecil untuk berisi apa pun.");
    }

    #[DataProvider('daftarLaporan')]
    public function test_xlsx_benar_benar_arsip_excel(string $jenis): void
    {
        $this->siapkanData();

        $isi = $this->unduh($jenis, 'xlsx');

        // "PK" adalah tanda tangan arsip ZIP; xlsx adalah ZIP berisi XML.
        $this->assertStringStartsWith('PK', $isi, "Laporan {$jenis} bukan arsip XLSX yang sah.");
        $this->assertTrue($this->arsipMemuat($isi, 'xl/workbook.xml'), "Laporan {$jenis} tidak memuat workbook.");
    }

    /* --------------------------------------------------------------------- isi kop */

    public function test_pdf_memuat_kop_toko_periode_dan_pencetak(): void
    {
        $this->siapkanData();

        $html = view('laporan.ekspor.pdf', [
            'laporan' => \App\Support\Laporan::buat('Uji Kop', 'uji')
                ->periode(now()->startOfMonth()->toDateString(), now()->toDateString())
                ->pencetak('Administrator')
                ->bagian('Isi', [['label' => 'A', 'key' => 'a']], [['a' => 'baris']]),
        ])->render();

        $this->assertStringContainsString('Toko Jayla Makmur', $html, 'Kop toko hilang.');
        $this->assertStringContainsString('Jl. Merdeka No. 17, Semarang', $html);
        $this->assertStringContainsString('s/d', $html, 'Periode tidak tercetak.');
        $this->assertStringContainsString('Administrator', $html, 'Nama pencetak tidak tercetak.');
    }

    /**
     * Angka laporan keuangan tidak bisa dipertanggungjawabkan tanpa tahu cara menghitungnya.
     * Yang membaca laporan ini bisa saja bank atau kantor pajak.
     *
     * Diperiksa lewat XLSX, bukan PDF: isi halaman PDF terkompresi Flate, jadi mencari teks di
     * dalam bytenya tidak membuktikan apa pun. Keduanya disusun dari objek Laporan yang sama.
     */
    public function test_laporan_keuangan_membawa_catatan_asumsinya(): void
    {
        $this->siapkanData();

        $teks = $this->teksLembar('laba-rugi');

        $this->assertStringContainsString('Catatan', $teks);
        $this->assertStringContainsString('HPP dihitung dari harga modal yang berlaku saat barang itu terjual', $teks);
        $this->assertStringContainsString('Omset TIDAK termasuk pajak', $teks);
    }

    /** Kop toko, periode, dan nama pencetak harus ikut ke versi Excel juga, bukan cuma PDF. */
    public function test_excel_membawa_kop_toko_dan_jejak_cetak(): void
    {
        $this->siapkanData();

        $teks = $this->teksLembar('laba-rugi');

        $this->assertStringContainsString('Toko Jayla Makmur', $teks);
        $this->assertStringContainsString('Jl. Merdeka No. 17, Semarang', $teks);
        $this->assertStringContainsString('Laporan Laba Rugi', $teks);
        $this->assertStringContainsString('Dicetak', $teks);
    }

    /* --------------------------------------------------- angka Excel tetap angka */

    /**
     * INI ALASAN ORANG MINTA EXCEL DAN BUKAN PDF.
     *
     * Menulis "Rp 1.500.000" sebagai teks ke dalam sel membuatnya tidak bisa dijumlah, tidak
     * bisa diurutkan, dan tidak bisa dipakai rumus. Rupiahnya harus diurus format sel.
     */
    public function test_kolom_rupiah_di_excel_tersimpan_sebagai_angka(): void
    {
        $this->siapkanData();

        $berkas = tempnam(sys_get_temp_dir(), 'jpos') . '.xlsx';
        file_put_contents($berkas, $this->unduh('stok', 'xlsx'));

        $buku = \PhpOffice\PhpSpreadsheet\IOFactory::load($berkas);
        $lembar = $buku->getActiveSheet();

        $ketemu = false;

        foreach ($lembar->getRowIterator() as $baris) {
            foreach ($baris->getCellIterator() as $sel) {
                $nilai = $sel->getValue();

                if (is_float($nilai) && $nilai >= 500000) {
                    $ketemu = true;
                    $this->assertStringContainsString(
                        '#,##0',
                        $sel->getStyle()->getNumberFormat()->getFormatCode(),
                        'Angka rupiah tersimpan tanpa format ribuan.'
                    );
                    break 2;
                }

                $this->assertNotSame(
                    'Rp 500.000',
                    $nilai,
                    'Nilai rupiah ditulis sebagai teks - tidak bisa dijumlah di Excel.'
                );
            }
        }

        $this->assertTrue($ketemu, 'Tidak ada satu pun sel angka rupiah di laporan stok.');

        $buku->disconnectWorksheets();
        @unlink($berkas);
    }

    public function test_baris_judul_excel_dibekukan(): void
    {
        $this->siapkanData();

        $berkas = tempnam(sys_get_temp_dir(), 'jpos') . '.xlsx';
        file_put_contents($berkas, $this->unduh('stok', 'xlsx'));

        $buku = \PhpOffice\PhpSpreadsheet\IOFactory::load($berkas);

        $this->assertNotNull(
            $buku->getActiveSheet()->getFreezePane(),
            'Nama kolom akan hilang begitu laporan panjang digulir.'
        );

        $buku->disconnectWorksheets();
        @unlink($berkas);
    }

    /**
     * Nama lembar Excel dibatasi 31 karakter dan tidak boleh memuat : \ / ? * [ ].
     * Melanggarnya bukan menghasilkan peringatan, tapi berkas yang benar-benar gagal dibuka.
     */
    public function test_nama_lembar_excel_selalu_sah(): void
    {
        $this->siapkanData();

        foreach (array_column(self::daftarLaporan(), 0) as $jenis) {
            $berkas = tempnam(sys_get_temp_dir(), 'jpos') . '.xlsx';
            file_put_contents($berkas, $this->unduh($jenis, 'xlsx'));

            $buku = \PhpOffice\PhpSpreadsheet\IOFactory::load($berkas);
            $nama = $buku->getActiveSheet()->getTitle();

            $this->assertLessThanOrEqual(31, mb_strlen($nama), "Nama lembar laporan {$jenis} terlalu panjang.");
            $this->assertDoesNotMatchRegularExpression('/[:\\\\\/\?\*\[\]]/', $nama);

            $buku->disconnectWorksheets();
            @unlink($berkas);
        }
    }

    /* ------------------------------------------------------------- filter & izin */

    /**
     * Berkas yang terunduh harus persis sama dengan yang sedang dilihat di layar. Kalau
     * periode pada alamat diabaikan, pemilik toko akan mengunduh laporan bulan berjalan
     * padahal yang ditampilkan bulan lalu - dan tidak akan menyadarinya.
     */
    public function test_periode_pada_alamat_ikut_menentukan_isi_laporan(): void
    {
        $this->siapkanData();

        $sekarang = $this->teksLembar('laba-rugi');
        $this->assertStringContainsString('Rp 80.000', $sekarang, 'Omset periode berjalan tidak muncul.');

        $lampau = $this->teksLembar('laba-rugi', [
            'from' => now()->subYears(3)->toDateString(),
            'to' => now()->subYears(3)->addDay()->toDateString(),
        ]);

        $this->assertStringNotContainsString('Rp 80.000', $lampau, 'Periode pada alamat diabaikan.');
        $this->assertStringContainsString(now()->subYears(3)->format('d/m/Y'), $lampau, 'Periode yang tercetak salah.');
    }

    /**
     * Sepuluh halaman yang diberi tombol unduh harus benar-benar bisa dibuka.
     *
     * Tombolnya dibuat lewat route() dengan parameter yang berbeda-beda per halaman; salah
     * satu nama parameter saja akan melempar saat halaman dirender - bukan saat tombolnya
     * ditekan. Tanpa test ini, halaman yang tadinya sehat bisa mati hanya karena diberi
     * tombol unduh.
     */
    public function test_semua_halaman_bertombol_unduh_tetap_terbuka(): void
    {
        $this->siapkanData();

        $halaman = [
            '/laporan/laba', '/laporan/omset', '/laporan/penjualan', '/laporan/terlaris',
            '/laporan/per-pelanggan', '/laporan/stok', '/laporan/neraca',
            '/kas', '/pembelian', '/kasir/waiting-list',
        ];

        foreach ($halaman as $url) {
            $respons = $this->actingAs($this->admin)->get($url);

            $respons->assertOk("Halaman {$url} gagal dibuka setelah diberi tombol unduh.");
            $respons->assertSee('Unduh PDF', false);
            $respons->assertSee('Unduh Excel', false);
        }
    }

    /* -------------------------------------------------- batas rincian versi PDF */

    /**
     * Rincian PDF dipotong, TAPI pemotongannya diumumkan - dan totalnya tetap dari seluruh data.
     *
     * Diukur pada tabel berkolom delapan dengan batas memori 512 MB: 2.000 baris menghabiskan
     * 380 MB dan 46 detik, dan tanpa pemecahan tabel prosesnya mati sama sekali. Selama itu
     * kasir tidak bisa berjualan, karena server bawaan hanya melayani satu permintaan pada
     * satu waktu.
     *
     * Laporan yang diam-diam terpotong lebih berbahaya daripada laporan yang jelas bilang
     * "ini sebagian": yang pertama akan dikira lengkap, dan angkanya dipakai mengambil
     * keputusan.
     */
    public function test_rincian_pdf_yang_kepanjangan_dipotong_dan_diumumkan(): void
    {
        $jumlah = \App\Support\Laporan::BATAS_BARIS_PDF + 120;

        $baris = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $baris[] = ['nama' => 'Produk ' . $i, 'nilai' => 1000.0];
        }

        $laporan = \App\Support\Laporan::buat('Uji Batas', 'uji-batas')
            ->bagian('Rincian', [
                ['label' => 'Nama', 'key' => 'nama'],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah'],
            ], $baris, ['nama' => 'TOTAL', 'nilai' => $jumlah * 1000.0]);

        $this->assertSame(120, $laporan->jumlahBarisDibuang(\App\Support\Laporan::BATAS_BARIS_PDF));

        $tercetak = $laporan->daftarBagian(\App\Support\Laporan::BATAS_BARIS_PDF);
        $this->assertCount(\App\Support\Laporan::BATAS_BARIS_PDF, $tercetak[0]['baris']);

        $html = view('laporan.ekspor.pdf', [
            'laporan' => $laporan,
            'bagianTercetak' => $tercetak,
            'dibuang' => 120,
        ])->render();

        $this->assertStringContainsString('Rincian ini SEBAGIAN', $html, 'Pemotongan disembunyikan.');
        $this->assertStringContainsString('120', $html, 'Jumlah baris yang dibuang tidak disebutkan.');
        $this->assertStringContainsString('Excel', $html, 'Tidak diarahkan ke versi yang lengkap.');

        // Baris terakhir yang tercetak dan baris pertama yang dibuang.
        $this->assertStringContainsString('Produk ' . \App\Support\Laporan::BATAS_BARIS_PDF, $html);
        $this->assertStringNotContainsString('Produk ' . ($jumlah), $html);

        // Totalnya WAJIB dari seluruh data, bukan dari yang tercetak saja - kalau tidak,
        // laporan sebagian akan menampilkan total yang salah tanpa ada yang menyadari.
        $this->assertStringContainsString(
            \App\Support\Laporan::format($jumlah * 1000.0, 'rupiah'),
            $html,
            'Baris TOTAL ikut terpotong - angkanya jadi salah.'
        );
    }

    /** Excel sengaja TIDAK dibatasi: 2.000 baris cuma 9 detik dan 62 MB di sana. */
    public function test_excel_tidak_ikut_dibatasi(): void
    {
        $jumlah = \App\Support\Laporan::BATAS_BARIS_PDF + 50;

        for ($i = 1; $i <= $jumlah; $i++) {
            $this->makeProduct(['name' => 'Barang ' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'stock' => 1]);
        }

        $teks = $this->teksLembar('stok');

        $this->assertStringContainsString('Barang ' . str_pad((string) $jumlah, 5, '0', STR_PAD_LEFT), $teks,
            'Excel ikut terpotong - padahal justru itu jalan keluarnya.');
    }

    public function test_jenis_laporan_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)->get('/laporan/ekspor/laporan-hantu/pdf')->assertNotFound();
    }

    public function test_format_selain_pdf_dan_xlsx_ditolak(): void
    {
        $this->actingAs($this->admin)->get('/laporan/ekspor/laba-rugi/docx')->assertNotFound();
    }

    public function test_kasir_tidak_bisa_mengunduh_laporan(): void
    {
        $this->actingAs($this->kasir)->get('/laporan/ekspor/laba-rugi/pdf')->assertForbidden();
    }

    /* -------------------------------------------------------------------- pembantu */

    private function unduh(string $jenis, string $format, array $filter = []): string
    {
        $respons = $this->actingAs($this->admin)
            ->get('/laporan/ekspor/' . $jenis . '/' . $format . ($filter ? '?' . http_build_query($filter) : ''));

        $respons->assertOk();

        return $respons->streamedContent();
    }

    /**
     * Seluruh teks yang benar-benar tertulis di lembar Excel.
     *
     * Dipakai untuk memeriksa ISI laporan. Versi PDF tidak bisa diperiksa begitu saja karena
     * isi halamannya terkompresi Flate; keduanya disusun dari objek Laporan yang sama, jadi
     * memeriksa satu sudah membuktikan isinya.
     */
    private function teksLembar(string $jenis, array $filter = []): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'jpos') . '.xlsx';
        file_put_contents($berkas, $this->unduh($jenis, 'xlsx', $filter));

        $buku = \PhpOffice\PhpSpreadsheet\IOFactory::load($berkas);
        $potongan = [];

        foreach ($buku->getActiveSheet()->getRowIterator() as $baris) {
            foreach ($baris->getCellIterator() as $sel) {
                $nilai = $sel->getValue();

                if ($nilai === null || $nilai === '') {
                    continue;
                }

                $potongan[] = is_float($nilai) || is_int($nilai)
                    ? \App\Support\Laporan::format($nilai, 'rupiah')
                    : (string) $nilai;
            }
        }

        $buku->disconnectWorksheets();
        @unlink($berkas);

        return implode(chr(10), $potongan);
    }

    private function arsipMemuat(string $isiZip, string $berkas): bool
    {
        $sementara = tempnam(sys_get_temp_dir(), 'jpos');
        file_put_contents($sementara, $isiZip);

        $zip = new \ZipArchive();
        $terbuka = $zip->open($sementara) === true;
        $ada = $terbuka && $zip->locateName($berkas) !== false;

        if ($terbuka) {
            $zip->close();
        }

        @unlink($sementara);

        return $ada;
    }
}
