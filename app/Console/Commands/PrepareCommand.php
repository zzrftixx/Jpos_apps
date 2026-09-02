<?php

namespace App\Console\Commands;

use App\Support\PenandaCache;
use App\Support\ProductCatalog;
use App\Support\SqliteBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Persiapan sekali jalan sebelum server dinyalakan, dipanggil JPOS.exe saat start.
 *
 * Sebelumnya logika ini tersebar di launcher C#: membuat file database, menjalankan
 * `migrate --force` di SETIAP start terhadap database produksi client tanpa backup,
 * dan seluruh kegagalannya ditelan `catch { }` kosong tanpa jejak. Kalau migrasi
 * gagal, aplikasi tetap menyala di atas skema yang salah dan error muncul
 * di mana-mana tanpa petunjuk apa pun.
 *
 * Sekarang: migrasi hanya dijalankan kalau memang ada yang tertunda, selalu didahului
 * snapshot database, dan setiap langkah dilaporkan lewat exit code + keluaran yang
 * direkam launcher ke storage/logs/launcher.log.
 */
class PrepareCommand extends Command
{
    protected $signature = 'jpos:prepare
                            {--skip-cache : Lewati pembangunan ulang cache config/route/view}';

    protected $description = 'Menyiapkan database dan cache JPOS sebelum server dijalankan';

