<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Support\Struk;
use Illuminate\Http\Request;

class BarcodePrintController extends Controller
{
    /**
     * Batas jumlah label sekali cetak.
     *
     * Angkanya dipilih supaya cukup untuk pekerjaan sungguhan - melabeli seluruh rak baru
     * biasanya di bawah 300 label - tapi tetap berhenti jauh sebelum segulung kertas habis.
     */
    public const MAKS_LABEL_SEKALI_CETAK = 300;

    /** Batas lembar per satu baris terpilih. */
    public const MAKS_PER_LABEL = 50;


    public function index(Request $request)
    {
        // Penyaring dipakai dua kali - untuk halaman yang tampil dan untuk daftar "pilih
        // semua" - jadi ditulis sekali di sini. Sebelumnya `orWhere` tidak dikelompokkan,
        // sehingga pada query yang punya kondisi lain hasilnya bisa bocor.
        $saring = fn ($q) => $q->when($request->q, fn ($w) => $w->where(fn ($g) => $g
            ->where('name', 'like', "%{$request->q}%")
            ->orWhere('barcode', 'like', "%{$request->q}%")
            ->orWhere('sku', 'like', "%{$request->q}%")));

        $products = Product::with('units.unit')
            ->tap($saring)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // "Pilih semua" dulu hanya mencakup 20 baris yang sedang tampil, karena token
        // dibangun dari koleksi hasil paginasi. Pemilik toko yang mencentangnya lalu
        // mencetak mengira seluruh katalognya sudah dilabeli, padahal baru satu halaman -
        // dan tidak ada apa pun yang memberitahunya.
        //
        // Sekarang tokennya dibangun dari SELURUH hasil penyaringan. Hanya id yang diambil,
        // bukan seluruh baris produk, supaya katalog besar tidak membebani halaman ini.
        $semuaToken = Product::query()
            ->tap($saring)
            ->orderBy('name')
            ->with(['units:id,product_id'])
            ->get(['id'])
            ->flatMap(fn ($p) => array_merge(
                [(string) $p->id],
                $p->units->map(fn ($pu) => $p->id . ':' . $pu->id)->all()
            ))
            ->values();

        return view('transaksi.barcode.index', compact('products', 'semuaToken'));
    }

    /**
     * Label bisa dipilih per SATUAN, bukan cuma per produk.
     *
     * Produk yang dijual dalam beberapa satuan butuh label yang berbeda untuk tiap satuan -
     * label rak untuk harga per Kg tidak boleh menempel di kemasan yang dijual per Gram.
     * Karena itu tiap token di parameter "ids" berbentuk:
     *
     *     "{product_id}"                    -> satuan dasar, harga jual produk
     *     "{product_id}:{product_unit_id}"  -> satuan tambahan, dengan harga satuan itu
     */
    public function print(Request $request)
    {
        $tokens = array_filter(explode(',', $request->query('ids', '')));
        $qtyDiminta = max(1, (int) $request->query('qty', 1));

        // REM CETAK. Sebelum ini `qty` sama sekali tidak dibatasi: satu angka salah ketik
        // ("10" jadi "100") pada 20 baris terpilih langsung menjadi 2.000 label, dan tidak
        // ada apa pun yang menahannya sampai kertasnya benar-benar habis. Kertas yang sudah
        // keluar tidak bisa ditarik kembali, jadi batasnya dipasang SEBELUM dicetak, bukan
        // berupa peringatan yang bisa dilewati.
        $qty = min($qtyDiminta, self::MAKS_PER_LABEL);
        $jumlahBarisMaks = (int) floor(self::MAKS_LABEL_SEKALI_CETAK / max(1, $qty));

        $productIds = collect($tokens)->map(fn ($t) => (int) explode(':', $t)[0])->unique();
        $productsById = Product::with('units.unit')->whereIn('id', $productIds)->get()->keyBy('id');

        // Baris yang produk atau satuannya sudah dihapus dibuang diam-diam: halaman ini
        // dibuka di jendela baru untuk langsung dicetak, jadi memunculkan galat di sana
        // hanya membuat kasir kehilangan seluruh cetakan gara-gara satu baris basi.
        $labels = collect($tokens)->map(function ($token) use ($productsById) {
            [$productId, $unitId] = array_pad(explode(':', $token, 2), 2, null);
            $product = $productsById->get((int) $productId);

            if (! $product) {
                return null;
            }

            if ($unitId) {
                $productUnit = $product->units->firstWhere('id', (int) $unitId);

                return $productUnit ? [
                    'product' => $product,
                    'unit_label' => $productUnit->unit->name,
                    'price' => (float) $productUnit->price,
                    // Label satuan membawa kode satuannya sendiri kalau ada, supaya
                    // memindainya langsung memilih satuan itu di kasir. Kalau satuannya
                    // belum diberi kode, ia jatuh ke kode produk seperti sebelumnya -
                    // jadi tidak ada label yang berubah tanpa diminta.
                    'kode' => $productUnit->barcode ?: ($product->barcode ?: $product->sku),
                ] : null;
            }

            return [
                'product' => $product,
                'unit_label' => null,
                'price' => (float) $product->sell_price,
                'kode' => $product->barcode ?: $product->sku,
            ];
        })->filter()->values();

        $barisDiminta = $labels->count();
        $labels = $labels->take($jumlahBarisMaks);

        $printerBarcode = Setting::get('printer_barcode', ['label_width' => 40, 'label_height' => 25]);
        $template = Setting::get('template_barcode', ['show_name' => true, 'show_price' => true, 'show_barcode_text' => true, 'barcode_type' => 'CODE128']);

        // MODE ROL vs MODE LABEL.
        //
        // Sebagian toko tidak punya printer label sama sekali dan menyambungkan menu ini ke
        // printer struk 58/80mm yang sudah ada. Printer struk tidak mengenal ukuran halaman
        // 40x25mm: setiap pemisah halaman diterjemahkan jadi satu UMPAN KERTAS PENUH. Enam
        // label karena itu bisa menghabiskan kertas jauh lebih banyak daripada isinya -
        // persis keluhan "ngeprint banyak sampai kertas habis".
        //
        // Di mode rol, label dicetak menyambung ke bawah dengan garis potong, tanpa satu pun
        // pemisah halaman.
        $modeRoll = ($printerBarcode['mode'] ?? 'label') === 'roll';
        $printerStruk = Setting::get('printer_struk', []);
        $lebarCetak = Struk::lebarCetak($printerStruk);

        $lebarLabel = (float) ($printerBarcode['label_width'] ?? 40);
        $tinggiLabel = (float) ($printerBarcode['label_height'] ?? 25);

        $jumlahBaris = $labels->count();
        $totalDiminta = $barisDiminta * $qtyDiminta;
        $totalLabel = $jumlahBaris * $qty;
        $dipangkas = $totalDiminta > $totalLabel;

        return view('transaksi.barcode.print', compact(
            'labels', 'qty', 'printerBarcode', 'template',
            'modeRoll', 'lebarCetak', 'lebarLabel', 'tinggiLabel',
            'jumlahBaris', 'totalLabel', 'totalDiminta', 'dipangkas'
        ));
    }
}
