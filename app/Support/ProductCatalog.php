<?php

namespace App\Support;

use App\Models\Product;
use App\Models\RackSlot;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    /**
     * Daftar produk aktif dalam bentuk siap pakai untuk keranjang belanja.
     */
    public function forCart(): array
    {
        return Cache::remember($this->cacheKey(), self::TTL_SECONDS, fn () => $this->build());
    }

    /**
     * Buang katalog yang tersimpan supaya dibangun ulang pada permintaan berikutnya.
     */
    public function flush(): void
    {
        Cache::forget($this->cacheKey());
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

        return Product::with('units.unit')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $product->toCartArray($petaTimbangan, $petaLokasi))
            ->values()
            ->all();
    }

    /**
     * Sidik jari isi katalog. Murah: dua agregat sederhana.
     */
    private function cacheKey(): string
    {
        $products = DB::table('products')
            ->selectRaw("COUNT(*) as jumlah, COALESCE(MAX(updated_at), '') as terbaru")
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

        return 'jpos:katalog:' . md5(implode('|', [
            $products->jumlah, $products->terbaru,
            $units->jumlah, $units->terbaru,
            $satuan->jumlah, $satuan->terbaru,
            $rak->jumlah, $rak->terbaru,
        ]));
    }
}
