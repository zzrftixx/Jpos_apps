<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no', 'customer_id', 'user_id', 'subtotal', 'discount',
        'tax_amount', 'total', 'paid_amount', 'change_amount', 'payment_method', 'status',
        'order_status', 'due_date', 'note', 'parked_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'parked_at' => 'datetime',
    ];

    /**
     * Transaksi yang ditahan di meja kasir - BUKAN pesanan DP.
     *
     * Keduanya sama-sama `order_status = 'waiting'` dengan stok direservasi; yang
     * membedakan cuma niatnya. Lihat migrasi 000340 untuk alasan lengkapnya.
     */
    public function scopeTertahan($q)
    {
        return $q->where('order_status', 'waiting')->whereNotNull('parked_at');
    }

    /** Pesanan DP: waiting yang BUKAN transaksi tertahan. */
    public function scopePesanan($q)
    {
        return $q->where('order_status', 'waiting')->whereNull('parked_at');
    }

    public function getRemainingAttribute(): float
    {
        return max((float) $this->total - (float) $this->paid_amount, 0);
    }

    public function isWaiting(): bool
    {
        return $this->order_status === 'waiting';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * Nomor invoice berikutnya untuk hari ini, mis. INV2607280001.
     *
     * Versi lama membaca 4 karakter terakhir (`substr($no, -4)`) dan selalu memadatkan
     * ke 4 digit. Begitu transaksi harian menembus 9999, nomor ke-10000 menjadi
     * INV<tgl>10000 (5 digit), lalu pembacaan berikutnya mengambil "0000" sehingga
     * urutan kembali ke 1 dan menabrak kolom invoice_no yang unique - kasir mendapat
     * HTTP 500 tepat saat menekan tombol bayar.
     *
     * Sekarang urutan dibaca dari SELURUH bagian setelah prefix tanggal, dan lebar
     * padding menyesuaikan sendiri di atas 9999.
     */
    public static function generateInvoiceNo(): string
    {
        $prefix = 'INV' . now()->format('ymd');

        $highest = (int) self::where('invoice_no', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTR(invoice_no, ?) AS INTEGER)) as seq', [strlen($prefix) + 1])
            ->value('seq');

        $next = $highest + 1;
        $candidate = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        // Jaring pengaman terakhir: kolom invoice_no unique, dan tabrakan di sini berarti
        // kasir mendapat error saat menekan bayar. Lebih baik naik satu nomor diam-diam.
        while (self::where('invoice_no', $candidate)->exists()) {
            $candidate = $prefix . str_pad((string) ++$next, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }
}
