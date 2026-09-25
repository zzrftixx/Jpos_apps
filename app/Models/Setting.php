<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Cache in-memory per siklus permintaan (request) untuk mencegah N+1 pembacaan settings (B1). */
    protected static array $memo = [];

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $row = self::where('key', $key)->first();
        if (!$row) {
            return self::$memo[$key] = $default;
        }

        $decoded = json_decode($row->value, true);
        return self::$memo[$key] = (json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value);
    }

    public static function set(string $key, $value): void
    {
        $stored = is_array($value) ? json_encode($value) : $value;
        self::updateOrCreate(['key' => $key], ['value' => $stored]);
        self::$memo[$key] = is_array($value) ? $value : $stored;
    }

    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
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

    /**
     * Pengaturan fitur Shift Kasir (aktif / nonaktif, kas laci, waktu shift, dan aturan operasional).
     */
    public static function shiftKasir(): array
    {
        $default = [
            'enabled' => true,
            'require_shift_for_sales' => false,
            'show_expected_cash_on_close' => true,
            // Kas Laci (Modal Awal / Cash Float)
            'default_starting_cash' => 0,
            'starting_cash_mode' => 'fixed', // 'fixed', 'last_closing', atau 'disabled'
            'require_positive_starting_cash' => false,
            // Sistem Waktu Shift (Otomatis vs Manual)
            'time_mode' => 'auto', // 'auto' atau 'manual'
        ];
        $setting = self::get('shift_kasir', $default);
        if (!is_array($setting)) {
            return $default;
        }
        return array_merge($default, $setting);
    }

    public static function shiftKasirEnabled(): bool
    {
        return (bool) (self::shiftKasir()['enabled'] ?? true);
    }

    /**
     * Memeriksa apakah mode Kas Laci (modal awal & hitung fisik) aktif.
     */
    public static function cashDrawerEnabled(): bool
    {
        return (self::shiftKasir()['starting_cash_mode'] ?? 'fixed') !== 'disabled';
    }

    /**
     * Menghitung nilai default modal kas laci berdasarkan pengaturan yang aktif.
     */
    public static function defaultStartingCash(): float
    {
        $settings = self::shiftKasir();
        if (!self::cashDrawerEnabled()) {
            return 0.0;
        }
        if (($settings['starting_cash_mode'] ?? 'fixed') === 'last_closing') {
            $lastClosed = CashierShift::where('status', 'closed')->latest('closed_at')->first();
            if ($lastClosed && $lastClosed->actual_cash !== null) {
                return (float) $lastClosed->actual_cash;
            }
        }
        return (float) ($settings['default_starting_cash'] ?? 0);
    }
}
