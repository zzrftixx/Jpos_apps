<?php

namespace App\Models;

use App\Support\Angka;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_no', 'supplier_id', 'supplier_invoice_no', 'purchase_date',
        'subtotal', 'other_cost', 'total', 'paid_amount', 'sisa_hutang',
        'due_date', 'note', 'user_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'other_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'sisa_hutang' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function sudahLunas(): bool
    {
        return Angka::bulat($this->sisa_hutang) <= 0;
    }

    /**
     * Berapa hari nota ini lewat jatuh tempo. Negatif berarti belum jatuh tempo.
     *
     * Dipakai daftar hutang untuk menandai mana yang harus dibayar duluan - pemasok yang
     * ditunggak biasanya berhenti mengirim barang sebelum menagih.
     */
    public function umurTunggakan(): ?int
    {
        if ($this->sudahLunas() || ! $this->due_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false) * -1;
    }

    /**
     * Nomor nota berurutan per hari, mengikuti pola yang sudah dipakai penjualan dan retur.
     *
     * MAX + CAST, bukan membaca nota terakhir lalu menambah satu: cara itu kembali ke 1
     * begitu urutannya menembus empat digit, lalu menabrak kolom unique.
     */
    public static function generateNo(): string
    {
        $prefix = 'PB' . now()->format('Ymd');
        $panjangPrefix = strlen($prefix) + 1;

        $urutan = (int) static::where('purchase_no', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTR(purchase_no, {$panjangPrefix}) AS INTEGER)) as urut")
            ->value('urut');

        do {
            $urutan++;
            $nomor = $prefix . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
        } while (static::where('purchase_no', $nomor)->exists());

        return $nomor;
    }
}
