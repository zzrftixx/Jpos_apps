<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesUnitPricing;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Support\Angka;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    use ResolvesUnitPricing;

    public function index(Request $request, ProductCatalog $catalog)
    {
        $returns = SaleReturn::with(['sale', 'items'])
            ->when($request->q, fn($q) => $q->where('return_no', 'like', "%{$request->q}%"))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $productsForCart = $catalog->forCart();

        $prefillInvoice = $request->get('invoice_no', '');
        ['view' => $defaultView, 'toggle' => $allowToggle] = Setting::kasirDisplayMode();

        return view('transaksi.retur.index', compact('returns', 'productsForCart', 'prefillInvoice', 'defaultView', 'allowToggle'));
    }

    public function findSale(Request $request)
    {
        $sale = Sale::with(['items' => function ($q) {
            $q->whereColumn('returned_qty', '<', 'qty');
        }])->where('invoice_no', $request->invoice_no)
            ->where('order_status', 'completed')
            ->first();

        if (!$sale) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'sale' => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'total' => (float) $sale->total,
                'paid_amount' => (float) $sale->paid_amount,
                'created_at' => $sale->created_at->format('d/m/Y H:i'),
                'items' => $sale->items->map(fn($i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'product_name' => $i->product_name,
                    'price' => (float) $i->price,
                    'qty' => $i->qty,
                    'unit_label' => $i->unit_label,
                    'returned_qty' => $i->returned_qty,
                    'available' => $i->qty - $i->returned_qty,
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'reason' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.sale_item_id' => ['required_with:items', 'exists:sale_items,id'],
            // numeric, bukan integer: satuan timbangan boleh dijual pecahan (mis. 2,5 Kg).
            // Boleh-tidaknya pecahan tergantung satuan yang dipilih per baris, jadi tidak
            // bisa ditentukan di sini - diperiksa lagi di dalam transaksi di bawah.
            'items.*.qty' => ['required_with:items', 'numeric', 'min:0.001'],
            'add_items' => ['nullable', 'array'],
            'add_items.*.product_id' => ['required_with:add_items', 'exists:products,id'],
            'add_items.*.qty' => ['required_with:add_items', 'numeric', 'min:0.001'],
            'add_items.*.unit_type' => ['nullable', 'string'],
            'additional_payment' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (empty($data['items']) && empty($data['add_items'])) {
            abort(422, 'Tidak ada perubahan yang dikirim.');
        }

        $result = DB::transaction(function () use ($data, $request) {
            $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);
            if ($sale->order_status !== 'completed') {
                abort(422, 'Transaksi ini tidak bisa diedit.');
            }

            $return = null;

            if (!empty($data['items'])) {
                $returnTotal = 0;
                $return = SaleReturn::create([
                    'return_no' => SaleReturn::generateReturnNo(),
                    'sale_id' => $sale->id,
                    'user_id' => $request->user()->id,
                    'reason' => $data['reason'] ?? null,
                    'total' => 0,
                ]);

                foreach ($data['items'] as $item) {
                    $saleItem = SaleItem::lockForUpdate()->findOrFail($item['sale_item_id']);
                    $available = Angka::bulat($saleItem->qty - $saleItem->returned_qty);

                    // Berepsilon: meretur 0,3 Kg dari 0,3 Kg yang terjual akan ditolak kalau
                    // dibandingkan mentah, karena 0,1 + 0,2 di float tidak persis 0,3.
                    if (! Angka::cukup($item['qty'], $available)) {
                        abort(422, "Qty retur untuk {$saleItem->product_name} melebihi qty yang bisa diretur (" . Angka::qty($available) . ").");
                    }

                    $subtotal = $saleItem->price * $item['qty'];
                    $returnTotal += $subtotal;

                    SaleReturnItem::create([
                        'sale_return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'qty' => $item['qty'],
                        'price' => $saleItem->price,
                        'subtotal' => $subtotal,
                    ]);

                    // Ditulis eksplisit dan dibulatkan, bukan increment(): SQL `SET x = x + ?`
                    // menumpuk galat float setelah beberapa retur pecahan berturut-turut.
                    $saleItem->returned_qty = Angka::bulat($saleItem->returned_qty + $item['qty']);
                    $saleItem->save();

                    if ($saleItem->product_id) {
                        $product = Product::lockForUpdate()->find($saleItem->product_id);
                        if ($product && !$product->isJasa()) {
                            // Qty retur dalam satuan jual aslinya (mis. dus); dikonversi balik ke satuan
                            // dasar (mis. pcs) sebelum menambah stok, sesuai unit_conversion saat dijual.
                            $qtyInBaseUnits = Angka::keSatuanDasar($item['qty'], $saleItem->unit_conversion);
                            $stockAfter = $product->ubahStok($qtyInBaseUnits);
                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => 'return',
                                'qty' => $qtyInBaseUnits,
                                'stock_after' => $stockAfter,
                                'note' => 'Retur ' . $return->return_no,
                                'user_id' => $request->user()->id,
                            ]);
                        }
                    }
                }

                $return->update(['total' => $returnTotal]);

                $allReturned = $sale->items()->whereColumn('returned_qty', '<', 'qty')->doesntExist();
                $sale->status = $allReturned ? 'returned' : 'partial_returned';
            }

            if (!empty($data['add_items'])) {
                $addedSubtotal = 0;
                $addedTaxable = 0;

                foreach ($data['add_items'] as $item) {
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                    $qty = $item['qty'];

                    ['conversion' => $unitConversion, 'label' => $unitLabel, 'price' => $unitPrice, 'is_weighable' => $bolehPecahan]
                        = $this->resolveUnitPricing($product, $item['unit_type'] ?? 'base', $qty);

                    $this->pastikanQtyBolehPecahan($product, $qty, $bolehPecahan, $unitLabel);

                    $qtyInBaseUnits = Angka::keSatuanDasar($qty, $unitConversion);
                    if (!$product->isJasa() && ! Angka::cukup($qtyInBaseUnits, $product->stock)) {
                        abort(422, "Stok {$product->name} tidak cukup (tersisa " . Angka::qty($product->stock) . " {$product->unit}).");
                    }

                    $lineSubtotal = $unitPrice * $qty;
                    $addedSubtotal += $lineSubtotal;
                    if ($product->is_taxable) {
                        $addedTaxable += $lineSubtotal;
                    }

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'price' => $unitPrice,
                        'cost_price_snapshot' => $product->cost_price,
                        'qty' => $qty,
                        'unit_label' => $unitLabel,
                        'unit_conversion' => $unitConversion,
                        'subtotal' => $lineSubtotal,
                    ]);

                    if (!$product->isJasa()) {
                        $stockAfter = $product->ubahStok(-$qtyInBaseUnits);
                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'sale',
                            'qty' => -$qtyInBaseUnits,
                            'stock_after' => $stockAfter,
                            'note' => 'Tambah item pasca-transaksi ' . $sale->invoice_no,
                            'user_id' => $request->user()->id,
                        ]);
                    }
                }

                $taxSetting = Setting::get('tax', ['enabled' => false, 'percent' => 0, 'include_in_price' => false]);
                $taxPercent = ($taxSetting['enabled'] ?? false) ? ($taxSetting['percent'] ?? 0) : 0;
                $addedTax = $taxPercent > 0 ? round($addedTaxable * $taxPercent / 100) : 0;
                $requiredPayment = $addedSubtotal + $addedTax;

                if (($data['additional_payment'] ?? 0) < $requiredPayment) {
                    abort(422, 'Pembayaran tambahan kurang. Minimal Rp ' . number_format($requiredPayment, 0, ',', '.') . ' untuk menutup selisih item baru.');
                }

                $sale->subtotal += $addedSubtotal;
                $sale->tax_amount += $addedTax;
                $sale->total += $requiredPayment;
                $sale->paid_amount += $data['additional_payment'];
                $sale->change_amount = max($sale->paid_amount - $sale->total, 0);
            }

            $sale->save();

            return ['return_id' => $return?->id];
        });

        return response()->json(['success' => true] + $result);
    }

    public function cancelSale(Request $request, Sale $sale)
    {
        if ($sale->order_status !== 'completed') {
            return back()->with('error', 'Transaksi ini tidak bisa dibatalkan.');
        }

        DB::transaction(function () use ($sale, $request) {
            // Status dikunci & diperiksa ULANG di dalam transaksi supaya dua pembatalan yang
            // hampir bersamaan tidak mengembalikan stok dua kali.
            $sale = Sale::lockForUpdate()->find($sale->id);
            if (! $sale || $sale->order_status !== 'completed') {
                return;
            }

            foreach ($sale->items as $item) {
                // Kalau sebagian item ini sudah pernah diretur, hanya sisa yang belum diretur
                // yang perlu dikembalikan ke stok (bagian yang sudah diretur sudah dikembalikan
                // sebelumnya lewat proses retur).
                //
                // Angka::bulat WAJIB (HUKUM 1). Untuk barang timbangan, 0,3 - 0,2 dalam float
                // biner menghasilkan 0,09999999999999998 - bukan 0,1. Sisa mikro seperti itu
                // menempel ke stok dan menumpuk diam-diam setiap pembatalan.
                //
                // KasirController sudah melakukan ini sejak lama; baris ini yang tertinggal,
                // dan menjadi satu-satunya pengecualian H1 yang tersisa di jalur stok.
                $remainingQty = Angka::bulat($item->qty - $item->returned_qty);

                if ($remainingQty > 0 && $item->product_id) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product && !$product->isJasa()) {
                        $baseUnits = Angka::keSatuanDasar($remainingQty, $item->unit_conversion);
                        $stockAfter = $product->ubahStok($baseUnits);
                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'return',
                            'qty' => $baseUnits,
                            'stock_after' => $stockAfter,
                            'note' => 'Pembatalan transaksi ' . $sale->invoice_no,
                            'user_id' => $request->user()->id,
                        ]);
                    }
                }
            }

            $sale->order_status = 'cancelled';
            $sale->save();
        });

        return back()->with('success', "Transaksi {$sale->invoice_no} dibatalkan, stok telah dikembalikan.");
    }
}
