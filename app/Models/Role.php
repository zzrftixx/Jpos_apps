<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'permissions', 'is_system'];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasAccess(string $menuKey): bool
    {
        if ($this->slug === 'admin') {
            return true;
        }
        return in_array($menuKey, $this->permissions ?? []);
    }

    public static function allMenuKeys(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'produk' => 'Master Data - Produk',
            'kategori' => 'Master Data - Kategori',
            'satuan' => 'Master Data - Satuan',
            'supplier' => 'Master Data - Supplier',
            'pelanggan' => 'Master Data - Pelanggan',
            'planogram' => 'Master Data - Planogram (Peta Rak)',
            'kasir' => 'Transaksi - Kasir',
            'retur' => 'Transaksi - Retur',
            'pembelian' => 'Transaksi - Pembelian &amp; Hutang',
            'kas' => 'Transaksi - Kas Masuk/Keluar',
            'laporan' => 'Laporan (semua)',
            'user' => 'Manajemen - User',
            'role' => 'Manajemen - Role Akses',
            'pengaturan' => 'Pengaturan (semua)',
        ];
    }
}
