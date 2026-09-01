<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Support\Akuntansi;
use App\Support\Angka;
use App\Support\EksporLaporan;
use App\Support\Laporan;
use App\Support\MetodeBayar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Satu pintu ekspor untuk sepuluh laporan.
 *
 * Ditulis terpusat, bukan sepuluh method ekspor yang disalin ke ReportController: kop toko,
 * nomor halaman, format rupiah, dan catatan asumsi harus persis sama di semua laporan, dan
 * kalau tiap laporan menyusunnya sendiri, cepat atau lambat salah satunya tertinggal.
 *
 * Tiap laporan hanya menyusun ISINYA - judul, kolom, baris, ringkasan, catatan. Cara
 * menjadikannya PDF atau Excel bukan urusan di sini.
 */
class LaporanEksporController extends Controller
{
    public function __invoke(Request $request, string $jenis, string $format, EksporLaporan $ekspor)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $penyusun = 'laporan' . str_replace(' ', '', ucwords(str_replace('-', ' ', $jenis)));

        abort_unless(method_exists($this, $penyusun), 404);

        $laporan = $this->{$penyusun}($request)->pencetak($request->user()->name);

        return $format === 'pdf' ? $ekspor->pdf($laporan) : $ekspor->excel($laporan);
    }

    /** @return array{0:string,1:string} tanggal awal & akhir, default bulan berjalan */
    private function rentang(Request $request): array
    {
        return [
            $request->from ?: now()->startOfMonth()->toDateString(),
            $request->to ?: now()->toDateString(),
        ];
    }

    private const CATATAN_HPP = 'HPP dihitung dari harga modal yang berlaku saat barang itu terjual, bukan harga modal terkini. Laporan periode lampau karena itu bisa dicetak ulang dengan angka yang sama.';
    private const CATATAN_PAJAK = 'Omset TIDAK termasuk pajak yang dipungut dari pembeli. Pajak itu titipan yang harus disetorkan, bukan pendapatan toko.';
    private const CATATAN_RETUR = 'Retur mengurangi omset dan HPP, dicatat pada tanggal returnya - bukan mundur ke tanggal penjualan.';
    private const CATATAN_MODAL = 'Setoran modal pemilik dan uang yang diambil untuk keperluan pribadi (prive) TIDAK termasuk di sini. Keduanya memindahkan uang antara pemilik dan tokonya, bukan hasil berdagang; tempatnya di Neraca.';

    /* ==================================================================== LABA RUGI */

    private function laporanLabaRugi(Request $request): Laporan
    {
        [$dari, $sampai] = $this->rentang($request);

        $harian = Akuntansi::labaRugiHarian($dari, $sampai);
        $ringkas = Akuntansi::ringkasanLabaRugi($dari, $sampai);
        $pajak = Akuntansi::pajakDipungut($dari, $sampai);

        return Laporan::buat('Laporan Laba Rugi', 'laba-rugi')
            ->periode($dari, $sampai)
            ->ringkasan([
                'Omset Bersih' => Laporan::format($ringkas->omset, 'rupiah'),
                'Harga Pokok Penjualan' => Laporan::format($ringkas->hpp, 'rupiah'),
                'Laba Kotor' => Laporan::format($ringkas->laba_kotor, 'rupiah'),
                'Pendapatan Lain' => Laporan::format($ringkas->pendapatan_lain, 'rupiah'),
                'Beban Operasional' => Laporan::format($ringkas->beban, 'rupiah'),
                'LABA BERSIH' => Laporan::format($ringkas->laba_bersih, 'rupiah'),
                'Pajak dipungut (titipan)' => Laporan::format($pajak, 'rupiah'),
            ])
            ->bagian('Rincian Harian', [
                ['label' => 'Tanggal', 'key' => 'tanggal', 'format' => 'tanggal', 'lebar' => 14],
                ['label' => 'Omset Bersih', 'key' => 'omset', 'format' => 'rupiah'],
                ['label' => 'HPP', 'key' => 'hpp', 'format' => 'rupiah'],
                ['label' => 'Laba Kotor', 'key' => 'laba_kotor', 'format' => 'rupiah'],
                ['label' => 'Pendapatan Lain', 'key' => 'pendapatan_lain', 'format' => 'rupiah'],
                ['label' => 'Beban', 'key' => 'beban', 'format' => 'rupiah'],
                ['label' => 'Laba Bersih', 'key' => 'laba_bersih', 'format' => 'rupiah'],
            ], $harian->map(fn ($b) => (array) $b)->all(), [
                'tanggal' => 'TOTAL',
                'omset' => $ringkas->omset,
                'hpp' => $ringkas->hpp,
                'laba_kotor' => $ringkas->laba_kotor,
                'pendapatan_lain' => $ringkas->pendapatan_lain,
                'beban' => $ringkas->beban,
                'laba_bersih' => $ringkas->laba_bersih,
            ])
            ->catatan(self::CATATAN_PAJAK, self::CATATAN_HPP, self::CATATAN_RETUR, self::CATATAN_MODAL);
    }

    /* ======================================================================= NERACA */

    private function laporanNeraca(Request $request): Laporan
    {
        $tanggal = $request->tanggal ?: now()->toDateString();
        $p = Akuntansi::posisiPada($tanggal);

        $pos = fn (string $nama, float $nilai) => ['pos' => $nama, 'nilai' => $nilai];

        $laporan = Laporan::buat('Neraca', 'neraca')
            ->periode($tanggal)
            ->ringkasan([
                'Total Aset' => Laporan::format($p->total_aset, 'rupiah'),
                'Total Kewajiban' => Laporan::format($p->total_kewajiban, 'rupiah'),
                'Total Modal' => Laporan::format($p->total_modal, 'rupiah'),
                'Selisih belum tercatat' => Laporan::format($p->selisih, 'rupiah'),
            ])
            ->bagian('ASET', [
                ['label' => 'Pos', 'key' => 'pos', 'lebar' => 46],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah', 'lebar' => 20],
            ], [
                $pos('Kas & setara kas', $p->kas),
                $pos('Persediaan barang', $p->persediaan),
                $pos('Aset tetap (setelah penyusutan)', $p->aset_tetap),
            ], ['pos' => 'TOTAL ASET', 'nilai' => $p->total_aset])
            ->bagian('KEWAJIBAN', [
                ['label' => 'Pos', 'key' => 'pos', 'lebar' => 46],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah', 'lebar' => 20],
            ], [
                $pos('Hutang usaha ke pemasok', $p->hutang_usaha),
                $pos('Uang muka pelanggan', $p->uang_muka),
            ], ['pos' => 'TOTAL KEWAJIBAN', 'nilai' => $p->total_kewajiban])
            ->bagian('MODAL', [
                ['label' => 'Pos', 'key' => 'pos', 'lebar' => 46],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah', 'lebar' => 20],
            ], [
                $pos('Modal awal', $p->modal_awal),
                $pos('Tambahan modal', $p->tambahan_modal),
                $pos('Prive (pengambilan pemilik)', -$p->prive),
                $pos('Laba ditahan', $p->laba_ditahan),
            ], ['pos' => 'TOTAL MODAL', 'nilai' => $p->total_modal])
            ->bagian('PEMERIKSAAN KESEIMBANGAN', [
                ['label' => 'Uraian', 'key' => 'pos', 'lebar' => 46],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah', 'lebar' => 20],
            ], [
                $pos('Total Aset', $p->total_aset),
                $pos('Total Kewajiban + Modal', Angka::bulat($p->total_kewajiban + $p->total_modal)),
            ], ['pos' => 'SELISIH (seharusnya nol)', 'nilai' => $p->selisih]);

        $laporan->catatan(
            'Aset = Kewajiban + Modal. Kalau kedua sisi tidak sama, ada transaksi yang menggerakkan satu sisi saja dan belum tercatat.',
            'Barang pesanan DP tetap dihitung sebagai persediaan karena belum diserahkan, dan uang mukanya dicatat sebagai kewajiban - toko masih berhutang barang kepada pembelinya.',
            'Nilai persediaan memakai stok dan harga modal SAAT INI, jadi neraca hanya sahih untuk tanggal hari ini kecuali sudah dibekukan lewat Tutup Buku.',
            self::CATATAN_MODAL,
        );

        if ($p->sisa_tagihan_pesanan > 0) {
            $laporan->catatan(
                'Sisa tagihan pesanan DP ' . Laporan::format($p->sisa_tagihan_pesanan, 'rupiah')
                . ' sengaja TIDAK dimasukkan ke neraca: barangnya belum diserahkan sehingga penjualannya belum boleh diakui.'
            );
        }

        if (abs($p->selisih) >= 0.01) {
            $laporan->catatan(
                'PERHATIAN: neraca belum seimbang. Penyebab tersering - stok disesuaikan lewat Master Produk tanpa dicatat sebagai Pembelian, modal awal belum diisi dengan benar, atau ada peralatan toko yang belum didaftarkan sebagai aset tetap.'
            );
        }

        return $laporan;
    }

    /* ==================================================================== PENJUALAN */

    private function laporanPenjualan(Request $request): Laporan
    {
        [$dari, $sampai] = $this->rentang($request);

        // Penyaring metode dijaga sama persis dengan yang di layar (ReportController::penjualan).
        // Kalau tidak, pemilik toko yang menyaring "QRIS" lalu menekan Unduh PDF akan
        // mendapat berkas berisi SELURUH transaksi - dan tidak akan sadar sampai ia
        // memakainya untuk menagih.
        $metode = in_array($request->metode, MetodeBayar::kunci(), true) ? $request->metode : null;

        // Status pesanan dijaga sama persis dengan yang di layar, dengan alasan yang sama.
        // Nilai karangan diabaikan, bukan diteruskan mentah ke query.
        $status = in_array($request->order_status, ['completed', 'waiting', 'cancelled'], true)
            ? $request->order_status
            : null;

        $penjualan = Sale::with(['customer', 'cashier', 'payments'])
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->when($status, fn ($q) => $q->where('order_status', $status))
            ->when($metode, fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', $metode)))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Sale $s) => [
                'tanggal' => $s->created_at->format('d/m/Y H:i'),
                'invoice' => $s->invoice_no,
                'pelanggan' => $s->customer->name ?? 'Umum',
                'kasir' => $s->cashier->name ?? '-',
                'metode' => $s->payments->isEmpty()
                    ? '-'
                    : $s->payments->map(fn ($b) => MetodeBayar::label($b->method))->unique()->implode(', '),
                'status' => $this->labelStatus($s->order_status),
                'diskon' => (float) $s->discount,
                'pajak' => (float) $s->tax_amount,
                'total' => (float) $s->total,
            ]);

        // Rincian per status - pertanyaan yang sama dengan yang dijawab di layar.
        //
        // Ditambahkan supaya berkas unduhan dan layar tidak menjawab pertanyaan yang berbeda.
        // Pemilik toko yang membuka PDF-nya akan bertanya persis seperti saat melihat layar:
        // "kalau semuanya dijumlahkan jadi berapa, dan isinya apa saja" - dan jawabannya
        // harus ada di berkas itu juga, bukan cuma di layar yang sedang tidak ia buka.
        $perStatus = Sale::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->when($status, fn ($q) => $q->where('order_status', $status))
            ->selectRaw('order_status, COUNT(*) as jumlah, SUM(total) as nilai')
            ->groupBy('order_status')
            ->get()
            ->map(fn ($r) => [
                'status' => $this->labelStatus($r->order_status),
                'jumlah' => (int) $r->jumlah,
                'nilai' => (float) $r->nilai,
                'catatan' => match ($r->order_status) {
                    'completed' => 'dihitung sebagai omset',
                    'waiting' => 'piutang, belum jadi omset',
                    'cancelled' => 'uangnya tidak pernah masuk',
                    default => '-',
                },
            ]);

        // Uang yang benar-benar diterima pada rentang ini, dikelompokkan per metode.
        // Angkanya SENGAJA tidak sama dengan jumlah kolom Total - alasannya ada di catatan
        // di bawah, dan ikut tercetak supaya tidak dikira laporannya rusak.
        $uangMasuk = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereDate('sale_payments.created_at', '>=', $dari)
            ->whereDate('sale_payments.created_at', '<=', $sampai)
            ->where('sales.order_status', '<>', 'cancelled')
            ->when($metode, fn ($q) => $q->where('sale_payments.method', $metode))
            ->selectRaw('sale_payments.method, COUNT(*) as jumlah, SUM(sale_payments.amount) as nilai')
            ->groupBy('sale_payments.method')
            ->get()
            ->map(fn ($b) => [
                'metode' => MetodeBayar::label($b->method),
                'jumlah' => (int) $b->jumlah,
                'nilai' => (float) $b->nilai,
            ]);

        return Laporan::buat('Laporan Penjualan', 'penjualan')
            ->periode($dari, $sampai)
            ->ringkasan([
                'Jumlah Transaksi' => number_format($penjualan->count(), 0, ',', '.'),
                'Total Nilai Transaksi' => Laporan::format($penjualan->sum('total'), 'rupiah'),
                'Total Diskon' => Laporan::format($penjualan->sum('diskon'), 'rupiah'),
                'Total Pajak Dipungut' => Laporan::format($penjualan->sum('pajak'), 'rupiah'),
            ])
            ->bagian('Rincian Transaksi', [
                ['label' => 'Waktu', 'key' => 'tanggal', 'lebar' => 18],
                ['label' => 'No. Invoice', 'key' => 'invoice', 'lebar' => 20],
                ['label' => 'Pelanggan', 'key' => 'pelanggan', 'lebar' => 24],
                ['label' => 'Kasir', 'key' => 'kasir', 'lebar' => 18],
                ['label' => 'Metode Bayar', 'key' => 'metode', 'lebar' => 20],
                ['label' => 'Status', 'key' => 'status', 'lebar' => 14],
                ['label' => 'Diskon', 'key' => 'diskon', 'format' => 'rupiah'],
                ['label' => 'Pajak', 'key' => 'pajak', 'format' => 'rupiah'],
                ['label' => 'Total', 'key' => 'total', 'format' => 'rupiah'],
            ], $penjualan->all(), [
                'tanggal' => 'TOTAL',
                'diskon' => $penjualan->sum('diskon'),
                'pajak' => $penjualan->sum('pajak'),
                'total' => $penjualan->sum('total'),
            ])
            ->bagian('Rincian Seluruh Transaksi', [
                ['label' => 'Status Pesanan', 'key' => 'status', 'lebar' => 20],
                ['label' => 'Jumlah', 'key' => 'jumlah'],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah'],
                ['label' => 'Keterangan', 'key' => 'catatan', 'lebar' => 30],
            ], $perStatus->all(), [
                'status' => 'JUMLAH SEMUANYA',
                'jumlah' => $perStatus->sum('jumlah'),
                'nilai' => $perStatus->sum('nilai'),
            ])
            ->bagian('Uang Masuk per Metode', [
                ['label' => 'Metode Pembayaran', 'key' => 'metode', 'lebar' => 24],
                ['label' => 'Jumlah Penerimaan', 'key' => 'jumlah'],
                ['label' => 'Nilai', 'key' => 'nilai', 'format' => 'rupiah'],
            ], $uangMasuk->all(), [
                'metode' => 'TOTAL',
                'jumlah' => $uangMasuk->sum('jumlah'),
                'nilai' => $uangMasuk->sum('nilai'),
            ])
            ->catatan(
                'Kolom Total memuat pajak, karena ini nilai yang benar-benar dibayar pembeli. Untuk omset tanpa pajak, lihat Laporan Laba Rugi.',
                'Pesanan berstatus Menunggu dan Batal ikut ditampilkan supaya seluruh transaksi bisa ditelusuri, tapi keduanya tidak dihitung sebagai omset.',
                'Uang Masuk per Metode menghitung uang yang BENAR-BENAR diterima pada rentang tanggal ini, jadi jumlahnya sengaja tidak sama dengan jumlah kolom Total. DP yang diterima bulan ini untuk pesanan yang selesai bulan depan sudah masuk di sini tapi belum jadi omset; sebaliknya pesanan yang selesai bulan ini tapi DP-nya diterima bulan lalu hanya tercatat sebesar pelunasannya. Inilah angka yang diadu dengan isi laci dan mutasi rekening.',
                'Transaksi yang dibatalkan tidak dihitung sebagai uang masuk karena uangnya sudah dikembalikan ke pembeli.',
                'Pada Rincian Seluruh Transaksi, JUMLAH SEMUANYA adalah penjumlahan ketiga status - dan itulah cara aplikasi versi lama menghitung omset, sehingga angkanya dulu terlihat jauh lebih besar. Yang benar-benar omset hanya baris berstatus Selesai.',
            );
    }

    /* ======================================================================== OMSET */

    private function laporanOmset(Request $request): Laporan
    {
        [$dari, $sampai] = $this->rentang($request);

        $omset = Akuntansi::omsetHarian($dari, $sampai);

        $jumlahTransaksi = Sale::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->where('order_status', 'completed')
            ->selectRaw('DATE(created_at) as tanggal, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->pluck('jumlah', 'tanggal');

        $baris = collect(array_keys($omset + $jumlahTransaksi->all()))
            ->unique()->sort()->values()
            ->map(fn (string $t) => [
                'tanggal' => $t,
                'transaksi' => (int) ($jumlahTransaksi[$t] ?? 0),
                'omset' => $omset[$t] ?? 0.0,
                'rata_rata' => ($jumlahTransaksi[$t] ?? 0) > 0
                    ? Angka::bulat(($omset[$t] ?? 0) / $jumlahTransaksi[$t])
                    : 0.0,
            ]);

        return Laporan::buat('Laporan Omset', 'omset')
            ->periode($dari, $sampai)
            ->ringkasan([
                'Total Omset Bersih' => Laporan::format($baris->sum('omset'), 'rupiah'),
                'Jumlah Transaksi' => number_format($baris->sum('transaksi'), 0, ',', '.'),
                'Rata-rata per Transaksi' => Laporan::format(
                    $baris->sum('transaksi') > 0 ? $baris->sum('omset') / $baris->sum('transaksi') : 0,
                    'rupiah'
                ),
                'Pajak Dipungut' => Laporan::format(Akuntansi::pajakDipungut($dari, $sampai), 'rupiah'),
            ])
            ->bagian('Omset Harian', [
                ['label' => 'Tanggal', 'key' => 'tanggal', 'format' => 'tanggal', 'lebar' => 16],
                ['label' => 'Jumlah Transaksi', 'key' => 'transaksi', 'format' => 'angka'],
                ['label' => 'Omset Bersih', 'key' => 'omset', 'format' => 'rupiah'],
                ['label' => 'Rata-rata / Transaksi', 'key' => 'rata_rata', 'format' => 'rupiah'],
            ], $baris->all(), [
                'tanggal' => 'TOTAL',
                'transaksi' => $baris->sum('transaksi'),
                'omset' => $baris->sum('omset'),
            ])
            ->catatan(self::CATATAN_PAJAK, self::CATATAN_RETUR);
    }

    /* ========================================================================= STOK */

    private function laporanStok(Request $request): Laporan
    {
        $produk = Product::with(['category', 'rackSlot.rack'])
            ->where('type', 'barang')
            ->orderBy('name')
            ->get();

        $baris = $produk->map(fn (Product $p) => [
            'nama' => $p->name,
            'sku' => $p->sku ?: '-',
            'kategori' => $p->category->name ?? '-',
            'lokasi' => $p->rackSlot?->label() ?: '-',
            'stok' => (float) $p->stock,
            'min' => (float) $p->min_stock,
            'modal' => (float) $p->cost_price,
            'nilai' => Angka::bulat((float) $p->stock * (float) $p->cost_price),
        ]);

        $menipis = $produk->filter(fn (Product $p) => $p->isLowStock())->count();

        return Laporan::buat('Laporan Stok & Nilai Persediaan', 'stok')
            ->periode(now()->toDateString())
            ->ringkasan([
                'Jumlah Jenis Barang' => number_format($produk->count(), 0, ',', '.'),
                'Nilai Persediaan' => Laporan::format($baris->sum('nilai'), 'rupiah'),
                'Barang Stok Menipis' => number_format($menipis, 0, ',', '.') . ' jenis',
            ])
            ->bagian('Rincian Persediaan', [
                ['label' => 'Nama Produk', 'key' => 'nama', 'lebar' => 34],
                ['label' => 'SKU', 'key' => 'sku', 'lebar' => 16],
                ['label' => 'Kategori', 'key' => 'kategori', 'lebar' => 18],
                ['label' => 'Lokasi Rak', 'key' => 'lokasi', 'lebar' => 16],
                ['label' => 'Stok', 'key' => 'stok', 'format' => 'angka'],
                ['label' => 'Min. Stok', 'key' => 'min', 'format' => 'angka'],
                ['label' => 'Harga Modal', 'key' => 'modal', 'format' => 'rupiah'],
                ['label' => 'Nilai Persediaan', 'key' => 'nilai', 'format' => 'rupiah'],
            ], $baris->all(), [
                'nama' => 'TOTAL',
                'nilai' => $baris->sum('nilai'),
            ])
            ->catatan(
                'Nilai persediaan = stok x harga modal terkini. Angka ini yang masuk sebagai pos Persediaan di Neraca.',
                'Produk jasa tidak ditampilkan karena tidak punya stok fisik.',
                'Lokasi rak diambil dari Planogram. Tanda "-" berarti produknya belum ditaruh di rak mana pun.',
            );
    }

    /* ===================================================================== TERLARIS */

    private function laporanTerlaris(Request $request): Laporan
    {
        [$dari, $sampai] = $this->rentang($request);

        $baris = SaleItem::select('product_name')
            ->selectRaw('SUM(qty * unit_conversion) as total_qty, SUM(subtotal) as total_nilai')
            ->whereHas('sale', fn ($q) => $q->whereDate('created_at', '>=', $dari)
                ->whereDate('created_at', '<=', $sampai)
                ->where('order_status', 'completed'))
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->get()
            ->values()
            ->map(fn ($r, $i) => [
                'urutan' => $i + 1,
                'nama' => $r->product_name,
                'qty' => (float) $r->total_qty,
                'nilai' => (float) $r->total_nilai,
            ]);

        return Laporan::buat('Laporan Produk Terlaris', 'produk-terlaris')
            ->periode($dari, $sampai)
            ->ringkasan([
                'Jenis Produk Terjual' => number_format($baris->count(), 0, ',', '.'),
                'Total Nilai Penjualan' => Laporan::format($baris->sum('nilai'), 'rupiah'),
            ])
            ->bagian('Peringkat Penjualan', [
                ['label' => 'No.', 'key' => 'urutan', 'format' => 'angka', 'lebar' => 8],
                ['label' => 'Nama Produk', 'key' => 'nama', 'lebar' => 42],
                ['label' => 'Jumlah Terjual', 'key' => 'qty', 'format' => 'angka'],
                ['label' => 'Nilai Penjualan', 'key' => 'nilai', 'format' => 'rupiah'],
            ], $baris->all(), [
                'urutan' => null,
                'nama' => 'TOTAL',
                'nilai' => $baris->sum('nilai'),
            ])
            ->catatan(
                'Jumlah terjual dinyatakan dalam SATUAN DASAR produk. Penjualan 2 Dus @ 40 Pcs terhitung 80, bukan 2.',
                'Nama produk diambil dari catatan saat transaksi, jadi produk yang namanya sudah diubah tetap muncul dengan nama lamanya.',
                'Hanya transaksi selesai yang dihitung.',
            );
    }

    /* ================================================================ PER PELANGGAN */

    private function laporanPerPelanggan(Request $request): Laporan
    {
        [$dari, $sampai] = $this->rentang($request);

        $baris = Sale::leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereDate('sales.created_at', '>=', $dari)
            ->whereDate('sales.created_at', '<=', $sampai)
            ->where('sales.order_status', 'completed')
            ->selectRaw("COALESCE(customers.name, 'Umum') as nama, COUNT(*) as transaksi, SUM(sales.total) as belanja")
            ->groupBy('sales.customer_id', 'customers.name')
            ->orderByDesc('belanja')
            ->get()
            ->map(fn ($r) => [
                'nama' => $r->nama,
                'transaksi' => (float) $r->transaksi,
                'belanja' => (float) $r->belanja,
                'rata_rata' => $r->transaksi > 0 ? Angka::bulat($r->belanja / $r->transaksi) : 0.0,
            ]);

        return Laporan::buat('Laporan Penjualan per Pelanggan', 'penjualan-per-pelanggan')
            ->periode($dari, $sampai)
            ->ringkasan([
                'Jumlah Pelanggan' => number_format($baris->count(), 0, ',', '.'),
                'Total Belanja' => Laporan::format($baris->sum('belanja'), 'rupiah'),
            ])
            ->bagian('Rincian per Pelanggan', [
                ['label' => 'Pelanggan', 'key' => 'nama', 'lebar' => 38],
                ['label' => 'Jumlah Transaksi', 'key' => 'transaksi', 'format' => 'angka'],
                ['label' => 'Total Belanja', 'key' => 'belanja', 'format' => 'rupiah'],
                ['label' => 'Rata-rata Belanja', 'key' => 'rata_rata', 'format' => 'rupiah'],
            ], $baris->all(), [
                'nama' => 'TOTAL',
                'transaksi' => $baris->sum('transaksi'),
                'belanja' => $baris->sum('belanja'),
            ])
            ->catatan(
                'Transaksi tanpa pelanggan terdaftar dikelompokkan sebagai "Umum".',
                'Nilai belanja memuat pajak, sesuai yang dibayar pelanggan.',
            );
    }

    /* ========================================================================== KAS */

    private function laporanKas(Request $request): Laporan
    {
        [$dari, $sampai] = $this->rentang($request);

        $kas = CashTransaction::with('user')
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai)
            ->orderBy('created_at')
            ->get();

        $kategori = CashTransaction::categories();

        $baris = $kas->map(fn (CashTransaction $t) => [
            'tanggal' => $t->created_at->format('d/m/Y H:i'),
            'jenis' => $t->type === 'in' ? 'Masuk' : 'Keluar',
            'kategori' => $kategori[$t->category] ?? $t->category,
            'keterangan' => $t->note ?: '-',
            'petugas' => $t->user->name ?? '-',
            'masuk' => $t->type === 'in' ? (float) $t->amount : 0.0,
            'keluar' => $t->type === 'out' ? (float) $t->amount : 0.0,
        ]);

        return Laporan::buat('Laporan Kas Masuk & Keluar', 'kas')
            ->periode($dari, $sampai)
            ->ringkasan([
                'Total Kas Masuk' => Laporan::format($baris->sum('masuk'), 'rupiah'),
                'Total Kas Keluar' => Laporan::format($baris->sum('keluar'), 'rupiah'),
                'Selisih' => Laporan::format($baris->sum('masuk') - $baris->sum('keluar'), 'rupiah'),
            ])
            ->bagian('Rincian Mutasi Kas', [
                ['label' => 'Waktu', 'key' => 'tanggal', 'lebar' => 18],
                ['label' => 'Jenis', 'key' => 'jenis', 'lebar' => 10],
                ['label' => 'Kategori', 'key' => 'kategori', 'lebar' => 26],
                ['label' => 'Keterangan', 'key' => 'keterangan', 'lebar' => 36],
                ['label' => 'Petugas', 'key' => 'petugas', 'lebar' => 18],
                ['label' => 'Masuk', 'key' => 'masuk', 'format' => 'rupiah'],
                ['label' => 'Keluar', 'key' => 'keluar', 'format' => 'rupiah'],
            ], $baris->all(), [
                'tanggal' => 'TOTAL',
                'masuk' => $baris->sum('masuk'),
                'keluar' => $baris->sum('keluar'),
            ])
            ->catatan(
                'Buku ini HANYA memuat pergerakan uang di luar penjualan. Uang tunai dari transaksi kasir tidak muncul di sini - lihat Laporan Omset.',
                'Kategori Pembelian Barang dan Beli Peralatan TIDAK mengurangi laba: uangnya berubah wujud jadi stok atau peralatan.',
                self::CATATAN_MODAL,
            );
    }

    /* ================================================================ HUTANG USAHA */

    private function laporanHutang(Request $request): Laporan
    {
        $nota = Purchase::with('supplier')
            ->where('sisa_hutang', '>', 0)
            ->orderBy('due_date')
            ->orderBy('purchase_date')
            ->get();

        $hariIni = Carbon::today();

        $baris = $nota->map(function (Purchase $p) use ($hariIni) {
            $umur = (int) $p->purchase_date->diffInDays($hariIni);
            $jatuhTempo = $p->due_date;

            return [
                'nota' => $p->purchase_no,
                'pemasok' => $p->supplier->name ?? '-',
                'tanggal' => $p->purchase_date->toDateString(),
                'jatuh_tempo' => $jatuhTempo?->toDateString(),
                'status' => $jatuhTempo && $jatuhTempo->lt($hariIni)
                    ? 'LEWAT ' . (int) $jatuhTempo->diffInDays($hariIni) . ' hari'
                    : ($jatuhTempo ? 'Belum jatuh tempo' : 'Tanpa tempo'),
                'umur' => (float) $umur,
                'total' => (float) $p->total,
                'dibayar' => (float) $p->paid_amount,
                'sisa' => (float) $p->sisa_hutang,
            ];
        });

        $lewatTempo = $baris->filter(fn ($b) => str_starts_with($b['status'], 'LEWAT'));

        return Laporan::buat('Laporan Hutang Usaha', 'hutang-usaha')
            ->periode(now()->toDateString())
            ->ringkasan([
                'Jumlah Nota Belum Lunas' => number_format($baris->count(), 0, ',', '.'),
                'Total Sisa Hutang' => Laporan::format($baris->sum('sisa'), 'rupiah'),
                'Sudah Lewat Jatuh Tempo' => number_format($lewatTempo->count(), 0, ',', '.') . ' nota ('
                    . Laporan::format($lewatTempo->sum('sisa'), 'rupiah') . ')',
            ])
            ->bagian('Rincian Hutang per Nota', [
                ['label' => 'No. Nota', 'key' => 'nota', 'lebar' => 20],
                ['label' => 'Pemasok', 'key' => 'pemasok', 'lebar' => 28],
                ['label' => 'Tgl. Beli', 'key' => 'tanggal', 'format' => 'tanggal', 'lebar' => 14],
                ['label' => 'Jatuh Tempo', 'key' => 'jatuh_tempo', 'format' => 'tanggal', 'lebar' => 14],
                ['label' => 'Status', 'key' => 'status', 'lebar' => 20],
                ['label' => 'Umur (hari)', 'key' => 'umur', 'format' => 'angka'],
                ['label' => 'Total Nota', 'key' => 'total', 'format' => 'rupiah'],
                ['label' => 'Sudah Dibayar', 'key' => 'dibayar', 'format' => 'rupiah'],
                ['label' => 'Sisa Hutang', 'key' => 'sisa', 'format' => 'rupiah'],
            ], $baris->all(), [
                'nota' => 'TOTAL',
                'total' => $baris->sum('total'),
                'dibayar' => $baris->sum('dibayar'),
                'sisa' => $baris->sum('sisa'),
            ])
            ->catatan(
                'Hanya nota yang masih menyisakan hutang yang ditampilkan. Nota lunas tidak muncul.',
                'Umur dihitung dari tanggal pembelian sampai hari ini, bukan dari jatuh tempo.',
                'Total Sisa Hutang di sini sama dengan pos Hutang Usaha di Neraca.',
            );
    }

    /* ====================================================================== PIUTANG */

    private function laporanPiutang(Request $request): Laporan
    {
        $pesanan = Sale::with('customer')
            ->where('order_status', 'waiting')
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get();

        $hariIni = Carbon::today();

        $baris = $pesanan->map(function (Sale $s) use ($hariIni) {
            $sisa = max((float) $s->total - (float) $s->paid_amount, 0);
            $tempo = $s->due_date ? Carbon::parse($s->due_date) : null;

            return [
                'invoice' => $s->invoice_no,
                'pelanggan' => $s->customer->name ?? 'Umum',
                'tanggal' => $s->created_at->toDateString(),
                'jatuh_tempo' => $tempo?->toDateString(),
                'status' => $tempo && $tempo->lt($hariIni)
                    ? 'LEWAT ' . (int) $tempo->diffInDays($hariIni) . ' hari'
                    : ($tempo ? 'Belum jatuh tempo' : 'Tanpa tempo'),
                'total' => (float) $s->total,
                'dp' => (float) $s->paid_amount,
                'sisa' => $sisa,
            ];
        });

        return Laporan::buat('Laporan Piutang Pesanan', 'piutang')
            ->periode(now()->toDateString())
            ->ringkasan([
                'Jumlah Pesanan Belum Lunas' => number_format($baris->count(), 0, ',', '.'),
                'Total Uang Muka Diterima' => Laporan::format($baris->sum('dp'), 'rupiah'),
                'Total Sisa Tagihan' => Laporan::format($baris->sum('sisa'), 'rupiah'),
            ])
            ->bagian('Rincian Pesanan Belum Lunas', [
                ['label' => 'No. Invoice', 'key' => 'invoice', 'lebar' => 20],
                ['label' => 'Pelanggan', 'key' => 'pelanggan', 'lebar' => 28],
                ['label' => 'Tgl. Pesan', 'key' => 'tanggal', 'format' => 'tanggal', 'lebar' => 14],
                ['label' => 'Jatuh Tempo', 'key' => 'jatuh_tempo', 'format' => 'tanggal', 'lebar' => 14],
                ['label' => 'Status', 'key' => 'status', 'lebar' => 20],
                ['label' => 'Nilai Pesanan', 'key' => 'total', 'format' => 'rupiah'],
                ['label' => 'Uang Muka', 'key' => 'dp', 'format' => 'rupiah'],
                ['label' => 'Sisa Tagihan', 'key' => 'sisa', 'format' => 'rupiah'],
            ], $baris->all(), [
                'invoice' => 'TOTAL',
                'total' => $baris->sum('total'),
                'dp' => $baris->sum('dp'),
                'sisa' => $baris->sum('sisa'),
            ])
            ->catatan(
                'Sisa tagihan pesanan TIDAK muncul sebagai piutang di Neraca, karena barangnya belum diserahkan sehingga penjualannya belum boleh diakui.',
                'Uang muka yang sudah diterima justru dicatat sebagai KEWAJIBAN di Neraca - toko masih berhutang barang kepada pembelinya.',
                'Stok barang pesanan sudah dipotong supaya tidak terjual dua kali, tapi nilainya tetap dihitung sebagai persediaan.',
            );
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai',
            'waiting' => 'Menunggu',
            'cancelled' => 'Batal',
            default => $status,
        };
    }
}
