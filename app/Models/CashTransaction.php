<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $fillable = ['type', 'category', 'amount', 'note', 'fixed_asset_id', 'user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public static function categories(): array
    {
        return [
            'operasional' => 'Operasional',
            'gaji' => 'Gaji',
            'sewa' => 'Sewa',
            'pembelian' => 'Pembelian Barang',
            'aset_tetap' => 'Beli Peralatan / Aset Tetap',
            'modal_tambahan' => 'Modal Tambahan',
            'ambil_pribadi' => 'Ambil Pribadi (Prive)',
            'lain_lain' => 'Lain-lain',
        ];
    }

    /**
     * Kategori yang benar-benar mempengaruhi UNTUNG RUGI.
     *
     * Menyetor modal dan mengambil uang pribadi sengaja TIDAK termasuk. Keduanya memindahkan
     * uang antara pemilik dan tokonya - tidak ada yang dihasilkan maupun dihabiskan. Dulu
     * keduanya ikut dihitung, sehingga menyetor modal 5 juta menaikkan "Laba Bersih" sebesar
     * 5 juta walau tidak ada satu barang pun terjual.
     *
     * Pembelian barang juga tidak termasuk: uangnya berubah wujud jadi persediaan di rak,
     * belum jadi beban. Ia baru menjadi beban saat barangnya terjual, dan pada saat itu
     * dihitung sebagai HPP.
     *
     * Begitu pula membeli peralatan. Etalase 3 juta bukan kerugian 3 juta di bulan itu -
     * uangnya berubah wujud jadi barang yang dipakai bertahun-tahun. Bebannya dicicil lewat
     * penyusutan, bukan dibebankan sekaligus.
     */
    public static function kategoriBeban(): array
    {
        return ['operasional', 'gaji', 'sewa', 'lain_lain'];
    }

    /** Kategori yang mempengaruhi MODAL pemilik, bukan laba rugi. */
    public static function kategoriModal(): array
    {
        return ['modal_tambahan', 'ambil_pribadi'];
    }

    /**
     * Penjelasan singkat tiap kategori, ditampilkan di form Kas supaya pemilik toko tahu
     * mana yang mempengaruhi laba dan mana yang tidak.
     */
    public static function keteranganKategori(): array
    {
        return [
            'operasional' => 'Listrik, air, internet, perlengkapan - mengurangi laba.',
            'gaji' => 'Upah karyawan - mengurangi laba.',
            'sewa' => 'Sewa tempat - mengurangi laba.',
            'pembelian' => 'Beli barang dagangan - TIDAK mengurangi laba, uangnya berubah jadi stok.',
            'aset_tetap' => 'Beli etalase, komputer, motor - TIDAK mengurangi laba sekaligus; dicicil lewat penyusutan.',
            'modal_tambahan' => 'Uang pemilik disetor ke toko - menambah modal, BUKAN laba.',
            'ambil_pribadi' => 'Uang toko diambil pemilik - mengurangi modal, BUKAN rugi.',
            'lain_lain' => 'Pemasukan atau biaya di luar penjualan - mengurangi/menambah laba.',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Aset tetap yang dibeli oleh baris kas ini, kalau kategorinya `aset_tetap`.
     *
     * Keduanya hidup dan mati bersama: menghapus salah satu sendirian membuat neraca timpang
     * persis sebesar harga perolehannya.
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }
}
