<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesUnitPricing;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Support\Angka;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasirController extends Controller
{
    use ResolvesUnitPricing;

    public function index(Request $request, ProductCatalog $catalog)
    {
        // Pencarian & filter kategori di halaman ini dikerjakan sepenuhnya di sisi klien
        // (lihat allProducts.filter() di view), jadi jalur normal selalu memuat katalog
        // penuh dan hasilnya bisa di-cache. Parameter q/category_id tetap dilayani lewat
        // query langsung supaya perilaku lama tidak berubah sedikit pun bagi siapa pun
        // yang mengetik URL-nya manual.
        $productsForCart = $request->filled('q') || $request->filled('category_id')
            ? $this->filteredCatalog($request)
            : $catalog->forCart();

        $categories = Category::orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        $tax = Setting::get('tax', ['enabled' => false, 'percent' => 0, 'include_in_price' => false]);
        ['view' => $defaultView, 'toggle' => $allowToggle] = Setting::kasirDisplayMode();

        // Keranjang yang baru diambil dari tahanan, dititipkan lewat sesi oleh ambilTahan().
        // Lewat sesi, bukan lewat URL: isi keranjang bisa panjang, dan URL yang memuat
        // seluruh belanjaan pelanggan akan tersimpan di riwayat peramban komputer kasir.
        $keranjangDiambil = session('kasir_keranjang_diambil');
        $jumlahTertahan = Sale::tertahan()->count();

        // Pilihan dokumen cetak (Struk / Invoice / Keduanya). Dibaca di sini, bukan di
        // dalam Blade, supaya halaman kasir tidak menambah query saat sedang melayani.
        $templateStruk = Setting::get('template_struk', []);
        $pilihDokumen = (bool) ($templateStruk['pilih_dokumen'] ?? false);
        // Disaring, bukan dipakai apa adanya: nilainya berakhir di dalam <script> halaman
        // kasir, dan baris pengaturan yang pernah disunting tangan tidak boleh bisa
        // menuliskan apa pun ke sana.
        $dokumenDefault = in_array($templateStruk['dokumen_default'] ?? null, ['struk', 'invoice', 'keduanya'], true)
            ? $templateStruk['dokumen_default']
            : 'struk';

        return view('transaksi.kasir.index', compact(
            'categories', 'customers', 'tax', 'productsForCart', 'defaultView', 'allowToggle',
            'keranjangDiambil', 'jumlahTertahan', 'pilihDokumen', 'dokumenDefault'
        ));
    }

    private function filteredCatalog(Request $request): array
    {
        return Product::with('units.unit')->where('is_active', true)
            ->when($request->q, fn($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->q}%")
                    ->orWhere('barcode', 'like', "%{$request->q}%")
                    ->orWhere('sku', 'like', "%{$request->q}%");
            }))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name')
            ->get()
            ->map(fn($p) => $p->toCartArray())
            ->values()
            ->all();
    }

    /**
     * Cari barang dari hasil pindaian.
     *
     * URUTAN PENCARIANNYA DISENGAJA, dari yang paling spesifik ke yang paling umum:
     *
     *   1. barcode SATUAN  -> langsung tahu satuannya, kasir tidak perlu memilih apa pun
     *   2. barcode produk  -> satuan dasar
     *   3. SKU produk      -> label yang dicetak tanpa barcode manual memakai SKU sebagai kode
     *
     * Langkah 1 yang baru. Sebelumnya memindai label "Karung" dan label "Kg" menghasilkan
     * hal yang persis sama, dan kasir tetap harus memilih satuannya sendiri - langkah yang
     * paling sering terlewat pada jam ramai, dan akibatnya barang seharga Rp 250.000 per
     * karung tercatat terjual seharga per kilo.
     *
     * Balasannya juga berubah: yang tidak ketemu sekarang membawa `message`. Sebelumnya
     * balasannya cuma `{found: false}` dan sisi kasir tidak menampilkan apa pun sama sekali -
     * kasir memindai berulang kali tanpa satu pun petunjuk, lalu menyimpulkan "pindai tidak
     * bisa". Kegagalan yang diam adalah kegagalan yang tidak bisa diperbaiki siapa pun.
     */
    public function findByBarcode(Request $request)
    {
        $kode = trim((string) $request->query('barcode', ''));

        if ($kode === '') {
            return response()->json(['found' => false, 'message' => 'Kode pindaian kosong.']);
        }

        // 1. Barcode milik satuan tertentu.
        $satuan = ProductUnit::with(['product', 'unit'])
            ->where('barcode', $kode)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->first();

        if ($satuan) {
            return response()->json([
                'found' => true,
                'product' => $satuan->product->toCartArray(),
                // Dipakai sisi kasir untuk langsung memilih satuannya di baris keranjang.
                'unit_id' => $satuan->id,
                'unit_name' => $satuan->unit->name,
            ]);
        }

        // 2 & 3. Barcode atau SKU produk.
        $product = Product::where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $kode)->orWhere('sku', $kode))
            ->first();

        if (! $product) {
            return response()->json([
                'found' => false,
                'message' => 'Kode ' . $kode . ' tidak terdaftar. Cari barangnya lewat nama, '
                    . 'atau daftarkan kodenya di Master Produk.',
            ]);
        }

        return response()->json([
            'found' => true,
            'product' => $product->toCartArray(),
            'unit_id' => null,
            'unit_name' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            // numeric, bukan integer: satuan timbangan boleh dijual pecahan (mis. 2,5 Kg).
            // Boleh-tidaknya pecahan tergantung satuan yang dipilih per baris, jadi tidak
            // bisa ditentukan di sini - diperiksa lagi di dalam transaksi di bawah.
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_type' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string'],
            'is_waiting_list' => ['nullable', 'boolean'],
            'is_parked' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        // Transaksi tertahan adalah waiting list juga - mesinnya sama persis. Yang
        // membedakan cuma niatnya, dan niat itu disimpan di kolomnya sendiri supaya
        // seluruh pembukuan yang sudah benar tidak perlu disentuh. Lihat migrasi 000340.
        $isParked = $request->boolean('is_parked');
        $isWaitingList = $isParked || $request->boolean('is_waiting_list');

        $sale = DB::transaction(function () use ($data, $request, $isWaitingList, $isParked) {
            $subtotal = 0;
            $taxable = 0;
            $lineItems = [];
            $lockedProducts = [];
            $requestedBaseUnitsByProduct = [];

            foreach ($data['items'] as $item) {
                $productId = $item['product_id'];
                if (!isset($lockedProducts[$productId])) {
                    $lockedProducts[$productId] = Product::lockForUpdate()->findOrFail($productId);
                    $requestedBaseUnitsByProduct[$productId] = 0;
                }
                $product = $lockedProducts[$productId];
                $qty = $item['qty'];

                ['conversion' => $unitConversion, 'label' => $unitLabel, 'price' => $unitPrice, 'is_weighable' => $bolehPecahan]
                    = $this->resolveUnitPricing($product, $item['unit_type'] ?? 'base', $qty);

                $this->pastikanQtyBolehPecahan($product, $qty, $bolehPecahan, $unitLabel);

                $qtyInBaseUnits = Angka::keSatuanDasar($qty, $unitConversion);

                // Produk jasa tidak punya stok fisik, jadi tidak pernah divalidasi/dipotong.
                if (!$product->isJasa()) {
                    // Diakumulasi per produk supaya 2 baris produk yang sama (mis. sebagian PCS,
                    // sebagian DUS) tetap divalidasi terhadap total stok yang sama, bukan dicek terpisah.
                    //
                    // Yang dijumlahkan WAJIB nilai pecahannya, bukan hasil pembulatan tiap baris:
                    // dua baris 400 Gram yang masing-masing dibulatkan lebih dulu terbaca 0 + 0 = 0
                    // dan lolos walau stok kosong.
                    $requestedBaseUnitsByProduct[$productId] = Angka::bulat(
                        $requestedBaseUnitsByProduct[$productId] + $qtyInBaseUnits
                    );

                    if (! Angka::cukup($requestedBaseUnitsByProduct[$productId], $product->stock)) {
                        abort(422, "Stok {$product->name} tidak cukup (tersisa " . Angka::qty($product->stock) . " {$product->unit}).");
                    }
                }

                $lineSubtotal = $unitPrice * $qty;
                $subtotal += $lineSubtotal;
                if ($product->is_taxable) {
                    $taxable += $lineSubtotal;
                }

                $lineItems[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'qty_in_base_units' => $qtyInBaseUnits,
                    'unit_label' => $unitLabel,
                    'unit_conversion' => $unitConversion,
                    'price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discount = $data['discount'] ?? 0;
            $taxSetting = Setting::get('tax', ['enabled' => false, 'percent' => 0, 'include_in_price' => false]);
            $taxPercent = ($taxSetting['enabled'] ?? false) ? ($taxSetting['percent'] ?? 0) : 0;
            $taxBase = max($taxable - ($discount * ($taxable / max($subtotal, 1))), 0);
            $taxAmount = $taxPercent > 0 ? round($taxBase * $taxPercent / 100) : 0;
            $total = $subtotal - $discount + $taxAmount;

            if ($isParked) {
                // Ditahan sebentar di meja kasir - tidak ada pembayaran dan tidak ada
                // jatuh tempo. Dipaksa di sini, bukan cuma disembunyikan di layar, supaya
                // permintaan yang datang langsung ke endpoint pun tidak bisa menyimpang.
                $data['paid_amount'] = 0;
                $data['due_date'] = null;
            } elseif ($isWaitingList) {
                // DP 0 SENGAJA DIIZINKAN: pelanggan memesan barang tanpa membayar muka dulu
                // ("titip dulu, besok saya ambil"). Barangnya tetap direservasi, piutangnya
                // sebesar total, dan pembukuannya tetap benar tanpa perlakuan khusus -
                // pesanan `waiting` memang belum jadi omset, dan uang muka Rp 0 tidak
                // menambah kewajiban apa pun di neraca.
                if ($data['paid_amount'] >= $total) {
                    abort(422, 'Jumlah DP tidak boleh sama dengan atau melebihi total. Gunakan pembayaran biasa untuk lunas penuh.');
                }
            } else {
                if ($data['paid_amount'] < $total) {
                    abort(422, 'Jumlah bayar kurang dari total belanja.');
                }
            }

            $sale = Sale::create([
                'invoice_no' => Sale::generateInvoiceNo(),
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'paid_amount' => $data['paid_amount'],
                'change_amount' => $isWaitingList ? 0 : ($data['paid_amount'] - $total),
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'order_status' => $isWaitingList ? 'waiting' : 'completed',
                'due_date' => $data['due_date'] ?? null,
                'parked_at' => $isParked ? now() : null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lineItems as $li) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $li['product']->id,
                    'product_name' => $li['product']->name,
                    'price' => $li['price'],
                    // Harga modal dibekukan di sini. Tanpa ini, memperbarui harga modal
                    // produk akan mengubah laba seluruh bulan lampau.
                    'cost_price_snapshot' => $li['product']->cost_price,
                    'qty' => $li['qty'],
                    'unit_label' => $li['unit_label'],
                    'unit_conversion' => $li['unit_conversion'],
                    'subtotal' => $li['subtotal'],
                ]);

                if (!$li['product']->isJasa()) {
                    // Stock is reserved immediately, even for waiting-list orders,
                    // so the same item can't be oversold while the order is pending.
                    // Stok selalu dikurangi dalam satuan dasar, meskipun qty di atas dalam satuan besar.
                    $stockAfter = $li['product']->ubahStok(-$li['qty_in_base_units']);

                    StockMovement::create([
                        'product_id' => $li['product']->id,
                        'type' => 'sale',
                        'qty' => -$li['qty_in_base_units'],
                        'stock_after' => $stockAfter,
                        'note' => ($isWaitingList ? 'Pesanan (DP) ' : 'Penjualan ') . $sale->invoice_no,
                        'user_id' => $request->user()->id,
                    ]);
                }
            }

            return $sale;
        });

        return response()->json(['success' => true, 'sale_id' => $sale->id, 'receipt_url' => route('kasir.receipt', $sale->id)]);
    }

    /**
     * Daftar transaksi yang sedang ditahan di meja kasir.
     *
     * Jalur ini sengaja TERPISAH dari daftar pesanan DP. Keduanya secara mesin sama
     * (`order_status = 'waiting'`), tapi yang dicari orang di keduanya berbeda jauh:
     * di sini kasir mencari keranjang pelanggan yang barusan kembali - dalam hitungan
     * menit; di sana pemilik toko memantau pesanan yang jatuh temponya berhari-hari lagi.
     * Mencampur keduanya membuat masing-masing sulit ditemukan.
     */
    public function tahanList()
    {
        $tertahan = Sale::tertahan()->with(['items', 'cashier'])
            ->orderByDesc('parked_at')
            ->get();

        return view('transaksi.kasir.tahan', compact('tertahan'));
    }

    /**
     * Ambil kembali transaksi yang ditahan, lanjutkan di layar kasir.
     *
     * Dikerjakan dengan MEMBATALKAN transaksi tahanannya (stok kembali) lalu menaruh
     * isinya ke keranjang. Bukan dengan "melanjutkan" barisnya di tempat.
     *
     * Kenapa begitu: keranjang yang diambil kembali hampir selalu masih akan berubah -
     * pelanggannya kembali justru karena menambah barang. Membiarkan barisnya hidup di
     * database sementara kasir menambah-nambah di layar berarti ada dua sumber kebenaran
     * untuk satu keranjang yang sama, dan stok yang direservasi bisa tertinggal kalau
     * kasir menutup halaman di tengah jalan.
     *
     * Dengan cara ini, keranjang tahanan selalu bersih setelah diambil: stoknya sudah
     * dikembalikan, dan yang tersisa cuma isi keranjang di layar - persis seperti kasir
     * memasukkannya ulang satu per satu, tanpa harus mengetik ulang.
     */
    public function ambilTahan(Request $request, Sale $sale)
    {
        if ($sale->order_status !== 'waiting' || $sale->parked_at === null) {
            return redirect()->route('kasir.tahan')->with('error', 'Transaksi ini sudah tidak tertahan.');
        }

        $keranjang = $sale->items->whereNotNull('product_id')->map(fn ($item) => [
            'product_id' => $item->product_id,
            'qty' => (float) $item->qty,
            'unit_conversion' => (float) $item->unit_conversion,
            'unit_label' => $item->unit_label,
        ])->values()->all();

        // cancelWaiting() yang sudah ada: mengunci baris, memeriksa ulang status di dalam
        // transaksi, mengembalikan stok, dan mencatat StockMovement-nya (H2, H3, H4).
        // Tidak ada jalur pengembalian stok kedua yang perlu dijaga.
        $this->cancelWaiting($sale);

        return redirect()->route('kasir.index')
            ->with('kasir_keranjang_diambil', $keranjang)
            ->with('success', 'Transaksi ' . $sale->invoice_no . ' diambil kembali. Silakan lanjutkan.');
    }

    public function waitingList(Request $request)
    {
        $status = $request->get('status', 'waiting');

        // Transaksi tertahan sengaja TIDAK tampil di sini: ia punya jalur sendiri
        // (kasir.tahan). Mencampurnya membuat daftar pesanan penuh keranjang yang umurnya
        // cuma beberapa menit, dan pemilik toko kehilangan gambaran pesanan sungguhan.
        $orders = Sale::with(['items', 'customer', 'cashier'])
            ->where('order_status', $status)
            ->whereNull('parked_at')
            ->when($request->q, fn($q) => $q->where('invoice_no', 'like', "%{$request->q}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $waitingCount = Sale::pesanan()->count();

        return view('transaksi.kasir.waiting-list', compact('orders', 'status', 'waitingCount'));
    }

    public function payWaiting(Request $request, Sale $sale)
    {
        if ($sale->order_status !== 'waiting') {
            return back()->with('error', 'Pesanan ini bukan waiting list yang aktif.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Dikunci & diperiksa ulang dalam transaksi supaya dua klik "Lunasi" yang hampir
        // bersamaan tidak menambah pembayaran dua kali ke pesanan yang sama.
        $sale = DB::transaction(function () use ($sale, $data) {
            $sale = Sale::lockForUpdate()->find($sale->id);
            if (! $sale || $sale->order_status !== 'waiting') {
                return null;
            }

            $sale->paid_amount += $data['amount'];

            if ($sale->paid_amount >= $sale->total) {
                $sale->change_amount = $sale->paid_amount - $sale->total;
                $sale->order_status = 'completed';
            }

            $sale->save();

            return $sale;
        });

        if (! $sale) {
            return back()->with('error', 'Pesanan ini bukan waiting list yang aktif.');
        }

        return back()->with('success', $sale->order_status === 'completed'
            ? "Pesanan {$sale->invoice_no} telah lunas dan selesai."
            : "Pembayaran tambahan untuk {$sale->invoice_no} berhasil disimpan.");
    }

    public function cancelWaiting(Sale $sale)
    {
        if ($sale->order_status !== 'waiting') {
            return back()->with('error', 'Pesanan ini tidak bisa dibatalkan.');
        }

        DB::transaction(function () use ($sale) {
            // Status dikunci & diperiksa ULANG di dalam transaksi. Tanpa ini, dua klik
            // "Batalkan" yang hampir bersamaan bisa sama-sama lolos pemeriksaan di atas dan
            // mengembalikan stok DUA KALI untuk pesanan yang sama.
            $sale = Sale::lockForUpdate()->find($sale->id);
            if (! $sale || $sale->order_status !== 'waiting') {
                return;
            }

            foreach ($sale->items as $item) {
                if ($item->product_id) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product && !$product->isJasa()) {
                        // qty tersimpan dalam satuan jual aslinya (mis. dus); dikonversi ke satuan
                        // dasar (mis. pcs) dulu sebelum menambah stok.
                        $baseUnits = Angka::keSatuanDasar($item->qty, $item->unit_conversion);
                        $stockAfter = $product->ubahStok($baseUnits);
                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'return',
                            'qty' => $baseUnits,
                            'stock_after' => $stockAfter,
                            'note' => 'Pembatalan pesanan ' . $sale->invoice_no,
                            'user_id' => auth()->id(),
                        ]);
                    }
                }
            }

            $sale->order_status = 'cancelled';
            $sale->save();
        });

        return back()->with('success', "Pesanan {$sale->invoice_no} dibatalkan, stok telah dikembalikan.");
    }

    /**
     * Menghapus pesanan/transaksi secara PERMANEN dari database.
     *
     * Berbeda dari cancelWaiting() dan cancelSale() yang hanya mengubah status: baris Sale
     * benar-benar dibuang, dan sale_items serta sale_returns yang menempel ikut terhapus
     * lewat cascade. Dipakai untuk membersihkan catatan salah input yang tidak boleh ikut
     * muncul di laporan penjualan.
     *
     * Stok yang masih "keluar" dikembalikan lebih dulu, kalau tidak menghapus baris ini
     * diam-diam menghilangkan stok dari pembukuan. Pesanan yang sudah berstatus 'cancelled'
     * dilewati karena stoknya sudah dikembalikan saat dibatalkan.
     */
    public function destroy(Request $request, Sale $sale)
    {
        // Menghapus pesanan yang masih menunggu atau sudah dibatalkan adalah pekerjaan
        // kasir sehari-hari - salah input, pelanggan batal. Tapi transaksi yang SUDAH LUNAS
        // sudah masuk Laporan Penjualan, dan menghapusnya mengubah angka omzet secara
        // permanen. Itu keputusan pemilik toko, bukan keputusan kasir yang sedang melayani
        // antrean, jadi di sini diminta izin yang sama dengan membuka laporan.
        if ($sale->order_status === 'completed' && ! $request->user()->can_access('laporan')) {
            return back()->with('error', 'Transaksi yang sudah lunas hanya bisa dihapus oleh pengguna yang punya akses Laporan. Gunakan "Batalkan" kalau barangnya dikembalikan.');
        }

        $nomorNota = $sale->invoice_no;

        DB::transaction(function () use ($sale, $request) {
            // Dikunci & dibaca ULANG di dalam transaksi, mengikuti pola cancelWaiting().
            // Tanpa ini, dua klik "Hapus" yang hampir bersamaan bisa sama-sama masuk dan
            // mengembalikan stok DUA KALI sebelum barisnya benar-benar terhapus.
            $sale = Sale::lockForUpdate()->with('items')->find($sale->id);
            if (! $sale) {
                return;
            }

            if ($sale->order_status !== 'cancelled') {
                foreach ($sale->items as $item) {
                    $sisaQty = Angka::bulat($item->qty - $item->returned_qty);

                    if ($sisaQty <= 0 || ! $item->product_id) {
                        continue;
                    }

                    $product = Product::lockForUpdate()->find($item->product_id);
                    if (! $product || $product->isJasa()) {
                        continue;
                    }

                    $baseUnits = Angka::keSatuanDasar($sisaQty, $item->unit_conversion);
                    $stockAfter = $product->ubahStok($baseUnits);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'return',
                        'qty' => $baseUnits,
                        'stock_after' => $stockAfter,
                        'note' => 'Hapus ' . ($sale->order_status === 'waiting' ? 'pesanan' : 'transaksi') . ' ' . $sale->invoice_no,
                        'user_id' => $request->user()->id,
                    ]);
                }
            }

            $sale->delete();
        });

        return back()->with('success', "{$nomorNota} berhasil dihapus permanen.");
    }

    public function editWaiting(Sale $sale)
    {
        if ($sale->order_status !== 'waiting') {
            return redirect()->route('kasir.waiting-list')->with('error', 'Pesanan ini tidak bisa diedit.');
        }

        $sale->load('items');

        $products = Product::with('units.unit')->where('is_active', true)->orderBy('name')->get();
        $productsById = $products->keyBy('id');
        // Dibaca sekali untuk seluruh katalog, bukan sekali per produk.
        $petaTimbangan = Unit::pluck('is_weighable', 'name')->map(fn ($v) => (bool) $v)->all();
        $productsForCart = $products->map(fn($p) => $p->toCartArray($petaTimbangan))->values();

        // Total satuan dasar yang sudah "dipesan" oleh order ini per produk, supaya batas stok
        // saat mengedit bukan cuma stok yang tersisa sekarang, tapi + apa yang sudah direservasi
        // order ini (karena stok itu akan direstore dulu sebelum item baru divalidasi/dipotong).
        $initialReserved = [];
        foreach ($sale->items as $item) {
            if ($item->product_id) {
                $baseUnits = Angka::keSatuanDasar($item->qty, $item->unit_conversion);
                $initialReserved[$item->product_id] = ($initialReserved[$item->product_id] ?? 0) + $baseUnits;
            }
        }

        $initialCart = $sale->items->whereNotNull('product_id')->map(function ($item) use ($productsById, $petaTimbangan) {
            $product = $productsById->get($item->product_id);
            $matchedUnit = null;
            if ($product && $item->unit_label) {
                $matchedUnit = $product->units->first(fn($pu) => strcasecmp($pu->unit->name, $item->unit_label) === 0);
            }

            return [
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'product_type' => $product->type ?? 'barang',
                'image_url' => $product?->image_url ?? asset('images/no-image.svg'),
                'unit_type' => $matchedUnit ? 'unit_' . $matchedUnit->id : 'base',
                'unit_label' => $item->unit_label,
                'conversion' => (float) $item->unit_conversion,
                // Satuan tambahan menentukan izin pecahan per produk; satuan dasar
                // mengambilnya dari tabel satuan.
                'is_weighable' => $matchedUnit
                    ? (bool) $matchedUnit->allow_decimal
                    : ($product ? ($petaTimbangan[$product->unit] ?? false) : false),
                'qty' => (float) $item->qty,
                'price' => (float) $item->price,
                'wholesale_price' => $matchedUnit
                    ? ($matchedUnit->wholesale_price !== null ? (float) $matchedUnit->wholesale_price : null)
                    : ($product && $product->wholesale_price !== null ? (float) $product->wholesale_price : null),
                'wholesale_min_qty' => $matchedUnit ? $matchedUnit->wholesale_min_qty : ($product->wholesale_min_qty ?? null),
            ];
        })->values();

        $orphanedItems = $sale->items->whereNull('product_id')->values();
        $tax = Setting::get('tax', ['enabled' => false, 'percent' => 0, 'include_in_price' => false]);
        ['view' => $defaultView, 'toggle' => $allowToggle] = Setting::kasirDisplayMode();

        return view('transaksi.kasir.waiting-list-edit', compact(
            'sale', 'productsForCart', 'initialCart', 'initialReserved', 'orphanedItems', 'tax', 'defaultView', 'allowToggle'
        ));
    }

    public function updateWaiting(Request $request, Sale $sale)
    {
        if ($sale->order_status !== 'waiting') {
            return response()->json(['message' => 'Pesanan ini tidak bisa diedit.'], 422);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            // numeric, bukan integer: satuan timbangan boleh dijual pecahan (mis. 2,5 Kg).
            // Boleh-tidaknya pecahan tergantung satuan yang dipilih per baris, jadi tidak
            // bisa ditentukan di sini - diperiksa lagi di dalam transaksi di bawah.
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_type' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $sale, $request) {
            // 1. Kembalikan stok dari item lama (yang produknya masih ada), lalu hapus baris lama.
            //    Item yang produknya sudah dihapus dari master data dibiarkan apa adanya (tidak
            //    ikut diedit/dihapus), konsisten dengan cancelWaiting() yang juga melewatkannya.
            foreach ($sale->items as $oldItem) {
                if ($oldItem->product_id) {
                    $product = Product::lockForUpdate()->find($oldItem->product_id);
                    if ($product && !$product->isJasa()) {
                        $baseUnits = Angka::keSatuanDasar($oldItem->qty, $oldItem->unit_conversion);
                        $stockAfter = $product->ubahStok($baseUnits);
                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'return',
                            'qty' => $baseUnits,
                            'stock_after' => $stockAfter,
                            'note' => 'Edit pesanan ' . $sale->invoice_no . ' (hapus item lama)',
                            'user_id' => $request->user()->id,
                        ]);
                    }
                }
            }
            $sale->items()->whereNotNull('product_id')->delete();

            // 2. Buat ulang baris item dari daftar baru, memakai logika yang sama seperti store().
            $subtotal = 0;
            $taxable = 0;
            $lockedProducts = [];
            $requestedBaseUnitsByProduct = [];

            foreach ($data['items'] as $item) {
                $productId = $item['product_id'];
                if (!isset($lockedProducts[$productId])) {
                    $lockedProducts[$productId] = Product::lockForUpdate()->findOrFail($productId);
                    $requestedBaseUnitsByProduct[$productId] = 0;
                }
                $product = $lockedProducts[$productId];
                $qty = $item['qty'];

                ['conversion' => $unitConversion, 'label' => $unitLabel, 'price' => $unitPrice, 'is_weighable' => $bolehPecahan]
                    = $this->resolveUnitPricing($product, $item['unit_type'] ?? 'base', $qty);

                $this->pastikanQtyBolehPecahan($product, $qty, $bolehPecahan, $unitLabel);

                $qtyInBaseUnits = Angka::keSatuanDasar($qty, $unitConversion);

                if (!$product->isJasa()) {
                    $requestedBaseUnitsByProduct[$productId] = Angka::bulat(
                        $requestedBaseUnitsByProduct[$productId] + $qtyInBaseUnits
                    );

                    if (! Angka::cukup($requestedBaseUnitsByProduct[$productId], $product->stock)) {
                        abort(422, "Stok {$product->name} tidak cukup (tersisa " . Angka::qty($product->stock) . " {$product->unit}).");
                    }
                }

                $lineSubtotal = $unitPrice * $qty;
                $subtotal += $lineSubtotal;
                if ($product->is_taxable) {
                    $taxable += $lineSubtotal;
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
                        'note' => 'Edit pesanan (DP) ' . $sale->invoice_no,
                        'user_id' => $request->user()->id,
                    ]);
                }
            }

            // Item yang produknya sudah terhapus tetap ikut dihitung ke subtotal (harga dibekukan),
            // tapi tidak ikut basis pajak karena status kena-pajaknya sudah tidak diketahui lagi.
            $orphanedSubtotal = (float) $sale->items()->whereNull('product_id')->sum('subtotal');
            $subtotal += $orphanedSubtotal;

            $discount = $data['discount'] ?? (float) $sale->discount;
            $taxSetting = Setting::get('tax', ['enabled' => false, 'percent' => 0, 'include_in_price' => false]);
            $taxPercent = ($taxSetting['enabled'] ?? false) ? ($taxSetting['percent'] ?? 0) : 0;
            $taxBase = max($taxable - ($discount * ($taxable / max($subtotal, 1))), 0);
            $taxAmount = $taxPercent > 0 ? round($taxBase * $taxPercent / 100) : 0;
            $total = $subtotal - $discount + $taxAmount;

            $sale->subtotal = $subtotal;
            $sale->discount = $discount;
            $sale->tax_amount = $taxAmount;
            $sale->total = $total;

            // Kalau DP yang sudah dibayar ternyata sudah cukup/lebih dari total baru, otomatis lunas
            // (mengikuti perilaku yang sama seperti pelunasan DP bertahap di payWaiting()).
            if ($sale->paid_amount >= $total) {
                $sale->change_amount = $sale->paid_amount - $total;
                $sale->order_status = 'completed';
            } else {
                $sale->change_amount = 0;
            }

            $sale->save();
        });

        return response()->json(['success' => true, 'redirect_url' => route('kasir.waiting-list')]);
    }

    /**
     * Halaman struk / invoice satu transaksi.
     *
     * Bentuknya bisa ditentukan lewat `?dokumen=struk` atau `?dokumen=invoice`. Tanpa
     * parameter itu, yang dipakai adalah layout yang tersimpan di Pengaturan - jadi setiap
     * tautan struk yang sudah ada di aplikasi ini berperilaku persis seperti sebelumnya.
     *
     * Parameternya sengaja ditaruh di URL, bukan di sesi: satu transaksi bisa perlu dicetak
     * dua kali dalam bentuk berbeda (struk untuk pelanggan, invoice untuk arsip toko), dan
     * dua jendela yang terbuka bersamaan tidak boleh saling menimpa pilihan yang lain.
     */
    public function receipt(Request $request, Sale $sale)
    {
        $sale->load(['items', 'customer', 'cashier']);
        $storeProfile = Setting::get('store_profile', []);
        $templateStruk = Setting::get('template_struk', []);
        $printerStruk = Setting::get('printer_struk', ['paper_size' => 80, 'margin' => 0, 'font_size' => 12]);

        // Lebar yang benar-benar bisa dicetak - bukan lebar kertas. Lihat App\Support\Struk.
        $lebarCetak = \App\Support\Struk::lebarCetak($printerStruk);
        $layout = $this->layoutDokumen($templateStruk, $request->query('dokumen'));

        // Tata letak dan ukuran fontnya diputuskan DI SINI, bukan di tengah Blade: blok
        // <style> struk dibangun dari nilai-nilai ini, jadi kalau ditentukan belakangan,
        // CSS-nya sudah terlanjur ditulis untuk tata letak yang lain.
        $lebarIsi = max(1.0, $lebarCetak - (2 * (float) ($printerStruk['margin'] ?? 0)));
        $karakterAngka = (int) ($sale->items->flatMap(fn ($item) => [
            strlen(number_format($item->price, 0, ',', '.')),
            strlen(number_format($item->subtotal, 0, ',', '.')),
        ])->push(strlen(number_format($sale->total, 0, ',', '.')))->max() ?: 5);

        $fontNota = \App\Support\Struk::fontNota($lebarIsi, $karakterAngka);
        $notaTerlaluSempit = false;

        if ($layout === 'tabel' && ! \App\Support\Struk::muatNota($lebarIsi, $fontNota, $karakterAngka)) {
            // Tabel 4 kolom pada dasarnya tata letak kertas 80mm. Memaksakannya di kertas
            // sempit menghasilkan struk yang tiap barisnya membungkus tiga kali - secara
            // teknis muat, tapi tidak ada pelanggan yang bisa membacanya. Lebih baik
            // mengalah ke tata letak bertumpuk yang memang dirancang untuk kertas sempit.
            $layout = 'simple';
            $notaTerlaluSempit = true;
        }

        // Halaman struk sering diubah lewat Pengaturan > Template Struk; cegah browser
        // menyimpan versi lama di cache supaya perubahan template selalu langsung terlihat.
        return response()
            ->view('transaksi.kasir.receipt', compact(
                'sale', 'storeProfile', 'templateStruk', 'printerStruk',
                'lebarCetak', 'lebarIsi', 'layout', 'fontNota', 'karakterAngka', 'notaTerlaluSempit'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Bentuk dokumen mana yang dipakai: yang tersimpan di Pengaturan, atau yang diminta URL.
     *
     * Satu kasus perlu penjelasan. Toko boleh memilih "Invoice (Dot Matrix)" sebagai bentuk
     * struknya - itu sah dan sudah berjalan sejak 2.1.0. Tapi begitu pilihan dokumen
     * dinyalakan, tombol "Struk" dan "Invoice" di meja kasir akan mencetak berkas yang sama
     * persis, dan kasir tidak punya cara menebak kenapa. Karena itu permintaan `struk` yang
     * jatuh ke layout invoice dialihkan ke "Tabel (Nota)" - bentuk termal paling lengkap,
     * yang isinya paling mendekati invoice tanpa memakai kertas continuous form.
     */
    private function layoutDokumen(array $templateStruk, ?string $dokumen): string
    {
        $tersimpan = $templateStruk['layout'] ?? 'simple';

        if ($dokumen === 'invoice') {
            return 'invoice';
        }

        if ($dokumen === 'struk') {
            return $tersimpan === 'invoice' ? 'tabel' : $tersimpan;
        }

        return $tersimpan;
    }
}
