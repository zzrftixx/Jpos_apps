<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'product_name', 'price', 'cost_price_snapshot', 'qty', 'unit_label', 'unit_conversion', 'returned_qty', 'subtotal'];

    /**
     * Kuantitas boleh pecahan sejak barang timbangan bisa dijual per Kg atau per Gram.
     * Cast 'float' (bukan 'decimal:4') supaya nilainya tetap berupa angka, bukan string
     * "0.4000" yang lalu ikut ke JSON dan menyusahkan perhitungan di sisi kasir.
     */
    protected $casts = [
        'qty' => 'float',
        'returned_qty' => 'float',
        'unit_conversion' => 'float',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
