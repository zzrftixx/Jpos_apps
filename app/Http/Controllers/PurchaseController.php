<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Angka;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian barang dagangan.
 *
 * Menutup lubang paling mendasar di pembukuan JPos: sebelumnya stok bertambah tanpa jejak
 * uang sama sekali. Setiap pembelian di sini selalu menggerakkan TIGA hal sekaligus, dalam
 * satu transaksi database:
 *
 *   1. STOK bertambah, beserta catatan mutasinya
 *   2. HARGA MODAL produk diperbarui, termasuk bagian biaya kirimnya
 *   3. UANG tercatat - keluar dari kas kalau tunai, atau menjadi hutang kalau belum dibayar
 *
 * Kalau salah satu tidak ikut, neraca berhenti seimbang dan penyebabnya sulit dilacak
 * berbulan-bulan kemudian.
 */
class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $purchases = Purchase::with(['supplier', 'items'])
            ->when($request->q, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('purchase_no', 'like', "%{$request->q}%")
                    ->orWhere('supplier_invoice_no', 'like', "%{$request->q}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$request->q}%"));
            }))
            ->when($request->status === 'hutang', fn ($q) => $q->where('sisa_hutang', '>', 0))
            ->when($request->status === 'lunas', fn ($q) => $q->where('sisa_hutang', '<=', 0))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $ringkasan = (object) [
            'total_hutang' => Angka::bulat(Purchase::where('sisa_hutang', '>', 0)->sum('sisa_hutang')),
            'jumlah_nota_hutang' => Purchase::where('sisa_hutang', '>', 0)->count(),
            'jatuh_tempo' => Purchase::where('sisa_hutang', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->count(),
        ];

        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::with('units.unit')->where('is_active', true)->where('type', 'barang')
            ->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'cost_price' => (float) $p->cost_price,
                'units' => $p->units->map(fn ($pu) => [
                    'id' => $pu->id,
                    'name' => $pu->unit->name,
                    'conversion' => (float) $pu->conversion,
                ])->values()->all(),
            ])->values();

        return view('transaksi.pembelian.index', compact('purchases', 'ringkasan', 'suppliers', 'products'));
    }

    public function store(Request $request, ProductCatalog $catalog)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            // TIDAK BOLEH TANGGAL DEPAN, dan ini bukan sekadar kerapian.
            //
            // Stok bertambah SEKARANG apa pun tanggal notanya, tapi hutangnya baru ikut
            // dihitung Neraca setelah tanggal itu tiba (Akuntansi::posisiPada menyaring
            // purchase_date <= tanggal). Jadi satu salah ketik tahun membuat persediaan naik
            // tanpa pasangan di sisi kewajiban - neraca timpang persis sebesar nota itu, dan
            // pemilik toko tidak akan punya cara menebak penyebabnya.
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_type' => ['nullable', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'bayar' => ['required', 'in:tunai,hutang'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $purchase = DB::transaction(function () use ($data, $request, $catalog) {
            $biayaLain = (float) ($data['other_cost'] ?? 0);

            // Baris dihitung dulu supaya subtotal nota diketahui sebelum biaya kirim dibagi.
            $baris = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                ['conversion' => $konversi, 'label' => $label] = $this->satuanPembelian($product, $item['unit_type'] ?? 'base');

                $nilai = Angka::bulat((float) $item['qty'] * (float) $item['price']);
                $subtotal = Angka::bulat($subtotal + $nilai);

                $baris[] = [
                    'product' => $product,
                    'qty' => (float) $item['qty'],
                    'konversi' => $konversi,
                    'label' => $label,
                    'price' => (float) $item['price'],
                    'subtotal' => $nilai,
                ];
            }

            $total = Angka::bulat($subtotal + $biayaLain);
            $tunai = $data['bayar'] === 'tunai';
            $dibayar = $tunai ? $total : Angka::bulat($data['paid_amount'] ?? 0);

            if ($dibayar > $total) {
                abort(422, 'Jumlah bayar melebihi total pembelian.');
            }

            $purchase = Purchase::create([
                'purchase_no' => Purchase::generateNo(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'other_cost' => $biayaLain,
                'total' => $total,
                'paid_amount' => $dibayar,
                'sisa_hutang' => Angka::bulat($total - $dibayar),
                'due_date' => $tunai ? null : ($data['due_date'] ?? null),
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            foreach ($baris as $b) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $b['product']->id,
                    'product_name' => $b['product']->name,
                    'qty' => $b['qty'],
                    'unit_label' => $b['label'],
                    'unit_conversion' => $b['konversi'],
                    'price' => $b['price'],
                    'subtotal' => $b['subtotal'],
                ]);

                $qtySatuanDasar = Angka::keSatuanDasar($b['qty'], $b['konversi']);
                $stokSesudah = $b['product']->ubahStok($qtySatuanDasar);

                StockMovement::create([
                    'product_id' => $b['product']->id,
                    'type' => 'in',
                    'qty' => $qtySatuanDasar,
                    'stock_after' => $stokSesudah,
                    'note' => 'Pembelian ' . $purchase->purchase_no,
                    'user_id' => $request->user()->id,
                ]);

                $this->perbaruiHargaModal($b, $subtotal, $biayaLain, $qtySatuanDasar);
            }

            if ($dibayar > 0) {
                $this->catatKasKeluar($purchase, $dibayar, $request->user()->id, 'Pembelian ' . $purchase->purchase_no);
            }

            return $purchase;
        });

        // Harga modal berubah, jadi katalog kasir harus dibangun ulang.
        $catalog->flush();

        return back()->with('success', "Pembelian {$purchase->purchase_no} tersimpan. Stok sudah bertambah.");
    }

    /**
     * Mencatat pembayaran hutang, sebagian atau seluruhnya.
     */
    public function bayar(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request, $purchase) {
            // Dikunci dan dibaca ULANG di dalam transaksi. Tanpa ini, dua klik "Bayar" yang
            // hampir bersamaan bisa sama-sama lolos pemeriksaan sisa hutang dan mencatat
            // pembayaran dua kali untuk nota yang sama.
            $nota = Purchase::lockForUpdate()->find($purchase->id);
            if (! $nota) {
                return;
            }

            $jumlah = Angka::bulat($data['amount']);

            if (! Angka::cukup($jumlah, $nota->sisa_hutang)) {
                abort(422, 'Jumlah bayar melebihi sisa hutang (Rp ' . number_format((float) $nota->sisa_hutang, 0, ',', '.') . ').');
            }

            PurchasePayment::create([
                'purchase_id' => $nota->id,
                'amount' => $jumlah,
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $nota->paid_amount = Angka::bulat($nota->paid_amount + $jumlah);
            $nota->sisa_hutang = Angka::bulat($nota->total - $nota->paid_amount);
            $nota->save();

            $this->catatKasKeluar($nota, $jumlah, $request->user()->id, 'Bayar hutang ' . $nota->purchase_no);
        });

        return back()->with('success', 'Pembayaran tersimpan.');
    }

    /**
     * Membatalkan pembelian: stok dikembalikan, dan seluruh jejak uangnya ikut dibuang.
     */
    public function destroy(Request $request, Purchase $purchase, ProductCatalog $catalog)
    {
        $nomor = $purchase->purchase_no;

        DB::transaction(function () use ($purchase, $request) {
            $nota = Purchase::lockForUpdate()->with('items')->find($purchase->id);
            if (! $nota) {
                return;
            }

            foreach ($nota->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::lockForUpdate()->find($item->product_id);
                if (! $product || $product->isJasa()) {
                    continue;
                }

                $qtySatuanDasar = Angka::keSatuanDasar($item->qty, $item->unit_conversion);

                if (! Angka::cukup($qtySatuanDasar, $product->stock)) {
                    abort(422, "Stok {$product->name} sudah terjual sebagian, jadi pembelian ini tidak bisa dibatalkan. Buat penyesuaian stok kalau memang salah catat.");
                }

                $stokSesudah = $product->ubahStok(-$qtySatuanDasar);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'qty' => -$qtySatuanDasar,
                    'stock_after' => $stokSesudah,
                    'note' => 'Pembatalan pembelian ' . $nota->purchase_no,
                    'user_id' => $request->user()->id,
                ]);
            }

            // Uang yang sudah dibayar dikembalikan ke kas, supaya saldo kas tetap benar.
            $totalDibayar = Angka::bulat($nota->paid_amount);
            if ($totalDibayar > 0) {
                CashTransaction::create([
                    'type' => 'in',
                    'category' => 'pembelian',
                    'amount' => $totalDibayar,
                    'note' => 'Pembatalan pembelian ' . $nota->purchase_no,
                    'user_id' => $request->user()->id,
                ]);
            }

            $nota->delete();
        });

        $catalog->flush();

        return back()->with('success', "Pembelian {$nomor} dibatalkan, stok dan kas sudah dikembalikan.");
    }

    /**
     * Satuan yang dipakai membeli: satuan dasar produk, atau salah satu satuan tambahannya.
     * Toko biasanya kulakan per Dus walau menjual per Pcs.
     */
    private function satuanPembelian(Product $product, string $unitType): array
    {
        if ($unitType === 'base' || $unitType === '') {
            return ['conversion' => 1.0, 'label' => $product->unit];
        }

        $productUnit = $product->units->firstWhere('id', (int) str_replace('unit_', '', $unitType));

        if (! $productUnit) {
            abort(422, "Satuan pembelian untuk {$product->name} tidak ditemukan atau sudah dihapus.");
        }

        return ['conversion' => (float) $productUnit->conversion, 'label' => $productUnit->unit->name];
    }

    /**
     * Memperbarui harga modal produk dari harga beli nota ini.
     *
     * Biaya kirim dibagi ke tiap barang SEBANDING NILAINYA, bukan rata per baris: mengirim
     * satu dus barang mahal dan satu dus barang murah memang menghabiskan ongkos yang sama,
     * tapi membebankan ongkir yang sama ke keduanya membuat margin barang murah terlihat
     * jauh lebih buruk daripada kenyataannya.
     */
    private function perbaruiHargaModal(array $baris, float $subtotalNota, float $biayaLain, float $qtySatuanDasar): void
    {
        if ($qtySatuanDasar <= 0) {
            return;
        }

        $porsiBiaya = $subtotalNota > 0
            ? Angka::bulat($biayaLain * ($baris['subtotal'] / $subtotalNota))
            : 0.0;

        $modalPerSatuanDasar = round(($baris['subtotal'] + $porsiBiaya) / $qtySatuanDasar, 2);

        $baris['product']->cost_price = $modalPerSatuanDasar;
        $baris['product']->save();
    }

    /**
     * Kas keluar untuk pembelian barang.
     *
     * Kategorinya sengaja `pembelian`, yang TIDAK termasuk beban di laporan laba rugi.
     * Uangnya belum jadi biaya - ia berubah wujud menjadi persediaan di rak, dan baru
     * menjadi beban saat barangnya terjual, dihitung di sana sebagai HPP.
     */
    private function catatKasKeluar(Purchase $purchase, float $jumlah, int $userId, string $catatan): void
    {
        CashTransaction::create([
            'type' => 'out',
            'category' => 'pembelian',
            'amount' => $jumlah,
            'note' => $catatan . ($purchase->supplier ? ' - ' . $purchase->supplier->name : ''),
            'user_id' => $userId,
        ]);
    }
}
