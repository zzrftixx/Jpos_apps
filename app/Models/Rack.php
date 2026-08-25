<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu rak toko, digambarkan sebagai kisi baris x kolom.
 */
class Rack extends Model
{
    protected $fillable = ['name', 'description', 'rows', 'cols', 'sort_order'];

    protected $casts = [
        'rows' => 'integer',
        'cols' => 'integer',
        'sort_order' => 'integer',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(RackSlot::class);
    }

    /**
     * Isi rak dipetakan sebagai "baris-kolom" => slot, supaya penggambar kisi bisa mencari
     * isi tiap kotak tanpa menyusuri seluruh koleksi untuk setiap kotak.
     */
    public function petaSlot(): array
    {
        return $this->slots
            ->keyBy(fn (RackSlot $slot) => $slot->row . '-' . $slot->col)
            ->all();
    }
}
