<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Buku kas: seluruh pergerakan uang DI LUAR penjualan.
 *
 * Sejak versi 2.3.0, ini adalah SATU-SATUNYA tempat transaksi dicatat tangan - termasuk
 * setoran modal, pengambilan pribadi (prive), dan pembelian peralatan. Neraca hanya
 * MENAMPILKAN hasilnya; ia tidak lagi punya formulir sendiri.
 *
 * Alasannya bukan kerapian tampilan. Satu jenis transaksi yang bisa dimasukkan dari dua
 * tempat cepat atau lambat akan diperlakukan berbeda di salah satunya - dan selisih neraca
 * yang muncul karenanya nyaris mustahil ditelusuri berbulan-bulan kemudian.
 */
class CashTransactionController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $transactions = CashTransaction::with(['user', 'fixedAsset'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $summary = CashTransaction::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in")
            ->selectRaw("SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out")
            ->first();

        return view('transaksi.kas.index', [
            'transactions' => $transactions,
            'summary' => $summary,
            'categories' => CashTransaction::categories(),
            'keteranganKategori' => CashTransaction::keteranganKategori(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Mencatat satu pergerakan kas.
     *
     * Kategori `aset_tetap` diperlakukan khusus: ia tidak cuma mengeluarkan uang, ia juga
     * MEMBUAT ASET. Keduanya ditulis dalam satu transaksi database dan saling terhubung,
     * supaya tidak mungkin ada aset tanpa jejak uangnya atau sebaliknya - dua keadaan yang
     * masing-masing membuat neraca timpang.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'category' => ['required', 'in:' . implode(',', array_keys(CashTransaction::categories()))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        $belanjaAset = $data['category'] === 'aset_tetap';
        $aset = [];

        if ($belanjaAset) {
            // Peralatan hanya bisa DIBELI, tidak bisa "masuk" sebagai uang. Membiarkan
            // kategori ini dipakai untuk kas masuk akan membuat aset bertambah sementara
            // kas juga bertambah - dua-duanya di sisi kiri neraca, tanpa pasangan.
            if ($data['type'] !== 'out') {
                return back()->withInput()->withErrors([
                    'category' => 'Beli Peralatan hanya berlaku untuk kas KELUAR. Uang yang masuk dari menjual peralatan dicatat sebagai Lain-lain.',
                ]);
            }

            $aset = $request->validate([
                'nama_aset' => ['required', 'string', 'max:100'],
                'acquired_at' => ['required', 'date', 'before_or_equal:today'],
                'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            ]);
        }

        DB::transaction(function () use ($data, $request, $belanjaAset, $aset) {
            $asetTetap = null;

            if ($belanjaAset) {
                $asetTetap = FixedAsset::create([
                    'name' => $aset['nama_aset'],
                    'acquired_at' => $aset['acquired_at'],
                    'acquisition_cost' => $data['amount'],
                    'useful_life_months' => $aset['useful_life_months'] ?? null,
                    'note' => $data['note'] ?? null,
                    'user_id' => $request->user()->id,
                ]);
            }

            CashTransaction::create([
                'type' => $data['type'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'note' => $data['note'] ?? ($asetTetap ? 'Beli ' . $asetTetap->name : null),
                'fixed_asset_id' => $asetTetap?->id,
                'user_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', $belanjaAset
            ? 'Pembelian peralatan dicatat. Nilainya muncul sebagai Aset Tetap di Neraca.'
            : 'Transaksi kas berhasil dicatat.');
    }

    /**
     * Menghapus satu baris kas - beserta aset tetap yang dibelinya, kalau ada.
     *
     * Menghapus kasnya saja akan menyisakan peralatan yang seolah didapat gratis, dan neraca
     * timpang persis sebesar harga perolehannya.
     */
    public function destroy(CashTransaction $cashTransaction)
    {
        $aset = $cashTransaction->fixedAsset;

        DB::transaction(function () use ($cashTransaction, $aset) {
            $cashTransaction->delete();
            $aset?->delete();
        });

        return back()->with('success', $aset
            ? 'Transaksi kas dan aset tetap terkait dihapus.'
            : 'Transaksi kas berhasil dihapus.');
    }
}
