<?php

namespace App\Support;

use App\Models\CashTransaction;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat rumus keuangan JPos ditulis.
 *
 * Sebelumnya tiap laporan menghitung sendiri, dan hasilnya dua halaman yang sama-sama
 * berjudul "omset" menampilkan angka berbeda: Laporan Omset memakai `total` (termasuk pajak)
 * sedangkan Laporan Laba memakai `subtotal - discount`. Pemilik toko tidak punya cara tahu
 * mana yang benar.
 *
 * TIGA ATURAN YANG DIPEGANG DI SINI, dan alasannya:
 *
 * 1. OMSET TIDAK TERMASUK PAJAK. Pajak yang dipungut dari pembeli bukan pendapatan toko -
 *    itu titipan yang harus disetorkan. Memasukkannya ke omset membuat toko terlihat lebih
 *    besar daripada yang sebenarnya.
 *
 * 2. RETUR MENGURANGI OMSET DAN HPP, dan dicatat pada TANGGAL RETURNYA. Barang yang kembali
 *    ke rak tidak jadi terjual. Mencatatnya mundur ke tanggal penjualan akan mengubah
 *    laporan bulan yang sudah lewat - hal yang justru sedang kita hilangkan.
 *
 * 3. MODAL DAN PRIVE BUKAN LABA RUGI. Pemilik menyetor uang ke tokonya atau mengambil untuk
 *    keperluan pribadi hanya memindahkan uang antara dirinya dan tokonya; tidak ada yang
 *    dihasilkan maupun dihabiskan. Keduanya milik Neraca, bukan Laba Rugi.
 */
class Akuntansi
{
    /**
     * Omset bersih per tanggal: nilai barang terjual, tanpa pajak, dikurangi retur.
     *
     * @return array<string,float> tanggal (Y-m-d) => omset
     */
    public static function omsetHarian(string $dari, string $sampai): array
    {
        $penjualan = Sale::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->where('order_status', 'completed')
            // subtotal - discount, BUKAN total: total sudah memuat pajak titipan.
            ->selectRaw('DATE(created_at) as tanggal, SUM(subtotal - discount) as nilai')
            ->groupBy('tanggal')
            ->pluck('nilai', 'tanggal');

        $retur = SaleReturn::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->selectRaw('DATE(created_at) as tanggal, SUM(total) as nilai')
            ->groupBy('tanggal')
            ->pluck('nilai', 'tanggal');

        return self::gabung($penjualan, $retur, fn ($jual, $kembali) => Angka::bulat($jual - $kembali));
    }

    /**
     * Harga pokok penjualan per tanggal, memakai harga modal yang dibekukan saat transaksi.
     *
     * @return array<string,float>
     */
    public static function hppHarian(string $dari, string $sampai): array
    {
        // COALESCE: baris lama belum punya snapshot, jadi jatuh ke harga modal produk saat
        // ini - tidak seakurat aslinya, tapi jauh lebih baik daripada nol.
        $modal = 'COALESCE(sale_items.cost_price_snapshot, products.cost_price, 0)';

        $terjual = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->whereDate('sales.created_at', '>=', $dari)
            ->whereDate('sales.created_at', '<=', $sampai)
            ->where('sales.order_status', 'completed')
            ->selectRaw("DATE(sales.created_at) as tanggal, SUM(sale_items.qty * sale_items.unit_conversion * {$modal}) as nilai")
            ->groupBy('tanggal')
            ->pluck('nilai', 'tanggal');

        $dikembalikan = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->whereDate('sale_returns.created_at', '>=', $dari)
            ->whereDate('sale_returns.created_at', '<=', $sampai)
            ->selectRaw("DATE(sale_returns.created_at) as tanggal, SUM(sale_return_items.qty * sale_items.unit_conversion * {$modal}) as nilai")
            ->groupBy('tanggal')
            ->pluck('nilai', 'tanggal');

        return self::gabung($terjual, $dikembalikan, fn ($jual, $kembali) => Angka::bulat($jual - $kembali));
    }

