<?php

namespace App\Models;

use App\Support\Angka;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aset tetap: barang yang dipakai untuk berjualan, bukan untuk dijual.
 *
 * Etalase, komputer kasir, motor pengantar, timbangan. Bedanya dengan persediaan sederhana:
 * persediaan diniatkan habis terjual, aset tetap diniatkan dipakai bertahun-tahun.
 */
class FixedAsset extends Model
{
    protected $fillable = ['name', 'acquired_at', 'acquisition_cost', 'useful_life_months', 'note', 'user_id'];

    protected $casts = [
        'acquired_at' => 'date',
        'acquisition_cost' => 'decimal:2',
        'useful_life_months' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Penyusutan yang sudah terjadi sampai tanggal tertentu, garis lurus.
     *
     * Penyusutan adalah cara mengakui bahwa barang yang dipakai lama itu nilainya berkurang
     * sedikit demi sedikit. Komputer 6 juta yang dipakai 3 tahun tidak "habis" saat dibeli;
     * ia menyusut 6.000.000 / 36 = 166.667 setiap bulan. Tanpa ini, neraca akan terus
     * mencatat komputer itu senilai 6 juta walaupun sudah tua dan hampir tidak berharga.
     *
     * Umur manfaat dikosongkan berarti pemilik toko memilih tidak menyusutkannya - wajar
     * untuk barang yang nilainya memang tidak berkurang berarti.
     */
    public function penyusutanSampai(Carbon|string $tanggal): float
    {
        if (! $this->useful_life_months || $this->useful_life_months <= 0) {
            return 0.0;
        }

        $sampai = $tanggal instanceof Carbon ? $tanggal : Carbon::parse($tanggal);

        if ($sampai->lt($this->acquired_at)) {
            return 0.0;
        }

        $bulanBerjalan = (int) $this->acquired_at->diffInMonths($sampai);
        $bulanBerjalan = min($bulanBerjalan, $this->useful_life_months);

        $perBulan = (float) $this->acquisition_cost / $this->useful_life_months;

        return Angka::bulat($perBulan * $bulanBerjalan);
    }

    /** Nilai yang masih tercatat sebagai aset setelah dikurangi penyusutan. */
    public function nilaiBukuSampai(Carbon|string $tanggal): float
    {
        return Angka::bulat((float) $this->acquisition_cost - $this->penyusutanSampai($tanggal));
    }
}
