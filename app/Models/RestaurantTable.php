<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'capacity',
        'status',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * URL pemesanan mandiri oleh pelanggan melalui pemindaian QR code meja.
     */
    public function getOrderUrlAttribute(): string
    {
        return url('/meja/' . $this->code);
    }

    /**
     * Hubungan dengan pesanan kasir yang mengacu pada nomor meja ini.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'table_no', 'name');
    }

    /**
     * Mencari pesanan aktif / waiting list yang sedang berjalan di meja ini.
     */
    public function activeSale(): ?Sale
    {
        return Sale::where('table_no', $this->name)
            ->where('order_status', 'waiting')
            ->latest('id')
            ->first();
    }

    /**
     * Hitung jumlah pesanan waiting list aktif di meja ini.
     */
    public function activeSalesCount(): int
    {
        return Sale::where('table_no', $this->name)
            ->where('order_status', 'waiting')
            ->count();
    }

    /**
     * Mengisi meja default jika tabel masih kosong.
     */
    public static function seedDefaultTablesIfEmpty(): void
    {
        if (static::count() > 0) {
            return;
        }

        $defaultTables = [
            ['name' => 'Meja 01', 'code' => 'M01', 'capacity' => 4, 'sort_order' => 1, 'description' => 'Area Utama'],
            ['name' => 'Meja 02', 'code' => 'M02', 'capacity' => 4, 'sort_order' => 2, 'description' => 'Area Utama'],
            ['name' => 'Meja 03', 'code' => 'M03', 'capacity' => 4, 'sort_order' => 3, 'description' => 'Area Utama'],
            ['name' => 'Meja 04', 'code' => 'M04', 'capacity' => 4, 'sort_order' => 4, 'description' => 'Area Utama'],
            ['name' => 'Meja 05', 'code' => 'M05', 'capacity' => 4, 'sort_order' => 5, 'description' => 'Area Utama'],
            ['name' => 'Meja 06', 'code' => 'M06', 'capacity' => 4, 'sort_order' => 6, 'description' => 'Area Utama'],
            ['name' => 'Meja 07', 'code' => 'M07', 'capacity' => 4, 'sort_order' => 7, 'description' => 'Area Utama'],
            ['name' => 'Meja 08', 'code' => 'M08', 'capacity' => 4, 'sort_order' => 8, 'description' => 'Area Utama'],
            ['name' => 'Meja 09', 'code' => 'M09', 'capacity' => 6, 'sort_order' => 9, 'description' => 'Area Tengah'],
            ['name' => 'Meja 10', 'code' => 'M10', 'capacity' => 6, 'sort_order' => 10, 'description' => 'Area Tengah'],
            ['name' => 'Meja 11', 'code' => 'M11', 'capacity' => 2, 'sort_order' => 11, 'description' => 'Dekat Jendela'],
            ['name' => 'Meja 12', 'code' => 'M12', 'capacity' => 2, 'sort_order' => 12, 'description' => 'Dekat Jendela'],
            ['name' => 'VIP 1',   'code' => 'VIP01', 'capacity' => 8, 'sort_order' => 13, 'description' => 'Ruang VIP AC'],
            ['name' => 'VIP 2',   'code' => 'VIP02', 'capacity' => 8, 'sort_order' => 14, 'description' => 'Ruang VIP AC'],
            ['name' => 'Outdoor 1', 'code' => 'OUT01', 'capacity' => 4, 'sort_order' => 15, 'description' => 'Balkon Luar'],
            ['name' => 'Outdoor 2', 'code' => 'OUT02', 'capacity' => 4, 'sort_order' => 16, 'description' => 'Balkon Luar'],
        ];

        foreach ($defaultTables as $table) {
            static::create([
                'name' => $table['name'],
                'code' => $table['code'],
                'capacity' => $table['capacity'],
                'description' => $table['description'],
                'status' => 'available',
                'is_active' => true,
                'sort_order' => $table['sort_order'],
            ]);
        }
    }
}