    /**
     * Beban operasional per tanggal - HANYA kategori yang benar-benar beban.
     *
     * Modal tambahan dan prive sengaja tidak ikut: keduanya transaksi antara pemilik dan
     * tokonya, bukan biaya menjalankan usaha.
     *
     * @return array<string,float>
     */
    public static function bebanHarian(string $dari, string $sampai): array
    {
        $beban = CashTransaction::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->where('type', 'out')
            ->whereIn('category', CashTransaction::kategoriBeban())
            ->selectRaw('DATE(created_at) as tanggal, SUM(amount) as nilai')
            ->groupBy('tanggal')
            ->pluck('nilai', 'tanggal');

        return $beban->map(fn ($v) => Angka::bulat($v))->all();
    }

    /**
     * Pendapatan lain di luar penjualan (mis. jual kardus bekas) per tanggal.
     *
     * @return array<string,float>
     */
    public static function pendapatanLainHarian(string $dari, string $sampai): array
    {
        $lain = CashTransaction::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->where('type', 'in')
            ->whereIn('category', CashTransaction::kategoriBeban())
            ->selectRaw('DATE(created_at) as tanggal, SUM(amount) as nilai')
            ->groupBy('tanggal')
            ->pluck('nilai', 'tanggal');

        return $lain->map(fn ($v) => Angka::bulat($v))->all();
    }

    /**
     * Laba rugi lengkap per tanggal, sudah tersusun urut.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public static function labaRugiHarian(string $dari, string $sampai)
    {
        $omset = self::omsetHarian($dari, $sampai);
        $hpp = self::hppHarian($dari, $sampai);
        $beban = self::bebanHarian($dari, $sampai);
        $lain = self::pendapatanLainHarian($dari, $sampai);

        $tanggal = collect(array_keys($omset + $hpp + $beban + $lain))->unique()->sort()->values();

        return $tanggal->map(function (string $t) use ($omset, $hpp, $beban, $lain) {
            $o = $omset[$t] ?? 0.0;
            $h = $hpp[$t] ?? 0.0;
            $b = $beban[$t] ?? 0.0;
            $p = $lain[$t] ?? 0.0;
            $kotor = Angka::bulat($o - $h);

            return (object) [
                'tanggal' => $t,
                'omset' => $o,
                'hpp' => $h,
                'laba_kotor' => $kotor,
                'pendapatan_lain' => $p,
                'beban' => $b,
                'laba_bersih' => Angka::bulat($kotor + $p - $b),
            ];
        });
    }

    /** Ringkasan satu periode, dari baris harian yang sama. */
    public static function ringkasanLabaRugi(string $dari, string $sampai): object
    {
        $harian = self::labaRugiHarian($dari, $sampai);

        return (object) [
            'omset' => Angka::bulat($harian->sum('omset')),
            'hpp' => Angka::bulat($harian->sum('hpp')),
            'laba_kotor' => Angka::bulat($harian->sum('laba_kotor')),
            'pendapatan_lain' => Angka::bulat($harian->sum('pendapatan_lain')),
            'beban' => Angka::bulat($harian->sum('beban')),
            'laba_bersih' => Angka::bulat($harian->sum('laba_bersih')),
        ];
    }

    /**
     * Pajak yang dipungut dari pembeli selama periode.
     *
     * Ditampilkan terpisah, bukan sebagai omset: ini titipan yang harus disetorkan, bukan
     * hak toko.
     */
    public static function pajakDipungut(string $dari, string $sampai): float
    {
        return Angka::bulat(
            Sale::whereDate('created_at', '>=', $dari)
                ->whereDate('created_at', '<=', $sampai)
                ->where('order_status', 'completed')
                ->sum('tax_amount')
        );
    }

    /* ====================================================================== NERACA */

    /**
     * Pengaturan awal pembukuan: dari kapan dihitung, dan berapa yang sudah ada saat itu.
     *
     * Tanpa titik nol, laba ditahan akan menjumlahkan seluruh riwayat sejak aplikasi
     * dipasang - termasuk periode yang datanya belum lengkap.
     */
    public static function pengaturanPembukuan(): array
    {
        $tersimpan = Setting::get('pembukuan', []);

        return [
            'tanggal_mulai' => $tersimpan['tanggal_mulai'] ?? null,
            'saldo_awal_kas' => (float) ($tersimpan['saldo_awal_kas'] ?? 0),
            'modal_awal' => (float) ($tersimpan['modal_awal'] ?? 0),
        ];
    }

