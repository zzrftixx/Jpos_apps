<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Angka;
use App\Support\MetodeBayar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller API Sinkronisasi untuk Aplikasi Kasir Tablet Android (Offline-First).
 *
 * Mengizinkan tablet mengunduh seluruh katalog produk ke database lokal tablet (SQLite/IndexedDB),
 * dan mengunggah kembali antrean transaksi penjualan offline yang terjadi di tablet ke server PC utama.
 */
class TabletSyncController extends Controller
{
    /**
     * Cek status koneksi antara Tablet dan PC Server.
     */
    public function status(): JsonResponse
    {
        $profile = Setting::get('store_profile', ['name' => 'JPOS Store']);

        return response()->json([
            'status' => 'online',
            'server_name' => $profile['name'] ?? 'JPOS Server',
            'server_time' => now()->toIso8601String(),
            'version' => config('jpos.version', '1.0.0'),
            'total_products' => Product::where('is_active', true)->count(),
        ]);
    }

    /**
     * Ekspor seluruh data katalog produk, kategori, pelanggan, dan profil toko untuk disimpan di tablet.
     */
    public function katalog(): JsonResponse
    {
        $storeProfile = Setting::get('store_profile', [
            'name' => 'JPOS Store',
            'address' => '',
            'phone' => '',
            'logo' => null,
        ]);

        $printerStruk = Setting::get('printer_struk', [
            'paper_size' => 58,
            'header' => '',
            'footer' => 'Terima kasih atas kunjungan Anda!',
        ]);

        $taxSettings = Setting::get('tax', [
            'enabled' => false,
            'percent' => 11,
            'name' => 'PPN',
        ]);

        $etag = '"ktlg-' . md5((string) Product::max('updated_at') . '-' . Product::count() . '-' . Customer::count()) . '"';
        if (request()->header('If-None-Match') === $etag) {
            return response()->json(null, 304, ['ETag' => $etag]);
        }

        $categories = Category::select('id', 'name')->orderBy('name')->get();

        $products = Product::select('id', 'name', 'barcode', 'sku', 'category_id', 'sell_price', 'stock', 'unit', 'type', 'image')
            ->with(['units:id,product_id,unit_id,conversion,price,barcode', 'units.unit:id,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'barcode' => $p->barcode ?: $p->sku,
                    'sku' => $p->sku,
                    'category_id' => $p->category_id,
                    'price' => (float) ($p->sell_price ?? 0),
                    'stock' => (float) $p->stock,
                    'unit' => $p->unit ?: 'Pcs',
                    'is_jasa' => (bool) $p->isJasa(),
                    'image' => $p->image ? url('media/' . $p->image) : null,
                    'units' => $p->units->map(function ($u) {
                        return [
                            'unit_id' => $u->id,
                            'name' => $u->unit?->name ?? 'Satuan',
                            'conversion' => (float) $u->conversion,
                            'price' => (float) $u->price,
                            'barcode' => $u->barcode,
                        ];
                    }),
                ];
            });

