<?php

namespace Tests\Feature;

use App\Console\Commands\PulihkanLoginCommand;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\SqliteBackup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Penjagaan data: riwayat backup, penghapusannya, dan pemulihan login.
 *
 * Data adalah satu-satunya hal di aplikasi ini yang tidak bisa dibuat ulang. Kode yang rusak
 * bisa diperbaiki dan dikirim lagi; penjualan tiga bulan yang hilang tidak bisa dikembalikan
 * oleh siapa pun. Yang dijaga di sini semuanya berada di jalur itu.
 *
 * Memakai database BERKAS sementara, bukan :memory: seperti kebanyakan test lain - sama
 * seperti BackupRestoreTest, karena yang diuji di sini justru perilaku terhadap berkas:
 * urutan berdasarkan waktu berkas, penghapusan, dan snapshot sebelum perubahan.
 */
class PenjagaanDataTest extends TestCase
{
    private string $workspace;
    private string $dbFile;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir() . '/jpos-jaga-' . bin2hex(random_bytes(6));
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

    /** Berkas backup palsu dengan waktu tertentu, supaya urutannya bisa diuji. */
    private function berkasBackup(string $nama, int $mundurDetik = 0): string
    {
        $path = $this->backup()->directory() . DIRECTORY_SEPARATOR . $nama;

        File::put($path, 'isi-palsu');
        touch($path, now()->getTimestamp() - $mundurDetik);

        return $path;
    }

    // ---------------------------------------------------------------------
    // Urutan riwayat backup
    // ---------------------------------------------------------------------

    /**
     * INI BUG YANG DIPERBAIKI. Daftar dulu diurutkan berdasarkan NAMA berkas, dan secara
     * abjad `pre-wipe` berada di atas `backup`. Snapshot lama karena itu duduk di paling
     * atas, di atas backup yang baru dibuat lima menit lalu - dan orang yang panik akan
     * mengambil yang paling atas.
     */
    public function test_backup_terbaru_selalu_di_paling_atas_walau_awalannya_berbeda(): void
    {
        $this->berkasBackup('pre-wipe-2026-01-01_100000.sqlite', 60 * 60 * 24 * 30);
        $this->berkasBackup('backup-2026-08-18_120000.sqlite', 300);
        $this->berkasBackup('otomatis-2026-08-17_080000.sqlite', 60 * 60 * 24);

        $daftar = $this->backup()->daftar();

        $this->assertSame('backup-2026-08-18_120000.sqlite', $daftar[0]['nama'],
            'Backup terbaru tidak berada di paling atas.');
        $this->assertSame('pre-wipe-2026-01-01_100000.sqlite', $daftar[2]['nama'],
            'Backup terlama tidak berada di paling bawah.');
    }

    public function test_setiap_backup_diberi_keterangan_jenis(): void
    {
        $b = $this->backup();

        $this->assertSame('Manual', $b->jenisDari('backup-2026-08-18_120000.sqlite'));
        $this->assertSame('Otomatis harian', $b->jenisDari('otomatis-2026-08-18_120000.sqlite'));
        $this->assertSame('Sebelum pembaruan', $b->jenisDari('pre-update-2026-08-18_120000.sqlite'));
        $this->assertSame('Sebelum hapus data', $b->jenisDari('pre-wipe-2026-08-18_120000.sqlite'));

        // Awalan yang mengandung tanda hubung sendiri - memecah dengan explode('-') salah di sini.
        $this->assertSame('Sebelum perbaikan harga',
            $b->jenisDari('pre-perbaikan-harga-2026-08-18_120000.sqlite'));

        // Nama dengan pembeda detik-yang-sama tetap terbaca jenisnya.
        $this->assertSame('Manual', $b->jenisDari('backup-2026-08-18_120000-2.sqlite'));
    }

    // ---------------------------------------------------------------------
    // Penghapusan riwayat backup
    // ---------------------------------------------------------------------

