<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Models\Unit;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Composer '*' berjalan untuk SETIAP view yang dirender - termasuk tiap partial,
        // komponen, dan @include. Lihat profilToko() untuk kenapa hasilnya diingat.
        View::composer('*', function ($view) {
            $view->with('storeProfile', $this->profilToko());
        });

        $this->lupakanProfilTokoSaatBerubah();
        $this->invalidateProductCatalogOnChange();
        $this->daftarkanDirektifQty();
    }

    /** Profil toko untuk permintaan yang sedang berjalan. null = belum pernah dibaca. */
    private ?array $profilToko = null;

    /**
     * Profil toko, dibaca SEKALI per permintaan.
     *
     * Sebelumnya isi fungsi ini berada langsung di dalam View::composer('*'), dan itu
     * membuatnya berjalan sekali untuk TIAP view yang dirender - bukan sekali per halaman.
     * Satu halaman JPos terdiri dari puluhan fragmen (layout, sidebar, tab, partial, tiap
     * komponen ikon), jadi biayanya berlipat sebanyak itu.
     *
     * Terukur pada halaman Planogram yang isinya cuma satu rak:
     *
     *     30x  select exists (... sqlite_master ...)      <- pemeriksaan keberadaan tabel
     *     30x  select * from settings where key = ?
     *      4x  query yang benar-benar dibutuhkan halaman
     *     ---
     *     64 query untuk menampilkan satu daftar rak.
     *
     * Beban itu ada di SETIAP halaman, dan pada server bawaan yang melayani satu permintaan
     * pada satu waktu, itu menahan kasir yang sedang menunggu di layar lain.
     *
     * Pemeriksaan hasTable tetap ada dan ikut diingat: tabel settings memang belum ada saat
     * instalasi baru pertama kali dibuka, sebelum migrasi jalan.
     */
    private function profilToko(): array
    {
        if ($this->profilToko !== null) {
            return $this->profilToko;
        }

        try {
            $this->profilToko = SchemaFacade::hasTable('settings')
                ? (array) Setting::get('store_profile', [])
                : [];
        } catch (\Throwable) {
            // Database belum siap sama sekali - halaman tetap harus bisa dirender.
            $this->profilToko = [];
        }

        return $this->profilToko;
    }

    /**
     * Melupakan profil yang diingat begitu pengaturan berubah.
     *
     * Tanpa ini, menyimpan logo baru lalu merender halaman di permintaan yang SAMA akan
     * menampilkan logo lama. Alur normal memang menyimpan lalu mengalihkan ke permintaan
     * baru, tapi mengandalkan itu berarti perbaikan performa ini menyimpan jebakan untuk
     * perubahan alur di kemudian hari.
     */
    private function lupakanProfilTokoSaatBerubah(): void
    {
        $lupakan = function () {
            $this->profilToko = null;
        };

        Setting::saved($lupakan);
        Setting::deleted($lupakan);
    }

    /**
     * @qty($nilai) - kuantitas untuk dibaca manusia.
     *
     * Kuantitas boleh pecahan sejak barang timbangan bisa dijual per Kg atau per Gram, jadi
     * `number_format($qty, 0, ',', '.')` yang tersebar di struk dan laporan berubah dari
     * benar menjadi berbahaya: penjualan 0,5 Kg akan TERCETAK "1 x 25.000" di struk
     * pelanggan sementara subtotalnya 12.500.
     *
     * Direktif ini memusatkan aturannya di satu tempat sekaligus membuat pemakaiannya
     * sesingkat yang digantikannya, supaya tidak ada alasan menulis number_format lagi.
     */
    private function daftarkanDirektifQty(): void
    {
        Blade::directive('qty', function (string $ekspresi) {
            return "<?php echo e(\\App\\Support\\Angka::qty({$ekspresi})); ?>";
        });

        $this->daftarkanDirektifAset();
    }

    /**
     * @aset('vendor/jpos.css') - alamat aset beserta penanda versinya.
     *
     * Menghasilkan mis. /vendor/jpos.css?v=2.2.1. Penanda itu yang membuat aset boleh
     * disimpan peramban selama setahun (lihat server.php): begitu paket update dipasang,
     * versinya naik, alamatnya berubah, dan peramban wajib mengambil yang baru.
     *
     * Tanpa penanda ini, cache panjang berarti kasir melihat tampilan versi lama setelah
     * aplikasi diperbarui - jauh lebih buruk daripada lambat.
     */
    private function daftarkanDirektifAset(): void
    {
        Blade::directive('aset', function (string $ekspresi) {
            return "<?php echo e(asset({$ekspresi}) . '?v=' . config('jpos.version')); ?>";
        });
    }

    /**
     * Katalog produk yang ditanam ke halaman Kasir & Retur di-cache (lihat ProductCatalog).
     * Perubahan produk atau satuannya harus langsung terlihat kasir - harga basi di
     * aplikasi kasir berarti uang yang salah.
     */
    private function invalidateProductCatalogOnChange(): void
    {
        $flush = fn () => app(ProductCatalog::class)->flush();

        foreach ([Product::class, ProductUnit::class, Unit::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
    }
}
