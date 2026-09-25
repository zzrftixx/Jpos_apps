<?php

/**
 * BENCHMARK ULTRA HOT - JPOS PERFORMANCE ENGINE
 *
 * Menguji kinerja throughput, latensi, dan efisiensi memori pada simpul kritis JPos:
 * 1. ProductCatalog: Cache Miss vs Cache Hit Latency (2.000+ produk emulasi)
 * 2. Kasir Checkout Engine: Transaksi/detik (TPS), Database Locking & Aritmetika Angka
 * 3. Akuntansi Engine: Komputasi Omset Harian, HPP, & Neraca Keseimbangan H7
 * 4. Dashboard Query Optimization: Join vs WhereHas Empirical Latency
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Unit;
use App\Support\Akuntansi;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "========================================================================\n";
echo "           JPOS ULTRA HOT BENCHMARK - EMPIRICAL AUDIT REPORT            \n";
echo "========================================================================\n";
echo "Environment: PHP " . PHP_VERSION . " | SQLite " . DB::select("SELECT sqlite_version() as v")[0]->v . " | OS: Windows\n";
echo "Waktu Uji  : " . date('Y-m-d H:i:s') . "\n";
echo "------------------------------------------------------------------------\n\n";

// Sesi Benchmark 1: ProductCatalog Latency & Serialization
echo "[BENCHMARK 1] ProductCatalog Cache Engine\n";
$catalog = app(ProductCatalog::class);

// Cache Miss (Rebuild catalog dari DB)
$catalog->flush();
$memBefore = memory_get_usage();
$t0 = microtime(true);
$cartItems = $catalog->forCart();
$t1 = microtime(true);
$memAfter = memory_get_usage();
$durasiMiss = ($t1 - $t0) * 1000;
$memDelta = ($memAfter - $memBefore) / 1024;

echo sprintf("  - Cold Rebuild (Cache Miss) : %8.2f ms | Mem: %6.1f KB | Items: %d\n", $durasiMiss, $memDelta, count($cartItems));

// Cache Hit (Pengambilan cepat dari cache driver)
$t0 = microtime(true);
$iterations = 1000;
for ($i = 0; $i < $iterations; $i++) {
    $dummy = $catalog->forCart();
}
$t1 = microtime(true);
$durasiHitTotal = ($t1 - $t0) * 1000;
$durasiHitAvg = $durasiHitTotal / $iterations;
$hitOps = $iterations / ($t1 - $t0);

echo sprintf("  - Warm Read    (Cache Hit)  : %8.4f ms/op | %8.1f ops/sec (1.000 iterasi)\n", $durasiHitAvg, $hitOps);
$speedup = $durasiMiss / max($durasiHitAvg, 0.0001);
echo sprintf("  - Cache Acceleration Ratio  : %8.1fx LEBIH CEPAT\n\n", $speedup);


// Sesi Benchmark 2: Kasir Checkout Engine Throughput
echo "[BENCHMARK 2] Transaksi Checkout Engine (DB::transaction + lockForUpdate + StockMovement)\n";
$user = User::first() ?? User::create(['name' => 'Kasir Uji', 'username' => 'kasir_uji', 'password' => bcrypt('password'), 'role' => 'kasir']);
$product = Product::where('type', 'barang')->where('stock', '>', 500)->first();

if (!$product) {
    $product = Product::create([
        'sku' => 'BNCH001',
        'name' => 'Produk Benchmark',
        'type' => 'barang',
        'cost_price' => 5000,
        'sell_price' => 10000,
        'stock' => 10000,
        'unit' => 'Pcs',
        'is_active' => true,
    ]);
}

$trxCount = 100;
$t0 = microtime(true);

for ($i = 1; $i <= $trxCount; $i++) {
    DB::transaction(function () use ($product, $user, $i) {
        $p = Product::lockForUpdate()->find($product->id);
        $stockAfter = $p->ubahStok(-1);

        $sale = Sale::create([
            'invoice_no' => 'INV-BENCH-' . time() . '-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'subtotal' => 10000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
            'paid_amount' => 10000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'order_status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $p->id,
            'product_name' => $p->name,
            'price' => 10000,
            'cost_price_snapshot' => $p->cost_price,
            'qty' => 1,
            'unit_conversion' => 1.0,
            'subtotal' => 10000,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'tunai',
            'amount' => 10000,
            'kind' => 'bayar',
            'user_id' => $user->id,
        ]);

        StockMovement::create([
            'product_id' => $p->id,
            'type' => 'sale',
            'qty' => -1,
            'stock_after' => $stockAfter,
            'note' => 'Penjualan ' . $sale->invoice_no,
            'user_id' => $user->id,
        ]);
    });
}
$t1 = microtime(true);
$durasiTrxTotal = ($t1 - $t0) * 1000;
$trxPerSec = $trxCount / ($t1 - $t0);
$avgLatency = $durasiTrxTotal / $trxCount;

echo sprintf("  - Total Transaksi Dieksekusi : %d transaksi penuh (H1, H2, H3, H4, H6)\n", $trxCount);
echo sprintf("  - Waktu Total                : %8.2f ms\n", $durasiTrxTotal);
echo sprintf("  - Throughput (TPS)           : %8.1f transaksi / detik\n", $trxPerSec);
echo sprintf("  - Rata-rata Latensi per Trx  : %8.2f ms / transaksi\n\n", $avgLatency);


// Sesi Benchmark 3: Akuntansi Engine Keseimbangan Finansial
echo "[BENCHMARK 3] Akuntansi & Neraca Calculation Engine\n";
$hariIni = date('Y-m-d');
$t0 = microtime(true);
$omset = Akuntansi::omsetHarian(date('Y-m-01'), $hariIni);
$t1 = microtime(true);
$durasiOmset = ($t1 - $t0) * 1000;

$t0 = microtime(true);
$posisi = Akuntansi::posisiPada($hariIni);
$t1 = microtime(true);
$durasiNeraca = ($t1 - $t0) * 1000;

echo sprintf("  - Komputasi Omset Harian Bln Ini : %8.2f ms\n", $durasiOmset);
echo sprintf("  - Komputasi Posisi Neraca Lengkap: %8.2f ms\n", $durasiNeraca);
echo sprintf("  - Status Persamaan Neraca (H7)   : Total Aset: Rp %s | Kewajiban+Modal: Rp %s | Selisih: Rp %s (%s)\n\n",
    number_format($posisi->total_aset, 0, ',', '.'),
    number_format($posisi->total_kewajiban + $posisi->total_modal, 0, ',', '.'),
    number_format($posisi->selisih, 0, ',', '.'),
    $posisi->selisih == 0 ? 'SEIMBANG TEPAT NOL' : 'TIMPANG'
);


// Sesi Benchmark 4: Query Optimization (JOIN vs WHEREHAS)
echo "[BENCHMARK 4] Top Products Optimization Empirical Proof (JOIN vs WHEREHAS)\n";
$iterQuery = 50;

// Versi Lama: whereHas (EXISTS subquery)
$t0 = microtime(true);
for ($i = 0; $i < $iterQuery; $i++) {
    $qOld = SaleItem::select('product_name')
        ->selectRaw('SUM(qty * unit_conversion) as total_qty')
        ->whereHas('sale', function ($q) {
            $q->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('order_status', 'completed');
        })
        ->groupBy('product_name')
        ->orderByDesc('total_qty')
        ->limit(5)
        ->get();
}
$t1 = microtime(true);
$durasiOld = (($t1 - $t0) * 1000) / $iterQuery;

// Versi Baru: JOIN langsung
$t0 = microtime(true);
for ($i = 0; $i < $iterQuery; $i++) {
    $qNew = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
        ->select('sale_items.product_name')
        ->selectRaw('SUM(sale_items.qty * sale_items.unit_conversion) as total_qty')
        ->whereMonth('sales.created_at', now()->month)
        ->whereYear('sales.created_at', now()->year)
        ->where('sales.order_status', 'completed')
        ->groupBy('sale_items.product_name')
        ->orderByDesc('total_qty')
        ->limit(5)
        ->get();
}
$t1 = microtime(true);
$durasiNew = (($t1 - $t0) * 1000) / $iterQuery;

$querySpeedup = ($durasiOld - $durasiNew) / max($durasiOld, 0.001) * 100;
echo sprintf("  - Versi Lama (whereHas subquery) : %8.3f ms / call\n", $durasiOld);
echo sprintf("  - Versi Baru (direct JOIN)       : %8.3f ms / call\n", $durasiNew);
echo sprintf("  - Efisiensi Peningkatan Kecepatan: %8.1f%% LEBIH RINGAN\n\n", $querySpeedup);

echo "========================================================================\n";
echo "                     BENCHMARK ULTRA HOT SELESAI                        \n";
echo "========================================================================\n";
