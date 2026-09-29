<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id', 'unit_id', 'barcode', 'conversion', 'ratio_to_previous', 'sort_order',
        'price', 'reseller_price', 'grosir_price', 'cost_price', 'modal_total', 'biaya_lain',
        'wholesale_price', 'wholesale_min_qty', 'allow_decimal',
    ];

    protected $casts = [
        'conversion' => 'decimal:4',
        'ratio_to_previous' => 'decimal:4',
        'sort_order' => 'integer',
        'price' => 'decimal:2',
        'reseller_price' => 'decimal:2',
        'grosir_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'modal_total' => 'decimal:2',
        'biaya_lain' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'allow_decimal' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function toCartArray(): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'unit_name' => $this->unit->name,
            'conversion' => (float) $this->conversion,
            // Dinamai is_weighable, bukan allow_decimal, supaya sisi kasir memakai satu nama
            // yang sama untuk satuan dasar (yang mengambilnya dari tabel units) dan satuan
            // tambahan (yang menentukannya per produk).
            'is_weighable' => (bool) $this->allow_decimal,
            'price' => (float) $this->price,
            'reseller_price' => $this->reseller_price !== null ? (float) $this->reseller_price : null,
            'grosir_price' => $this->grosir_price !== null ? (float) $this->grosir_price : null,
            'wholesale_price' => $this->wholesale_price !== null ? (float) $this->wholesale_price : null,
            'wholesale_min_qty' => $this->wholesale_min_qty,
        ];
    }
}
