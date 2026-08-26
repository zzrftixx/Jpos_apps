<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OptimizesUploadedImages;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Rack;
use App\Models\RackSlot;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\Angka;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use OptimizesUploadedImages;

    public function index(Request $request)
    {
        $filtered = fn() => Product::query()
            ->when($request->q, fn($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->q}%")
                    ->orWhere('sku', 'like', "%{$request->q}%")
                    ->orWhere('barcode', 'like', "%{$request->q}%");
            }))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type));

        $products = $filtered()->with(['category', 'supplier', 'units.unit'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $summary = $filtered()
            ->selectRaw('COUNT(*) as total_produk, COALESCE(SUM(stock), 0) as total_stok, COALESCE(SUM(stock * cost_price), 0) as total_nilai_hpp, COALESCE(SUM(stock * sell_price), 0) as total_nilai_jual')
            ->first();

        $categories = Category::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $produkMode = Setting::produkMode();
        $rakTersedia = $this->rakBesertaKotakKosong();

        return view('master.produk.index', compact(
            'products', 'categories', 'suppliers', 'summary', 'units', 'produkMode', 'rakTersedia'
        ));
    }

    /**
     * Daftar rak beserta kotak yang MASIH BISA DIPILIH, untuk dropdown di form produk.
     *
     * Kotak yang sudah ditempati produk lain sengaja tidak ditawarkan - batasan UNIQUE di
     * database akan menolaknya, dan pesan galat batasan database tidak bisa dipahami siapa
     * pun. Lebih baik pilihannya memang tidak ada.
     *
     * Kotak yang sedang ditempati produk yang SEDANG DIEDIT tetap ditawarkan, kalau tidak
     * membuka form lalu menyimpan tanpa mengubah apa pun akan melepas produknya dari rak.
     *
     * Dibaca SEKALI untuk seluruh halaman - bukan sekali per produk. Halaman ini menampilkan
     * 15 produk per halaman, dan tiap barisnya punya formulir edit sendiri.
     */
    /**
     * Menempatkan produk di kotak rak yang dipilih dari form produk.
     *
     * KENAPA ADA DI SINI, padahal Planogram punya halamannya sendiri. Saat pemilik toko
     * memasukkan barang baru, dia sedang memegang barangnya dan tahu persis mau ditaruh di
     * mana. Memaksanya membuka halaman lain untuk itu berarti langkah itu ditunda - dan
     * peta rak yang setengah terisi lebih menyesatkan daripada tidak ada peta sama sekali.
     *
     * Aturannya sama persis dengan halaman Planogram, karena batasannya memang di database:
     * satu produk hanya menempati satu kotak. Memilih kotak baru MEMINDAHKAN, bukan
     * menggandakan.
     */
    private function simpanLokasiRak(Product $product, Request $request): void
    {
        $rakId = $request->input('rack_id');

        // Tidak dikirim sama sekali -> formulir ini memang tidak mengurus rak. Jangan
        // menyentuh penempatan yang sudah ada.
        if (! $request->has('rack_id')) {
            return;
        }

        RackSlot::where('product_id', $product->id)->delete();

        if (! $rakId) {
            return; // "- Tidak ditaruh di rak -"
        }

        [$row, $col] = array_pad(explode('-', (string) $request->input('rack_slot')), 2, null);

        if ($row === null || $col === null || $row === '') {
            return;
        }

        // Kotak yang ternyata sudah ditempati produk lain dilepas dulu. Tanpa ini yang
        // menolak adalah batasan UNIQUE database, dengan pesan yang tidak bisa dipahami.
        RackSlot::where('rack_id', $rakId)->where('row', (int) $row)->where('col', (int) $col)->delete();

        RackSlot::create([
            'rack_id' => (int) $rakId,
            'row' => (int) $row,
            'col' => (int) $col,
            'product_id' => $product->id,
        ]);

        app(\App\Support\ProductCatalog::class)->flush();
    }

    private function rakBesertaKotakKosong(): array
    {
        $terisi = RackSlot::select('rack_id', 'row', 'col', 'product_id')->get()
            ->groupBy('rack_id');

        return Rack::orderBy('sort_order')->orderBy('name')->get()
            ->map(function (Rack $rak) use ($terisi) {
                $isi = $terisi->get($rak->id, collect())->keyBy(fn ($s) => $s->row . '-' . $s->col);
                $kotak = [];

                for ($r = 0; $r < $rak->rows; $r++) {
                    for ($k = 0; $k < $rak->cols; $k++) {
                        $slot = $isi->get($r . '-' . $k);

                        $kotak[] = [
                            'row' => $r,
                            'col' => $k,
                            'label' => 'Baris ' . ($r + 1) . ' Kolom ' . ($k + 1),
                            'product_id' => $slot?->product_id,
                        ];
                    }
                }

                return ['id' => $rak->id, 'nama' => $rak->name, 'kotak' => $kotak];
            })
            ->values()
            ->all();
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $unitsInput = $data['units'] ?? [];
        unset($data['units']);

        $data['sku'] = $this->generateSku();

        if ($request->hasFile('image')) {
            $data['image'] = $this->saveOptimizedImage($request->file('image'), 'products');
        }

        DB::transaction(function () use ($data, $unitsInput, $request) {
            $product = Product::create($data);
            $this->syncUnits($product, $unitsInput);
            $this->simpanLokasiRak($product, $request);

            if ($product->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'qty' => $product->stock,
                    'stock_after' => $product->stock,
                    'note' => 'Stok awal produk baru',
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request, $product->id);
        $unitsInput = $data['units'] ?? [];
        unset($data['units']);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $this->saveOptimizedImage($request->file('image'), 'products');
        }

        $oldStock = $product->stock;

        DB::transaction(function () use ($data, $unitsInput, $product, $oldStock, $request) {
            $product->update($data);
            $this->syncUnits($product, $unitsInput);
            $this->simpanLokasiRak($product, $request);

            // Dibandingkan setelah dibulatkan ke skala kerja: stok pecahan yang berasal dari
            // penjualan bisa menyimpan sisa float sekecil 1e-15, dan tanpa pembulatan itu
            // terbaca sebagai "stok berubah" lalu mencatat penyesuaian palsu setiap kali
            // produk disimpan ulang tanpa diubah sama sekali.
            $selisih = Angka::bulat($data['stock'] - $oldStock);

            if ($selisih != 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'qty' => $selisih,
                    'stock_after' => Angka::bulat($data['stock']),
                    'note' => 'Penyesuaian manual stok produk',
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Menyimpan rantai satuan, sekaligus melipatnya menjadi konversi ke satuan dasar.
     *
     * Form hanya mengirim isi tiap satuan terhadap satuan SEBELUMNYA ("1 Dus = 4 Pack"),
     * karena itulah yang benar-benar diketahui pemilik toko - dia membaca angkanya dari
     * kardus, bukan menghitung sendiri bahwa satu Dus berisi 288 Pcs.
     *
     * `conversion` dihitung di sini dan tetap menjadi satu-satunya sumber kebenaran untuk
     * seluruh perhitungan hilir (Kasir, struk, laporan, mutasi stok), sehingga tidak ada
     * satu pun bagian lain aplikasi yang perlu tahu soal rantai ini.
     */
    private function syncUnits(Product $product, array $unitsInput): void
    {
        $product->units()->delete();

        // Urutan array dari form menentukan rantainya, jadi kuncinya dibuang lebih dulu:
        // form memakai kunci acak per baris supaya menghapus baris di tengah tidak membuat
        // Alpine memakai ulang state baris lain.
        $running = 1.0;

        foreach (array_values($unitsInput) as $urutan => $row) {
            $running = Angka::bulat($running * (float) $row['ratio_to_previous']);

            // Saat harga modal diturunkan dari pembelian, cost_price baris ini adalah
            // jumlah kedua angka nota - bukan sesuatu yang perlu diketik sendiri.
            $modal = $row['modal_total'] ?? null;
            $biaya = $row['biaya_lain'] ?? null;
            $hargaModalBaris = ($modal !== null || $biaya !== null)
                ? round((float) $modal + (float) $biaya, 2)
                : ($row['cost_price'] ?? null);

            $product->units()->create([
                'unit_id' => $row['unit_id'],
                // Kode satuan sendiri. Kalau diisi, memindainya di Kasir langsung memilih
                // satuan ini; kalau kosong, satuan ini ikut kode produknya seperti dulu.
                'barcode' => trim((string) ($row['barcode'] ?? '')) ?: null,
                'ratio_to_previous' => $row['ratio_to_previous'],
                'conversion' => $running,
                'sort_order' => $urutan,
                'price' => $row['price'],
                'cost_price' => $hargaModalBaris,
                'modal_total' => $modal,
                'biaya_lain' => $biaya,
                'wholesale_price' => $row['wholesale_price'] ?? null,
                'wholesale_min_qty' => $row['wholesale_min_qty'] ?? null,
                'allow_decimal' => ! empty($row['allow_decimal']),
            ]);
        }
    }

    public function destroy(Product $product)
    {
        // Produk yang masih tercantum di pesanan yang belum lunas tidak boleh dihapus.
        // Stoknya sudah direservasi untuk pesanan itu, dan menghapusnya membuat barisnya
        // jadi yatim: pesanan tetap menagih barang yang datanya sudah tidak ada, dan
        // pembatalan pesanan tidak bisa lagi mengembalikan stoknya.
        $adaPesananAktif = $product->id && \App\Models\SaleItem::where('product_id', $product->id)
            ->whereHas('sale', fn ($q) => $q->where('order_status', 'waiting'))
            ->exists();

        if ($adaPesananAktif) {
            return back()->with('error',
                "Produk \"{$product->name}\" masih dipakai pesanan yang belum lunas, jadi tidak bisa dihapus. " .
                'Selesaikan atau batalkan pesanannya dulu. Kalau hanya ingin menyembunyikan produk ini dari kasir, hilangkan centang "Aktif" lewat tombol Edit.');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * Barcode produk dan barcode satuan hidup di SATU ruang nama yang sama.
     *
     * Ini bukan pilihan gaya - itu kenyataan yang dipaksakan oleh kasir. `findByBarcode()`
     * mencari ke `product_units.barcode` lebih dulu, baru ke `products.barcode`. Jadi satu
     * kode yang sama di dua tempat berarti yang satu MENUTUPI yang lain, selamanya, tanpa
     * pesan apa pun.
     *
     * Yang dijaga di sini ada empat, dan semuanya berujung pada hal yang sama - salah harga:
     *
     *   1. Dua satuan dalam satu form memakai kode yang sama.
     *   2. Satuan memakai kode yang sama dengan produk induknya sendiri.
     *   3. Satuan memakai kode milik produk lain.
     *   4. Satuan memakai kode milik satuan produk lain.
     *
     * Nomor 4 yang paling berbahaya, dan paling tidak kelihatan: `findByBarcode()` memakai
     * `->first()`, jadi dua satuan berkode sama membuat pemindaian memilih salah satunya
     * SEMBARANG. Karung seharga Rp 250.000 dan Kg seharga Rp 12.000 bisa tertukar tanpa ada
     * yang menyadarinya sampai tutup buku.
     *
     * `products.barcode` sendiri sudah dijaga `unique:products,barcode` plus indeks unik di
     * database. Yang belum dijaga sama sekali adalah sisi satuannya - kolomnya cuma punya
     * indeks biasa, bukan indeks unik.
     */
    private function periksaBarcodeUnik(Request $request, ?int $ignoreId): void
    {
        $barisSatuan = $request->input('units', []);
        $galat = [];

        // Kode yang benar-benar diisi saja; yang kosong memang boleh berkali-kali.
        $kodeSatuan = [];
        foreach (is_array($barisSatuan) ? $barisSatuan : [] as $kunci => $baris) {
            $kode = trim((string) ($baris['barcode'] ?? ''));

            if ($kode !== '') {
                $kodeSatuan[$kunci] = $kode;
            }
        }

        if ($kodeSatuan === []) {
            return;
        }

        $barcodeProduk = trim((string) $request->input('barcode', ''));

        // (1) & (2) - diperiksa tanpa menyentuh database sama sekali.
        $sudahDipakai = [];
        foreach ($kodeSatuan as $kunci => $kode) {
            if (isset($sudahDipakai[$kode])) {
                $galat["units.{$kunci}.barcode"] = 'Barcode ' . $kode . ' dipakai dua kali di produk ini. '
                    . 'Tiap satuan harus punya kode sendiri, kalau tidak hasil pindaian bisa memilih satuan yang salah.';
                continue;
            }

            $sudahDipakai[$kode] = true;

            if ($barcodeProduk !== '' && $kode === $barcodeProduk) {
                $galat["units.{$kunci}.barcode"] = 'Barcode ' . $kode . ' sama dengan barcode produk ini sendiri. '
                    . 'Saat dipindai, satuan akan selalu menang atas produknya - jadi satuan dasar tidak akan pernah terpilih.';
            }
        }

        // (3) & (4) - dua query, bukan satu per baris.
        $kodeUnik = array_values(array_unique($kodeSatuan));

        $bentrokProduk = Product::whereIn('barcode', $kodeUnik)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('name', 'barcode');

        $bentrokSatuan = ProductUnit::with('product:id,name')
            ->whereIn('barcode', $kodeUnik)
            ->when($ignoreId, fn ($q) => $q->where('product_id', '!=', $ignoreId))
            ->get()
            ->keyBy('barcode');

        foreach ($kodeSatuan as $kunci => $kode) {
            if (isset($galat["units.{$kunci}.barcode"])) {
                continue;
            }

            if ($bentrokProduk->has($kode)) {
                $galat["units.{$kunci}.barcode"] = 'Barcode ' . $kode . ' sudah dipakai produk "'
                    . $bentrokProduk[$kode] . '".';
            } elseif ($bentrokSatuan->has($kode)) {
                $galat["units.{$kunci}.barcode"] = 'Barcode ' . $kode . ' sudah dipakai salah satu satuan produk "'
                    . ($bentrokSatuan[$kode]->product->name ?? '-') . '".';
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    /**
     * Barcode PRODUK juga tidak boleh menabrak barcode satuan mana pun.
     *
     * `unique:products,barcode` hanya melihat tabel `products`. Tanpa pemeriksaan ini, produk
     * baru bisa diberi kode yang sudah dipakai satuan produk lain - dan karena satuan dicari
     * lebih dulu, produk baru itu tidak akan pernah ketemu saat dipindai.
     */
    private function periksaBarcodeProdukTidakMenabrakSatuan(Request $request, ?int $ignoreId): void
    {
        $kode = trim((string) $request->input('barcode', ''));

        if ($kode === '') {
            return;
        }

        $bentrok = ProductUnit::with('product:id,name')
            ->where('barcode', $kode)
            ->when($ignoreId, fn ($q) => $q->where('product_id', '!=', $ignoreId))
            ->first();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode ' . $kode . ' sudah dipakai salah satu satuan produk "'
                    . ($bentrok->product->name ?? '-') . '". Saat dipindai, satuan itu akan menang, '
                    . 'jadi produk ini tidak akan pernah ketemu.',
            ]);
        }
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['barcode' => $request->barcode ?: null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:barang,jasa'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode' . ($ignoreId ? ",{$ignoreId}" : '')],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'required_with:wholesale_min_qty'],
            'wholesale_min_qty' => ['nullable', 'integer', 'min:2', 'required_with:wholesale_price'],
            'multi_unit_enabled' => ['nullable', 'boolean'],
            'hpp_calc_enabled' => ['nullable', 'boolean'],
            'units' => ['nullable', 'array'],
            'units.*.unit_id' => ['required_with:units', 'exists:units,id'],
            // Kode satuan boleh kosong: satuan tanpa kode sendiri ikut kode produknya,
            // jadi tidak ada toko yang perlu mengisi apa pun setelah pembaruan ini.
            'units.*.barcode' => ['nullable', 'string', 'max:64'],
            // Rincian pembentuk harga modal: harga beli satuan ini, dan biaya yang menempel
            // padanya (ongkir, bongkar muat).
            'units.*.modal_total' => ['nullable', 'numeric', 'min:0'],
            'units.*.biaya_lain' => ['nullable', 'numeric', 'min:0'],
            // Isi terhadap satuan sebelumnya dalam rantai, bukan lagi konversi langsung ke
            // satuan dasar - itu dihitung server di syncUnits().
            'units.*.ratio_to_previous' => ['required_with:units', 'numeric', 'min:0.0001'],
            'units.*.price' => ['required_with:units', 'numeric', 'min:0'],
            'units.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.allow_decimal' => ['nullable', 'boolean'],
            'units.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.wholesale_min_qty' => ['nullable', 'integer', 'min:2'],
            // numeric, bukan integer: stok barang timbangan wajar bernilai pecahan
            // (mis. sisa 9,6 Kg setelah menjual 400 Gram).
            'stock' => ['required_if:type,barang', 'nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_taxable' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'image' => $this->imageRules(),
        ]);

        // Dijalankan SESUDAH aturan dasar lolos, supaya kasir tidak dihujani pesan sekaligus.
        // Keduanya menjaga satu hal yang sama: satu barcode hanya boleh menunjuk ke satu
        // barang dengan satu harga. Lihat docblock masing-masing.
        $this->periksaBarcodeProdukTidakMenabrakSatuan($request, $ignoreId);
        $this->periksaBarcodeUnik($request, $ignoreId);

        $data['is_active'] = $request->boolean('is_active', true);
        // Default true mempertahankan perilaku lama: sebelum ada opsi ini di form,
        // semua produk otomatis kena pajak.
        $data['is_taxable'] = $request->boolean('is_taxable', true);
        $data['unit'] = $request->unit ?: 'pcs';
        $data['multi_unit_enabled'] = $request->boolean('multi_unit_enabled', false);
        $data['hpp_calc_enabled'] = $request->boolean('hpp_calc_enabled', false);

        // Produk jasa dibuat sederhana - cuma harga modal, harga jual, satuan. Field stok, harga
        // grosir, dan satuan besar dipaksa kosong di server apapun yang terkirim dari form,
        // walau field-nya sudah disembunyikan di UI saat tipe Jasa dipilih.
        if ($data['type'] === 'jasa') {
            $data['stock'] = 0;
            $data['min_stock'] = 0;
            $data['wholesale_price'] = null;
            $data['wholesale_min_qty'] = null;
            $data['multi_unit_enabled'] = false;
            $data['hpp_calc_enabled'] = false;
            $data['units'] = [];
        } else {
            $data['min_stock'] = $data['min_stock'] ?? 0;
        }

        // Mematikan toggle Multi Satuan harus benar-benar membuang satuannya di server juga.
        // Form sudah mengosongkan barisnya, tapi permintaan bisa datang dari mana saja - dan
        // menyimpan satuan yang tak terlihat di form adalah cara termudah membuat pemilik toko
        // menjual dengan konversi yang dia kira sudah dihapus.
        if (! $data['multi_unit_enabled']) {
            $data['units'] = [];
            $data['hpp_calc_enabled'] = false;
        }

        if ($data['hpp_calc_enabled']) {
            $data = $this->hitungHargaModalDariPembelian($data);
        }

        return $data;
    }

    /**
     * Menurunkan harga modal dari harga beli, supaya pemilik toko tidak perlu menghitungnya.
     *
     * Yang diketahui pemilik toko cuma dua angka di nota: harga beli satu Dus, dan ongkirnya.
     * Berapa harga modal per Pcs adalah pekerjaan aplikasi.
     *
     * INI BAGIAN YANG MEMBUAT FITURNYA BERGUNA, bukan sekadar penghias form. Harga modal per
     * SATUAN TAMBAHAN tidak dipakai perhitungan apa pun di aplikasi ini - yang menggerakkan
     * Laporan Laba dan Nilai Stok adalah products.cost_price, yaitu harga modal satuan DASAR.
     * Jadi kalau angka itu tidak ikut diturunkan, pemilik toko sudah mengisi seluruh rincian
     * pembeliannya tapi laporan labanya tetap salah - dan tidak ada satu pun tanda bahwa ada
     * yang keliru.
     *
     * Baris yang dipakai adalah baris TERISI PERTAMA. Rasio tiap baris dilipat kumulatif
     * dengan cara yang sama seperti syncUnits(), sehingga angkanya konsisten dengan konversi
     * yang tersimpan.
     */
    private function hitungHargaModalDariPembelian(array $data): array
    {
        $konversi = 1.0;

        foreach (array_values($data['units'] ?? []) as $baris) {
            $konversi = Angka::bulat($konversi * (float) ($baris['ratio_to_previous'] ?? 0));

            $modal = (float) ($baris['modal_total'] ?? 0) + (float) ($baris['biaya_lain'] ?? 0);

            if ($modal <= 0 || $konversi <= 0) {
                continue;
            }

            // Dibulatkan 2 desimal mengikuti kolomnya. Sisa pembagian yang tidak habis
            // (mis. 110.000 / 3) memang tidak bisa diwakili persis, dan itu wajar untuk
            // harga modal - yang penting nilainya tidak melenceng.
            $data['cost_price'] = round($modal / $konversi, 2);

            return $data;
        }

        return $data;
    }

    private function generateSku(): string
    {
        do {
            $sku = 'SKU-' . Str::upper(Str::random(8));
        } while (Product::where('sku', $sku)->exists());
        return $sku;
    }
}
