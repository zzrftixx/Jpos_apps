<?php

namespace Tests\Feature;

use App\Support\ProductCatalog;
use App\Support\SqliteMaintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

class SqliteMaintenanceTest extends JposTestCase
{
    public function test_sqlite_maintenance_menjalankan_checkpoint_dan_pruning_sesi_lama(): void
    {
        // 1. Buat sesi lama (> 40 hari lalu) dan sesi aktif (hari ini)
        DB::table('sessions')->insert([
            [
                'id' => 'sess_lama_001',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'TestAgent',
                'payload' => serialize(['data' => 'expired']),
                'last_activity' => now()->subDays(40)->timestamp,
            ],
            [
                'id' => 'sess_aktif_001',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'TestAgent',
                'payload' => serialize(['data' => 'active']),
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'sess_lama_001']);
        $this->assertDatabaseHas('sessions', ['id' => 'sess_aktif_001']);

        // 2. Jalankan pemeliharaan rutin SQLite
        $hasil = SqliteMaintenance::runRoutines();

        // 3. Verifikasi: sesi lama terhapus, sesi aktif tetap aman
        $this->assertDatabaseMissing('sessions', ['id' => 'sess_lama_001']);
        $this->assertDatabaseHas('sessions', ['id' => 'sess_aktif_001']);
        $this->assertTrue($hasil['checkpoint']);
        $this->assertGreaterThanOrEqual(1, $hasil['sessions_pruned']);
    }

    public function test_product_catalog_menggunakan_file_store_tanpa_mengotori_tabel_cache_sqlite(): void
    {
        $catalog = app(ProductCatalog::class);
        $catalog->flush();

        // Bersihkan tabel cache SQLite
        DB::table('cache')->where('key', 'like', '%jpos:katalog%')->delete();

        // Ambil katalog
        $data = $catalog->forCart();
        $this->assertIsArray($data);

        // Verifikasi penting: data katalog TIDAK tersimpan di tabel database.sqlite,
        // melainkan tersimpan di Cache file store agar tidak memicu fragmentasi freelist
        $sqliteCacheCount = DB::table('cache')->where('key', 'like', '%jpos:katalog%')->count();
        $this->assertSame(0, $sqliteCacheCount, 'Tabel cache SQLite tidak boleh menyimpan blob katalog raksasa');

        $this->assertTrue(Cache::store('file')->has('jpos:katalog:data'));
        $this->assertTrue(Cache::store('file')->has('jpos:katalog:fingerprint'));
    }

    public function test_prepare_command_menjalankan_rutinitas_sqlite_maintenance_secara_aman(): void
    {
        $exitCode = Artisan::call('jpos:prepare', ['--skip-cache' => true]);
        $this->assertSame(0, $exitCode);
    }
}