    /**
     * Posisi keuangan pada satu tanggal - isi Neraca.
     *
     * PERSAMAANNYA HARUS SELALU SEIMBANG: Aset = Kewajiban + Modal. Kalau tidak, ada yang
     * belum tercatat, dan selisihnya ditampilkan apa adanya supaya ketahuan.
     *
     * BAGIAN YANG PALING MUDAH SALAH: pesanan DP. Stoknya sudah dipotong saat pesanan dibuat
     * (supaya tidak terjual dua kali), tapi penjualannya belum diakui karena barangnya belum
     * diserahkan. Kalau dibiarkan begitu, aset berkurang tanpa ada pengurangan di sisi lain
     * dan neraca langsung timpang.
     *
     * Perlakuan yang benar: barang pesanan masih MILIK TOKO, jadi nilainya dikembalikan ke
     * persediaan; dan uang muka yang sudah diterima adalah KEWAJIBAN, karena toko masih
     * berhutang barang kepada pembelinya.
     */
    public static function posisiPada(string $tanggal): object
    {
        $atur = self::pengaturanPembukuan();
        $mulai = $atur['tanggal_mulai'] ?: '1970-01-01';

        // ---- ASET -------------------------------------------------------------------
        $kas = self::kasPada($tanggal, $mulai, $atur['saldo_awal_kas']);
        $persediaan = self::persediaanPada();
        $asetTetap = self::asetTetapPada($tanggal);

        $totalAset = Angka::bulat($kas + $persediaan + $asetTetap);

        // ---- KEWAJIBAN --------------------------------------------------------------
        $hutangUsaha = Angka::bulat(Purchase::whereDate('purchase_date', '<=', $tanggal)->sum('sisa_hutang'));
        $uangMuka = self::uangMukaPelanggan($tanggal, $mulai);

        $totalKewajiban = Angka::bulat($hutangUsaha + $uangMuka);

        // ---- MODAL ------------------------------------------------------------------
        $tambahanModal = Angka::bulat(
            CashTransaction::whereDate('created_at', '>=', $mulai)
                ->whereDate('created_at', '<=', $tanggal)
                ->where('type', 'in')->where('category', 'modal_tambahan')->sum('amount')
        );

        $prive = Angka::bulat(
            CashTransaction::whereDate('created_at', '>=', $mulai)
                ->whereDate('created_at', '<=', $tanggal)
                ->where('type', 'out')->where('category', 'ambil_pribadi')->sum('amount')
        );

        $labaDitahan = self::ringkasanLabaRugi($mulai, $tanggal)->laba_bersih;

        $totalModal = Angka::bulat($atur['modal_awal'] + $tambahanModal - $prive + $labaDitahan);

        // Sisa tagihan pesanan DP, ditampilkan sebagai keterangan - BUKAN pos neraca, karena
        // barangnya belum diserahkan sehingga penjualannya belum boleh diakui.
        $sisaTagihanPesanan = Angka::bulat(
            Sale::where('order_status', 'waiting')->whereDate('created_at', '<=', $tanggal)
                ->selectRaw('COALESCE(SUM(MAX(total - paid_amount, 0)), 0) as sisa')->value('sisa')
        );

        return (object) [
            'tanggal' => $tanggal,
            'tanggal_mulai' => $atur['tanggal_mulai'],

            'kas' => $kas,
            'persediaan' => $persediaan,
            'aset_tetap' => $asetTetap,
            'total_aset' => $totalAset,

            'hutang_usaha' => $hutangUsaha,
            'uang_muka' => $uangMuka,
            'total_kewajiban' => $totalKewajiban,

            'modal_awal' => Angka::bulat($atur['modal_awal']),
            'tambahan_modal' => $tambahanModal,
            'prive' => $prive,
            'laba_ditahan' => $labaDitahan,
            'total_modal' => $totalModal,

            'selisih' => Angka::bulat($totalAset - $totalKewajiban - $totalModal),
            'sisa_tagihan_pesanan' => $sisaTagihanPesanan,
        ];
    }

