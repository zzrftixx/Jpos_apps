<?php

namespace App\Models;

use App\Support\Angka;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'name', 'type', 'sku', 'barcode', 'category_id', 'supplier_id', 'unit',
        'multi_unit_enabled', 'hpp_calc_enabled',
        'cost_price', 'sell_price', 'wholesale_price', 'wholesale_min_qty',
        'stock', 'min_stock', 'is_taxable',
        'image', 'description', 'is_active',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'multi_unit_enabled' => 'boolean',
        'hpp_calc_enabled' => 'boolean',
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        // Sengaja 'float', bukan 'decimal:4'. Stok boleh pecahan sejak barang timbangan
        // bisa dijual per Kg atau per Gram; cast decimal menghasilkan STRING ("9.6000")
        // yang lalu ikut ke katalog JSON dan menyusahkan perhitungan di sisi kasir.
        'stock' => 'float',
        'min_stock' => 'float',
    ];

    /**
     * image_url adalah accessor, jadi TIDAK ikut saat model diubah ke JSON kecuali
     * didaftarkan di sini. Tanpa ini, tombol Edit di Master Produk mengirim data produk
     * ke form tanpa image_url, sehingga kotak pratinjau gambar selalu kosong walaupun
     * produknya punya gambar.
     */
    protected $appends = ['image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Satuan tambahan, SELALU terurut dari yang paling dekat ke satuan dasar.
     *
     * Urutannya load-bearing, bukan kosmetik: rantai satuan dibaca dan ditulis mengikuti
     * urutan ini, jadi tanpa orderBy hasil pelipatan rasio bisa berbeda tiap kali data
     * dibaca ulang.
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image ? url('media/' . $this->image) : asset('images/no-image.svg');
    }

    /**
     * Menambah atau mengurangi stok, lalu mengembalikan stok setelahnya.
     *
     * Sengaja TIDAK memakai increment()/decrement(). Keduanya menghasilkan SQL
     * `SET stock = stock + ?` yang dijalankan langsung oleh SQLite tanpa pembulatan, dan
     * setelah puluhan transaksi pecahan galat float menumpuk menjadi sisa seperti
     * 9.599999999999999 - yang lalu tampil apa adanya di layar kasir dan tersimpan
     * selamanya. Membulatkan di sini membuat stok selalu bersih.
     *
     * Pemanggilnya SELALU sudah memegang lockForUpdate() atas produk ini, jadi membaca
     * lalu menulis kembali di sini aman dari balapan.
     */
    public function ubahStok(float $selisih): float
    {
        $this->stock = Angka::bulat($this->stock + $selisih);
        $this->save();

        return $this->stock;
    }

    public function isLowStock(): bool
    {
        return !$this->isJasa() && $this->stock <= $this->min_stock;
    }

    public function isJasa(): bool
    {
        return $this->type === 'jasa';
    }

    /**
     * @param array<string,bool>|null $petaSatuanTimbangan Nama satuan -> boleh qty pecahan.
     *        Dilewatkan dari pemanggil yang membacanya sekali untuk seluruh katalog; kalau
     *        tidak diberikan, satu query dijalankan di sini. Untuk katalog 2.000 produk,
     *        selisihnya 1 query lawan 2.000.
     */
    public function toCartArray(?array $petaSatuanTimbangan = null, ?array $petaLokasiRak = null): array
    {
        $bolehPecahan = $petaSatuanTimbangan !== null
            ? ($petaSatuanTimbangan[$this->unit] ?? false)
            : (bool) Unit::where('name', $this->unit)->value('is_weighable');

        // Sama alasannya dengan peta satuan timbangan di atas: dibaca sekali untuk seluruh
        // katalog, bukan sekali per produk. Untuk pemanggilan satuan (mis. hasil pindai
        // barcode) peta boleh dikosongkan dan lokasinya dicari langsung.
        $lokasiRak = $petaLokasiRak !== null
            ? ($petaLokasiRak[$this->id] ?? null)
            : $this->lokasiRak();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'sku' => $this->sku,
            'sell_price' => (float) $this->sell_price,
            'wholesale_price' => $this->wholesale_price !== null ? (float) $this->wholesale_price : null,
            'wholesale_min_qty' => $this->wholesale_min_qty,
            'unit' => $this->unit,
            // Satuan dasar mengambil izin pecahan dari tabel units ("Kg" memang satuan
            // timbangan di mana pun ia dipakai); satuan tambahan menentukannya per produk.
            'is_weighable' => $bolehPecahan,
            // ->all() (bukan ->values()) supaya isinya benar-benar array biasa sampai ke
            // dalam. Sebagai Collection, nilai ini tidak selamat melewati serialisasi
            // cache dan berubah menjadi __PHP_Incomplete_Class saat dibaca kembali.
            'additional_units' => $this->units->map(fn($pu) => $pu->toCartArray())->values()->all(),
            'stock' => $this->stock,
            'category_id' => $this->category_id,
            'image_url' => $this->image_url,
            'is_taxable' => $this->is_taxable,
            // Ditanam ke katalog supaya kasir bisa menjawab "ada di mana?" tanpa mencari ke
            // belakang. null berarti produk itu belum ditaruh di planogram mana pun.
            'rack_location' => $lokasiRak,
        ];
    }

    public function rackSlot(): HasOne
    {
        return $this->hasOne(RackSlot::class);
    }

    /** Label lokasi rak produk ini, mis. "Rak A 2-3". null kalau belum ditaruh di rak mana pun. */
    public function lokasiRak(): ?string
    {
        $slot = $this->relationLoaded('rackSlot') ? $this->rackSlot : RackSlot::where('product_id', $this->id)->first();

        return $slot?->label();
    }
}
