<?php

namespace Tests\Feature;

use App\Support\PenandaCache;
use Illuminate\Support\Facades\File;
use Tests\JposTestCase;

/**
 * UAT waktu buka aplikasi.
 *
 * `jpos:prepare` dijalankan JPOS.exe setiap kali aplikasi dibuka, dan kasir menunggu
 * sepanjang itu sebelum layar pertama muncul. Versi sebelumnya membangun ulang cache
 * config/route/view/event di SETIAP start. Diukur pada paket 2.2.0, lima kali masing-masing:
 *
 *     selalu bangun ulang        rata-rata 6.086 ms
 *     bangun ulang bila perlu    rata-rata 1.074 ms
 *
 * `view:cache` sendirian menghabiskan 5 detik untuk mengompilasi ulang 86 berkas Blade
 * yang isinya sama persis dengan kompilasi sebelumnya.
 *
 * Yang dijaga di sini bukan angkanya - itu bergantung mesin - melainkan PERILAKUNYA:
 * melompat saat tidak ada yang berubah, dan benar-benar membangun ulang saat ada.
 * MELOMPAT TERLALU RAJIN JAUH LEBIH BERBAHAYA DARIPADA LAMBAT: aplikasi akan berjalan
 * dengan tampilan versi lama setelah diperbarui, dan pemiliknya akan melaporkan
 * "fiturnya tidak muncul" padahal kodenya sudah ada di sana.
 *
 * Seluruh test bekerja di atas POHON BERKAS TIRUAN di folder sementara. Menulis berkas
 * cache Laravel sungguhan dari dalam test akan merusak lingkungan yang sedang dipakai -
 * itu sempat terjadi saat menyusun test ini, dan aplikasinya langsung gagal boot.
 */
class WaktuBukaTest extends JposTestCase
{
    private string $akar;

    private PenandaCache $penanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->akar = sys_get_temp_dir() . '/jpos-penanda-' . bin2hex(random_bytes(6));

        foreach (['bootstrap/cache', 'config', 'routes', 'resources/views', 'app'] as $folder) {
            File::ensureDirectoryExists($this->akar . '/' . $folder);
        }

        File::put($this->akar . '/VERSION', '2.2.0');
        File::put($this->akar . '/.env', "APP_URL=http://localhost:8000\n");
        File::put($this->akar . '/config/app.php', '<?php return [];');
        File::put($this->akar . '/routes/web.php', '<?php');
        File::put($this->akar . '/resources/views/halaman.blade.php', 'halo');
        File::put($this->akar . '/app/Kelas.php', '<?php');