    public function test_backup_bisa_dihapus_dari_riwayat(): void
    {
        $target = $this->berkasBackup('backup-2026-08-18_120000.sqlite');
        $this->berkasBackup('backup-2026-08-17_120000.sqlite', 86400);

        $this->assertNull($this->backup()->hapus('backup-2026-08-18_120000.sqlite'));
        $this->assertFileDoesNotExist($target);
        $this->assertCount(1, $this->backup()->daftar());
    }

    /** Folder backup kosong berarti toko berjalan tanpa satu pun titik balik. */
    public function test_backup_terakhir_tidak_boleh_dihapus(): void
    {
        $satunya = $this->berkasBackup('backup-2026-08-18_120000.sqlite');

        $pesan = $this->backup()->hapus('backup-2026-08-18_120000.sqlite');

        $this->assertNotNull($pesan, 'Backup terakhir seharusnya ditolak untuk dihapus.');
        $this->assertFileExists($satunya);
    }

    /**
     * PALING MENENTUKAN DI BAGIAN INI: nama berkas datang dari permintaan HTTP, jadi ia
     * harus tidak bisa dipakai menghapus apa pun di luar folder backup - termasuk database
     * yang sedang dipakai toko.
     */
    public function test_nama_berkas_tidak_bisa_dipakai_keluar_dari_folder_backup(): void
    {
        $this->berkasBackup('backup-2026-08-18_120000.sqlite');
        $this->berkasBackup('backup-2026-08-17_120000.sqlite', 86400);

        $this->assertFileExists($this->dbFile);

        $luar = $this->workspace . '/jangan-sentuh.sqlite';
        File::put($luar, 'berkas penting di luar folder backup');

        $folderBackup = $this->backup()->directory();
        $relatifKeDb = $this->jalurRelatif($folderBackup, $this->dbFile);
        $relatifKeLuar = $this->jalurRelatif($folderBackup, $luar);

        foreach ([
            $relatifKeDb,
            str_replace('/', '\\', $relatifKeDb),
            $relatifKeLuar,
            $this->dbFile,
            'catatan.txt',
            '',
            '   ',
        ] as $jahat) {
            $this->assertNotNull($this->backup()->hapus($jahat),
                'Nama berkas "' . $jahat . '" seharusnya ditolak.');
        }

        $this->assertFileExists($this->dbFile, 'Database aktif ikut terhapus.');
        $this->assertFileExists($luar, 'Berkas di luar folder backup ikut terhapus.');
        $this->assertCount(2, $this->backup()->daftar());
    }

    private function jalurRelatif(string $dari, string $ke): string
    {
        return str_repeat('..' . DIRECTORY_SEPARATOR, substr_count(trim($dari, '/\\'), DIRECTORY_SEPARATOR) + 1)
            . ltrim($ke, '/\\');
    }

    public function test_halaman_backup_menghapus_lewat_tombol(): void
    {
        $this->berkasBackup('backup-2026-08-18_120000.sqlite');
        $this->berkasBackup('backup-2026-08-17_120000.sqlite', 86400);

        $this->actingAs($this->admin)
            ->delete(route('pengaturan.backup-restore.hapus'), ['nama' => 'backup-2026-08-17_120000.sqlite'])
            ->assertRedirect();

        $this->assertCount(1, $this->backup()->daftar());
    }

    public function test_halaman_backup_menampilkan_daftar_terbaru_di_atas(): void
    {
        $this->berkasBackup('pre-wipe-2026-01-01_100000.sqlite', 60 * 60 * 24 * 30);
        $this->berkasBackup('backup-2026-08-18_120000.sqlite', 60);

        $isi = $this->actingAs($this->admin)
            ->get(route('pengaturan.backup-restore'))
            ->assertOk()
            ->getContent();

        $posisiBaru = strpos($isi, 'backup-2026-08-18_120000.sqlite');
        $posisiLama = strpos($isi, 'pre-wipe-2026-01-01_100000.sqlite');

        $this->assertNotFalse($posisiBaru);
        $this->assertNotFalse($posisiLama);
        $this->assertLessThan($posisiLama, $posisiBaru, 'Backup terbaru tidak tampil lebih dulu di halaman.');
    }

