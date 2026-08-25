<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kotak di rak, berisi satu produk.
 */
class RackSlot extends Model
{
    protected $fillable = ['rack_id', 'row', 'col', 'product_id', 'facings'];

    protected $casts = [
        'row' => 'integer',
        'col' => 'integer',
        'facings' => 'integer',
    ];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Label lokasi yang dibaca kasir: "Rak A-2-3" berarti Rak A, baris 2, kolom 3.
     *
     * Baris dan kolom ditampilkan mulai dari 1, bukan 0 - yang membaca ini orang yang sedang
     * berdiri di depan rak, bukan program.
     */
    public function label(): string
    {
        $namaRak = $this->relationLoaded('rack') ? $this->rack?->name : Rack::find($this->rack_id)?->name;

        return trim(($namaRak ?: 'Rak') . ' ' . ($this->row + 1) . '-' . ($this->col + 1));
    }
}
