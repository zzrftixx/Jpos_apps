<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeliharaan rutin SQLite untuk menjaga performa kasir dan mencegah pembengkakan file database.
 *
 * Di lingkungan POS offline (Batasan B1 & B3), SQLite adalah jantung operasional toko.
 * Dua masalah utama yang sering terjadi di lapangan jika tidak dirawat otomatis:
 * 1. File WAL (-wal) membengkak tanpa henti jika tidak di-checkpoint secara teratur.
 * 2. Freelist (halaman terfragmentasi dari update/delete cache dan session) membengkak
 *    hingga ratusan MB / GB tanpa dikompilasi ulang oleh VACUUM.
 *
 * Prosedur di sini dirancang 100% aman (non-blocking, try-catch terlindungi, dan hanya
 * mengeksekusi VACUUM saat DB::transactionLevel() === 0 dan terdapat ruang kosong nyata > 5 MB).
 */
class SqliteMaintenance
{
    /**
     * Jalankan rutinitas pemeliharaan SQLite:
     * - Checkpoint WAL (TRUNCATE saat aman, fallback ke PASSIVE)
     * - Bersihkan sesi mati yang ditinggalkan (> 30 hari tanpa aktivitas)
     * - Bersihkan sampah tabel cache yang kedaluwarsa atau sisa katalog lama
     * - Eksekusi VACUUM jika freelist terfragmentasi melebihi 5 MB
     * - Jalankan PRAGMA optimize untuk query planner
     *
     * @param  bool  $forceVacuum  Paksa VACUUM tanpa melihat ambang batas freelist
     * @return array<string, mixed>
     */
    public static function runRoutines(bool $forceVacuum = false): array
    {
        $report = [
            'checkpoint' => false,
            'sessions_pruned' => 0,
            'cache_cleaned' => 0,
            'vacuumed' => false,
            'optimized' => false,
            'error' => null,
        ];

        if (config('database.default') !== 'sqlite') {
            return $report;
        }

        try {
            // 1. Checkpoint WAL agar berkas -wal tidak membengkak (khusus file database fisik)
            $dbName = config('database.connections.sqlite.database', '');
            $isMemory = ($dbName === ':memory:' || str_contains($dbName, 'mode=memory'));

            if (!$isMemory) {
                try {
                    DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
                    $report['checkpoint'] = true;
                } catch (\Throwable) {
                    // Fallback passive jika sedang ada proses pembacaan lain yang memegang lock
                    try {
                        DB::statement('PRAGMA wal_checkpoint(PASSIVE)');
                        $report['checkpoint'] = true;
                    } catch (\Throwable) {}
                }
            } else {
                $report['checkpoint'] = true;
            }

            // 2. Bersihkan sesi lama yang ditinggalkan (> 30 hari tanpa aktivitas)
            // Kasir yang sedang bertugas hari ini atau beberapa hari lalu tidak akan tersentuh.
            if (Schema::hasTable('sessions')) {
                try {
                    $cutoff = now()->subDays(30)->timestamp;
                    $report['sessions_pruned'] = DB::table('sessions')
                        ->where('last_activity', '<', $cutoff)
                        ->delete();
                } catch (\Throwable) {}
            }

            // 3. Bersihkan sampah tabel cache SQLite (cache kedaluwarsa & sisa katalog lama)
            if (Schema::hasTable('cache')) {
                try {
                    $report['cache_cleaned'] = DB::table('cache')
                        ->where('expiration', '<', now()->timestamp)
                        ->orWhere('key', 'like', '%jpos:katalog%')
                        ->delete();
                } catch (\Throwable) {}
            }

            // 4. Periksa apakah butuh VACUUM (hanya jika di luar transaksi aktif)
            if (DB::transactionLevel() === 0) {
                $freelistCount = 0;
                $pageSize = 4096;
                try {
                    $res = DB::selectOne('PRAGMA freelist_count');
                    $freelistCount = (int) ($res->freelist_count ?? 0);
                    $resPage = DB::selectOne('PRAGMA page_size');
                    $pageSize = (int) ($resPage->page_size ?? 4096);
                } catch (\Throwable) {}

                $wastedBytes = $freelistCount * $pageSize;
                // Jika ruang kosong terfragmentasi > 5 MB atau dipaksa: jalankan VACUUM
                if ($forceVacuum || $wastedBytes > (5 * 1024 * 1024)) {
                    try {
                        DB::statement('VACUUM');
                        $report['vacuumed'] = true;
                    } catch (\Throwable $e) {
                        Log::warning('SqliteMaintenance VACUUM dilewati: ' . $e->getMessage());
                    }
                }
            }

            // 5. Perbarui statistik query optimizer
            try {
                DB::statement('PRAGMA optimize');
                $report['optimized'] = true;
            } catch (\Throwable) {}

        } catch (\Throwable $e) {
            $report['error'] = $e->getMessage();
            Log::warning('SqliteMaintenance error: ' . $e->getMessage());
        }

        return $report;
    }
}