    public function handle(SqliteBackup $backup, ProductCatalog $catalog, PenandaCache $penanda): int
    {
        try {
            $this->prepareDatabase($backup);
        } catch (\Throwable $e) {
            $this->error('Persiapan database gagal: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->pastikanAppKeyUnik();
        $this->backupHarian($backup);
        $this->bersihkanTautanStorageRusak();

        // Melepas stok yang dikunci keranjang TERTAHAN yang ditinggal kasir kemarin.
        //
        // Dipasang di sini, bukan lewat cron: komputer toko tidak selalu menyala, dan
        // menambah penjadwal sistem berarti satu bagian lagi yang bisa diam-diam mati tanpa
        // ada yang tahu. Aplikasi yang dinyalakan tiap pagi adalah jadwal yang paling andal
        // di lingkungan ini.
        //
        // Perintahnya HANYA menyentuh transaksi tertahan (`parked_at IS NOT NULL`), tidak
        // pernah pesanan DP - lihat BersihkanKeranjangCommand untuk alasan lengkapnya.
        try {
            $this->callSilently('jpos:bersihkan-keranjang');
        } catch (\Throwable) {
            // Gagal melepas keranjang bukan alasan aplikasi gagal menyala. Stok yang
            // tertahan sehari lagi jauh lebih ringan daripada kasir yang tidak bisa buka.
        }

        // Katalog produk yang tersimpan bisa berasal dari versi aplikasi sebelumnya.
        try {
            $catalog->flush();
        } catch (\Throwable) {
            // Cache kosong bukan alasan gagal start.
        }

        if (! $this->option('skip-cache')) {
            $this->rebuildCaches($penanda);
        }

        $this->info('JPOS siap dijalankan.');

        return self::SUCCESS;
    }

    /**
     * Pastikan APP_KEY terisi dan unik per instalasi.
     *
     * APP_KEY mengenkripsi cookie sesi dan menandatangani URL, jadi harus unik di tiap toko.
     * Dua kondisi yang perlu diperbaiki di sini:
     *
     *   - KOSONG: paket instalasi baru dikirim tanpa APP_KEY. Biasanya launcher yang membuatnya
     *     saat first run, tapi command ini juga bisa dipanggil lewat UPDATE.bat tanpa launcher -
     *     jadi jaring pengaman ini membuat aplikasi tetap bisa dipakai apa pun jalurnya.
     *   - KUNCI BAWAAN LAMA: versi paket sebelumnya dikirim dengan APP_KEY identik di semua toko.
     *     Paket update tidak menyentuh .env, jadi toko lama tetap memakai kunci bersama itu sampai
     *     diganti di sini.
     *
     * Sekali diganti, APP_KEY sudah acak dan kondisi ini tidak terpenuhi lagi.
     */
    private function pastikanAppKeyUnik(): void
    {
        $kunci = (string) config('app.key');
        $kunciBawaanLama = 'base64:orz8CxdFwOz5eUhykwT0Y67rgw+31Y9atp3z4anDZLU=';

        $perluGanti = $kunci === '' || $kunci === $kunciBawaanLama;

        if (! $perluGanti) {
            return;
        }

        // Mengganti APP_KEY membuat sesi login yang sedang aktif gugur - konsekuensi yang wajar
        // dan cuma sekali; kasir tinggal login ulang.
        try {
            Artisan::call('key:generate', ['--force' => true]);
            $this->line($kunci === ''
                ? 'Kunci keamanan aplikasi dibuat untuk instalasi ini.'
                : 'Kunci keamanan bawaan diganti dengan kunci unik untuk instalasi ini.');
        } catch (\Throwable $e) {
            $this->warn('Tidak bisa memperbarui kunci keamanan: ' . $e->getMessage());
        }
    }

    /**
     * Satu salinan otomatis per hari, dibuat saat aplikasi pertama dibuka hari itu.
     *
     * Sampai sekarang backup hanya lahir kalau seseorang mengklik tombolnya, atau tepat
     * sebelum tindakan berisiko. Toko yang tidak pernah mengklik apa pun - dan itu mayoritas -
     * berjalan berbulan-bulan tanpa satu pun titik balik. Ketika kemudian ada yang salah,
     * tidak ada yang bisa dikembalikan.
     *
     * Biayanya kecil: VACUUM INTO sekali sehari di komputer yang sedang dinyalakan, bukan
     * saat kasir melayani. Kegagalannya sengaja tidak menghentikan start - toko harus tetap
     * bisa berjualan walau salinan hari ini gagal dibuat.
     */
    private function backupHarian(SqliteBackup $backup): void
    {
        try {
            if (! is_file($backup->databasePath())) {
                return;
            }

            $hariIni = now()->startOfDay()->getTimestamp();

            foreach ($backup->daftar() as $berkas) {
                if ($berkas['waktu'] >= $hariIni) {
                    return; // Hari ini sudah punya salinan, apa pun jenisnya.
                }
            }

            $this->line('Salinan otomatis hari ini dibuat: ' . basename($backup->create('otomatis')));
        } catch (\Throwable $e) {
            $this->warn('Salinan otomatis harian gagal dibuat: ' . $e->getMessage());
        }
    }

    private function prepareDatabase(SqliteBackup $backup): void
    {
        $path = $backup->databasePath();

        if (! is_file($path)) {
            $this->line('Database belum ada, membuat instalasi baru di: ' . $path);
            File::ensureDirectoryExists(dirname($path));
            touch($path);

            $this->callSilently('migrate', ['--force' => true]);
            $this->callSilently('db:seed', ['--force' => true]);
            $this->info('Database baru dibuat beserta data awal.');

            return;
        }

        $pending = $this->pendingMigrations();

        if ($pending === 0) {
            $this->line('Struktur database sudah mutakhir, tidak ada migrasi yang perlu dijalankan.');

            return;
        }

        // Migrasi mengubah struktur database client secara permanen. Selalu ada titik
        // balik sebelum satu baris pun diubah.
        $snapshot = $backup->create('pre-update');
        $this->line(sprintf('Ada %d migrasi tertunda. Snapshot dibuat: %s', $pending, basename($snapshot)));

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            $this->kembalikanKeSnapshot($backup, $snapshot, $e);

            throw new \RuntimeException('Pembaruan struktur database dibatalkan dan data dikembalikan ke keadaan semula.', 0, $e);
        }

        $this->line(trim(Artisan::output()));
        $this->info('Migrasi selesai.');
    }

    /**
     * Kembalikan database ke snapshot sebelum migrasi.
     *
     * INI PELAJARAN DARI KASUS SUNGGUHAN. Database sebuah toko gagal diperbarui di migrasi
     * ke-2 dari 10 (`duplicate column name`). Migrasi Laravel di SQLite tidak transaksional
     * untuk perubahan skema, jadi dua migrasi pertama tetap menempel: sebagian tabel sudah
     * berbentuk baru, sebagian masih lama. Aplikasi tidak bisa membaca keduanya sekaligus.
     *
     * Snapshot `pre-update` sebetulnya sudah dibuat sedetik sebelumnya - tapi tidak ada satu
     * pun yang memberitahu pemilik toko bahwa berkas itu ada, apalagi cara memakainya. Yang
     * ia lihat cuma "database tidak terbaca".
     *
     * Sekarang pengembaliannya otomatis. Kalaupun penyebab aslinya belum diperbaiki, toko
     * berakhir di keadaan yang UTUH dan diketahui - bukan di keadaan setengah jadi yang
     * tidak bisa dijelaskan siapa pun.
     *
     * Start sengaja tetap digagalkan sesudahnya: skema lama dengan kode baru akan salah di
     * mana-mana, dan salah yang diam jauh lebih mahal daripada berhenti dengan jelas.
     */
    private function kembalikanKeSnapshot(SqliteBackup $backup, string $snapshot, \Throwable $penyebab): void
    {
        $this->newLine();
        $this->error('MIGRASI GAGAL: ' . $penyebab->getMessage());

        try {
            $backup->replaceWith($snapshot);
            $this->warn('Database dikembalikan ke keadaan sebelum pembaruan. Tidak ada data yang hilang.');
        } catch (\Throwable $gagalPulih) {
            // Sampai di sini keadaannya paling buruk: skema setengah jadi DAN pengembalian
            // gagal. Yang bisa diselamatkan tinggal informasinya - path snapshot harus
            // sampai ke tangan orang, bukan tenggelam di log.
            $this->error('PENGEMBALIAN OTOMATIS JUGA GAGAL: ' . $gagalPulih->getMessage());
            $this->error('Data Anda TIDAK hilang. Salinan lengkapnya ada di:');
            $this->error('  ' . $snapshot);
            $this->error('Jangan menjalankan aplikasi. Hubungi pembuat aplikasi sambil membawa berkas itu.');

            return;
        }

        $this->newLine();
        $this->warn('Aplikasi sengaja tidak dijalankan supaya data tidak tercatat di atas struktur yang salah.');
        $this->warn('Salinan sebelum pembaruan: ' . $snapshot);
        $this->warn('Hubungi pembuat aplikasi dan sebutkan pesan galat di atas.');
    }

    /**
     * Buang berkas public/storage yang bukan folder.
     *
     * Proyek ini pernah dikembangkan di macOS, dan symlink public/storage ikut tersalin ke
     * Windows sebagai BERKAS BIASA berisi teks path lama. Server PHP bawaan meresolusi
     * /storage/logo/xxx.png ke berkas itu dan membalas 404 sebelum Laravel sempat
     * menanganinya - sehingga logo toko hilang di halaman login, pratinjau template struk,
     * dan STRUK CETAK, tanpa pesan error apa pun.
     *
     * Paket update hanya menyalin berkas, tidak menghapus, jadi instalasi yang sudah
     * terlanjur membawa berkas itu perlu dibersihkan di sini.
     */
    private function bersihkanTautanStorageRusak(): void
    {
        $path = public_path('storage');

        if (! file_exists($path) || is_dir($path)) {
            return;
        }

        // Hanya berkas kecil berisi path yang dibuang; jangan sampai menyentuh sesuatu
        // yang ternyata memang dipakai.
        if (@filesize($path) > 4096) {
            return;
        }

        if (@unlink($path)) {
            $this->line('Berkas public/storage yang rusak dibuang (penyebab logo toko tidak muncul).');
        }
    }

    private function pendingMigrations(): int
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('migrations')) {
                return 1; // Database ada tapi belum pernah dimigrasi.
            }

