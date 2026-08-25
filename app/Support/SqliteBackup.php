<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Backup & pemulihan database SQLite JPOS.
 *
 * Dua masalah yang diperbaiki di sini:
 *
 * 1. Lokasi. createBackup() dulu menulis ke storage/app/backups sementara daftar backup
 *    dibaca lewat Storage::disk('local') yang sejak Laravel 11 berakar di
 *    storage/app/private. Akibatnya backup yang dibuat kasir tidak pernah muncul di
 *    daftar dan tidak bisa diunduh - termasuk salinan pengaman sebelum Wipe/Restore.
 *
 * 2. Konsistensi. Database berjalan dalam mode WAL, jadi transaksi terbaru bisa masih
 *    berada di file -wal. copy() polos hanya menyalin file utama sehingga backup diam-diam
 *    kehilangan penjualan paling akhir. VACUUM INTO menghasilkan snapshot yang dijamin
 *    konsisten dalam satu operasi atomik, sekaligus memadatkan file.
 */
class SqliteBackup
{
    /** Jumlah file backup yang dipertahankan sebelum yang terlama dibuang. */
    public const KEEP = 30;

    /**
     * Snapshot pengaman terbaru yang SELALU dipertahankan, walau sudah lewat batas KEEP.
     *
     * Backup manual dan harian datang terus setiap hari; snapshot pengaman hanya lahir di
     * saat-saat berbahaya (sebelum update, restore, migrasi, wipe). Kalau keduanya diperlakukan
     * sama, sepuluh hari backup harian bisa mendorong keluar satu-satunya titik balik sebelum
     * pembaruan - justru berkas yang paling dibutuhkan saat pembaruan ternyata bermasalah.
     */
    public const SIMPAN_PENGAMAN = 5;

    /**
     * Backup terbaru yang dipertahankan APA PUN ukurannya.
     *
     * Batas ukuran di bawah tidak boleh bisa mengosongkan folder backup. Kalau satu berkas
     * database saja sudah melewati batas, yang benar adalah menyimpan lebih sedikit salinan -
     * bukan berhenti menyimpan sama sekali.
     */
    public const SIMPAN_MINIMAL = 10;

    /**
     * Batas total isi folder backup, dalam MB.
     *
     * Diukur, bukan ditebak. Pada database simulasi yang digelembungkan sampai 33,6 MB
     * (289.824 baris item penjualan), satu snapshot berukuran sama dengan databasenya.
     * Dengan batas lama yang hanya menghitung JUMLAH berkas, toko sebesar itu menumpuk
     * 30 x 33,6 MB = 1 GB backup; toko yang databasenya 100 MB menumpuk 3,5 GB.
     *
     * Di komputer kasir yang diskinya kecil, itu bukan angka yang bisa diabaikan - dan
     * disk penuh punya akibat yang jauh lebih buruk daripada backup yang lebih sedikit:
     * SQLite tidak bisa menulis, dan kasir berhenti bisa berjualan.
     */
    public const BATAS_TOTAL_MB = 2048;

    private const HEADER = "SQLite format 3\0";

    /**
     * Awalan nama berkas beserta artinya bagi pemilik toko.
     *
     * Halaman Backup & Restore dulu hanya menampilkan nama berkas mentah, sehingga
     * `pre-wipe-2026-08-18_101500.sqlite` tidak berbeda tampak dari backup biasa - padahal
     * yang satu itu satu-satunya salinan sebelum seluruh data dihapus.
     */
    private const JENIS = [
        'otomatis' => 'Otomatis harian',
        'pre-update' => 'Sebelum pembaruan',
        'pre-restore' => 'Sebelum restore',
        'pre-migrate' => 'Sebelum migrasi',
        'pre-wipe' => 'Sebelum hapus data',
        'pre-perbaikan-harga' => 'Sebelum perbaikan harga',
        'pre-pulihkan-login' => 'Sebelum pemulihan login',
        'backup' => 'Manual',
    ];

    /**
     * Tabel yang wajib ada supaya sebuah file layak disebut database JPOS.
     */
    private const REQUIRED_TABLES = [
        'migrations', 'users', 'roles', 'products', 'sales', 'sale_items', 'settings', 'sessions',
    ];

