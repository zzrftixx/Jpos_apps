<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan tambahan berubah dari daftar datar menjadi RANTAI BERJENJANG.
 *
 * Sebelumnya tiap satuan tambahan diisi konversinya langsung ke satuan dasar: Pack = 10,
 * Dus = 40. Pemilik toko harus menghitung sendiri bahwa satu Dus berisi 40 Pcs, dan setiap
 * kali isi Pack berubah, seluruh angka di bawahnya harus dihitung ulang manual.
 *
 * Sekarang tiap baris cuma menyebut isinya terhadap satuan SEBELUMNYA - "1 Pack = 10 Pcs",
 * "1 Dus = 4 Pack" - dan konversi ke satuan dasar dihitung server dengan mengalikannya
 * secara kumulatif. Kolom `conversion` tetap dipertahankan sebagai satu-satunya sumber
 * kebenaran untuk Kasir, struk, dan laporan, jadi tidak ada satu pun perhitungan hilir
 * yang perlu tahu soal rantai ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        // IDEMPOTEN: cek keberadaan tiap kolom dulu.
        //
        // Database yang datang dari baseline petshop/vitur lama SUDAH punya sebagian kolom ini
        // dari migrasi bernama beda (mis. 000220_add_multi_unit_chain_columns di sana). Tanpa
        // pemeriksaan ini, `alter table add column` menabrak "duplicate column name", migrasi
        // gagal di tengah, dan database tertinggal setengah termigrasi - persis gejala
        // "database tidak terbaca" yang dialami client saat memperbarui. Untuk instalasi baru
        // dari nol hasilnya identik: kolomnya belum ada, jadi tetap ditambahkan.
        if (! Schema::hasColumn('products', 'multi_unit_enabled')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('multi_unit_enabled')->default(false)->after('unit');
            });
        }

        $ratioBaruDibuat = false;

        Schema::table('product_units', function (Blueprint $table) use (&$ratioBaruDibuat) {
            if (! Schema::hasColumn('product_units', 'ratio_to_previous')) {
                // Isi satuan ini terhadap satuan sebelumnya dalam rantai, atau terhadap satuan
                // dasar produk kalau ini baris pertama.
                $table->decimal('ratio_to_previous', 15, 4)->nullable()->after('conversion');
                $ratioBaruDibuat = true;
            }
            if (! Schema::hasColumn('product_units', 'sort_order')) {
                // Urutan dalam rantai, dari yang paling dekat ke satuan dasar (0) ke yang terbesar.
                $table->integer('sort_order')->default(0)->after('ratio_to_previous');
            }
        });

        // Backfill rantai HANYA kalau kolomnya baru dibuat. Baseline lama yang sudah punya
        // kolom ini juga sudah mengisinya; menjalankan ulang pelipatan di atasnya akan
        // menggandakan konversinya - kerusakan yang justru dijaga docblock di bawah.
        if ($ratioBaruDibuat) {
            $this->isiRantaiUntukDataLama();
        }
    }

    /**
     * Menurunkan rantai dari data lama yang hanya punya konversi ke satuan dasar.
     *
     * Bagian ini menentukan keselamatan data toko yang sudah berjalan, jadi perlu dijelaskan
     * kenapa dibuat berbelit.
     *
     * Yang TIDAK boleh dilakukan adalah menyalin `conversion` apa adanya ke
     * `ratio_to_previous`. Nilainya memang cocok untuk baris pertama, tapi begitu produk itu
     * dibuka lalu disimpan kembali - tanpa diubah sama sekali - server melipat rasio secara
     * kumulatif dan hasilnya berlipat ganda. Produk lama dengan Dus=24 dan Lusin=12 akan
     * berubah menjadi Dus=24 dan Lusin=288, dan sejak saat itu harga, potongan stok, serta
     * seluruh laporan salah tanpa ada yang menyadarinya.
     *
     * Yang benar adalah membalik rumus pelipatannya: rasio tiap baris = konversinya dibagi
     * konversi baris sebelumnya, diurutkan dari yang terkecil. Dengan begitu pelipatan
     * kumulatif menghasilkan kembali `conversion` yang persis sama seperti semula.
     */
    private function isiRantaiUntukDataLama(): void
    {
        $baris = DB::table('product_units')
            ->select('id', 'product_id', 'conversion')
            ->orderBy('product_id')
            ->orderBy('conversion')
            ->orderBy('id')
            ->get();

        $konversiSebelumnya = [];
        $urutan = [];

        foreach ($baris as $satuan) {
            $produk = $satuan->product_id;
            $konversi = (float) $satuan->conversion;
            $sebelumnya = $konversiSebelumnya[$produk] ?? null;

            // Baris pertama diukur langsung terhadap satuan dasar produk.
            $rasio = ($sebelumnya === null || $sebelumnya == 0.0)
                ? $konversi
                : $konversi / $sebelumnya;

            DB::table('product_units')->where('id', $satuan->id)->update([
                'ratio_to_previous' => round($rasio, 4),
                'sort_order' => $urutan[$produk] = ($urutan[$produk] ?? -1) + 1,
            ]);

            $konversiSebelumnya[$produk] = $konversi;
        }

        // Produk yang sudah punya satuan tambahan jelas memakainya, jadi togglenya dinyalakan
        // supaya tidak ada satu pun toko yang kehilangan satuan setelah pembaruan.
        if (! empty($urutan)) {
            DB::table('products')->whereIn('id', array_keys($urutan))->update(['multi_unit_enabled' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('multi_unit_enabled');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn(['ratio_to_previous', 'sort_order']);
        });
    }
};
