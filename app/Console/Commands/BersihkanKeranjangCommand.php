<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Support\Angka;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Melepas stok yang dikunci KERANJANG TERTAHAN yang ditinggal kasir.
 *
 * MASALAHNYA. Transaksi yang ditahan di meja kasir langsung memotong stok - itu memang
 * disengaja, supaya barang yang sudah dipegang pelanggan tidak terjual dua kali. Tapi kalau
 * kasir menutup halaman dan tidak pernah kembali, keranjang itu tidak pernah dibatalkan dan
 * stoknya terkunci SELAMANYA. Tidak ada satu pun jalur di aplikasi yang membersihkannya.
 *
 * YANG DISENTUH: HANYA transaksi TERTAHAN (`parked_at IS NOT NULL`).
 *
 * Ini pembatasan yang paling penting di seluruh berkas ini, dan alasannya konkret. Pesanan DP
 * dan transaksi tertahan sama-sama `order_status = 'waiting'` - lihat migrasi 000340, yang
 * dibuat justru untuk memisahkan keduanya. Yang membedakan cuma NIAT-nya:
 *
 *   TRANSAKSI TERTAHAN  keranjang beberapa MENIT di meja kasir. Tidak ada uang. Ditinggal
 *                       berarti memang ditinggal.
 *   PESANAN DP          pelanggan memesan, mengambil beberapa HARI lagi, sudah membayar muka.
 *                       Umurnya MEMANG berhari-hari - itu bukan tanda usang, itu memang
 *                       bentuknya.
 *
 * Perintah yang menyapu seluruh `waiting` berdasarkan umur akan membatalkan pesanan DP
 * sungguhan. Di satu database client ada 24 pesanan menunggu senilai Rp 109 juta dengan
 * piutang Rp 40 juta - semuanya berumur berhari-hari, dan semuanya akan lenyap. Karena itu
 * penyaring `parked_at` di bawah TIDAK BOLEH dilonggarkan, apa pun alasannya.
 *
 * Pola pengembalian stoknya menyalin `KasirController::cancelWaiting` persis: kunci baris di
 * dalam transaksi, periksa ULANG statusnya di sana (H4), kembalikan stok dalam satuan dasar,
 * dan catat `StockMovement` untuk tiap barisnya (H2). Satu keranjang = satu transaksi (H3).
 */
class BersihkanKeranjangCommand extends Command
{
    protected $signature = 'jpos:bersihkan-keranjang
                            {--jam=12 : Umur dalam jam sebelum keranjang tertahan dianggap ditinggal}
                            {--dry-run : Tampilkan yang akan dibersihkan, jangan ubah apa pun}';

    protected $description = 'Melepas stok yang dikunci keranjang TERTAHAN yang ditinggal kasir (tidak menyentuh pesanan DP)';

    public function handle(): int
    {
        $jam = max(1, (int) $this->option('jam'));
        $batas = now()->subHours($jam);

        // scopeTertahan() = order_status 'waiting' DAN parked_at NOT NULL. Memakai scope-nya,
        // bukan menulis ulang syaratnya, supaya definisi "tertahan" cuma hidup di satu tempat.
        $tertahan = Sale::tertahan()->where('parked_at', '<', $batas)->with('items')->get();

        if ($tertahan->isEmpty()) {
            $this->line('Tidak ada keranjang tertahan yang perlu dilepas.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($tertahan as $sale) {
                $this->line(sprintf(
                    '  %s  ditahan %s  (%d barang)',
                    $sale->invoice_no,
                    $sale->parked_at->diffForHumans(),
                    $sale->items->count()
                ));
            }
            $this->info($tertahan->count() . ' keranjang akan dilepas. Tidak ada yang diubah (--dry-run).');

            return self::SUCCESS;
        }

        $dilepas = 0;

        foreach ($tertahan as $keranjang) {
            DB::transaction(function () use ($keranjang, &$dilepas) {
                // Dikunci dan diperiksa ULANG di dalam transaksi (H4). Tanpa ini, kasir yang
                // kebetulan mengambil kembali keranjangnya pada detik yang sama membuat
                // stoknya dikembalikan DUA KALI.
                $sale = Sale::lockForUpdate()->find($keranjang->id);

                if (! $sale || $sale->order_status !== 'waiting' || $sale->parked_at === null) {
                    return;
                }

                foreach ($sale->items as $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = Product::lockForUpdate()->find($item->product_id);

                    if (! $product || $product->isJasa()) {
                        continue;
                    }

                    // qty tersimpan dalam satuan jualnya (mis. dus); dikonversi ke satuan
                    // dasar dulu sebelum menambah stok.
                    $baseUnits = Angka::keSatuanDasar($item->qty, $item->unit_conversion);
                    $stockAfter = $product->ubahStok($baseUnits);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'return',
                        'qty' => $baseUnits,
                        'stock_after' => $stockAfter,
                        'note' => 'Keranjang tertahan ditinggalkan, stok dilepas otomatis - ' . $sale->invoice_no,
                        'user_id' => null,
                    ]);
                }

                $sale->order_status = 'cancelled';
                $sale->save();

                $dilepas++;
            });
        }

        $this->info($dilepas . ' keranjang tertahan dilepas, stoknya kembali ke rak.');

        return self::SUCCESS;
    }
}
