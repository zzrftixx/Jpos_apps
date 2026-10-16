<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionItem extends Model
{
    protected $fillable = [
        'promotion_id',
        'product_id',
        'promo_price',
        'promo_barcode',
    ];

    protected $casts = [
        'promo_price' => 'float',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Otomatis membuatkan barcode promo unik jika belum diisi.
     */
    public static function generatePromoBarcode(int $promotionId, int $productId): string
    {
        return 'PRM-' . str_pad((string)$promotionId, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT);
    }
}