    /**
     * Lokasi file database aktif.
     *
     * config/database.php sudah menjangkarkan path relatif ke folder aplikasi, jadi
     * nilai di sini selalu absolut dan tidak bergantung pada working directory.
     * Penting: fungsi ini TIDAK boleh mensyaratkan filenya sudah ada - pada instalasi
     * baru, justru path inilah yang dipakai untuk membuat filenya.
     */
    public function databasePath(): string
    {
        $configured = (string) config('database.connections.sqlite.database');

        if ($configured === ':memory:' || str_contains($configured, 'mode=memory') || str_starts_with($configured, 'file:')) {
            return $configured;
        }

        return is_file($configured) ? (realpath($configured) ?: $configured) : $configured;
    }

    /**
     * Folder backup.
     *
     * Sengaja diturunkan dari disk 'local' yang sama dengan yang dipakai halaman
     * Backup & Restore untuk menampilkan dan mengunduh daftar backup. Kalau di sini
     * memakai storage_path() sendiri, keduanya bisa menunjuk folder berbeda lagi -
     * dan justru itulah bug yang membuat backup tidak pernah muncul di daftar.
     */
    public function directory(): string
    {
        $directory = Storage::disk('local')->path('backups');

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        return $directory;
    }

    /**
     * Daftar backup, TERBARU DI ATAS.
     *
     * Sebelumnya halaman Backup & Restore mengurutkan dengan sortDesc() atas nama berkas.
     * Selama semua berkas berawalan sama itu kebetulan benar, tapi awalannya bermacam-macam:
     * secara abjad `pre-wipe` > `pre-update` > `pre-restore` > `otomatis` > `backup`, sehingga
     * satu snapshot `pre-wipe` dari bulan lalu duduk di atas backup yang dibuat lima menit lalu.
     * Pemilik toko yang mengambil "yang paling atas" saat panik justru memulihkan data terlama.
     *
     * Sekarang diurutkan dengan waktu berkas. Nama dipakai hanya sebagai pemutus seri, untuk
     * dua backup yang lahir di detik yang sama.
     *
     * @return array<int, array{nama:string,ukuran_kb:float,waktu:int,jenis:string,pengaman:bool}>
     */
    public function daftar(): array
    {
        $files = glob($this->directory() . DIRECTORY_SEPARATOR . '*.sqlite') ?: [];

        $baris = [];

        foreach ($files as $path) {
            $waktu = @filemtime($path);

            if ($waktu === false) {
                continue;
            }

            $nama = basename($path);

            $baris[] = [
                'nama' => $nama,
                'ukuran_kb' => round((@filesize($path) ?: 0) / 1024, 1),
                'waktu' => $waktu,
                'jenis' => $this->jenisDari($nama),
                'pengaman' => $this->pengaman($nama),
            ];
        }

        usort($baris, static fn ($a, $b) => [$b['waktu'], $b['nama']] <=> [$a['waktu'], $a['nama']]);

        return $baris;
    }

    /**
     * Terjemahkan awalan nama berkas jadi keterangan yang bisa dimengerti pemilik toko.
     *
     * Bagian tanggal dipotong lebih dulu karena sebagian awalan sendiri mengandung tanda
     * hubung (`pre-perbaikan-harga`), jadi memecah dengan explode('-') akan salah.
     */
    public function jenisDari(string $nama): string
    {
        $tanpaEkstensi = preg_replace('/\.sqlite$/i', '', $nama) ?? $nama;
        $awalan = preg_replace('/-\d{4}-\d{2}-\d{2}_\d{6}(-\d+)?$/', '', $tanpaEkstensi) ?? $tanpaEkstensi;

        return self::JENIS[$awalan] ?? 'Lainnya';
    }

    /** Snapshot yang lahir tepat sebelum tindakan berbahaya - dipertahankan lebih lama. */
    public function pengaman(string $nama): bool
    {
        return str_starts_with($nama, 'pre-');
    }