        $this->penanda = new PenandaCache($this->akar);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->akar);

        parent::tearDown();
    }

    /** Keadaan "cache baru saja dibangun dan semuanya sesuai". */
    private function anggapCacheBaruDibangun(): void
    {
        foreach (['config.php', 'routes-v7.php', 'events.php'] as $berkas) {
            File::put($this->akar . '/bootstrap/cache/' . $berkas, '<?php return [];');
        }

        $this->penanda->perbarui();
    }

    /** Waktu ubah dimajukan, bukan disamakan dengan sekarang - resolusi mtime cuma 1 detik. */
    private function sentuh(string $relatif): void
    {
        touch($this->akar . '/' . $relatif, time() + 5);
    }

    /* ------------------------------------------------------------------ melompat */

    public function test_tanpa_penanda_cache_wajib_dibangun(): void
    {
        $this->assertFalse($this->penanda->masihSesuai(), 'Instalasi baru harus membangun cache.');
    }

    /** Inilah yang menghemat sekitar 5 detik di setiap pembukaan aplikasi. */
    public function test_tanpa_ada_perubahan_cache_dipakai_apa_adanya(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->assertTrue($this->penanda->masihSesuai());
    }

    /* ---------------------------------------------------- wajib bangun ulang bila... */

    public function test_mengubah_tampilan_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->sentuh('resources/views/halaman.blade.php');

        $this->assertFalse($this->penanda->masihSesuai(), 'Blade berubah tapi view lama tetap dipakai.');
    }

    public function test_menambah_tampilan_baru_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        File::put($this->akar . '/resources/views/halaman-baru.blade.php', 'baru');
        touch($this->akar . '/resources/views/halaman-baru.blade.php', time() + 5);

        $this->assertFalse($this->penanda->masihSesuai());
    }

    public function test_mengubah_kode_php_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->sentuh('app/Kelas.php');

        $this->assertFalse($this->penanda->masihSesuai());
    }

    public function test_mengubah_rute_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->sentuh('routes/web.php');

        $this->assertFalse($this->penanda->masihSesuai(), 'Rute baru tidak akan pernah terdaftar.');
    }

    public function test_mengubah_config_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->sentuh('config/app.php');

        $this->assertFalse($this->penanda->masihSesuai());
    }

    /** Paket update selalu menaikkan VERSION, jadi itu sinyal yang paling bisa diandalkan. */
    public function test_versi_baru_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        File::put($this->akar . '/VERSION', '99.99.99');

        $this->assertFalse($this->penanda->masihSesuai());
    }

    /**
     * Port bisa berpindah kalau 8000 sedang dipakai program lain, dan launcher menulis
     * APP_URL baru ke .env. Config cache lama akan menunjuk port yang salah, dan seluruh
     * tautan di aplikasi ikut salah.
     */
    public function test_perubahan_env_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->sentuh('.env');

        $this->assertFalse($this->penanda->masihSesuai());
    }

    public function test_app_url_berbeda_memaksa_pembangunan_ulang(): void
    {
        $this->anggapCacheBaruDibangun();

        config(['app.url' => 'http://localhost:8099']);

        $this->assertFalse($this->penanda->masihSesuai(), 'Port pindah tapi config lama tetap dipakai.');
    }

    /**
     * Penanda yang tertinggal tidak boleh membuat aplikasi melompati pembangunan cache
     * yang berkasnya memang belum ada.
     */
    public function test_penanda_cocok_tapi_berkas_cache_hilang_tetap_dibangun(): void
    {
        $this->anggapCacheBaruDibangun();
        $this->assertTrue($this->penanda->masihSesuai());

        File::delete($this->akar . '/bootstrap/cache/config.php');

        $this->assertFalse($this->penanda->masihSesuai(), 'Cache hilang diabaikan hanya karena penandanya cocok.');
    }

    public function test_penanda_bisa_dihapus_untuk_memaksa_pembangunan(): void
    {
        $this->anggapCacheBaruDibangun();

        $this->penanda->hapus();

        $this->assertFalse($this->penanda->masihSesuai());
    }

    /* ------------------------------------------------------------------- biayanya */

    /**
     * Sidik jarinya harus JAUH lebih murah daripada cache yang dihemat, kalau tidak
     * perbaikannya tidak ada artinya. Diukur pada pohon sungguhan: sekitar 30 ms,
     * dibanding 5 detik yang dihemat.
     */
    public function test_menghitung_sidik_jari_jauh_lebih_murah_daripada_membangun_cache(): void
    {
        $nyata = new PenandaCache();

        $mulai = microtime(true);
        $nyata->sidikJari();
        $lama = (microtime(true) - $mulai) * 1000;

        $this->assertLessThan(
            1000,
            $lama,
            sprintf('Sidik jari makan %.0f ms - terlalu mahal untuk dijalankan tiap start.', $lama)
        );
    }

    public function test_sidik_jari_stabil_bila_tidak_ada_yang_berubah(): void
    {
        $this->assertSame(
            $this->penanda->sidikJari(),
            $this->penanda->sidikJari(),
            'Sidik jari berubah sendiri - cache akan dibangun ulang selamanya.'
        );
    }

    /** Test ini sendiri tidak boleh menyentuh cache aplikasi yang sedang dipakai. */
    public function test_pemeriksaan_tidak_menyentuh_cache_aplikasi_sungguhan(): void
    {
        $this->anggapCacheBaruDibangun();
        $this->penanda->masihSesuai();

        $this->assertStringStartsWith($this->akar, $this->penanda->berkasPenanda());
        $this->assertFileDoesNotExist(base_path('bootstrap/cache/jpos-cache-stamp'));
    }
}
