<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['product_id', 'type', 'qty', 'stock_after', 'note', 'user_id'];

    /**
     * Kuantitas boleh pecahan sejak barang timbangan bisa dijual per Kg atau per Gram.
     * Cast 'float' (bukan 'decimal:4') supaya nilainya tetap berupa angka, bukan string
     * "0.4000" yang lalu ikut ke JSON dan menyusahkan perhitungan di sisi kasir.
     */
    protected $casts = [
        'qty' => 'float',
        'stock_after' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