    /**
     * Hapus satu berkas backup.
     *
     * Tiga penjagaan, semuanya karena yang dihapus di sini adalah satu-satunya jalan pulang
     * kalau data toko rusak:
     *
     *   1. Nama dipangkas ke basename dan wajib berakhiran .sqlite, lalu path hasilnya
     *      dibandingkan dengan folder backup lewat realpath - supaya `../../database/database.sqlite`
     *      tidak bisa menghapus database yang sedang dipakai.
     *   2. Backup terakhir tidak boleh dihapus. Folder backup kosong berarti toko berjalan
     *      tanpa titik balik sama sekali.
     *   3. Pemanggilnya wajib memastikan ini permintaan sadar, bukan klik tak sengaja.
     *
     * @return string|null Pesan kesalahan, atau null kalau berhasil dihapus.
     */
    public function hapus(string $nama): ?string
    {
        $nama = basename(trim($nama));

        if ($nama === '' || ! str_ends_with(strtolower($nama), '.sqlite')) {
            return 'Nama berkas backup tidak sah.';
        }

        $folder = realpath($this->directory());
        $path = realpath($this->directory() . DIRECTORY_SEPARATOR . $nama);

        if ($folder === false || $path === false || ! is_file($path)) {
            return 'Berkas backup tidak ditemukan.';
        }

        if (! str_starts_with($path, $folder . DIRECTORY_SEPARATOR)) {
            return 'Berkas itu berada di luar folder backup dan tidak boleh dihapus dari sini.';
        }

        if (count($this->daftar()) <= 1) {
            return 'Ini satu-satunya backup yang tersisa. Buat backup baru dulu sebelum menghapus yang ini.';
        }

        if (! @unlink($path)) {
            return 'Berkas backup gagal dihapus. Pastikan tidak sedang dibuka di program lain.';
        }

        return null;
    }

    /**
     * Buat snapshot konsisten. Mengembalikan path absolut file backup.
     *
     * @throws \RuntimeException kalau snapshot tidak bisa dibuat sama sekali.
     */
    public function create(string $prefix = 'backup'): string
    {
        $source = $this->databasePath();

        if (! is_file($source)) {
            throw new \RuntimeException('File database tidak ditemukan di: ' . $source);
        }

        $destination = $this->directory() . DIRECTORY_SEPARATOR . $prefix . '-' . now()->format('Y-m-d_His') . '.sqlite';

        // VACUUM INTO gagal kalau file tujuan sudah ada (mis. dua klik beruntun dalam
        // detik yang sama), jadi namanya dibuat unik dulu.
        $suffix = 1;
        while (file_exists($destination)) {
            $destination = $this->directory() . DIRECTORY_SEPARATOR . $prefix . '-' . now()->format('Y-m-d_His') . '-' . (++$suffix) . '.sqlite';
        }

        if (! $this->vacuumInto($destination) && ! $this->checkpointAndCopy($source, $destination)) {
            throw new \RuntimeException('Gagal membuat salinan database.');
        }

        $this->prune();

        return $destination;
    }

