<?php

use App\Support\MetodeBayar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buku penerimaan uang: SATU BARIS untuk SETIAP kali uang diterima.
 *
 * KENAPA TABEL BARU, BUKAN KOLOM DI `sales`.
 *
 * Satu penjualan bisa menerima uang lebih dari sekali, dengan metode BERBEDA:
 *
 *     Pesanan cetak skripsi Rp 400.000
 *       Senin  : DP Rp 100.000  -> TUNAI
 *       Jumat  : lunas Rp 300.000 -> QRIS
 *
 * Kolom `sales.payment_method` cuma punya satu tempat jawaban. Sampai hari ini pesanan itu
 * tercatat seluruhnya "tunai", dan Rp 300.000 yang sebenarnya masuk lewat QRIS ikut
 * terhitung sebagai uang yang seharusnya ada di laci. Pemilik toko yang menghitung lacinya
 * malam itu akan menemukan selisih Rp 300.000 dan tidak punya cara menelusurinya - kolom
 * itu memang tidak pernah menyimpannya. Lihat KasirController::payWaiting: sebelum
 * perubahan ini, pelunasan hanya menambah `paid_amount` tanpa mencatat caranya SAMA SEKALI.
 *
 * KENAPA `amount` ADALAH UANG BERSIH YANG MASUK, BUKAN `paid_amount`.
 *
 * Pembeli menyerahkan Rp 50.000 untuk belanja Rp 43.000 dan menerima kembalian Rp 7.000.
 * Yang bertambah di laci adalah Rp 43.000, bukan Rp 50.000. Karena itu yang dicatat di sini
 * selalu `paid_amount - change_amount`. Dengan begitu jumlah seluruh baris pada satu hari
 * bisa langsung diadu dengan isi laci, tanpa koreksi apa pun.
 *
 * KENAPA TAMBAHAN INI TIDAK MENGUBAH SATU ANGKA PUN DI LAPORAN LAMA.
 *
 * Tabel ini murni TAMBAHAN. Kolom `sales.payment_method` tidak dihapus, tidak diubah, dan
 * tidak ditulis ulang - transaksi lama yang tersimpan 'cash' tetap 'cash'. Seluruh laporan
 * yang sudah ada tetap membaca `sales` persis seperti kemarin. Yang membaca tabel ini hanya
 * fitur yang memang baru.
 *
 * KENAPA NILAI DI SINI DIKANONKAN, PADAHAL DI `sales` TIDAK.
 *
 * Tabel ini lahir hari ini - tidak ada sejarah client yang perlu dilindungi di dalamnya.
 * Karena laporan mengelompokkan uang dengan GROUP BY, dan GROUP BY terjadi di dalam
 * database SEBELUM PHP sempat menerjemahkan apa pun, ejaan 'cash' dan 'tunai' akan menjadi
 * DUA EMBER TERPISAH untuk hal yang sama. Karena itu isian awal di bawah menerjemahkannya
 * lebih dulu, memakai peta yang sama dengan App\Support\MetodeBayar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_payments')) {
            return;
        }

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            // Kunci kanonik MetodeBayar: tunai | qris | debit | transfer | lainnya.
            $table->string('method', 30);

            // Uang bersih yang masuk. Boleh pecahan mengikuti seluruh kolom uang lain.
            $table->decimal('amount', 15, 2);

            // dp | pelunasan | bayar. Dipakai supaya struk bisa menuliskan "DP (Tunai)"
            // dan "Pelunasan (QRIS)" sebagai dua baris yang berbeda.
            $table->string('kind', 20)->default('bayar');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Laporan selalu bertanya "uang masuk per metode antara dua tanggal".
            $table->index(['created_at', 'method']);
        });

        $this->isiDariTransaksiLama();
    }

    /**
     * Isi awal dari transaksi yang sudah ada, supaya laporan baru tidak lahir kosong.
     *
     * Dikerjakan dengan SATU pernyataan INSERT ... SELECT, bukan perulangan PHP: database
     * client bisa berisi puluhan ribu transaksi, dan migrasi ini berjalan saat aplikasi
     * dinyalakan - dengan kasir menunggu di depan layar.
     *
     * Yang SENGAJA dilewati:
     *   - transaksi batal      : uangnya sudah kembali ke pembeli, bukan uang masuk.
     *   - transaksi tertahan   : keranjang beberapa menit di meja kasir, tidak ada uang.
     *   - nilai bersih <= 0    : pesanan tanpa DP (sah, lihat KasirController::store).
     *
     * Yang TIDAK BISA dipulihkan, dan harus jujur diakui: pesanan lama yang DP-nya tunai
     * lalu dilunasi lewat QRIS akan muncul di sini sebagai SATU baris dengan metode DP-nya
     * saja. Caranya memang tidak pernah tersimpan di mana pun, jadi tidak ada yang bisa
     * dibaca. Mulai hari ini setiap pelunasan mencatat metodenya sendiri.
     */
    private function isiDariTransaksiLama(): void
    {
        $kolom = 'LOWER(TRIM(COALESCE(payment_method, "")))';

        $kasus = 'CASE';
        foreach (MetodeBayar::SINONIM as $ejaan => $kanonik) {
            $kasus .= sprintf(' WHEN %s = %s THEN %s', $kolom, DB::getPdo()->quote($ejaan), DB::getPdo()->quote($kanonik));
        }
        $kasus .= sprintf(' WHEN %s = \'\' THEN \'tunai\' ELSE \'lainnya\' END', $kolom);

        DB::statement("
            INSERT INTO sale_payments (sale_id, method, amount, kind, user_id, created_at, updated_at)
            SELECT
                id,
                {$kasus},
                paid_amount - change_amount,
                CASE WHEN order_status = 'waiting' THEN 'dp' ELSE 'bayar' END,
                user_id,
                created_at,
                created_at
            FROM sales
            WHERE order_status <> 'cancelled'
              AND parked_at IS NULL
              AND (paid_amount - change_amount) > 0
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