    /**
     * Angka modal awal yang membuat neraca seimbang tepat nol.
     *
     * Pemilik toko yang baru mulai membukukan tidak akan tahu harus mengisi berapa - dan
     * salah mengisi berarti neracanya timpang selamanya tanpa ada yang bisa menjelaskan
     * kenapa. Padahal angkanya bisa dihitung: modal awal yang benar adalah persis selisih
     * yang tersisa sekarang, ditambah yang sudah terisi.
     *
     * Turunannya sederhana. Neraca seimbang berarti Aset = Kewajiban + Modal, sedangkan
     * selisih = Aset - Kewajiban - Modal. Menambahkan sebesar selisih itu ke modal awal
     * membuat ruas kanan bertambah tepat sebanyak yang kurang.
     *
     * Diuji pada data toko sungguhan berisi 142 produk dan 43 transaksi: angkanya keluar
     * persis sama dengan nilai stok awal yang memang tidak pernah tercatat asal uangnya -
     * karena sebelum modul Pembelian ada, stok bertambah tanpa jejak uang sama sekali.
     */
    public static function saranModalAwal(string $tanggal, ?object $posisi = null): float
    {
        // Posisi yang sudah dihitung boleh dioper. Halaman Neraca sudah punya angkanya, dan
        // menghitung ulang berarti menembakkan seluruh query neraca dua kali untuk satu
        // tampilan yang sama.
        $posisi ??= self::posisiPada($tanggal);

        return Angka::bulat(max($posisi->modal_awal + $posisi->selisih, 0));
    }

    /**
     * Saldo kas: saldo awal, ditambah seluruh uang yang benar-benar bergerak sesudahnya.
     *
     * Penjualan tunai TIDAK dicatat di buku kas (cash_transactions hanya untuk pergerakan di
     * luar penjualan), jadi uangnya diambil langsung dari tabel penjualan. Kembalian
     * dikurangkan karena itu uang yang keluar lagi.
     */
    public static function kasPada(string $tanggal, ?string $mulai = null, ?float $saldoAwal = null): float
    {
        if ($mulai === null || $saldoAwal === null) {
            $atur = self::pengaturanPembukuan();
            $mulai = $mulai ?? ($atur['tanggal_mulai'] ?: '1970-01-01');
            $saldoAwal = $saldoAwal ?? (float) $atur['saldo_awal_kas'];
        }
        // Transaksi yang DIBATALKAN dikeluarkan dari kas.
        //
        // Pesanan DP yang dibatalkan tetap menyimpan paid_amount-nya - dan itu benar, uang
        // itu sungguh pernah diterima. Yang salah adalah menganggapnya masih ada di laci:
        // saat pesanan dibatalkan, barangnya kembali ke rak dan uangnya kembali ke pembeli.
        // Sebelum perbaikan ini, DP pesanan batal terkunci di Kas selamanya, dan kasir yang
        // menghitung lacinya selalu terlihat kurang setor.
        //
        // KENAPA BUKAN MENOLKAN paid_amount SAAT PEMBATALAN. Itu menghapus fakta bahwa
        // uangnya pernah diterima - struk pesanan batal akan menampilkan DP 0, dan
        // 17 pesanan batal yang sudah terlanjur ada di database client tetap salah karena
        // penolan hanya berlaku ke depan. Memperbaiki PEMBACANYA menyembuhkan riwayat
        // sekaligus, tanpa menghapus satu baris pun. Prinsip yang sama dipakai MetodeBayar.
        //
        // WAJIB SEJALAN dengan uangMukaPelanggan() di bawah: DP batal dulu muncul di KEDUA
        // sisi neraca - sebagai Kas (aset) dan Uang Muka Pelanggan (kewajiban) - sehingga
        // saling meniadakan. Mengeluarkannya dari satu sisi saja MERUSAK H7.
        $dariPenjualan = Sale::where('order_status', '<>', 'cancelled')
            ->whereDate('created_at', '>=', $mulai)
            ->whereDate('created_at', '<=', $tanggal)
            ->selectRaw('COALESCE(SUM(paid_amount - change_amount), 0) as nilai')
            ->value('nilai');

        $refundRetur = SaleReturn::whereDate('created_at', '>=', $mulai)
            ->whereDate('created_at', '<=', $tanggal)
            ->sum('total');

        $cashTotals = CashTransaction::whereDate('created_at', '>=', $mulai)
            ->whereDate('created_at', '<=', $tanggal)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->first();

        $kasMasuk = (float) ($cashTotals->total_in ?? 0);
        $kasKeluar = (float) ($cashTotals->total_out ?? 0);

        return Angka::bulat($saldoAwal + (float) $dariPenjualan - (float) $refundRetur + (float) $kasMasuk - (float) $kasKeluar);
    }

