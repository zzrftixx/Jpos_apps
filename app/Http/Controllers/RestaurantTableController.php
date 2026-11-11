<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestaurantTableController extends Controller
{
    /**
     * Halaman Pengelolaan Meja & Barcode Meja.
     */
    public function index(Request $request)
    {
        RestaurantTable::seedDefaultTablesIfEmpty();

        $query = RestaurantTable::query();

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tables = $query->orderBy('sort_order')->orderBy('name')->get();

        // Hitung pesanan aktif per nomor meja
        $activeOrdersByTable = Sale::where('order_status', 'waiting')
            ->whereNotNull('table_no')
            ->select('table_no', DB::raw('count(*) as count'), DB::raw('max(id) as latest_sale_id'))
            ->groupBy('table_no')
            ->get()
            ->keyBy('table_no');

        // Statistik ringkasan
        $stats = [
            'total' => RestaurantTable::count(),
            'available' => RestaurantTable::where('status', 'available')->count(),
            'occupied' => RestaurantTable::where('status', 'occupied')->count(),
            'reserved' => RestaurantTable::where('status', 'reserved')->count(),
            'active_orders' => Sale::where('order_status', 'waiting')->whereNotNull('table_no')->count(),
        ];

        $storeProfile = Setting::getStoreProfile();

        return view('pengaturan.meja.index', compact('tables', 'activeOrdersByTable', 'stats', 'storeProfile'));
    }

    /**
     * Tambah Meja Baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:restaurant_tables,code',
            'description' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1|max:100',
            'status' => 'required|in:available,occupied,reserved',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ], [
            'name.required' => 'Nama meja wajib diisi.',
            'code.required' => 'Kode meja / barcode wajib diisi.',
            'code.unique' => 'Kode meja ini sudah digunakan oleh meja lain.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? (RestaurantTable::max('sort_order') + 1);

        $table = RestaurantTable::create($validated);

        return redirect()->route('meja.index')->with('success', "Meja '{$table->name}' (Kode: {$table->code}) berhasil ditambahkan.");
    }

    /**
     * Perbarui data Meja.
     */
    public function update(Request $request, RestaurantTable $meja)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:restaurant_tables,code,' . $meja->id,
            'description' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1|max:100',
            'status' => 'required|in:available,occupied,reserved',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ], [
            'name.required' => 'Nama meja wajib diisi.',
            'code.required' => 'Kode meja wajib diisi.',
            'code.unique' => 'Kode meja ini sudah digunakan oleh meja lain.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $meja->update($validated);

        return redirect()->route('meja.index')->with('success', "Data Meja '{$meja->name}' berhasil diperbarui.");
    }

    /**
     * Hapus Meja.
     */
    public function destroy(RestaurantTable $meja)
    {
        $hasActiveOrders = Sale::where('table_no', $meja->name)
            ->where('order_status', 'waiting')
            ->exists();

        if ($hasActiveOrders) {
            return redirect()->route('meja.index')->with('error', "Meja '{$meja->name}' tidak dapat dihapus karena masih memiliki pesanan aktif di kasir. Selesaikan atau batalkan pesanan terlebih dahulu.");
        }

        $nama = $meja->name;
        $meja->delete();

        return redirect()->route('meja.index')->with('success', "Meja '{$nama}' berhasil dihapus.");
    }

    /**
     * Pembuatan Cepat Meja (Quick Generate) Meja 1 s/d N.
     */
    public function quickGenerate(Request $request)
    {
        $request->validate([
            'prefix' => 'required|string|max:20',
            'start_number' => 'required|integer|min:1|max:500',
            'count' => 'required|integer|min:1|max:50',
            'capacity' => 'nullable|integer|min:1|max:50',
            'description' => 'nullable|string|max:100',
        ]);

        $prefix = trim($request->prefix);
        $start = (int) $request->start_number;
        $count = (int) $request->count;
        $capacity = (int) ($request->capacity ?? 4);
        $description = $request->description;

        $createdCount = 0;
        $currentMaxSort = (int) RestaurantTable::max('sort_order');

        for ($i = 0; $i < $count; $i++) {
            $num = $start + $i;
            $paddedNum = str_pad((string) $num, 2, '0', STR_PAD_LEFT);
            $name = $prefix . ' ' . $paddedNum;
            $code = 'M' . $paddedNum;

            // Jika kode bentrok, cari kode alternatif unik
            $finalCode = $code;
            $counter = 1;
            while (RestaurantTable::where('code', $finalCode)->exists()) {
                $finalCode = $code . '-' . $counter;
                $counter++;
            }

            RestaurantTable::create([
                'name' => $name,
                'code' => $finalCode,
                'capacity' => $capacity,
                'description' => $description,
                'status' => 'available',
                'is_active' => true,
                'sort_order' => $currentMaxSort + $i + 1,
            ]);

            $createdCount++;
        }

        return redirect()->route('meja.index')->with('success', "Berhasil membuat {$createdCount} meja baru secara otomatis.");
    }

    /**
     * Cetak Barcode & QR Code Meja (Format Kartu Akrilik / Stiker / Lembar A4).
     */
    public function printBarcodes(Request $request)
    {
        $ids = array_filter(explode(',', (string) $request->query('ids', '')));

        $query = RestaurantTable::query();
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('is_active', true);
        }

        $tables = $query->orderBy('sort_order')->orderBy('name')->get();

        $layout = $request->query('layout', 'tent_card'); // tent_card, sticker, sheet
        $storeProfile = Setting::getStoreProfile();

        return view('pengaturan.meja.print', compact('tables', 'layout', 'storeProfile'));
    }

    /**
     * Buka pesanan aktif meja langsung ke Modul Kasir F&B.
     */
    public function loadToCashier(RestaurantTable $meja)
    {
        // Cari pesanan waiting terbaru untuk meja ini jika ada
        $activeSale = $meja->activeSale();

        if ($activeSale) {
            $keranjang = $activeSale->items->whereNotNull('product_id')->map(fn ($item) => [
                'product_id' => $item->product_id,
                'qty' => (float) $item->qty,
                'unit_conversion' => (float) $item->unit_conversion,
                'unit_label' => $item->unit_label,
                'note' => $item->note,
            ])->values()->all();

            $discount = (float) $activeSale->discount;
            $note = $activeSale->note;
            $customerId = $activeSale->customer_id;

            // Batalkan status waiting sale sementara (stok kembali ke memori keranjang kasir)
            app(KasirController::class)->cancelWaiting($activeSale);

            return redirect()->route('kasir.index')
                ->with('kasir_keranjang_diambil', $keranjang)
                ->with('kasir_customer_id', $customerId)
                ->with('kasir_discount', $discount)
                ->with('kasir_note', $note)
                ->with('kasir_table_no', $meja->name)
                ->with('success', "Pesanan {$activeSale->invoice_no} dari {$meja->name} berhasil dimuat ke Kasir F&B.");
        }

        // Jika tidak ada pesanan aktif, cukup pindahkan pilihan meja ke meja ini
        return redirect()->route('kasir.index')
            ->with('kasir_table_no', $meja->name)
            ->with('info', "Meja aktif disetel ke {$meja->name}.");
    }

    /**
     * Buka pesanan waiting list tertentu langsung ke Modul Kasir F&B.
     */
    public function loadSaleToCashier(Sale $sale)
    {
        if ($sale->order_status !== 'waiting') {
            return redirect()->route('kasir.index')->with('error', "Pesanan {$sale->invoice_no} sudah tidak dalam status menunggu.");
        }

        $keranjang = $sale->items->whereNotNull('product_id')->map(fn ($item) => [
            'product_id' => $item->product_id,
            'qty' => (float) $item->qty,
            'unit_conversion' => (float) $item->unit_conversion,
            'unit_label' => $item->unit_label,
            'note' => $item->note,
        ])->values()->all();

        $discount = (float) $sale->discount;
        $note = $sale->note;
        $customerId = $sale->customer_id;
        $tableNo = $sale->table_no;

        // Batalkan status waiting sale sementara (stok kembali ke memori keranjang kasir)
        app(KasirController::class)->cancelWaiting($sale);

        return redirect()->route('kasir.index')
            ->with('kasir_keranjang_diambil', $keranjang)
            ->with('kasir_customer_id', $customerId)
            ->with('kasir_discount', $discount)
            ->with('kasir_note', $note)
            ->with('kasir_table_no', $tableNo)
            ->with('success', "Pesanan {$sale->invoice_no} ({$tableNo}) berhasil dimuat ke Kasir. Silakan proses.");
    }

    /**
     * API JSON: Daftar status meja & pesanan aktif (untuk notifikasi real-time & indikator di Kasir).
     */
    public function activeOrders(Request $request)
    {
        $tableSig = RestaurantTable::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'status', 'updated_at'])
            ->map(fn ($t) => $t->id . ':' . $t->status . ':' . ($t->updated_at?->timestamp ?? 0))
            ->implode('|');

        $saleSig = Sale::where('order_status', 'waiting')
            ->whereNotNull('table_no')
            ->orderBy('id')
            ->get(['id', 'table_no', 'total', 'updated_at'])
            ->map(fn ($s) => $s->id . ':' . $s->table_no . ':' . $s->total . ':' . ($s->updated_at?->timestamp ?? 0))
            ->implode('|');

        $sig = md5($tableSig . '#' . $saleSig);

        if ($request->query('sig') === $sig) {
            return response()->json([
                'success' => true,
                'unchanged' => true,
                'sig' => $sig,
            ]);
        }

        $tables = RestaurantTable::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code', 'capacity', 'status', 'description']);

        $waitingSales = Sale::where('order_status', 'waiting')
            ->whereNotNull('table_no')
            ->with(['items:id,sale_id,product_name,qty,price,subtotal,note', 'customer:id,name'])
            ->orderByDesc('id')
            ->get(['id', 'invoice_no', 'table_no', 'customer_id', 'total', 'paid_amount', 'created_at', 'note']);

        $salesByTable = $waitingSales->groupBy('table_no');

        $result = $tables->map(function ($t) use ($salesByTable) {
            $sales = $salesByTable->get($t->name, collect());
            return [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
                'capacity' => $t->capacity,
                'description' => $t->description,
                'status' => $sales->isNotEmpty() ? 'occupied' : $t->status,
                'order_count' => $sales->count(),
                'latest_invoice' => $sales->first()?->invoice_no,
                'latest_total' => (float) ($sales->first()?->total ?? 0),
                'order_url' => $t->order_url,
            ];
        });

        $tablesByName = $tables->keyBy('name');

        $recentOrders = $waitingSales->map(function ($s) use ($tablesByName) {
            $itemsSummary = $s->items->map(function ($it) {
                $str = (float)$it->qty . 'x ' . $it->product_name;
                if ($it->note) {
                    $str .= ' (' . $it->note . ')';
                }
                return $str;
            })->take(4)->implode(', ');

            if ($s->items->count() > 4) {
                $itemsSummary .= '... (+' . ($s->items->count() - 4) . ' lainnya)';
            }

            $table = $tablesByName->get($s->table_no);

            return [
                'id' => $s->id,
                'invoice_no' => $s->invoice_no,
                'table_no' => $s->table_no,
                'table_id' => $table?->id,
                'customer_name' => $s->customer?->name ?? 'Tamu ' . $s->table_no,
                'total' => (float) $s->total,
                'total_formatted' => 'Rp ' . number_format($s->total, 0, ',', '.'),
                'remaining' => (float) $s->remaining,
                'remaining_formatted' => 'Rp ' . number_format($s->remaining, 0, ',', '.'),
                'note' => $s->note,
                'items_count' => $s->items->count(),
                'items_summary' => $itemsSummary,
                'items' => $s->items->map(fn($it) => [
                    'product_name' => $it->product_name,
                    'qty' => (float)$it->qty,
                    'price' => (float)$it->price,
                    'subtotal' => (float)$it->subtotal,
                    'note' => $it->note,
                ])->values()->all(),
                'created_at' => $s->created_at->toISOString(),
                'time_ago' => $s->created_at->diffForHumans(),
                'load_url' => route('meja.buka-order', $s->id),
                'kitchen_url' => route('kasir.receipt.kitchen', $s->id),
                'receipt_url' => route('kasir.receipt', $s->id),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'sig' => $sig,
            'tables' => $result,
            'recent_orders' => $recentOrders,
            'latest_order_id' => $waitingSales->max('id') ?? 0,
            'total_waiting' => $waitingSales->count(),
        ]);
    }

    // =========================================================================
    // FITUR PEMESANAN MANDIRI OLEH PELANGGAN VIA SCAN BARCODE MEJA (SELF-ORDER)
    // =========================================================================

    /**
     * Halaman Menu Pelanggan di Meja (Diakses melalui Scan QR Code Meja di HP).
     */
    public function orderMenu(Request $request, string $code)
    {
        $table = RestaurantTable::where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        // Ambil produk aktif
        $products = Product::active()
            ->with(['category:id,name', 'units.unit'])
            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('name')->get();

        $storeProfile = Setting::getStoreProfile();
        $fnbStockMode = Setting::fnbStockMode();

        // Format produk untuk antarmuka katalog self-order
        $menuItems = $products->map(function ($p) use ($fnbStockMode) {
            $tracksStock = $p->tracksStock('fnb');
            $isAvailable = !$tracksStock || (float)$p->stock > 0;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name ?? 'Menu Lainnya',
                'price' => (float) $p->sell_price,
                'image_url' => $p->image ? ($p->image_url) : null,
                'description' => $p->description,
                'stock' => (float) $p->stock,
                'tracks_stock' => $tracksStock,
                'is_available' => $isAvailable,
                'unit' => $p->unit?->name ?? 'Porsi',
            ];
        });

        // Cek jika meja ini sudah memiliki pesanan aktif yang belum selesai di kasir
        $activeSale = $table->activeSale();

        return view('transaksi.meja.order', compact(
            'table', 'menuItems', 'categories', 'storeProfile', 'fnbStockMode', 'activeSale'
        ));
    }

    /**
     * Kirim Pesanan Pelanggan dari Meja ke Kasir.
     */
    public function submitOrder(Request $request, string $code)
    {
        $table = RestaurantTable::where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'customer_note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Keranjang pesanan masih kosong.',
            'items.min' => 'Pilih minimal satu menu untuk memesan.',
        ]);

        $customerName = trim($validated['customer_name'] ?? '');
        if ($customerName === '') {
            $customerName = 'Tamu ' . $table->name;
        }

        $customerNote = trim($validated['customer_note'] ?? '');
        $requestedItems = $validated['items'];

        $productIds = collect($requestedItems)->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Validasi stok produk jika produk melacak stok di mode FnB
        foreach ($requestedItems as $reqItem) {
            $product = $products->get($reqItem['product_id']);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu yang dipilih tidak ditemukan.',
                ], 422);
            }

            if ($product->tracksStock('fnb')) {
                if ((float)$product->stock < (float)$reqItem['qty']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok untuk menu '{$product->name}' tidak mencukupi (Tersisa: {$product->stock}). Silakan sesuaikan jumlah pesanan.",
                    ], 422);
                }
            }
        }

        // Buat Transaksi Penjualan Waiting List di Kasir
        $sale = DB::transaction(function () use ($table, $customerName, $customerNote, $requestedItems, $products) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($requestedItems as $req) {
                $product = $products->get($req['product_id']);
                $qty = (float) $req['qty'];
                $price = (float) $product->sell_price;
                $lineSubtotal = $price * $qty;
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'note' => $req['note'] ?? null,
                ];
            }

            $total = $subtotal;
            $invoiceNo = Sale::generateInvoiceNo();
            $saleNote = trim("[Self-Order {$table->name}] " . ($customerName ? "Pelanggan: {$customerName}. " : '') . $customerNote);

            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'customer_id' => null,
                'user_id' => null,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax_amount' => 0,
                'total' => $total,
                'paid_amount' => 0,
                'change_amount' => 0,
                'payment_method' => 'menunggu_kasir',
                'status' => 'pending',
                'order_status' => 'waiting',
                'is_waiting_list' => true,
                'table_no' => $table->name,
                'note' => $saleNote,
            ]);

            foreach ($lineItems as $li) {
                $prod = $li['product'];
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $prod->id,
                    'product_name' => $prod->name,
                    'note' => $li['note'] ?? null,
                    'price' => $li['price'],
                    'cost_price_snapshot' => $prod->cost_price,
                    'qty' => $li['qty'],
                    'unit_label' => $prod->unit?->name ?? 'Porsi',
                    'unit_conversion' => 1,
                    'subtotal' => $li['subtotal'],
                ]);

                // Kurangi stok jika produk ini melacak stok di FnB
                if ($prod->tracksStock('fnb')) {
                    $stockAfter = $prod->ubahStok(-$li['qty']);

                    StockMovement::create([
                        'product_id' => $prod->id,
                        'type' => 'sale',
                        'qty' => -$li['qty'],
                        'stock_after' => $stockAfter,
                        'note' => "Self-Order Meja ({$table->name}) {$sale->invoice_no}",
                        'user_id' => null,
                    ]);
                }
            }

            // Perbarui status meja menjadi terisi
            $table->update(['status' => 'occupied']);

            return $sale;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pesanan Anda berhasil dikirim ke kasir & dapur!',
            'sale_id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'table_name' => $table->name,
            'total' => (float) $sale->total,
            'redirect_url' => route('meja.order.status', ['code' => $table->code, 'sale' => $sale->id]),
        ]);
    }

    /**
     * Halaman Status Pesanan Pelanggan di Meja.
     */
    public function orderStatus(string $code, Sale $sale)
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        $sale->load(['items']);
        $storeProfile = Setting::getStoreProfile();

        return view('transaksi.meja.status', compact('table', 'sale', 'storeProfile'));
    }
}
