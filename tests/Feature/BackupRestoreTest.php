<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\SqliteBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UAT Backup & Restore.
 *
 * Sengaja TIDAK memakai SQLite :memory: seperti test lain: seluruh masalah yang
 * diperbaiki di sini - lokasi folder, mode WAL, penggantian file database - hanya
 * muncul pada database berbasis file. Setiap test memakai file sementara sendiri,
 * jadi database dev maupun client tidak pernah tersentuh.
 */
class BackupRestoreTest extends TestCase
{
    private string $workspace;
    private string $dbFile;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir() . '/jpos-uat-' . bin2hex(random_bytes(6));
        File::makeDirectory($this->workspace, 0755, true);
        $this->dbFile = $this->workspace . '/database.sqlite';
        touch($this->dbFile);

        // Alihkan aplikasi ke database file sementara, lengkap dengan mode WAL
        // seperti konfigurasi produksi.
        config([
            'database.connections.sqlite.database' => $this->dbFile,
            'database.connections.sqlite.journal_mode' => 'wal',
        ]);
        DB::purge('sqlite');

        // Folder backup juga diarahkan ke workspace supaya tidak mengotori storage asli.
        app()->useStoragePath($this->workspace . '/storage');
        File::makeDirectory($this->workspace . '/storage/app/private', 0755, true);
        // Root disk 'local' sudah dibekukan ke config saat boot, jadi harus ikut diarahkan.
        config(['filesystems.disks.local.root' => $this->workspace . '/storage/app/private']);
        Storage::forgetDisk('local');

        Artisan::call('migrate', ['--force' => true]);

        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => array_keys(Role::allMenuKeys()),
            'is_system' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@jpos.local',
            'password' => Hash::make('rahasia123'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    private function backup(): SqliteBackup
    {
        return app(SqliteBackup::class);
    }

    private function makeProduct(string $name): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'name' => $name,
            'type' => 'barang',
            'sku' => 'SKU-BK' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'unit' => 'pcs',
            'cost_price' => 1000,
            'sell_price' => 2000,
            'stock' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Regresi K3: backup ditulis ke storage/app/backups sementara daftarnya dibaca
     * dari storage/app/private/backups, sehingga backup tidak pernah terlihat.
     */
    public function test_backup_yang_dibuat_langsung_muncul_di_daftar_dan_bisa_diunduh(): void
    {
        $this->makeProduct('Produk Sebelum Backup');

        $this->actingAs($this->admin)
            ->post('/pengaturan/backup-restore/backup')
            ->assertSessionHas('success');

        $response = $this->actingAs($this->admin)->get('/pengaturan/backup-restore');
        $response->assertOk();
        $response->assertDontSee('Belum ada backup.');

        $files = glob($this->workspace . '/storage/app/private/backups/*.sqlite');
        $this->assertCount(1, $files, 'Backup harus berada di folder yang dibaca halaman Backup & Restore.');

        $this->actingAs($this->admin)
            ->get('/pengaturan/backup-restore/download/' . basename($files[0]))
            ->assertOk()
            ->assertDownload(basename($files[0]));
    }

    /**
     * Regresi K4: database berjalan mode WAL, jadi transaksi terbaru bisa masih berada
     * di file -wal. copy() polos akan melewatkannya dan backup diam-diam kehilangan
     * penjualan terakhir.
     */
    public function test_backup_menyertakan_transaksi_yang_masih_berada_di_wal(): void
    {
        $this->assertSame('wal', DB::select('PRAGMA journal_mode')[0]->journal_mode);

        $this->makeProduct('Produk Lama');
        // Tulis data baru lalu JANGAN checkpoint - ini kondisi normal kasir yang baru
        // saja menyelesaikan transaksi.
        $this->makeProduct('Produk Baru Sekali');

        $this->assertFileExists($this->dbFile . '-wal', 'Prasyarat test: harus ada file -wal.');

        $path = $this->backup()->create();

        $snapshot = new \PDO('sqlite:' . $path);
        $names = $snapshot->query('SELECT name FROM products ORDER BY name')->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('Produk Baru Sekali', $names, 'Transaksi yang masih di WAL wajib ikut ter-backup.');
        $this->assertContains('Produk Lama', $names);
    }

    /**
     * Bukti pembanding: cara lama (copy file utama begitu saja) memang kehilangan data.
     */
    public function test_copy_polos_terbukti_kehilangan_data_yang_masih_di_wal(): void
    {
        $this->makeProduct('Produk Lama');
        $this->makeProduct('Produk Baru Sekali');

        $naive = $this->workspace . '/salinan-polos.sqlite';
        copy($this->dbFile, $naive);

        $snapshot = new \PDO('sqlite:' . $naive);
        $snapshot->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            $names = $snapshot->query('SELECT name FROM products')->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            // Bahkan lebih parah dari dugaan: struktur tabelnya pun masih di WAL,
            // jadi hasil salinan polos sama sekali tidak bisa dipakai.
            $this->assertStringContainsString('no such table', $e->getMessage());

            return;
        }

        $this->assertNotContains(
            'Produk Baru Sekali',
            $names,
            'Kalau ini gagal, berarti WAL kebetulan sudah ter-checkpoint dan pembandingnya tidak valid.'
        );
    }

    /** Regresi K5: file sembarangan tidak boleh menimpa database aktif. */
    public function test_restore_menolak_file_yang_bukan_database_sqlite(): void
    {
        $this->makeProduct('Produk Penting');

        $sampah = UploadedFile::fake()->createWithContent('laporan.pdf', '%PDF-1.4 bukan database');

        $this->actingAs($this->admin)
            ->post('/pengaturan/backup-restore/restore', ['backup_file' => $sampah])
            ->assertSessionHas('error');

        $this->assertSame(1, Product::count(), 'Database aktif harus utuh setelah restore ditolak.');
        $this->assertSame('Produk Penting', Product::first()->name);
    }

