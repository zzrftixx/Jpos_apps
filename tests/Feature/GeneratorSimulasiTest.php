<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\JposTestCase;

/**
 * UAT alat pembuat database simulasi (`jpos:generate-masif-db`).
 *
 * Database ini dipakai untuk MENGAMATI perilaku aplikasi saat toko sudah berjalan masif -
 * ribuan produk, ribuan transaksi. Kalau datanya sendiri cacat, seluruh pengamatan di atasnya
 * menyesatkan: yang memeriksanya akan mengejar cacat yang sebenarnya ada di data, bukan di
 * aplikasi.
 *
 * Itu bukan kekhawatiran teoretis. Versi pertama alat ini membekukan tujuh snapshot neraca
 * bulanan yang KEDELAPANNYA timpang 10-16 juta - bukan karena aplikasinya salah, tapi karena
 * neraca tanggal lampau memang cuma perkiraan (BLUEPRINT §14 nomor 1). Siapa pun yang membuka
 * database itu akan menyimpulkan neracanya rusak.
 *
 * Test ini berjalan pada skala `kecil` supaya cukup cepat untuk ikut di setiap commit -
 * tanpa itu, penjaga ini tidak akan pernah dijalankan siapa pun.
 */
class GeneratorSimulasiTest extends JposTestCase
{
    private string $tujuan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tujuan = sys_get_temp_dir() . '/jpos-simulasi-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->tujuan);