    // ---------------------------------------------------------------------
    // Penyimpanan snapshot pengaman
    // ---------------------------------------------------------------------

    /**
     * Sejak ada backup otomatis harian, backup biasa lahir tiap hari. Tanpa jatah terpisah,
     * sebulan pemakaian mendorong keluar satu-satunya salinan sebelum pembaruan - justru
     * berkas yang paling dibutuhkan kalau pembaruan ternyata bermasalah.
     */
    public function test_snapshot_pengaman_tidak_ikut_terbuang_oleh_backup_harian(): void
    {
        $this->berkasBackup('pre-update-2026-01-01_100000.sqlite', 60 * 60 * 24 * 60);

        for ($i = 1; $i <= SqliteBackup::KEEP + 5; $i++) {
            $this->berkasBackup(sprintf('otomatis-har-%03d.sqlite', $i), $i * 3600);
        }

        $this->backup()->prune();

        $nama = array_column($this->backup()->daftar(), 'nama');

        $this->assertContains('pre-update-2026-01-01_100000.sqlite', $nama,
            'Snapshot sebelum pembaruan terbuang oleh backup harian.');
    }

    /**
     * Menghitung JUMLAH berkas saja tidak cukup untuk toko besar.
     *
     * Berkas backup sebesar databasenya. Diukur pada simulasi yang digelembungkan sampai
     * 33,6 MB: batas 30 berkas berarti 1 GB backup, dan toko dengan database 100 MB
     * menumpuk 3,5 GB. Disk komputer kasir yang penuh berarti SQLite tidak bisa menulis -
     * yaitu kasir berhenti bisa berjualan.
     */
    public function test_total_folder_backup_dibatasi_ukurannya(): void
    {
        // 1 MB per berkas, batas diturunkan ke 5 MB supaya testnya cepat.
        $isi = str_repeat('x', 1024 * 1024);

        for ($i = 1; $i <= 20; $i++) {
            $path = $this->backup()->directory() . DIRECTORY_SEPARATOR . sprintf('backup-besar-%03d.sqlite', $i);
            File::put($path, $isi);
            touch($path, now()->getTimestamp() - $i * 3600);
        }

        $this->backup()->prune(keep: 30, batasMb: 5);

        $daftar = $this->backup()->daftar();

        // Lantai SIMPAN_MINIMAL menang atas batas ukuran: folder backup tidak boleh
        // bisa dikosongkan oleh batas ukuran.
        $this->assertCount(SqliteBackup::SIMPAN_MINIMAL, $daftar,
            'Batas ukuran tidak menurunkan jumlah salinan ke lantai minimal.');

        $this->assertSame('backup-besar-001.sqlite', $daftar[0]['nama'],
            'Yang dibuang seharusnya yang terlama, bukan yang terbaru.');
    }

    /** Snapshot pengaman tetap disimpan walau batas ukuran sudah terlampaui. */
    public function test_batas_ukuran_tidak_membuang_snapshot_pengaman(): void
    {
        $isi = str_repeat('x', 1024 * 1024);

        $pengaman = $this->backup()->directory() . DIRECTORY_SEPARATOR . 'pre-update-2026-01-01_100000.sqlite';
        File::put($pengaman, $isi);
        touch($pengaman, now()->getTimestamp() - 60 * 60 * 24 * 365);

        for ($i = 1; $i <= 20; $i++) {
            $path = $this->backup()->directory() . DIRECTORY_SEPARATOR . sprintf('backup-besar-%03d.sqlite', $i);
            File::put($path, $isi);
            touch($path, now()->getTimestamp() - $i * 3600);
        }

        $this->backup()->prune(keep: 30, batasMb: 5);

        $this->assertContains('pre-update-2026-01-01_100000.sqlite',
            array_column($this->backup()->daftar(), 'nama'),
            'Snapshot pengaman terbuang oleh batas ukuran.');
    }

    // ---------------------------------------------------------------------
    // Backup otomatis harian
    // ---------------------------------------------------------------------

