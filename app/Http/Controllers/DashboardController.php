<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Akuntansi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        // ================================================================
        // OMSET DASHBOARD - satu definisi yang sama dengan seluruh laporan
        // ================================================================
        //
        // Sebelumnya angka di sini dihitung sendiri, dan berbeda dari Laporan Omset maupun
        // Laba Rugi dalam DUA hal sekaligus:
        //
        //   1. `where('status','!=','returned')` bukan `order_status = 'completed'`, sehingga
        //      pesanan DP yang belum diserahkan dan transaksi yang sudah DIBATALKAN ikut
        //      terhitung. `monthRevenue` dan grafiknya malah tidak menyaring apa pun.
        //   2. `SUM(total)` memuat PAJAK TITIPAN - uang yang harus disetorkan, bukan hak toko.
        //      Laporan Omset sudah diperbaiki memakai `subtotal - discount` sejak 2.2.0, tapi
        //      dashboard tertinggal.
        //
        // Akibatnya angka di halaman depan selalu lebih besar dari laporannya sendiri, dan
        // tidak ada cara bagi pemilik toko untuk tahu mana yang benar.
        //
        // Sekarang seluruhnya diturunkan dari `Akuntansi::omsetHarian()` - sumber yang sama
        // dengan Laporan Omset dan Laba Rugi, yang sudah mengurangkan retur juga.
        //
        // SATU panggilan untuk tiga angka: rentangnya diambil dari yang paling awal antara
        // awal bulan dan tujuh hari lalu. Ini bukan cuma lebih rapi - ia juga lebih hemat
        // daripada kode lama, yang memakai tiga query terpisah untuk tiga angka ini.
        $awalBulan = now()->startOfMonth()->toDateString();
        $awalGrafik = now()->subDays(6)->toDateString();

        $omsetHarian = Akuntansi::omsetHarian(min($awalBulan, $awalGrafik), $today);

        $todayRevenue = (float) ($omsetHarian[$today] ?? 0);

        $monthRevenue = (float) collect($omsetHarian)
            ->filter(fn ($nilai, $tanggal) => $tanggal >= $awalBulan)
            ->sum();

        $todayTransactions = Sale::whereDate('created_at', $today)
            ->where('order_status', 'completed')
            ->count();

        $lowStockCount = Product::whereColumn('stock', '<=', 'min_stock')->where('type', 'barang')->where('is_active', true)->count();
        $totalProducts = Product::where('is_active', true)->count();

        $waitingCount = Sale::where('order_status', 'waiting')->count();
        // Dihitung di SQL, bukan dengan memuat seluruh baris pesanan ke memori lalu
        // menjumlahkan accessor `remaining` satu per satu di PHP.
        $waitingOutstanding = (float) Sale::where('order_status', 'waiting')
            ->selectRaw('COALESCE(SUM(MAX(total - paid_amount, 0)), 0) as sisa')
            ->value('sisa');

        // Grafik memakai angka yang sama persis dengan kartu omset di atasnya - diambil dari
        // larik yang sudah dihitung, tanpa query tambahan. Tanggal yang tidak punya penjualan
        // sengaja tetap muncul bernilai 0, supaya garisnya tidak melompati hari kosong dan
        // memberi kesan tokonya lebih ramai daripada kenyataannya.
        $salesChart = collect(range(6, 0))
            ->map(function (int $mundur) use ($omsetHarian) {
                $tanggal = now()->subDays($mundur)->toDateString();

                return (object) [
                    'date' => $tanggal,
                    'total' => (float) ($omsetHarian[$tanggal] ?? 0),
                ];
            });

        // HUKUM 5 juga berlaku di sini: peringkat produk terlaris tidak boleh memuat pesanan
        // yang belum diserahkan maupun transaksi yang dibatalkan.
        //
        // `qty * unit_conversion` supaya satuannya sebanding: menjual 1 Dus berisi 24 dan
        // menjual 24 Pcs adalah jumlah barang yang sama, dan tanpa konversi yang pertama
        // terhitung "1" sehingga peringkatnya jadi menyesatkan.
        $topProducts = SaleItem::select('product_name')
            ->selectRaw('SUM(qty * unit_conversion) as total_qty')
            ->whereHas('sale', function ($q) {
                $q->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->where('order_status', 'completed');
            })
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')->where('type', 'barang')->where('is_active', true)->limit(5)->get();

        return view('dashboard', compact(
            'todayRevenue', 'todayTransactions', 'monthRevenue',
            'lowStockCount', 'totalProducts', 'salesChart', 'topProducts', 'lowStockProducts',
            'waitingCount', 'waitingOutstanding'
        ));
    }
}
