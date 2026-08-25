<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Rack;
use App\Models\RackSlot;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planogram - peta rak toko.
 *
 * Di toko kecil, letak barang biasanya cuma ada di kepala pemiliknya. Itu jalan sampai ada
 * karyawan baru, atau sampai pemiliknya tidak di tempat. Halaman ini memindahkannya ke layar.
 */
class RackController extends Controller
{
    public function index()
    {
        $racks = Rack::withCount('slots')->orderBy('sort_order')->orderBy('name')->get();

        return view('master.planogram.index', compact('racks'));
    }

    public function store(Request $request)
    {
        $data = $this->validasiRak($request);

        $rack = Rack::create($data);

        return redirect()->route('planogram.edit', $rack)->with('success', 'Rak dibuat. Sekarang isi kotaknya.');
    }

    /**
     * Penyunting kisi satu rak.
     *
     * Daftar produk yang bisa dipilih sengaja mengecualikan produk yang sudah punya kotak di
     * rak mana pun - satu produk satu tempat, supaya pertanyaan "ada di mana?" punya satu
     * jawaban. Produk yang sudah menempati kotak DI RAK INI tetap ikut, kalau tidak kotak
     * yang sedang terisi akan tampak seolah produknya hilang dari pilihan.
     */
    public function edit(Rack $rack)
    {
        $rack->load('slots.product');

        $sudahDitempatkan = RackSlot::where('rack_id', '!=', $rack->id)->pluck('product_id');

        $produk = Product::where('is_active', true)
            ->where('type', 'barang')
            ->whereNotIn('id', $sudahDitempatkan)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock', 'min_stock', 'unit']);

        return view('master.planogram.edit', [
            'rack' => $rack,
            'peta' => $rack->petaSlot(),
            'produk' => $produk,
        ]);
    }

    public function update(Request $request, Rack $rack)
    {
        $data = $this->validasiRak($request, $rack);

        // Mengecilkan kisi akan membuat kotak di luar batas menjadi tidak terlihat namun tetap
        // ada di database - produk seolah hilang tanpa jejak. Lebih baik ditolak dan pemiliknya
        // mengosongkan kotaknya dulu secara sadar.
        $diLuarBatas = $rack->slots()
            ->where(fn ($q) => $q->where('row', '>=', $data['rows'])->orWhere('col', '>=', $data['cols']))
            ->count();

        if ($diLuarBatas > 0) {
            throw ValidationException::withMessages([
                'rows' => "Ada {$diLuarBatas} kotak terisi di luar ukuran baru. Kosongkan dulu kotaknya, supaya tidak ada produk yang hilang tanpa jejak.",
            ]);
        }

        $rack->update($data);

        return back()->with('success', 'Rak diperbarui.');
    }

    public function destroy(Rack $rack)
    {
        $rack->delete();
        app(ProductCatalog::class)->flush();

        return redirect()->route('planogram.index')->with('success', 'Rak dihapus.');
    }

    /**
     * Mengisi atau mengosongkan satu kotak.
     *
     * product_id kosong berarti kotaknya dikosongkan. Penempatan memakai updateOrCreate atas
     * pasangan (rak, baris, kolom) yang sudah unik di database, jadi menaruh produk di kotak
     * yang sudah terisi otomatis menggantikan isinya - bukan menambah baris kedua.
     */
    public function simpanSlot(Request $request, Rack $rack)
    {
        $data = $request->validate([
            'row' => ['required', 'integer', 'min:0', 'max:' . ($rack->rows - 1)],
            'col' => ['required', 'integer', 'min:0', 'max:' . ($rack->cols - 1)],
            'product_id' => ['nullable', 'exists:products,id'],
            'facings' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        DB::transaction(function () use ($data, $rack) {
            if (empty($data['product_id'])) {
                RackSlot::where('rack_id', $rack->id)
                    ->where('row', $data['row'])
                    ->where('col', $data['col'])
                    ->delete();

                return;
            }

            // Satu produk satu tempat: kalau produknya sedang menempati kotak lain, kotak lama
            // dilepas dulu. Tanpa ini, batasan unik di database yang akan menolak - dengan
            // pesan yang tidak bisa dipahami siapa pun.
            RackSlot::where('product_id', $data['product_id'])->delete();

            RackSlot::updateOrCreate(
                ['rack_id' => $rack->id, 'row' => $data['row'], 'col' => $data['col']],
                ['product_id' => $data['product_id'], 'facings' => $data['facings'] ?? 1]
            );
        });

        // Katalog kasir membawa label lokasi rak, jadi harus dibangun ulang - kalau tidak,
        // kasir masih melihat lokasi yang lama.
        app(ProductCatalog::class)->flush();

        return back()->with('success', 'Peta rak diperbarui.');
    }

    /** Halaman cetak peta rak, dipakai saat stok opname. */
    public function cetak(Rack $rack)
    {
        $rack->load('slots.product');

        return view('master.planogram.cetak', [
            'rack' => $rack,
            'peta' => $rack->petaSlot(),
        ]);
    }

    private function validasiRak(Request $request, ?Rack $rack = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:150'],
            // Dibatasi 12x12 bukan karena database tidak sanggup, tapi karena kisi yang lebih
            // besar dari itu tidak lagi terbaca di layar - dan rak yang tidak terbaca sama
            // tidak bergunanya dengan tidak punya peta sama sekali.
            'rows' => ['required', 'integer', 'min:1', 'max:12'],
            'cols' => ['required', 'integer', 'min:1', 'max:12'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
