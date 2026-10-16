<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Setting;
use App\Support\ProductCatalog;
use App\Support\Struk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $promotions = Promotion::withCount('items')
            ->with(['items.product'])
            ->when($request->q, fn ($q) => $q->where('name', 'like', "%{$request->q}%"))
            ->when($request->status, function ($q, $status) {
                if ($status === 'aktif') {
                    $q->active();
                } elseif ($status === 'nonaktif') {
                    $q->where('is_active', false);
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('promo.index', compact('promotions'));
    }

    public function create()
    {
        $products = Product::where('is_active', true)->orderBy('name')->get();
        return view('promo.create', compact('products'));
    }

    public function store(Request $request, ProductCatalog $catalog)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.promo_price' => ['required', 'numeric', 'min:0'],
            'items.*.promo_barcode' => ['nullable', 'string', 'max:100', 'distinct', 'unique:promotion_items,promo_barcode'],
        ], [
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dipilih lebih dari satu kali dalam satu promo.',
            'items.*.promo_barcode.distinct' => 'Barcode promo tidak boleh kembar di dalam satu promo.',
            'items.*.promo_barcode.unique' => 'Barcode promo tersebut sudah digunakan pada item promo lain.',
        ]);

        $promo = DB::transaction(function () use ($data, $request) {
            $promotion = Promotion::create([
                'name' => $data['name'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $barcode = trim((string) ($item['promo_barcode'] ?? ''));
                if ($barcode === '') {
                    $barcode = PromotionItem::generatePromoBarcode($promotion->id, (int) $item['product_id']);
                }

                PromotionItem::create([
                    'promotion_id' => $promotion->id,
                    'product_id' => (int) $item['product_id'],
                    'promo_price' => (float) $item['promo_price'],
                    'promo_barcode' => $barcode,
                ]);
            }

            return $promotion;
        });

        $catalog->flush();

        return redirect()->route('promo.index')->with('success', "Promo '{$promo->name}' berhasil dibuat.");
    }

    public function edit(Promotion $promotion)
    {
        $promotion->load(['items.product']);
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('promo.edit', compact('promotion', 'products'));
    }

    public function update(Request $request, Promotion $promotion, ProductCatalog $catalog)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.promo_price' => ['required', 'numeric', 'min:0'],
            'items.*.promo_barcode' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
                \Illuminate\Validation\Rule::unique('promotion_items', 'promo_barcode')
                    ->where(fn ($q) => $q->where('promotion_id', '!=', $promotion->id)),
            ],
        ], [
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dipilih lebih dari satu kali dalam satu promo.',
            'items.*.promo_barcode.distinct' => 'Barcode promo tidak boleh kembar di dalam satu promo.',
            'items.*.promo_barcode.unique' => 'Barcode promo tersebut sudah digunakan pada item promo lain.',
        ]);

        DB::transaction(function () use ($data, $request, $promotion) {
            $promotion->update([
                'name' => $data['name'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'description' => $data['description'] ?? null,
            ]);

            $promotion->items()->delete();

            foreach ($data['items'] as $item) {
                $barcode = trim((string) ($item['promo_barcode'] ?? ''));
                if ($barcode === '') {
                    $barcode = PromotionItem::generatePromoBarcode($promotion->id, (int) $item['product_id']);
                }

                PromotionItem::create([
                    'promotion_id' => $promotion->id,
                    'product_id' => (int) $item['product_id'],
                    'promo_price' => (float) $item['promo_price'],
                    'promo_barcode' => $barcode,
                ]);
            }
        });

        $catalog->flush();

        return redirect()->route('promo.index')->with('success', "Promo '{$promotion->name}' berhasil diperbarui.");
    }

    public function destroy(Promotion $promotion, ProductCatalog $catalog)
    {
        $name = $promotion->name;
        $promotion->delete();
        $catalog->flush();

        return redirect()->route('promo.index')->with('success', "Promo '{$name}' berhasil dihapus.");
    }

    public function toggleActive(Promotion $promotion, ProductCatalog $catalog)
    {
        $promotion->is_active = ! $promotion->is_active;
        $promotion->save();
        $catalog->flush();

        $status = $promotion->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Promo '{$promotion->name}' berhasil {$status}.");
    }

    public function cetakBarcode(Request $request, Promotion $promotion)
    {
        $promotion->load(['items.product']);
        $qty = max(1, min(50, (int) $request->query('qty', 1)));

        $printer = Setting::get('printer_barcode', [
            'mode' => 'label',
            'paper_width' => 58,
            'label_width' => 40,
            'label_height' => 30,
        ]);

        $modeRoll = ($printer['mode'] ?? 'label') === 'roll';
        $lebarCetak = (int) ($printer['paper_width'] ?? 58);
        $lebarLabel = (int) ($printer['label_width'] ?? 40);
        $tinggiLabel = (int) ($printer['label_height'] ?? 30);

        $labels = $promotion->items->map(function ($item) {
            $product = $item->product;
            $sellPrice = (float) $product->sell_price;
            $promoPrice = (float) $item->promo_price;
            $hemat = max(0, $sellPrice - $promoPrice);

            return [
                'product' => $product,
                'name' => $product->name,
                'sell_price' => $sellPrice,
                'promo_price' => $promoPrice,
                'hemat' => $hemat,
                'barcode' => $item->promo_barcode ?: PromotionItem::generatePromoBarcode($item->promotion_id, $item->product_id),
            ];
        });

        return view('promo.cetak-barcode', compact(
            'promotion', 'labels', 'qty', 'modeRoll', 'lebarCetak', 'lebarLabel', 'tinggiLabel'
        ));
    }
}
