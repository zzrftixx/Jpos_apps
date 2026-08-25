<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_item_id', 'product_id', 'qty', 'price', 'subtotal'];

    /**
     * Kuantitas boleh pecahan sejak barang timbangan bisa dijual per Kg atau per Gram.
     * Cast 'float' (bukan 'decimal:4') supaya nilainya tetap berupa angka, bukan string
     * "0.4000" yang lalu ikut ke JSON dan menyusahkan perhitungan di sisi kasir.
     */
    protected $casts = [
        'qty' => 'float',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
