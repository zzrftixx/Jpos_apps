<?php

namespace Tests\Feature;

use App\Support\SqliteBackup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Apa yang terjadi kalau migrasi GAGAL di tengah jalan.
 *
 * Ini pelajaran dari kejadian nyata. Database sebuah toko gagal diperbarui di migrasi ke-2
 * dari 10 (`duplicate column name`). Perubahan skema di SQLite tidak transaksional, jadi dua
 * migrasi pertama tetap menempel: sebagian tabel sudah berbentuk baru, sebagian masih lama.
 *
 * Snapshot `pre-update` sebetulnya sudah dibuat sedetik sebelumnya - tapi tidak ada apa pun
 * yang memberitahu pemilik toko bahwa berkas itu ada. Yang ia lihat hanya "database tidak
 * terbaca", dan sejak itu tokonya berhenti bisa berjualan.
 *
 * Penyebab spesifik kejadian itu sudah diperbaiki (migrasi dibuat idempoten, lihat
 * MigrasiBaselineLamaTest). Yang dijaga DI SINI adalah lapisan berikutnya: apa pun sebab
 * kegagalan berikutnya - dan pasti ada - database tidak boleh tertinggal setengah jadi.
 */
class MigrasiGagalTest extends TestCase
{
    private string $workspace;
    private string $dbFile;
    private string $migrasiPalsu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir() . '/jpos-migrasi-' . bin2hex(random_bytes(6));
        File::makeDirectory($this->workspace, 0755, true);
        $this->dbFile = $this->workspace . '/database.sqlite';
        touch($this->dbFile);

        config([
            'database.connections.sqlite.database' => $this->dbFile,
            'database.connections.sqlite.journal_mode' => 'wal',
        ]);
        DB::purge('sqlite');

        app()->useStoragePath($this->workspace . '/storage');
        File::makeDirectory($this->workspace . '/storage/app/private', 0755, true);
        File::makeDirectory($this->workspace . '/storage/logs', 0755, true);
        config(['filesystems.disks.local.root' => $this->workspace . '/storage/app/private']);
        Storage::forgetDisk('local');

        Artisan::call('migrate', ['--force' => true]);

        $this->migrasiPalsu = '';
    }

    protected function tearDown(): void
    {
        if ($this->migrasiPalsu !== '' && is_file($this->migrasiPalsu)) {
            @unlink($this->migrasiPalsu);
        }

        DB::disconnect('sqlite');
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    /**
     * Tanam migrasi yang PASTI gagal, ditaruh paling belakang supaya migrasi sebelumnya
     * sempat berjalan lebih dulu - persis bentuk kegagalan yang dialami client: sebagian
     * berhasil, lalu berhenti.
     */
    private function tanamMigrasiGagal(): void
    {
        $this->migrasiPalsu = database_path('migrations/9999_12_31_235959_migrasi_yang_pasti_gagal.php');

        File::put($this->migrasiPalsu, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE penanda_migrasi_gagal (id INTEGER PRIMARY KEY)');
        DB::statement('ALTER TABLE tabel_yang_tidak_ada ADD COLUMN apa_saja TEXT');
    }
};
PHP);
    }

    /**
     * PALING MENENTUKAN: database tidak boleh tertinggal setengah termigrasi.
     *
     * Migrasi palsu membuat satu tabel penanda lebih dulu, baru gagal. Kalau penanda itu
     * masih ada sesudahnya, artinya perubahan separuh jalan tertinggal di database toko.
     */
    public function test_migrasi_gagal_mengembalikan_database_ke_keadaan_semula(): void
    {
        DB::table('settings')->insert([
            'key' => 'penanda_data_toko', 'value' => 'harus-selamat',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->tanamMigrasiGagal();

        $keluar = Artisan::call('jpos:prepare', ['--skip-cache' => true]);

        $this->assertNotSame(0, $keluar, 'Start seharusnya digagalkan, bukan dilanjutkan di atas skema yang salah.');

        DB::purge('sqlite');

        $this->assertFalse(
            DB::getSchemaBuilder()->hasTable('penanda_migrasi_gagal'),
            'Perubahan dari migrasi yang gagal tertinggal - database setengah termigrasi.'
        );

        $this->assertSame('harus-selamat',
            DB::table('settings')->where('key', 'penanda_data_toko')->value('value'),
            'Data toko hilang saat pengembalian.');
    }

    /** Snapshot sebelum pembaruan harus benar-benar dibuat, dan namanya disebutkan. */
    public function test_snapshot_sebelum_pembaruan_dibuat_dan_disebutkan(): void
    {
        $this->tanamMigrasiGagal();

        // Dipanggil lewat $this->artisan(), bukan Artisan::call() + Artisan::output():
        // yang kedua mengembalikan keluaran perintah TERDALAM (migrate), bukan keluaran
        // jpos:prepare yang justru berisi pemberitahuan pengembaliannya.
        $this->artisan('jpos:prepare', ['--skip-cache' => true])
            ->expectsOutputToContain('MIGRASI GAGAL')
            ->expectsOutputToContain('dikembalikan ke keadaan sebelum pembaruan')
            ->assertFailed();

        $jenis = array_column(app(SqliteBackup::class)->daftar(), 'jenis');

        $this->assertContains('Sebelum pembaruan', $jenis, 'Snapshot pre-update tidak dibuat.');
    }

    /** Kalau tidak ada yang gagal, start berjalan normal tanpa efek samping apa pun. */
    public function test_start_normal_tidak_terpengaruh(): void
    {
        $this->artisan('jpos:prepare', ['--skip-cache' => true])
            ->expectsOutputToContain('JPOS siap dijalankan')
            ->assertSuccessful();
    }
}
