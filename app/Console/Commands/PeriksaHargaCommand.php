<?php

namespace App\Console\Commands;

use App\Support\SqliteBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mendeteksi (dan memulihkan) harga produk yang terlanjur tergandakan.
 *
 * Sampai versi sebelumnya, membuka produk di form Edit menampilkan nominalnya seratus kali
 * lipat, karena kolom decimal dikirim Laravel sebagai string "1500.00" dan titiknya dibaca
 * sebagai pemisah ribuan. Setiap kali produk itu disimpan ulang, nilai yang salah itulah
 * yang tersimpan - dan berlipat lagi pada penyimpanan berikutnya.
 *
 * Bug-nya sudah diperbaiki, tapi data yang terlanjur berubah tidak bisa pulih sendiri.
 * Perintah ini membandingkan harga sekarang dengan berkas backup, lalu menandai nilai yang
 * berbeda TEPAT 100x, 10.000x, atau 1.000.000x - pola khas penggandaan ini. Kelipatan yang
 * tepat begitu hampir mustahil terjadi dari perubahan harga yang wajar.
 */
class PeriksaHargaCommand extends Command
{
    protected $signature = 'jpos:periksa-harga
                            {--dari= : Berkas backup pembanding (bawaan: backup terbaru)}
                            {--pulihkan : Kembalikan nilai yang tergandakan ke nilai di backup}';

    protected $description = 'Memeriksa harga produk yang tergandakan akibat bug form Edit, dan memulihkannya';

    /** Kelipatan khas penggandaan: 100 per penyimpanan (10.000 untuk konversi satuan). */
    private const KELIPATAN = [100, 10000, 1000000, 100000000];

    private const KOLOM_PRODUK = ['cost_price', 'sell_price', 'wholesale_price'];
    private const KOLOM_SATUAN = ['conversion', 'price', 'wholesale_price'];

    public function handle(SqliteBackup $backup): int
    {
        $pembanding = $this->option('dari') ?: $this->backupTerbaru($backup);

        if (! $pembanding || ! is_file($pembanding)) {
            $this->error('Tidak ada berkas backup untuk dibandingkan.');
            $this->line('Backup biasanya ada di storage/app/private/backups. Sebutkan manual dengan --dari=<berkas>.');

            return self::FAILURE;
        }

        if ($galat = $backup->validateArchive($pembanding)) {
            $this->error('Berkas pembanding tidak bisa dipakai. ' . $galat);

            return self::FAILURE;
        }

        $this->line('Membandingkan dengan: ' . basename($pembanding));
        $this->newLine();

        try {
            $lama = new \PDO('sqlite:' . $pembanding, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\Throwable $e) {
            $this->error('Backup tidak bisa dibuka: ' . $e->getMessage());

            return self::FAILURE;
        }

        $temuan = array_merge(
            $this->periksaTabel($lama, 'products', self::KOLOM_PRODUK),
            $this->periksaTabel($lama, 'product_units', self::KOLOM_SATUAN),
        );

        if ($temuan === []) {
            $this->info('Tidak ditemukan nilai yang tergandakan. Data harga Anda aman.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Ditemukan %d nilai yang diduga tergandakan:', count($temuan)));
        $this->newLine();

        $this->table(
            ['Tabel', 'ID', 'Nama', 'Kolom', 'Sekarang', 'Di backup', 'Kelipatan'],
            array_map(fn ($t) => [
                $t['tabel'], $t['id'], mb_strimwidth($t['nama'], 0, 28, '...'),
                $t['kolom'],
                number_format($t['sekarang'], 2, ',', '.'),
                number_format($t['seharusnya'], 2, ',', '.'),
                $t['kelipatan'] . 'x',
            ], $temuan)
        );

        if (! $this->option('pulihkan')) {
            $this->newLine();
            $this->line('Jalankan lagi dengan --pulihkan untuk mengembalikan nilai-nilai di atas.');
            $this->line('Backup pengaman akan dibuat otomatis sebelum apa pun diubah.');

            return self::SUCCESS;
        }

        try {
            $pengaman = $backup->create('pre-perbaikan-harga');
            $this->line('Backup pengaman dibuat: ' . basename($pengaman));
        } catch (\Throwable $e) {
            $this->error('Pemulihan dibatalkan karena backup pengaman gagal dibuat: ' . $e->getMessage());

            return self::FAILURE;
        }

        DB::transaction(function () use ($temuan) {
            foreach ($temuan as $t) {
                DB::table($t['tabel'])->where('id', $t['id'])->update([$t['kolom'] => $t['seharusnya']]);
            }
        });

        // Katalog kasir menyimpan harga; harus dibangun ulang supaya kasir langsung
        // melihat harga yang sudah dipulihkan.
        try {
            app(\App\Support\ProductCatalog::class)->flush();
        } catch (\Throwable) {
            // Bukan alasan untuk menganggap pemulihan gagal.
        }

        $this->newLine();
        $this->info(sprintf('%d nilai dipulihkan.', count($temuan)));

        return self::SUCCESS;
    }

    private function backupTerbaru(SqliteBackup $backup): ?string
    {
        $berkas = glob($backup->directory() . DIRECTORY_SEPARATOR . '*.sqlite') ?: [];

        if ($berkas === []) {
            return null;
        }

        usort($berkas, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $berkas[0];
    }

    private function periksaTabel(\PDO $lama, string $tabel, array $kolom): array
    {
        try {
            $barisLama = $lama->query("SELECT * FROM {$tabel}")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $indeksLama = [];
        foreach ($barisLama as $b) {
            $indeksLama[$b['id']] = $b;
        }

        $temuan = [];

        foreach (DB::table($tabel)->get() as $sekarang) {
            $sebelum = $indeksLama[$sekarang->id] ?? null;

            if (! $sebelum) {
                continue; // Baris baru, tidak ada pembandingnya.
            }

            foreach ($kolom as $k) {
                $nilaiSekarang = $sekarang->$k ?? null;
                $nilaiSebelum = $sebelum[$k] ?? null;

                if ($nilaiSekarang === null || $nilaiSebelum === null) {
                    continue;
                }

                $a = (float) $nilaiSekarang;
                $b = (float) $nilaiSebelum;

                if ($b <= 0 || $a <= 0 || $a === $b) {
                    continue;
                }

                foreach (self::KELIPATAN as $k2) {
                    // Perbandingan pakai toleransi kecil supaya pembulatan desimal
                    // tidak membuat kelipatan yang tepat jadi terlewat.
                    if (abs($a - $b * $k2) < 0.005) {
                        $temuan[] = [
                            'tabel' => $tabel,
                            'id' => $sekarang->id,
                            'nama' => $this->namaBaris($tabel, $sekarang),
                            'kolom' => $k,
                            'sekarang' => $a,
                            'seharusnya' => $b,
                            'kelipatan' => $k2,
                        ];
                        break;
                    }
                }
            }
        }

        return $temuan;
    }

    private function namaBaris(string $tabel, object $baris): string
    {
        if ($tabel === 'products') {
            return (string) ($baris->name ?? '-');
        }

        $produk = DB::table('products')->where('id', $baris->product_id ?? 0)->value('name');

        return 'satuan dari ' . ($produk ?: '-');
    }
}
