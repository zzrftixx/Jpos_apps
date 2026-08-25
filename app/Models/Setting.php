<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $row = self::where('key', $key)->first();
        if (!$row) return $default;
        $decoded = json_decode($row->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function set(string $key, $value): void
    {
        $stored = is_array($value) ? json_encode($value) : $value;
        self::updateOrCreate(['key' => $key], ['value' => $stored]);
    }

    /**
     * Mode tampilan produk (gambar/list/both) yang diatur di Pengaturan > Tampilan Kasir.
     * Dipakai di semua tempat yang menampilkan daftar produk untuk dipilih (Kasir, Retur/Edit
     * Transaksi, Edit Waiting List) supaya perilakunya konsisten satu sama lain.
     */
    public static function kasirDisplayMode(): array
    {
        $setting = self::get('kasir_display', ['default_view' => 'gambar']);
        $raw = $setting['default_view'] ?? 'gambar';

        return [
            'view' => in_array($raw, ['gambar', 'list']) ? $raw : 'gambar',
            'toggle' => $raw === 'both',
        ];
    }

    /**
     * Mode form Produk (sederhana/lengkap) yang diatur di Pengaturan > Mode Produk.
     * Harga Grosir & Min. Qty Grosir SELALU tampil di kedua mode (dianggap fitur dasar).
     * 'sederhana' (default) menyembunyikan section Satuan Tambahan (multi-satuan/konversi)
     * supaya form tambah produk tidak membingungkan untuk kebutuhan sehari-hari.
     * 'lengkap' menampilkan juga Satuan Tambahan untuk produk yang dijual per DUS/LUSIN/KG dll.
     */
    public static function produkMode(): string
    {
        $setting = self::get('produk_mode', ['mode' => 'sederhana']);
        $mode = $setting['mode'] ?? 'sederhana';

        return in_array($mode, ['sederhana', 'lengkap']) ? $mode : 'sederhana';
    }
}