            $sudahJalan = DB::table('migrations')->pluck('migration')->all();

            $tersedia = collect(File::files(database_path('migrations')))
                ->map(fn ($file) => $file->getFilenameWithoutExtension())
                ->all();

            return count(array_diff($tersedia, $sudahJalan));
        } catch (\Throwable $e) {
            // Kalau status migrasi tidak bisa dibaca, anggap perlu dijalankan -
            // migrate sendiri bersifat idempoten.
            return 1;
        }
    }

    /**
     * Membangun ulang cache config/route/view/event - TAPI HANYA KALAU PERLU.
     *
     * Keputusannya ada di App\Support\PenandaCache beserta alasan dan hasil pengukurannya.
     * Singkatnya: ini yang memangkas waktu buka aplikasi dari ~6 detik jadi ~1 detik.
     */
    private function rebuildCaches(PenandaCache $penanda): void
    {
        if ($penanda->masihSesuai()) {
            $this->line('Cache masih sesuai, tidak perlu dibangun ulang.');

            return;
        }

        foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $command) {
            try {
                $this->callSilently($command);
            } catch (\Throwable $e) {
                $this->warn("Gagal menjalankan {$command}: " . $e->getMessage());
            }
        }

        $penanda->perbarui();

        $this->line('Cache config, route, view, dan event dibangun ulang.');
    }
}
