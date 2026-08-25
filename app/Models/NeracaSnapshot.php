<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Neraca yang dibekukan pada satu tanggal.
 *
 * Perlu disimpan karena nilai persediaan tidak bisa dihitung mundur: stock_movements hanya
 * menyimpan kuantitas tanpa nilai, dan harga modal produk berubah setiap kali kulakan. Jadi
 * neraca yang dihitung langsung hanya sahih untuk hari ini.
 */
class NeracaSnapshot extends Model
{
    protected $fillable = [
        'tanggal', 'kas', 'piutang', 'persediaan', 'aset_tetap', 'total_aset',
        'hutang_usaha', 'total_kewajiban',
        'modal_awal', 'tambahan_modal', 'prive', 'laba_ditahan', 'total_modal',
        'selisih', 'note', 'user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'kas' => 'float',
        'piutang' => 'float',
        'persediaan' => 'float',
        'aset_tetap' => 'float',
        'total_aset' => 'float',
        'hutang_usaha' => 'float',
        'total_kewajiban' => 'float',
        'modal_awal' => 'float',
        'tambahan_modal' => 'float',
        'prive' => 'float',
        'laba_ditahan' => 'float',
        'total_modal' => 'float',
        'selisih' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
