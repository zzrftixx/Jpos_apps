<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\FixedAsset;
use App\Models\NeracaSnapshot;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Support\Akuntansi;
use App\Support\Angka;
use App\Support\MetodeBayar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function penjualan(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $metode = in_array($request->metode, MetodeBayar::kunci(), true) ? $request->metode : null;

        $sales = Sale::with(['customer', 'cashier', 'payments'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($request->order_status, fn($q) => $q->where('order_status', $request->order_status))
            ->when($metode, fn($q) => $q->whereHas('payments', fn($p) => $p->where('method', $metode)))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $summary = Sale::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('order_status', 'completed')
            ->selectRaw('COUNT(*) as trx_count, SUM(total) as total_revenue, SUM(discount) as total_discount, SUM(tax_amount) as total_tax')
            ->first();

        // UANG MASUK PER METODE - dan kenapa angkanya SENGAJA tidak sama dengan
        // "Total Pendapatan" di sebelahnya.
        //
        // Keduanya menjawab pertanyaan yang berbeda, dan mencoba menyamakannya justru akan
        // membuat salah satunya bohong:
        //
        //   Total Pendapatan  : nilai transaksi yang SELESAI dalam periode ini, apa pun
        //                       kapan uangnya diterima. Ini dasar omset dan laba rugi.
        //   Uang Masuk        : uang yang BENAR-BENAR diterima dalam periode ini, apa pun
        //                       transaksinya selesai kapan. Ini yang diadu dengan isi laci.
        //
        // Contoh yang membuat keduanya wajib berbeda: pesanan cetak skripsi Rp 400.000
        // dengan DP Rp 100.000 di akhir Januari dan pelunasan Rp 300.000 di awal Februari.
        // Januari menerima uang Rp 100.000 tapi belum punya omset; Februari punya omset
        // Rp 400.000 tapi hanya menerima Rp 300.000. Keduanya benar.
        //
        // Transaksi yang dibatalkan dikeluarkan: uangnya sudah kembali ke pembeli.
        $uangMasuk = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereDate('sale_payments.created_at', '>=', $from)
            ->whereDate('sale_payments.created_at', '<=', $to)
            ->where('sales.order_status', '<>', 'cancelled')
            ->selectRaw('sale_payments.method, COUNT(*) as jumlah, SUM(sale_payments.amount) as nilai')
            ->groupBy('sale_payments.method')
            ->pluck('nilai', 'method')
            ->all();

        // RINCIAN PER STATUS - supaya pemilik toko tidak perlu kalkulator.
        //
        // Diminta langsung oleh pemilik toko: "kalau aku pengin tahu ya harus hitung manual
        // dong mas, harus kalkulator". Ia benar. Sebelum ini, untuk tahu ke mana perginya
        // selisih antara angka lama dan angka sekarang, ia harus menyaring status satu per
        // satu lalu menjumlahkan barisnya sendiri - dan kotak ringkasan di atas SENGAJA
        // tidak ikut berubah saat disaring, karena ia memang khusus menghitung omset (H5).
        //
        // Sebuah penjelasan yang benar tapi memaksa orang membuka kalkulator sama saja
        // dengan tidak menjelaskan. Satu query dikelompokkan, bukan tiga (B1).
        $rincianStatus = Sale::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('order_status, COUNT(*) as jumlah, SUM(total) as nilai')
            ->groupBy('order_status')
            ->get()
            ->keyBy('order_status');

        return view('laporan.penjualan', compact(
            'sales', 'summary', 'from', 'to', 'metode', 'uangMasuk', 'rincianStatus'
        ));
    }

    public function stok(Request $request)
    {
        // Produk jasa tidak punya stok fisik, jadi tidak relevan ditampilkan di laporan stok.
        // rackSlot.rack ikut dimuat supaya lokasi rak tidak menembak dua query per baris.
        $products = Product::with(['category', 'rackSlot.rack'])
            ->where('type', 'barang')
            ->when($request->q, fn($q) => $q->where('name', 'like', "%{$request->q}%"))
            ->when($request->low_stock, fn($q) => $q->whereColumn('stock', '<=', 'min_stock'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.stok', compact('products'));
    }

    public function stokMovements(Request $request, Product $product)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $movements = $product->stockMovements()
            ->with('user')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.stok-detail', compact('product', 'movements', 'from', 'to'));
    }

    public function terlaris(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        // HUKUM 5: hanya transaksi `completed` yang dihitung. Tanpa filter ini, pesanan DP
        // yang belum diserahkan dan transaksi yang sudah dibatalkan ikut naik ke peringkat.
        //
        // Yang membuat cacat ini bertahan lama: versi EKSPOR laporan yang sama
        // (LaporanEksporController::laporanTerlaris) sudah menyaringnya sejak awal. Jadi
        // laporan yang sama memberi angka berbeda antara layar dan PDF - dan tidak ada satu
        // pun yang membandingkan keduanya.
        $topProducts = SaleItem::select('product_name')
            ->selectRaw('SUM(qty * unit_conversion) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('sale', function ($q) use ($from, $to) {
                $q->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to)
                    ->where('order_status', 'completed');
            })
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.terlaris', compact('topProducts', 'from', 'to'));
    }

    public function omset(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        // Jumlah transaksi tetap dari tabel penjualan; nilai omsetnya memakai definisi yang
        // sama dengan Laba Rugi (tanpa pajak titipan, dikurangi retur). Sebelumnya halaman
        // ini memakai SUM(total) yang memuat pajak, sehingga dua halaman menampilkan angka
        // omset yang berbeda dan tidak ada cara tahu mana yang benar.
        $jumlahTransaksi = Sale::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('order_status', 'completed')
            ->selectRaw('DATE(created_at) as tanggal, COUNT(*) as trx_count')
            ->groupBy('tanggal')
            ->pluck('trx_count', 'tanggal');

        $omset = Akuntansi::omsetHarian($from, $to);

        $daily = collect(array_keys($omset + $jumlahTransaksi->all()))
            ->unique()->sort()->values()
            ->map(fn (string $t) => (object) [
                'tanggal' => $t,
                'trx_count' => (int) ($jumlahTransaksi[$t] ?? 0),
                'total_omset' => $omset[$t] ?? 0.0,
            ]);

        $summary = (object) [
            'trx_count' => $daily->sum('trx_count'),
            'total_omset' => Angka::bulat($daily->sum('total_omset')),
            'pajak' => Akuntansi::pajakDipungut($from, $to),
        ];

        return view('laporan.omset', compact('daily', 'summary', 'from', 'to'));
    }

    /**
     * Laporan Laba Rugi.
     *
     * Seluruh rumusnya tinggal di App\Support\Akuntansi supaya tidak ada lagi dua halaman
     * yang menghitung "omset" dengan cara berbeda. Bentuknya mengikuti susunan laba rugi
     * yang lazim, sehingga tiap baris bisa ditelusuri:
     *
     *     Omset bersih  (penjualan - retur, tanpa pajak titipan)
     *   - HPP           (harga modal saat barang itu terjual)
     *   = Laba Kotor
     *   + Pendapatan lain
     *   - Beban operasional
     *   = Laba Bersih
     *
     * Modal yang disetor pemilik dan uang yang diambil untuk keperluan pribadi TIDAK ada di
     * sini - keduanya memindahkan uang antara pemilik dan tokonya, bukan hasil berdagang.
     * Tempatnya di Neraca.
     */
    public function laba(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $daily = Akuntansi::labaRugiHarian($from, $to);
        $summary = Akuntansi::ringkasanLabaRugi($from, $to);
        $pajak = Akuntansi::pajakDipungut($from, $to);

        return view('laporan.laba', compact('daily', 'summary', 'pajak', 'from', 'to'));
    }

    /**
     * Neraca - posisi keuangan pada satu tanggal.
     *
     * Bedanya dengan Laba Rugi: laba rugi merekam satu RENTANG waktu ("selama Januari saya
     * untung berapa"), neraca membekukan satu DETIK ("per 31 Januari, apa yang saya punya,
     * berapa yang saya utang, berapa yang benar-benar bagian saya").
     */
    public function neraca(Request $request)
    {
        $tanggal = $request->tanggal ?: now()->toDateString();

        $posisi = Akuntansi::posisiPada($tanggal);
        $atur = Akuntansi::pengaturanPembukuan();
        $asetTetap = FixedAsset::whereDate('acquired_at', '<=', $tanggal)->orderBy('acquired_at')->get();
        $snapshots = NeracaSnapshot::orderByDesc('tanggal')->limit(12)->get();

        // Angka modal awal yang membuat neraca seimbang tepat nol. Tanpa ini pemilik toko
        // tidak akan tahu harus mengisi berapa, dan salah mengisi berarti neracanya timpang
        // selamanya tanpa ada yang bisa menjelaskan kenapa.
        $saranModalAwal = Akuntansi::saranModalAwal($tanggal, $posisi);

        return view('laporan.neraca', compact('posisi', 'atur', 'asetTetap', 'snapshots', 'tanggal', 'saranModalAwal'));
    }

    /** Menyimpan pengaturan titik nol pembukuan. Tanpa ini, laba ditahan tidak punya awal. */
    public function simpanPembukuan(Request $request)
    {
        $data = $request->validate([
            'tanggal_mulai' => ['required', 'date'],
            'saldo_awal_kas' => ['required', 'numeric', 'min:0'],
            'modal_awal' => ['required', 'numeric', 'min:0'],
        ]);

        Setting::set('pembukuan', [
            'tanggal_mulai' => $data['tanggal_mulai'],
            'saldo_awal_kas' => (float) $data['saldo_awal_kas'],
            'modal_awal' => (float) $data['modal_awal'],
        ]);

        return back()->with('success', 'Titik awal pembukuan disimpan.');
    }

    /**
     * Tutup Buku - membekukan neraca tanggal itu.
     *
     * Diperlukan karena nilai persediaan TIDAK BISA dihitung mundur: stock_movements
     * menyimpan kuantitas tanpa nilai, dan harga modal produk berubah setiap kali kulakan.
     * Tanpa snapshot, pertanyaan "bulan lalu modal saya berapa?" tidak akan pernah bisa
     * dijawab.
     */
    public function tutupBuku(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->toDateString();
        $posisi = Akuntansi::posisiPada($tanggal);

        // DICARI DENGAN whereDate, BUKAN updateOrCreate(['tanggal' => ...]).
        //
        // Kolom `tanggal` di-cast 'date', dan Eloquent menyimpannya lewat format tanggal
        // bawaan model - yaitu 'Y-m-d H:i:s'. Jadi isi barisnya '2026-08-13 00:00:00',
        // sementara updateOrCreate mencarinya dengan '2026-08-13' apa adanya dari formulir.
        // Pencariannya tidak pernah cocok, Eloquent menyimpulkan barisnya belum ada, lalu
        // INSERT - dan menabrak batasan UNIQUE.
        //
        // Akibatnya: menekan "Bekukan Neraca" untuk kedua kalinya pada tanggal yang sama
        // SELALU berujung galat 500. Padahal membekukan ulang adalah hal yang wajar
        // dilakukan - angkanya berubah setiap ada transaksi baru di tanggal itu.
        $snapshot = NeracaSnapshot::whereDate('tanggal', $tanggal)->first() ?? new NeracaSnapshot();

        $snapshot->fill([
            'tanggal' => $tanggal,
            'kas' => $posisi->kas,
            'piutang' => $posisi->sisa_tagihan_pesanan,
            'persediaan' => $posisi->persediaan,
            'aset_tetap' => $posisi->aset_tetap,
            'total_aset' => $posisi->total_aset,
            'hutang_usaha' => $posisi->hutang_usaha,
            'total_kewajiban' => $posisi->total_kewajiban,
            'modal_awal' => $posisi->modal_awal,
            'tambahan_modal' => $posisi->tambahan_modal,
            'prive' => $posisi->prive,
            'laba_ditahan' => $posisi->laba_ditahan,
            'total_modal' => $posisi->total_modal,
            'selisih' => $posisi->selisih,
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Neraca tanggal '
            . Carbon::parse($tanggal)->format('d/m/Y') . ' dibekukan.');
    }

    /**
     * Mendaftarkan aset tetap, sekaligus mencatat uangnya keluar dari kas.
     *
     * KENAPA UANGNYA HARUS IKUT DICATAT: mendaftarkan etalase 3 juta menambah sisi aset
     * sebesar 3 juta. Kalau tidak ada yang berkurang di sisi mana pun, neraca langsung
     * timpang persis sebesar itu - lubang yang sama persis dengan "barang muncul dari udara"
     * pada pembelian stok.
     *
     * Kategori kasnya `aset_tetap`, yang sengaja BUKAN beban: membeli etalase bukan kerugian
     * di bulan itu. Bebannya dicicil lewat penyusutan sepanjang umur pakainya.
     *
     * Kalau barangnya sudah ada sebelum pembukuan dimulai (atau dibawa pemilik dari rumah),
     * uangnya tidak keluar sekarang - nilainya harus sudah termasuk di Modal Awal.
     */
    public function simpanAsetTetap(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'acquired_at' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'note' => ['nullable', 'string', 'max:255'],
            'dibayar_dari_kas' => ['nullable', 'boolean'],
        ]);

        $dariKas = $request->boolean('dibayar_dari_kas');

        DB::transaction(function () use ($data, $request, $dariKas) {
            $aset = FixedAsset::create([
                'name' => $data['name'],
                'acquired_at' => $data['acquired_at'],
                'acquisition_cost' => $data['acquisition_cost'],
                'useful_life_months' => $data['useful_life_months'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            if ($dariKas && $aset->acquisition_cost > 0) {
                CashTransaction::create([
                    'type' => 'out',
                    'category' => 'aset_tetap',
                    'amount' => $aset->acquisition_cost,
                    'note' => 'Beli ' . $aset->name,
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', $dariKas
            ? 'Aset tetap ditambahkan dan kas keluar dicatat.'
            : 'Aset tetap ditambahkan. Pastikan nilainya sudah termasuk di Modal Awal.');
    }

    /**
     * Menghapus aset tetap sekaligus membatalkan kas keluarnya, kalau ada.
     *
     * Tanpa ini, menghapus aset menurunkan sisi aset tapi kasnya tetap berkurang - dan
     * neracanya timpang lagi, kali ini ke arah sebaliknya.
     */
    public function hapusAsetTetap(FixedAsset $asset)
    {
        DB::transaction(function () use ($asset) {
            CashTransaction::where('category', 'aset_tetap')
                ->where('type', 'out')
                ->where('note', 'Beli ' . $asset->name)
                ->where('amount', $asset->acquisition_cost)
                ->limit(1)
                ->delete();

            $asset->delete();
        });

        return back()->with('success', 'Aset tetap dihapus.');
    }

    public function perPelanggan(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $data = Sale::leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->where('sales.order_status', 'completed')
            // 'Umum' WAJIB pakai kutip tunggal: di SQLite kutip ganda berarti nama kolom,
            // dan yang bikin ini tetap jalan cuma fallback legacy - bukan perilaku yang
            // aman diandalkan.
            ->selectRaw("sales.customer_id, COALESCE(customers.name, 'Umum') as customer_name, COUNT(*) as trx_count, SUM(sales.total) as total_belanja")
            ->groupBy('sales.customer_id', 'customers.name')
            ->orderByDesc('total_belanja')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.per-pelanggan', compact('data', 'from', 'to'));
    }
}