    public function test_restore_menolak_database_sqlite_dari_aplikasi_lain(): void
    {
        $this->makeProduct('Produk Penting');

        $asing = $this->workspace . '/aplikasi-lain.sqlite';
        $pdo = new \PDO('sqlite:' . $asing);
        $pdo->exec('CREATE TABLE catatan (id INTEGER PRIMARY KEY, isi TEXT)');
        $pdo = null;

        $this->actingAs($this->admin)
            ->post('/pengaturan/backup-restore/restore', [
                'backup_file' => new UploadedFile($asing, 'aplikasi-lain.sqlite', 'application/x-sqlite3', null, true),
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, Product::count());
    }

    public function test_restore_backup_yang_sah_mengembalikan_data(): void
    {
        $this->makeProduct('Produk Saat Backup');
        $arsip = $this->backup()->create();

        // Kondisi berubah setelah backup diambil.
        Product::query()->delete();
        $this->makeProduct('Produk Setelah Backup');
        $this->assertSame(['Produk Setelah Backup'], Product::pluck('name')->all());

        $response = $this->actingAs($this->admin)->post('/pengaturan/backup-restore/restore', [
            'backup_file' => new UploadedFile($arsip, basename($arsip), 'application/x-sqlite3', null, true),
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');
        // Sesi lama tidak ada di database hasil restore, jadi user harus dikeluarkan tertib.
        $this->assertGuest();

        DB::purge('sqlite');
        $this->assertSame(['Produk Saat Backup'], Product::pluck('name')->all());
    }

    public function test_restore_membuat_backup_pengaman_lebih_dulu(): void
    {
        $this->makeProduct('Kondisi Sebelum Restore');
        $arsip = $this->backup()->create();

        $this->actingAs($this->admin)->post('/pengaturan/backup-restore/restore', [
            'backup_file' => new UploadedFile($arsip, basename($arsip), 'application/x-sqlite3', null, true),
        ]);

        $pengaman = glob($this->workspace . '/storage/app/private/backups/pre-restore-*.sqlite');
        $this->assertNotEmpty($pengaman, 'Harus ada salinan pre-restore di folder yang bisa diakses user.');
    }

    /** Sisa file -wal milik database lama bisa merusak database hasil restore. */
    public function test_restore_membersihkan_file_wal_lama(): void
    {
        $this->makeProduct('Produk A');
        $arsip = $this->backup()->create();
        $this->makeProduct('Produk B');

        $this->assertFileExists($this->dbFile . '-wal');

        $this->backup()->replaceWith($arsip);

        $this->assertFileDoesNotExist($this->dbFile . '-wal', 'File -wal lama wajib dibuang saat database diganti.');
        $this->assertFileDoesNotExist($this->dbFile . '-shm');

        DB::purge('sqlite');
        $this->assertSame(['Produk A'], Product::pluck('name')->all());
        $this->assertSame('ok', DB::select('PRAGMA integrity_check')[0]->integrity_check);
    }

    public function test_wipe_data_membuat_backup_pengaman_dan_menyisakan_user(): void
    {
        $this->makeProduct('Produk Yang Akan Dihapus');

        $this->actingAs($this->admin)
            ->post('/pengaturan/database/wipe', ['confirmation' => 'HAPUS SEMUA DATA'])
            ->assertSessionHas('success');

        $this->assertSame(0, Product::count());
        $this->assertSame(1, User::count(), 'User & role sengaja tidak ikut dihapus supaya masih bisa login.');

        $pengaman = glob($this->workspace . '/storage/app/private/backups/pre-wipe-*.sqlite');
        $this->assertNotEmpty($pengaman, 'Salinan pre-wipe harus tersimpan di folder yang bisa diakses user.');

        $snapshot = new \PDO('sqlite:' . $pengaman[0]);
        $this->assertSame(
            1,
            (int) $snapshot->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'Backup pengaman harus berisi data SEBELUM dihapus.'
        );
    }

    public function test_wipe_data_dengan_konfirmasi_salah_tidak_menghapus_apa_pun(): void
    {
        $this->makeProduct('Produk Aman');

        $this->actingAs($this->admin)
            ->post('/pengaturan/database/wipe', ['confirmation' => 'hapus'])
            ->assertSessionHas('error');

        $this->assertSame(1, Product::count());
    }

    public function test_backup_lama_dibuang_otomatis_agar_folder_tidak_membengkak(): void
    {
        $directory = $this->workspace . '/storage/app/private/backups';
        File::makeDirectory($directory, 0755, true);

        // 35 backup lama palsu, umur berbeda-beda.
        for ($i = 0; $i < 35; $i++) {
            $file = $directory . '/backup-lama-' . $i . '.sqlite';
            file_put_contents($file, 'x');
            touch($file, time() - (3600 * ($i + 10)));
        }

        $this->backup()->create();

        $tersisa = glob($directory . '/*.sqlite');
        $this->assertCount(SqliteBackup::KEEP, $tersisa);

        // Yang dipertahankan harus yang paling baru, termasuk backup yang barusan dibuat.
        $this->assertNotEmpty(preg_grep('/backup-\d{4}-\d{2}-\d{2}_/', $tersisa));
    }

    public function test_download_menolak_path_traversal(): void
    {
        $this->actingAs($this->admin)
            ->get('/pengaturan/backup-restore/download/' . urlencode('../../../.env'))
            ->assertNotFound();
    }
}
