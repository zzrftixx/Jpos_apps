<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Unit;

trait ResolvesUnitPricing
{
    /** @var array<string,bool>|null Peta nama satuan -> boleh pecahan, dihidrasi sekali. */
    private ?array $petaSatuanTimbangan = null;

    /**
     * Tentukan konversi-ke-satuan-dasar, label satuan, harga (sudah memperhitungkan harga
     * grosir per satuan itu sendiri), dan apakah satuannya boleh dijual dengan qty pecahan.
     *
     * unit_type: 'base' (satuan dasar produk) atau 'unit_<id>' (salah satu ProductUnit
     * milik produk ini, mis. DUS/LUSIN yang dikonfigurasi di Master Data > Produk).
     *
     * Boleh-tidaknya qty pecahan datang dari dua tempat yang berbeda, dan itu disengaja:
     *
     *   - satuan DASAR mengambilnya dari tabel `units`, karena "Kg" memang satuan timbangan
     *     di mana pun ia dipakai;
     *   - satuan TAMBAHAN mengambilnya dari baris product_units, karena "Kg" pada Gula yang
     *     ditimbang boleh pecahan sementara "Kg" pada produk lain yang dijual per karung
     *     tidak.
     */
    protected function resolveUnitPricing(Product $product, ?string $unitType, float $qty): array
    {
        $unitType = $unitType ?: 'base';

        if ($unitType === 'base') {
            $price = ($product->wholesale_price !== null && $product->wholesale_min_qty !== null && $qty >= $product->wholesale_min_qty)
                ? (float) $product->wholesale_price
                : (float) $product->sell_price;

            return [
                'conversion' => 1.0,
                'label' => null,
                'price' => $price,
                'is_weighable' => $this->satuanDasarBolehPecahan($product->unit),
            ];
        }

        if (str_starts_with($unitType, 'unit_')) {
            $productUnitId = (int) substr($unitType, 5);
            $productUnit = $product->units->firstWhere('id', $productUnitId);

            if (!$productUnit) {
                abort(422, "Satuan yang dipilih untuk {$product->name} tidak ditemukan atau sudah dihapus.");
            }

            $price = ($productUnit->wholesale_price !== null && $productUnit->wholesale_min_qty !== null && $qty >= $productUnit->wholesale_min_qty)
                ? (float) $productUnit->wholesale_price
                : (float) $productUnit->price;

            return [
                'conversion' => (float) $productUnit->conversion,
                'label' => $productUnit->unit->name,
                'price' => $price,
                'is_weighable' => (bool) $productUnit->allow_decimal,
            ];
        }

        abort(422, 'Tipe satuan tidak valid.');
    }

    /**
     * Menolak qty pecahan untuk satuan yang tidak ditimbang.
     *
     * Setengah Dus tidak ada wujudnya, dan kalau diterima, stok toko akan berisi angka yang
     * tidak mungkin dicocokkan dengan barang di rak. Aturannya tidak bisa ditaruh di
     * validator permintaan karena boleh-tidaknya bergantung pada satuan yang dipilih di
     * baris itu, dan itu baru diketahui setelah produknya dibaca dari database.
     */
    protected function pastikanQtyBolehPecahan(Product $product, float $qty, bool $bolehPecahan, ?string $labelSatuan): void
    {
        if ($bolehPecahan || floor($qty) == $qty) {
            return;
        }

        $satuan = $labelSatuan ?: $product->unit;

        abort(422, "Qty {$product->name} ({$satuan}) harus bilangan bulat. Satuan ini bukan satuan timbangan - centang \"Satuan timbangan\" di Master Data > Satuan kalau memang dijual per berat.");
    }

    /**
     * Petanya dibaca SEKALI per permintaan, bukan sekali per baris keranjang.
     *
     * Pemanggilnya berada di dalam loop checkout yang sudah memegang kunci baris produk,
     * jadi satu query per baris di sini berarti keranjang 20 baris menahan database 20 kali
     * lebih lama daripada seharusnya - di server yang cuma melayani satu permintaan pada
     * satu waktu.
     */
    private function satuanDasarBolehPecahan(?string $namaSatuan): bool
    {
        if ($this->petaSatuanTimbangan === null) {
            $this->petaSatuanTimbangan = Unit::pluck('is_weighable', 'name')
                ->map(fn ($v) => (bool) $v)
                ->all();
        }

        return $this->petaSatuanTimbangan[$namaSatuan] ?? false;
    }
}