    /**
     * Nilai persediaan: stok di rak, ditambah barang yang sudah dipotong untuk pesanan DP.
     *
     * Barang pesanan masih milik toko sampai diserahkan, jadi nilainya tetap bagian dari
     * persediaan walaupun stoknya sudah dikunci supaya tidak terjual dua kali.
     */
    private static function persediaanPada(): float
    {
        $diRak = Product::where('type', 'barang')
            ->selectRaw('COALESCE(SUM(stock * cost_price), 0) as nilai')
            ->value('nilai');

        $modal = 'COALESCE(sale_items.cost_price_snapshot, products.cost_price, 0)';

        $dipesan = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.order_status', 'waiting')
            ->selectRaw("COALESCE(SUM(sale_items.qty * sale_items.unit_conversion * {$modal}), 0) as nilai")
            ->value('nilai');

        return Angka::bulat((float) $diRak + (float) $dipesan);
    }

    private static function asetTetapPada(string $tanggal): float
    {
        return Angka::bulat(
            FixedAsset::whereDate('acquired_at', '<=', $tanggal)->get()
                ->sum(fn (FixedAsset $a) => $a->nilaiBukuSampai($tanggal))
        );
    }

    /**
     * Uang muka pesanan: seluruh uang yang sudah diterima dari pesanan yang statusnya masih
     * 'waiting' (belum selesai) pada tanggal yang bersangkutan.
     */
    private static function uangMukaPelanggan(string $tanggal, string $mulai): float
    {
        return Angka::bulat(
            Sale::where('order_status', 'waiting')
                ->whereDate('created_at', '>=', $mulai)
                ->whereDate('created_at', '<=', $tanggal)
                ->sum('paid_amount')
        );
    }

    /**
     * Menggabungkan dua deret bertanggal, memakai gabungan seluruh tanggal yang muncul di
     * salah satunya - supaya hari yang hanya berisi retur tetap tampil.
     */
    private static function gabung($positif, $negatif, callable $hitung): array
    {
        $a = $positif->all();
        $b = $negatif->all();
        $hasil = [];

        foreach (array_keys($a + $b) as $tanggal) {
            $hasil[$tanggal] = $hitung((float) ($a[$tanggal] ?? 0), (float) ($b[$tanggal] ?? 0));
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Ringkasan mutasi kas terpadu untuk rentang tanggal:
     * memadukan kas masuk penjualan (kasir), kas masuk manual, kas keluar manual,
     * serta refund retur penjualan.
     */
    public static function ringkasanKas(string $dari, string $sampai): object
    {
        $cashTotals = CashTransaction::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->first();

        $kasMasukManual = (float) ($cashTotals->total_in ?? 0);
        $kasKeluarManual = (float) ($cashTotals->total_out ?? 0);

        $refundRetur = (float) SaleReturn::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->sum('total');

        $penjualanPerMetode = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereDate('sale_payments.created_at', '>=', $dari)
            ->whereDate('sale_payments.created_at', '<=', $sampai)
            ->where('sales.order_status', '<>', 'cancelled')
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as nilai')
            ->groupBy('sale_payments.method')
            ->pluck('nilai', 'method')
            ->all();

        $legacySales = Sale::where('order_status', '<>', 'cancelled')
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('sale_payments')
                    ->whereColumn('sale_payments.sale_id', 'sales.id');
            })
            ->selectRaw('payment_method, SUM(paid_amount - change_amount) as nilai')
            ->groupBy('payment_method')
            ->pluck('nilai', 'payment_method')
            ->all();

        foreach ($legacySales as $metode => $nilai) {
            $penjualanPerMetode[$metode] = ($penjualanPerMetode[$metode] ?? 0) + (float) $nilai;
        }

        $dariPenjualan = (float) array_sum($penjualanPerMetode);

        $totalIn = Angka::bulat($dariPenjualan + $kasMasukManual);
        $totalOut = Angka::bulat($kasKeluarManual + $refundRetur);
        $saldoPeriode = Angka::bulat($totalIn - $totalOut);
        $saldoKasToko = self::kasPada(now()->toDateString());

        return (object) [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'penjualan' => Angka::bulat($dariPenjualan),
            'manual_in' => Angka::bulat($kasMasukManual),
            'manual_out' => Angka::bulat($kasKeluarManual),
            'refund_retur' => Angka::bulat($refundRetur),
            'saldo_periode' => $saldoPeriode,
            'saldo_kas_toko' => $saldoKasToko,
            'penjualan_per_metode' => $penjualanPerMetode,
        ];
    }

