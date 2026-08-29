<?php

namespace App\Models;

use App\Support\MetodeBayar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali uang diterima untuk sebuah penjualan.
 *
 * Alasan keberadaannya ada di migrasi 000350. Ringkasnya: satu pesanan bisa dibayar
 * beberapa kali dengan cara berbeda, dan satu kolom tidak bisa menyimpan dua jawaban.
 */
class SalePayment extends Model
{
    protected $fillable = ['sale_id', 'method', 'amount', 'kind', 'user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function getLabelMetodeAttribute(): string
    {
        return MetodeBayar::label($this->method);
    }

    public function getLabelJenisAttribute(): string
    {
        return match ($this->kind) {
            'dp' => 'DP',
            'pelunasan' => 'Pelunasan',
            default => 'Bayar',
        };
    }
}