        $customers = Customer::select('id', 'name', 'phone', 'customer_type', 'points')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'synced_at' => now()->toIso8601String(),
            'store' => $storeProfile,
            'printer' => $printerStruk,
            'tax' => $taxSettings,
            'categories' => $categories,
            'customers' => $customers,
            'products' => $products,
        ], 200, ['ETag' => $etag]);
    }

    /**
     * Menerima antrean transaksi yang dilakukan di tablet saat offline, lalu memasukkannya
     * ke database server utama JPOS secara aman dan atomic.
     */
    public function syncTransaksi(Request $request): JsonResponse
    {
        $salesData = $request->input('sales', []);
        if (empty($salesData) || !is_array($salesData)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada transaksi yang dikirim untuk disinkronkan.',
                'synced_ids' => [],
            ], 422);
        }

        // Cari user kasir/admin bawaan untuk mencatat transaksi jika tablet tidak mengirim user_id spesifik
        $defaultUser = User::where('is_active', true)->first() ?? User::first();
        $defaultUserId = $defaultUser ? $defaultUser->id : 1;

        $syncedIds = [];
        $failed = [];

        foreach ($salesData as $saleInput) {
            $offlineId = $saleInput['offline_id'] ?? null;
            if (!$offlineId) {
                continue;
            }

            // Mencegah duplikasi data jika tablet menyinkronkan transaksi yang sama berulang kali
            $existing = Sale::where('note', 'like', "%[OFFLINE_ID:{$offlineId}]%")->first();
            if ($existing) {
                $syncedIds[] = $offlineId;
                continue;
            }

            try {
                DB::transaction(function () use ($saleInput, $offlineId, $defaultUserId, &$syncedIds) {
                    $subtotal = (float) ($saleInput['subtotal'] ?? 0);
                    $discount = (float) ($saleInput['discount'] ?? 0);
                    $taxAmount = (float) ($saleInput['tax_amount'] ?? 0);
                    $total = (float) ($saleInput['total'] ?? ($subtotal - $discount + $taxAmount));
                    $paidAmount = (float) ($saleInput['paid_amount'] ?? $total);
                    $changeAmount = (float) ($saleInput['change_amount'] ?? max(0, $paidAmount - $total));
                    $paymentMethod = MetodeBayar::normal($saleInput['payment_method'] ?? 'cash');
                    $cashierName = $saleInput['cashier_name'] ?? 'Kasir Tablet';

                    $note = trim(($saleInput['note'] ?? '') . " [OFFLINE_ID:{$offlineId}] [Kasir: {$cashierName}]");
                    $createdAt = !empty($saleInput['created_at']) ? date('Y-m-d H:i:s', strtotime($saleInput['created_at'])) : now();

                    $sale = new Sale();
                    $sale->invoice_no = Sale::generateInvoiceNo();
                    $sale->customer_id = $saleInput['customer_id'] ?? null;
                    $sale->user_id = $defaultUserId;
                    $sale->subtotal = $subtotal;
                    $sale->discount = $discount;
                    $sale->tax_amount = $taxAmount;
                    $sale->total = $total;
                    $sale->paid_amount = $paidAmount;
                    $sale->change_amount = $changeAmount;
                    $sale->payment_method = $paymentMethod;
                    $sale->status = 'completed';
                    $sale->order_status = 'completed';
                    $sale->note = $note;
                    $sale->created_at = $createdAt;
                    $sale->updated_at = now();
                    $sale->save();

                    // Simpan setiap item barang
                    $items = $saleInput['items'] ?? [];
                    foreach ($items as $item) {
                        $productId = $item['product_id'] ?? null;
                        if (!$productId) {
                            continue;
                        }

                        $product = Product::lockForUpdate()->find($productId);
                        if (!$product) {
                            continue;
                        }

                        $qty = (float) ($item['qty'] ?? 1);
                        if ($qty <= 0 || !is_finite($qty)) {
                            continue;
                        }

                        $unitConversion = (float) ($item['unit_conversion'] ?? 1);
                        if ($unitConversion <= 0 || !is_finite($unitConversion)) {
                            $unitConversion = 1.0;
                        }

                        $unitLabel = $item['unit_label'] ?? ($product->unit ?: 'Pcs');
                        $price = (float) ($item['price'] ?? $product->sell_price);
                        $lineSubtotal = (float) ($item['subtotal'] ?? ($price * $qty));
                        $qtyInBaseUnits = Angka::keSatuanDasar($qty, $unitConversion);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'price' => $price,
                            'cost_price_snapshot' => $product->cost_price,
                            'qty' => $qty,
                            'unit_label' => $unitLabel,
                            'unit_conversion' => $unitConversion,
                            'subtotal' => $lineSubtotal,
                            'created_at' => $createdAt,
                            'updated_at' => now(),
                        ]);

                        // Potong stok dan catat mutasi stok
                        if (!$product->isJasa()) {
                            $stockAfter = $product->ubahStok(-$qtyInBaseUnits);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => 'sale',
                                'qty' => -$qtyInBaseUnits,
                                'stock_after' => $stockAfter,
                                'note' => "Penjualan Tablet Offline {$sale->invoice_no} ({$offlineId})",
                                'user_id' => $defaultUserId,
                                'created_at' => $createdAt,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // Uang yang benar-benar masuk dicatat ke tabel sale_payments untuk semua metode bayar
                    // (tunai, QRIS, transfer, dll).
                    //
                    // CATATAN ARSITEKTUR (HUKUM 7 & Model Kas Akuntansi BLUEPRINT §7):
                    // Penjualan kasir TIDAK dicatat di cash_transactions (lihat Akuntansi.php L329).
                    // Tabel cash_transactions hanya untuk transaksi di luar penjualan (operasional, prive, modal).
                    // Memasukkan penjualan ke cash_transactions akan menyebabkan penerimaan dihitung ganda
                    // pada saldo kas di kasPada(), membuat aset > modal dan merusak neraca (HUKUM 7).
                    $uangMasuk = $paidAmount - $changeAmount;
                    if ($uangMasuk > 0) {
                        SalePayment::create([
                            'sale_id' => $sale->id,
                            'method' => $paymentMethod,
                            'amount' => $uangMasuk,
                            'kind' => 'bayar',
                            'user_id' => $defaultUserId,
                            'created_at' => $createdAt,
                            'updated_at' => now(),
                        ]);
                    }

                    $syncedIds[] = $offlineId;
                });
            } catch (\Throwable $e) {
                $failed[] = [
                    'offline_id' => $offlineId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'synced_count' => count($syncedIds),
            'synced_ids' => $syncedIds,
            'failed' => $failed,
            'message' => count($syncedIds) . ' transaksi dari tablet berhasil disinkronkan ke database server.',
        ]);
    }
}
