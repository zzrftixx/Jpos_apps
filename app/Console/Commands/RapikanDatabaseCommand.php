<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merapikan database setelah server dimatikan, dipanggil JPOS.exe saat aplikasi ditutup.
 *
 * SQLite dijalankan dengan mode WAL: setiap perubahan mendarat dulu di berkas pendamping
 * `-wal` sebelum dipindahkan ke berkas database utama. Mode ini yang membuat data tetap
 * utuh kalau aplikasi tertutup mendadak di tengah transaksi - transaksi yang belum
 * selesai memang tidak ikut terbaca lagi, tapi tidak ada yang rusak.
 *
 * Yang tersisa adalah satu ketidaknyamanan praktis: selama `-wal` belum dipindahkan,
 * berkas database utama BELUM berisi transaksi paling akhir. Client yang menyalin
 * `jpos.sqlite` sendirian - lewat flashdisk, ke folder cadangan, atau saat memindahkan
 * aplikasi ke komputer lain - akan mendapat data yang tertinggal beberapa transaksi.
 *
 * Perintah ini memindahkan seluruh isi `-wal` ke berkas utama, lalu memastikan hasilnya
 * benar-benar terbaca. Kegagalannya tidak pernah dianggap fatal: ini kerapian, bukan
 * syarat keselamatan data.
 */
class RapikanDatabaseCommand extends Command
{
    protected $signature = 'jpos:rapikan-database';

    protected $description = 'Memindahkan sisa WAL ke berkas database dan memeriksa keutuhannya';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return self::SUCCESS;
        }

        try {
            if ($this->modeJurnal() !== 'wal') {
                // Database uji (in-memory) dan instalasi yang sengaja tidak memakai WAL:
                // tidak ada berkas pendamping yang perlu dipindahkan.
                $this->line('Database tidak memakai WAL, tidak ada yang perlu dipindahkan.');

                return $this->periksaKeutuhan();
            }

            // TRUNCATE menunggu sampai seluruh isi WAL benar-benar pindah, lalu mengosongkan
            // berkas pendampingnya. Hasilnya: berkas database utama berdiri sendiri.
            $hasil = DB::select('PRAGMA wal_checkpoint(TRUNCATE)');

            // Kolom pertama 0 berarti checkpoint tuntas; 1 berarti ada koneksi lain yang
            // masih memegang database, jadi sebagian isi WAL tertinggal. Bukan kerusakan -
            // isinya tetap terbaca dan akan dipindahkan pada kesempatan berikutnya.
            $sisa = isset($hasil[0]) ? (array) $hasil[0] : [];
            $tuntas = (int) (reset($sisa) ?: 0) === 0;

            $this->line($tuntas
                ? 'Seluruh perubahan sudah tersimpan di berkas database utama.'
                : 'Sebagian perubahan masih menunggu di WAL karena database sedang dipakai proses lain.');
        } catch (\Throwable $e) {
            $this->warn('Checkpoint WAL tidak bisa dijalankan: ' . $e->getMessage());
        }

        return $this->periksaKeutuhan();
    }

    private function modeJurnal(): string
    {
        $baris = (array) (DB::select('PRAGMA journal_mode')[0] ?? []);

        return strtolower(trim((string) (reset($baris) ?: '')));
    }

    private function periksaKeutuhan(): int
    {
        try {
            $baris = (array) (DB::select('PRAGMA quick_check')[0] ?? []);
            $status = strtolower(trim((string) (reset($baris) ?: '')));

            if ($status !== 'ok') {
                // Dilaporkan sekeras mungkin: ini satu-satunya kondisi di alur ini yang
                // benar-benar berarti data client bermasalah.
                $this->error('PERINGATAN: pemeriksaan database tidak bersih -> ' . ($status ?: 'tidak diketahui'));

                return self::FAILURE;
            }

            $this->info('Database diperiksa dan dalam keadaan utuh.');
        } catch (\Throwable $e) {
            $this->warn('Pemeriksaan database tidak bisa dijalankan: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