    public function test_backup_otomatis_dibuat_sekali_sehari(): void
    {
        $this->assertCount(0, $this->backup()->daftar());

        Artisan::call('jpos:prepare', ['--skip-cache' => true]);
        $sesudahPertama = $this->backup()->daftar();

        $this->assertCount(1, $sesudahPertama, 'Salinan otomatis harian tidak dibuat saat aplikasi dibuka.');
        $this->assertSame('Otomatis harian', $sesudahPertama[0]['jenis']);

        // Dibuka lagi di hari yang sama - tidak boleh menumpuk salinan.
        Artisan::call('jpos:prepare', ['--skip-cache' => true]);
        $this->assertCount(1, $this->backup()->daftar(),
            'Aplikasi membuat salinan otomatis lebih dari sekali dalam sehari.');
    }

    // ---------------------------------------------------------------------
    // Pemulihan login
    // ---------------------------------------------------------------------

    public function test_pemulihan_login_mengembalikan_password_ke_bawaan(): void
    {
        $this->admin->password = 'sesuatu-yang-panjang-dan-terlupakan';
        $this->admin->is_active = false;
        $this->admin->save();

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->artisan('jpos:pulihkan-login', ['--user' => 'admin', '--yakin' => true])
            ->assertSuccessful();

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($this->admin->fresh()->is_active, 'Akun tidak diaktifkan kembali.');
    }

    /** Pemulihan pun tidak boleh berjalan tanpa titik balik. */
    public function test_pemulihan_login_membuat_snapshot_lebih_dulu(): void
    {
        $this->artisan('jpos:pulihkan-login', ['--user' => 'admin', '--yakin' => true])
            ->assertSuccessful();

        $daftar = $this->backup()->daftar();
        $jenis = array_column($daftar, 'jenis');

        $this->assertContains('Sebelum pemulihan login', $jenis,
            'Snapshot sebelum pemulihan login tidak dibuat.');
        $this->assertTrue($daftar[0]['pengaman'],
            'Snapshot pemulihan login tidak ditandai sebagai salinan pengaman.');
    }

    // ---------------------------------------------------------------------
    // Pengerasan halaman login
    // ---------------------------------------------------------------------