        parent::tearDown();
    }

    /** @return \PDO koneksi ke database simulasi yang baru dibuat */
    private function buatSimulasi(): \PDO
    {
        $kode = Artisan::call('jpos:generate-masif-db', [
            '--skala' => 'kecil',
            '--tujuan' => $this->tujuan,
        ]);

        $this->assertSame(0, $kode, "Pembuatan database simulasi gagal:\n" . Artisan::output());
        $this->assertFileExists($this->tujuan);

        return new \PDO('sqlite:' . $this->tujuan);
    }

    private function hitung(\PDO $db, string $tabel): int
    {
        return (int) $db->query("SELECT COUNT(*) FROM \"{$tabel}\"")->fetchColumn();
    }

    /* -------------------------------------------------------------- keutuhan berkas */

    public function test_database_simulasi_utuh_dan_bebas_pelanggaran_relasi(): void
    {
        $db = $this->buatSimulasi();

        $this->assertSame('ok', $db->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertFalse(
            (bool) $db->query('PRAGMA foreign_key_check')->fetch(),
            'Ada baris yang menunjuk ke induk yang tidak ada.'
        );
    }

    public function test_database_simulasi_berisi_data_di_seluruh_bagian_sistem(): void
    {
        $db = $this->buatSimulasi();

        foreach (['products', 'sales', 'sale_items', 'purchases', 'cash_transactions',
                  'fixed_assets', 'racks', 'rack_slots', 'customers', 'users'] as $tabel) {
            $this->assertGreaterThan(
                0,
                $this->hitung($db, $tabel),
                "Tabel {$tabel} kosong - bagian sistem itu tidak akan teramati sama sekali."
            );
        }
    }

    /* ------------------------------------------------------------- kebenaran angka */

    /**
     * INI YANG PALING MENENTUKAN.
     *
     * Neraca simulasi WAJIB seimbang tepat nol. Kalau tidak, siapa pun yang membukanya akan
     * mengira aplikasinya rusak - dan menghabiskan waktu mengejar cacat yang tidak ada.
     */
    public function test_neraca_database_simulasi_seimbang_tepat_nol(): void
    {
        $db = $this->buatSimulasi();

        $snapshot = $db->query('SELECT total_aset, total_kewajiban, total_modal, selisih FROM neraca_snapshots')
            ->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($snapshot, 'Tidak ada snapshot neraca sama sekali.');

        $this->assertEqualsWithDelta(0, (float) $snapshot['selisih'], 0.01, sprintf(
            "Neraca simulasi tidak seimbang.\nAset %s = Kewajiban %s + Modal %s, selisih %s",
            number_format((float) $snapshot['total_aset']),
            number_format((float) $snapshot['total_kewajiban']),
            number_format((float) $snapshot['total_modal']),
            number_format((float) $snapshot['selisih'])
        ));

        $this->assertEqualsWithDelta(
            (float) $snapshot['total_aset'],
            (float) $snapshot['total_kewajiban'] + (float) $snapshot['total_modal'],
            0.01,
            'Persamaan dasar neraca tidak terpenuhi di data simulasi.'
        );
    }

    /**
     * Snapshot tanggal LAMPAU tidak boleh dibuat.
     *
     * `Akuntansi::posisiPada()` menghitung persediaan dari stok dan harga modal SAAT INI,
     * karena catatan mutasi stok menyimpan kuantitas tanpa nilai. Neraca tanggal lampau
     * karena itu cuma perkiraan - membekukannya menghasilkan angka timpang yang terlihat
     * seperti cacat aplikasi.
     */
    public function test_hanya_membekukan_neraca_hari_ini(): void
    {
        $db = $this->buatSimulasi();

        $this->assertSame(1, $this->hitung($db, 'neraca_snapshots'),
            'Snapshot tanggal lampau ikut dibuat - angkanya pasti timpang dan akan disangka cacat aplikasi.');

        $tanggal = substr((string) $db->query('SELECT tanggal FROM neraca_snapshots')->fetchColumn(), 0, 10);
        $this->assertSame(now()->toDateString(), $tanggal);
    }

    /** Setiap baris penjualan wajib membekukan harga modalnya, kalau tidak laporan laba ngawur. */
    public function test_setiap_baris_penjualan_membawa_harga_modal_yang_dibekukan(): void
    {
        $db = $this->buatSimulasi();

        $tanpaSnapshot = (int) $db->query(
            'SELECT COUNT(*) FROM sale_items WHERE cost_price_snapshot IS NULL'
        )->fetchColumn();

        $this->assertSame(0, $tanpaSnapshot,
            "{$tanpaSnapshot} baris penjualan tanpa cost_price_snapshot - HPP-nya akan dihitung dari harga modal terkini.");
    }

    public function test_tidak_ada_stok_minus_di_data_simulasi(): void
    {
        $db = $this->buatSimulasi();

        $minus = (int) $db->query('SELECT COUNT(*) FROM products WHERE stock < 0')->fetchColumn();

        $this->assertSame(0, $minus, "{$minus} produk berstok minus - nilai persediaan jadi negatif.");
    }

    /* --------------------------------------------------------------------- keamanan */

    /**
     * Salinan simulasi TIDAK BOLEH mendarat di folder backup sungguhan.
     *
     * storage/app/private/backups dibaca menu Pengaturan > Backup & Restore. Salinan simulasi
     * di sana akan tampil berdampingan dengan backup asli, dan sekali klik Restore berarti
     * seluruh data penjualan toko diganti data karangan.
     */
    public function test_tidak_menulis_apa_pun_ke_folder_backup(): void
    {
        $folderBackup = storage_path('app/private/backups');
        File::ensureDirectoryExists($folderBackup);

        $sebelum = collect(File::files($folderBackup))->map->getFilename()->sort()->values()->all();

        $this->buatSimulasi();

        $sesudah = collect(File::files($folderBackup))->map->getFilename()->sort()->values()->all();

        $this->assertSame($sebelum, $sesudah,
            'Alat simulasi menulis ke folder backup - berkasnya akan muncul di menu Restore.');
    }

    /** Skala yang tidak dikenal jatuh ke bawaan, bukan galat. */
    public function test_skala_yang_tidak_dikenal_tetap_menghasilkan_database(): void
    {
        $kode = Artisan::call('jpos:generate-masif-db', [
            '--skala' => 'entah-apa',
            '--tujuan' => $this->tujuan,
        ]);

        $this->assertSame(0, $kode);
        $this->assertFileExists($this->tujuan);
    }
}
