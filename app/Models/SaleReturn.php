<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = ['return_no', 'sale_id', 'user_id', 'total', 'reason'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * Nomor nota retur harian, dengan pola yang sama persis seperti Sale::generateInvoiceNo().
     *
     * DUA CACAT DI VERSI SEBELUMNYA, dan keduanya baru muncul di toko yang benar-benar ramai:
     *
     * 1. `substr($last->return_no, -4)` memotong EMPAT digit terakhir. Selama nomornya masih
     *    empat digit itu benar. Begitu melewati 9.999, nomor berikutnya menjadi lima digit
     *    ("RET26081910000") dan pembacaannya berubah jadi "0000" - urutannya kembali ke 1,
     *    dan kolom `return_no` yang unique menolaknya dengan galat yang tidak bisa dipahami
     *    siapa pun di meja kasir.
     *
     * 2. Nomor tertinggi dicari lewat `orderByDesc('id')`, yaitu baris yang PALING BARU
     *    DIBUAT - bukan yang nomornya paling besar. Selama nomor selalu naik berurutan itu
     *    kebetulan sama, tapi ia bergantung pada kebetulan, bukan pada aturan.
     *
     * Sekarang keduanya diambil dari cara yang sudah terbukti di nomor invoice: nomor
     * tertinggi dicari dengan MAX() atas potongan angkanya, dan hasilnya diperiksa ulang
     * terhadap database sebagai jaring pengaman terakhir.
     */
    public static function generateReturnNo(): string
    {
        $prefix = 'RET' . now()->format('ymd');

        $tertinggi = (int) self::where('return_no', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTR(return_no, ?) AS INTEGER)) as seq', [strlen($prefix) + 1])
            ->value('seq');

        $berikut = $tertinggi + 1;
        $calon = $prefix . str_pad((string) $berikut, 4, '0', STR_PAD_LEFT);

        // Jaring pengaman terakhir: kolom return_no unique, dan tabrakan di sini berarti
        // kasir mendapat error saat menyimpan retur. Lebih baik naik satu nomor diam-diam.
        while (self::where('return_no', $calon)->exists()) {
            $calon = $prefix . str_pad((string) ++$berikut, 4, '0', STR_PAD_LEFT);
        }

        return $calon;
    }
}