    /**
     * Query mutasi kas terpadu (menggabungkan kas manual, penerimaan penjualan kasir, dan retur).
     */
    public static function queryMutasiKas(string $dari, string $sampai, ?string $type = null): \Illuminate\Database\Query\Builder
    {
        $q1 = DB::table('cash_transactions')
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->selectRaw("id, created_at, type, category, amount, note, user_id, 'cash_transaction' as source_type, id as reference_id, NULL as extra_info");

        $q2 = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.order_status', '<>', 'cancelled')
            ->where('sale_payments.amount', '>', 0)
            ->whereDate('sale_payments.created_at', '>=', $dari)
            ->whereDate('sale_payments.created_at', '<=', $sampai)
            ->selectRaw("sale_payments.id, sale_payments.created_at, 'in' as type, sale_payments.method as category, sale_payments.amount as amount, sales.invoice_no as note, COALESCE(sale_payments.user_id, sales.user_id) as user_id, 'sale' as source_type, sales.id as reference_id, customers.name as extra_info");

        $qLegacy = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.order_status', '<>', 'cancelled')
            ->whereRaw('(sales.paid_amount - sales.change_amount) > 0')
            ->whereDate('sales.created_at', '>=', $dari)
            ->whereDate('sales.created_at', '<=', $sampai)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('sale_payments')
                    ->whereColumn('sale_payments.sale_id', 'sales.id');
            })
            ->selectRaw("sales.id, sales.created_at, 'in' as type, sales.payment_method as category, (sales.paid_amount - sales.change_amount) as amount, sales.invoice_no as note, sales.user_id, 'sale' as source_type, sales.id as reference_id, customers.name as extra_info");

        $q3 = DB::table('sale_returns')
            ->where('total', '>', 0)
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->selectRaw("id, created_at, 'out' as type, 'retur' as category, total as amount, return_no as note, user_id, 'sale_return' as source_type, id as reference_id, reason as extra_info");

        $union = $q1->unionAll($q2)->unionAll($qLegacy)->unionAll($q3);
        $query = DB::query()->fromSub($union, 'mutasi');

        if ($type === 'in') {
            $query->where('type', 'in');
        } elseif ($type === 'out') {
            $query->where('type', 'out');
        } elseif ($type === 'sale') {
            $query->where('source_type', 'sale');
        } elseif ($type === 'manual_in') {
            $query->where('source_type', 'cash_transaction')->where('type', 'in');
        } elseif ($type === 'manual_out') {
            $query->where('source_type', 'cash_transaction')->where('type', 'out');
        } elseif ($type === 'return') {
            $query->where('source_type', 'sale_return');
        }

        return $query->orderByDesc('created_at');
    }
}