    /**
     * Snapshot atomik & konsisten (SQLite 3.27+). Termasuk isi WAL yang belum ter-checkpoint.
     */
    private function vacuumInto(string $destination): bool
    {
        try {
            DB::statement("VACUUM INTO '" . str_replace("'", "''", $destination) . "'");

            return is_file($destination) && filesize($destination) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cadangan kalau VACUUM INTO tidak tersedia: pindahkan dulu isi WAL ke file utama,
     * baru disalin - supaya transaksi terakhir tetap ikut terbawa.
     */
    private function checkpointAndCopy(string $source, string $destination): bool
    {
        try {
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable) {
            // Checkpoint gagal bukan alasan untuk tidak mencoba menyalin sama sekali.
        }

        return @copy($source, $destination) && is_file($destination) && filesize($destination) > 0;
    }

    /**
     * Periksa apakah sebuah file benar-benar database JPOS yang sehat.
     *
     * Sebelumnya restore hanya divalidasi 'required|file', sehingga file apa pun -
     * PDF, gambar, database aplikasi lain - langsung menimpa database aktif dan
     * melumpuhkan aplikasi tanpa bisa dibatalkan.
     *
     * @return string|null Pesan kesalahan, atau null kalau file valid.
     */
    public function validateArchive(string $path): ?string
    {
        if (! is_file($path) || filesize($path) < 512) {
            return 'File backup tidak terbaca atau ukurannya tidak wajar.';
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return 'File backup tidak bisa dibuka.';
        }

        $header = fread($handle, 16);
        fclose($handle);

        if ($header !== self::HEADER) {
            return 'File yang dipilih bukan database SQLite. Pilih file backup berekstensi .sqlite hasil dari menu ini.';
        }

        try {
            $pdo = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();

            if (strtolower((string) $integrity) !== 'ok') {
                return 'File backup rusak (integrity check gagal). Coba backup lain.';
            }

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
            $missing = array_diff(self::REQUIRED_TABLES, $tables);

            if ($missing !== []) {
                return 'File backup bukan database JPOS - tabel berikut tidak ada: ' . implode(', ', $missing) . '.';
            }
        } catch (\Throwable $e) {
            return 'File backup tidak bisa dibaca sebagai database SQLite.';
        } finally {
            $pdo = null;
        }

        return null;
    }

    /**
     * Ganti database aktif dengan isi $source.
     *
     * File -wal dan -shm milik database lama WAJIB dibuang: kalau tertinggal, SQLite
     * bisa menerapkan sisa WAL lama ke file database yang baru dan merusaknya.
     */
    public function replaceWith(string $source): void
    {
        $target = $this->databasePath();

        try {
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable) {
            // Diabaikan: koneksi tetap diputus di bawah.
        }

        DB::disconnect('sqlite');
        DB::purge('sqlite');

        $this->removeSidecars($target);

        if (! @copy($source, $target)) {
            throw new \RuntimeException('Gagal menulis database baru. Pastikan aplikasi tidak sedang dipakai di jendela lain.');
        }

        $this->removeSidecars($target);
    }

    private function removeSidecars(string $databasePath): void
    {
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            $sidecar = $databasePath . $suffix;

            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
        }
    }

    /**
     * Buang backup terlama supaya folder tidak tumbuh tanpa batas di komputer kasir.
     *
     * Snapshot pengaman (awalan `pre-`) diberi jatah sendiri. Sejak ada backup otomatis
     * harian, backup biasa lahir tiap hari sementara snapshot pengaman hanya sesekali -
     * tanpa jatah terpisah, sebulan pemakaian akan mendorong keluar satu-satunya salinan
     * sebelum pembaruan atau sebelum data dihapus.
     */
    public function prune(
        int $keep = self::KEEP,
        int $keepPengaman = self::SIMPAN_PENGAMAN,
        int $batasMb = self::BATAS_TOTAL_MB,
    ): void {
        $files = glob($this->directory() . DIRECTORY_SEPARATOR . '*.sqlite') ?: [];

        if ($files === []) {
            return;
        }

        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $dipertahankan = array_slice($files, 0, $keep);

        $pengamanTersisa = array_values(array_filter(
            array_slice($files, $keep),
            fn ($path) => $this->pengaman(basename($path))
        ));

        foreach (array_slice($pengamanTersisa, 0, $keepPengaman) as $wajibSimpan) {
            $dipertahankan[] = $wajibSimpan;
        }

        $dipertahankan = $this->batasiTotalUkuran($dipertahankan, $batasMb);

        foreach (array_diff($files, $dipertahankan) as $stale) {
            @unlink($stale);
        }
    }

    /**
     * Buang salinan terlama sampai total folder muat di bawah batas ukuran.
     *
     * Menghitung jumlah berkas saja tidak cukup: berkas backup sebesar databasenya, dan
     * database toko tumbuh terus. Toko yang databasenya 100 MB akan menumpuk 3,5 GB backup
     * tanpa ada yang menyadarinya, sampai disk komputer kasir penuh - dan disk penuh berarti
     * SQLite tidak bisa menulis, yaitu kasir berhenti bisa berjualan.
     *
     * Dua hal yang tetap dilindungi apa pun ukurannya: SIMPAN_MINIMAL salinan terbaru, dan
     * seluruh snapshot pengaman. Batas ukuran tidak boleh bisa mengosongkan folder backup.
     *
     * @param  array<int, string>  $kandidat  sudah terurut terbaru ke terlama
     * @return array<int, string>
     */
    private function batasiTotalUkuran(array $kandidat, int $batasMb = self::BATAS_TOTAL_MB): array
    {
        $batasBytes = $batasMb * 1024 * 1024;
        $total = 0;
        $hasil = [];

        foreach (array_values($kandidat) as $urutan => $path) {
            $ukuran = @filesize($path) ?: 0;
            $wajib = $urutan < self::SIMPAN_MINIMAL || $this->pengaman(basename($path));

            if ($wajib || $total + $ukuran <= $batasBytes) {
                $hasil[] = $path;
                $total += $ukuran;
            }
        }

        return $hasil;
    }
}