    /**
     * Password bawaan aplikasi ini diketahui umum, dan memang harus selalu bisa dipulihkan.
     * Yang membuatnya tetap aman bukan kerahasiaan passwordnya, tapi tidak adanya kesempatan
     * mencoba berkali-kali. Sampai versi ini halaman login menerima percobaan sebanyak apa
     * pun tanpa jeda sedetik pun.
     */
    public function test_percobaan_login_beruntun_dijeda(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'salah-' . $i])
                ->assertSessionHasErrors('username');
        }

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'salah-lagi'])
            ->assertSessionHasErrorsIn('default', ['username']);

        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('username'),
            'Login tidak dijeda setelah percobaan beruntun.'
        );

        // Yang menentukan: password BENAR pun ikut ditolak selama masa jeda. Kalau tidak,
        // penebak cukup menunggu satu percobaan berhasil dan pembatasnya tidak ada gunanya.
        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    /** Satu kasir yang salah ketik tidak boleh mengunci kasir lain di komputer yang sama. */
    public function test_jeda_hanya_berlaku_untuk_akun_yang_salah_ketik(): void
    {
        $kasirRole = Role::create([
            'name' => 'Kasir', 'slug' => 'kasir',
            'permissions' => ['dashboard', 'kasir'], 'is_system' => true,
        ]);

        User::create([
            'name' => 'Kasir Satu', 'username' => 'kasir1', 'email' => 'kasir1@jpos.local',
            'password' => Hash::make('rahasia123'), 'role_id' => $kasirRole->id, 'is_active' => true,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $this->post(route('login.attempt'), ['username' => 'kasir1', 'password' => 'salah-' . $i]);
        }

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
    }

    /** Login yang berhasil menghapus hitungan, supaya salah ketik biasa tidak menumpuk. */
    public function test_hitungan_percobaan_dikosongkan_setelah_berhasil_masuk(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'salah-' . $i]);
        }

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'salah-lagi-' . $i]);
        }

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
    }

    /** Tanpa konfirmasi diketik, klik tak sengaja tidak boleh mengubah apa pun. */
    public function test_pemulihan_login_batal_tanpa_konfirmasi(): void
    {
        $this->admin->password = 'password-asli-pemilik';
        $this->admin->save();

        $sebelum = $this->admin->fresh()->password;

        $this->artisan('jpos:pulihkan-login', ['--user' => 'admin'])
            ->expectsQuestion('  Ketik PULIHKAN untuk melanjutkan, atau tekan Enter untuk membatalkan', '')
            ->assertFailed();

        $this->assertSame($sebelum, $this->admin->fresh()->password, 'Password berubah walau dibatalkan.');
    }

    /**
     * Alat ini boleh selalu ada JUSTRU karena tidak bisa dipakai diam-diam. Kalau jejaknya
     * hilang, alat yang sama berubah jadi pintu belakang.
     */
    public function test_pemulihan_login_meninggalkan_jejak_yang_terlihat(): void
    {
        $this->artisan('jpos:pulihkan-login', ['--user' => 'admin', '--yakin' => true])
            ->assertSuccessful();

        $catatan = Setting::get(PulihkanLoginCommand::KUNCI_CATATAN);
        $this->assertIsArray($catatan);
        $this->assertSame('admin', $catatan['username']);

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'password']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dikembalikan ke bawaan lewat alat pemulihan', false);
    }

    /** Riwayat di berkas log tidak boleh bisa dihapus dari dalam aplikasi. */
    public function test_jejak_di_berkas_log_bertahan_walau_peringatan_ditutup(): void
    {
        $log = storage_path('logs/pemulihan-login.log');

        $this->artisan('jpos:pulihkan-login', ['--user' => 'admin', '--yakin' => true])
            ->assertSuccessful();

        $this->assertFileExists($log);
        $this->assertStringContainsString('admin', file_get_contents($log));

        $this->actingAs($this->admin->fresh())
            ->delete(route('pemulihan-login.tutup'))
            ->assertRedirect();

        $this->assertNull(Setting::get(PulihkanLoginCommand::KUNCI_CATATAN));
        $this->assertStringContainsString('admin', file_get_contents($log),
            'Riwayat di berkas log ikut terhapus dari dalam aplikasi.');
    }

    /** Toko yang kehilangan akun admin terakhirnya tidak boleh terkunci selamanya. */
    public function test_akun_admin_dibuatkan_ulang_kalau_sudah_tidak_ada(): void
    {
        User::query()->delete();

        $this->artisan('jpos:pulihkan-login', ['--yakin' => true])->assertSuccessful();

        $admin = User::where('username', 'admin')->first();

        $this->assertNotNull($admin, 'Akun admin tidak dibuatkan ulang.');
        $this->assertSame('admin', $admin->role?->slug);

        $this->post(route('login.attempt'), ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    /** Hanya yang boleh mengurus user yang boleh menutup peringatannya. */
    public function test_kasir_tidak_bisa_menutup_peringatan_pemulihan(): void
    {
        Setting::set(PulihkanLoginCommand::KUNCI_CATATAN, [
            'username' => 'admin', 'waktu' => now()->toDateTimeString(),
        ]);

        $kasirRole = Role::create([
            'name' => 'Kasir', 'slug' => 'kasir',
            'permissions' => ['dashboard', 'kasir'], 'is_system' => true,
        ]);

        $kasir = User::create([
            'name' => 'Kasir Satu', 'username' => 'kasir1', 'email' => 'kasir1@jpos.local',
            'password' => Hash::make('rahasia123'), 'role_id' => $kasirRole->id, 'is_active' => true,
        ]);

        $this->actingAs($kasir)->delete(route('pemulihan-login.tutup'))->assertForbidden();

        $this->assertNotNull(Setting::get(PulihkanLoginCommand::KUNCI_CATATAN));
    }
}
