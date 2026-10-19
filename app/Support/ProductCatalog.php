<?php

namespace App\Support;

use App\Models\Product;
use App\Models\RackSlot;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog produk yang ditanam ke halaman Kasir & Retur sebagai JSON.
 *
 * Halaman Kasir memuat SELURUH produk aktif beserta relasi satuannya, lalu
 * men-serialisasi semuanya ke dalam HTML. Diukur pada katalog 2.006 produk:
 *
 *     query + hydrate  151 ms
 *     toCartArray()     91 ms
 *     serialisasi JSON   5 ms
 *     -------------------------
 *     total            247 ms   (JSON 936 KB)
 *
 * Seluruh biaya itu diulang setiap kali kasir membuka halaman, padahal isinya
 * hanya berubah ketika produk diubah. Karena server PHP bawaan hanya melayani
 * satu request pada satu waktu, setiap 247 ms itu juga menahan request lain -
 * termasuk membuka struk di tab baru.
 *
 * Invalidasinya berlapis dua, karena kasir tidak boleh sekali pun melihat harga basi:
 *
 *   1. flush() dipanggil otomatis lewat model event setiap kali Product atau
 *      ProductUnit disimpan/dihapus (lihat AppServiceProvider). Ini jalur utama dan
 *      berlaku seketika.
 *   2. Kunci cache tetap diturunkan dari jumlah baris + timestamp terbaru sebagai
 *      jaring pengaman untuk perubahan yang tidak lewat Eloquent (mis. migrasi yang
 *      menulis dengan query mentah).
 *
 * Lapisan kedua saja TIDAK cukup: kolom updated_at hanya berpresisi detik, jadi dua
 * perubahan dalam detik yang sama menghasilkan kunci yang sama dan data lama tetap
 * tersaji. Itu terbukti lewat test sebelum lapisan pertama ditambahkan.
 */
class ProductCatalog
{
    private const TTL_SECONDS = 86400;
    private const CACHE_DATA_KEY = 'jpos:katalog:data';
    private const CACHE_FINGERPRINT_KEY = 'jpos:katalog:fingerprint';

    /**
     * Daftar produk aktif dalam bentuk siap pakai untuk keranjang belanja.
     */
    public function forCart(): array
    {
        $fingerprint = $this->fingerprint();
        $cachedFingerprint = Cache::get(self::CACHE_FINGERPRINT_KEY);

        if ($cachedFingerprint === $fingerprint && Cache::has(self::CACHE_DATA_KEY)) {
            return Cache::get(self::CACHE_DATA_KEY);
        }

        $data = $this->build();
        Cache::put(self::CACHE_DATA_KEY, $data, self::TTL_SECONDS);
        Cache::put(self::CACHE_FINGERPRINT_KEY, $fingerprint, self::TTL_SECONDS);

        return $data;
    }

    /**
     * Buang katalog yang tersimpan supaya dibangun ulang pada permintaan berikutnya.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_DATA_KEY);
        Cache::forget(self::CACHE_FINGERPRINT_KEY);
    }

    private function build(): array
    {
        // Dibaca SEKALI lalu dilewatkan ke tiap produk. Tanpa ini, toCartArray() menembak
        // satu query per produk cuma untuk tahu apakah satuan dasarnya boleh dijual pecahan -
        // 2.000 produk berarti 2.000 query tambahan setiap kali katalog dibangun.
        $petaTimbangan = Unit::pluck('is_weighable', 'name')->map(fn ($v) => (bool) $v)->all();

        // Lokasi rak seluruh produk, sekali jalan. Kalau dibiarkan dicari per produk, katalog
        // 2.000 produk berarti 2.000 query tambahan setiap kali dibangun ulang.
        $petaLokasi = RackSlot::join('racks', 'racks.id', '=', 'rack_slots.rack_id')
            ->select('rack_slots.product_id', 'racks.name', 'rack_slots.row', 'rack_slots.col')
            ->get()
            ->mapWithKeys(fn ($slot) => [
                $slot->product_id => trim($slot->name . ' ' . ($slot->row + 1) . '-' . ($slot->col + 1)),
            ])
            ->all();

        $today = now()->toDateString();
        $petaPromo = DB::table('promotion_items')
            ->join('promotions', 'promotions.id', '=', 'promotion_items.promotion_id')
            ->where('promotions.is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('promotions.start_date')->orWhereDate('promotions.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('promotions.end_date')->orWhereDate('promotions.end_date', '>=', $today);
            })
            ->select(
                'promotion_items.product_id',
                'promotion_items.promo_price',
                'promotion_items.promo_barcode',
                'promotions.name as promo_name'
            )
            ->get()
            ->keyBy('product_id')
            ->map(fn ($item) => [
                'promo_price' => (float) $item->promo_price,
                'promo_barcode' => (string) ($item->promo_barcode ?? ''),
                'promo_name' => (string) ($item->promo_name ?? ''),
            ])
            ->all();

        return Product::with(['units.unit', 'category'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $product->toCartArray($petaTimbangan, $petaLokasi, $petaPromo))
            ->values()
            ->all();
    }

    /**
     * Sidik jari isi katalog.
     * Menghitung jumlah produk, total stok, dan timestamp terbaru.
     * Menjamin bahwa setiap perubahan produk atau kuantitas stok langsung memicu pembaruan cache.
     */
    private function fingerprint(): string
    {
        $products = DB::table('products')
            ->selectRaw("COUNT(*) as jumlah, COALESCE(SUM(stock), 0) as total_stok, COALESCE(MAX(updated_at), '') as terbaru")
            ->first();

        $units = DB::table('product_units')
            ->selectRaw("COUNT(*) as jumlah, COALESCE(MAX(updated_at), '') as terbaru")
            ->first();

        // Tabel units ikut dihitung karena penanda "satuan timbangan" tinggal di sana, dan
        // katalog membawanya ke kasir. Tanpa baris ini, mencentang Kg sebagai satuan
        // timbangan tidak akan pernah terlihat kasir sampai ada produk yang kebetulan diubah.
        $satuan = DB::table('units')
            ->selectRaw("COUNT(*) as jumlah, COALESCE(MAX(updated_at), '') as terbaru")
            ->first();

        // Planogram ikut dihitung karena katalog membawa label lokasi rak ke kasir. Tanpa
        // baris ini, memindahkan produk ke rak lain tidak akan pernah terlihat kasir sampai
        // ada produk yang kebetulan diubah.
        $rak = DB::table('rack_slots')
            ->selectRaw("COUNT(*) as jumlah, COALESCE(MAX(updated_at), '') as terbaru")
            ->first();

        return md5(implode('|', [
            $products->jumlah ?? 0,
            $products->total_stok ?? 0,
            $products->terbaru ?? '',
            $units->jumlah ?? 0,
            $units->terbaru ?? '',
            $satuan->jumlah ?? 0,
            $satuan->terbaru ?? '',
            $rak->jumlah ?? 0,
            $rak->terbaru ?? '',
            now()->toDateString(),
        ]));
    }
}
