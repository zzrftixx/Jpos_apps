<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodePrintController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\LaporanEksporController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SesiAplikasiController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::redirect('/', '/dashboard');

// Penanda jendela aplikasi masih terbuka, dipakai JPOS.exe untuk tahu kapan server boleh
// dimatikan. Tanpa autentikasi supaya halaman login pun ikut menahan server tetap hidup.
Route::get('/jpos-detak', [SesiAplikasiController::class, 'detak'])->name('jpos.detak');
Route::get('/jpos-tutup', [SesiAplikasiController::class, 'tutup'])->name('jpos.tutup');

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('menu:dashboard')->name('dashboard');

    // Master Data
    Route::middleware('menu:produk')->prefix('master/produk')->name('produk.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('menu:kategori')->prefix('master/kategori')->name('kategori.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('menu:satuan')->prefix('master/satuan')->name('satuan.')->group(function () {
        Route::get('/', [UnitController::class, 'index'])->name('index');
        Route::post('/', [UnitController::class, 'store'])->name('store');
        Route::put('/{unit}', [UnitController::class, 'update'])->name('update');
        Route::delete('/{unit}', [UnitController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('menu:planogram')->prefix('master/planogram')->name('planogram.')->group(function () {
        Route::get('/', [RackController::class, 'index'])->name('index');
        Route::post('/', [RackController::class, 'store'])->name('store');
        Route::get('/{rack}', [RackController::class, 'edit'])->name('edit');
        Route::put('/{rack}', [RackController::class, 'update'])->name('update');
        Route::delete('/{rack}', [RackController::class, 'destroy'])->name('destroy');
        Route::post('/{rack}/slot', [RackController::class, 'simpanSlot'])->name('slot');
        Route::get('/{rack}/cetak', [RackController::class, 'cetak'])->name('cetak');
    });

    Route::middleware('menu:supplier')->prefix('master/supplier')->name('supplier.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('menu:pelanggan')->prefix('master/pelanggan')->name('pelanggan.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
    });

    // Transaksi
    Route::middleware('menu:kasir')->prefix('kasir')->name('kasir.')->group(function () {
        Route::get('/', [KasirController::class, 'index'])->name('index');
        Route::get('/scan', [KasirController::class, 'findByBarcode'])->name('scan');
        Route::post('/', [KasirController::class, 'store'])->name('store');
        Route::get('/receipt/{sale}', [KasirController::class, 'receipt'])->name('receipt');
        // Jalur transaksi tertahan - terpisah dari pesanan DP, lihat migrasi 000340.
        Route::get('/tahan', [KasirController::class, 'tahanList'])->name('tahan');
        Route::post('/tahan/{sale}/ambil', [KasirController::class, 'ambilTahan'])->name('tahan.ambil');

        Route::get('/waiting-list', [KasirController::class, 'waitingList'])->name('waiting-list');
        Route::get('/waiting-list/{sale}/edit', [KasirController::class, 'editWaiting'])->name('waiting-list.edit');
        Route::put('/waiting-list/{sale}', [KasirController::class, 'updateWaiting'])->name('waiting-list.update');
        Route::post('/waiting-list/{sale}/pay', [KasirController::class, 'payWaiting'])->name('waiting-list.pay');
        Route::post('/waiting-list/{sale}/cancel', [KasirController::class, 'cancelWaiting'])->name('waiting-list.cancel');
        Route::delete('/waiting-list/{sale}', [KasirController::class, 'destroy'])->name('waiting-list.destroy');
    });

    Route::middleware('menu:kasir')->prefix('barcode')->name('barcode.')->group(function () {
        Route::get('/', [BarcodePrintController::class, 'index'])->name('index');
        Route::get('/print', [BarcodePrintController::class, 'print'])->name('print');
    });

    Route::middleware('menu:retur')->prefix('retur')->name('retur.')->group(function () {
        Route::get('/', [ReturnController::class, 'index'])->name('index');
        Route::get('/find', [ReturnController::class, 'findSale'])->name('find');
        Route::post('/', [ReturnController::class, 'store'])->name('store');
        Route::post('/{sale}/cancel', [ReturnController::class, 'cancelSale'])->name('cancel-sale');
    });

    Route::middleware('menu:pembelian')->prefix('pembelian')->name('pembelian.')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::post('/', [PurchaseController::class, 'store'])->name('store');
        Route::post('/{purchase}/bayar', [PurchaseController::class, 'bayar'])->name('bayar');
        Route::delete('/{purchase}', [PurchaseController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('menu:kas')->prefix('kas')->name('kas.')->group(function () {
        Route::get('/', [CashTransactionController::class, 'index'])->name('index');
        Route::post('/', [CashTransactionController::class, 'store'])->name('store');
        Route::delete('/{cashTransaction}', [CashTransactionController::class, 'destroy'])->name('destroy');
    });

    // Laporan
    Route::middleware('menu:laporan')->prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/penjualan', [ReportController::class, 'penjualan'])->name('penjualan');
        Route::get('/omset', [ReportController::class, 'omset'])->name('omset');
        Route::get('/laba', [ReportController::class, 'laba'])->name('laba');
        Route::get('/per-pelanggan', [ReportController::class, 'perPelanggan'])->name('per-pelanggan');
        Route::get('/stok', [ReportController::class, 'stok'])->name('stok');
        Route::get('/stok/{product}', [ReportController::class, 'stokMovements'])->name('stok.detail');
        Route::get('/terlaris', [ReportController::class, 'terlaris'])->name('terlaris');

        Route::get('/neraca', [ReportController::class, 'neraca'])->name('neraca');
        Route::post('/neraca/pembukuan', [ReportController::class, 'simpanPembukuan'])->name('neraca.pembukuan');
        Route::post('/neraca/tutup-buku', [ReportController::class, 'tutupBuku'])->name('neraca.tutup-buku');

        // Satu pintu ekspor untuk sepuluh laporan. Jenis yang tidak dikenali menghasilkan 404
        // di controller, bukan rute terpisah per laporan.
        Route::get('/ekspor/{jenis}/{format}', LaporanEksporController::class)->name('ekspor');
    });

    // Manajemen
    Route::middleware('menu:user')->prefix('manajemen/user')->name('user.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('menu:role')->prefix('manajemen/role')->name('role.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Pengaturan
    Route::middleware('menu:pengaturan')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/profil-toko', [SettingController::class, 'profilToko'])->name('profil-toko');
        Route::post('/profil-toko', [SettingController::class, 'updateProfilToko'])->name('profil-toko.update');

        Route::get('/tampilan-kasir', [SettingController::class, 'tampilanKasir'])->name('tampilan-kasir');
        Route::post('/tampilan-kasir', [SettingController::class, 'updateTampilanKasir'])->name('tampilan-kasir.update');

        Route::get('/mode-produk', [SettingController::class, 'modeProduk'])->name('mode-produk');
        Route::post('/mode-produk', [SettingController::class, 'updateModeProduk'])->name('mode-produk.update');

        Route::get('/printer-struk', [SettingController::class, 'printerStruk'])->name('printer-struk');
        Route::post('/printer-struk', [SettingController::class, 'updatePrinterStruk'])->name('printer-struk.update');
        Route::get('/printer-struk/uji', [SettingController::class, 'ujiCetakStruk'])->name('printer-struk.uji');

        Route::get('/printer-barcode', [SettingController::class, 'printerBarcode'])->name('printer-barcode');
        Route::post('/printer-barcode', [SettingController::class, 'updatePrinterBarcode'])->name('printer-barcode.update');

        Route::get('/template-barcode', [SettingController::class, 'templateBarcode'])->name('template-barcode');
        Route::post('/template-barcode', [SettingController::class, 'updateTemplateBarcode'])->name('template-barcode.update');

        Route::get('/template-struk', [SettingController::class, 'templateStruk'])->name('template-struk');
        Route::post('/template-struk', [SettingController::class, 'updateTemplateStruk'])->name('template-struk.update');

        Route::get('/pajak', [SettingController::class, 'pajak'])->name('pajak');
        Route::post('/pajak', [SettingController::class, 'updatePajak'])->name('pajak.update');

        Route::get('/backup-restore', [SettingController::class, 'backupRestore'])->name('backup-restore');
        Route::post('/backup-restore/backup', [SettingController::class, 'createBackup'])->name('backup-restore.backup');
        Route::get('/backup-restore/download/{filename}', [SettingController::class, 'downloadBackup'])->name('backup-restore.download');
        Route::post('/backup-restore/restore', [SettingController::class, 'restoreBackup'])->name('backup-restore.restore');
        Route::delete('/backup-restore/hapus', [SettingController::class, 'deleteBackup'])->name('backup-restore.hapus');

        Route::get('/database', [SettingController::class, 'database'])->name('database');
        Route::post('/database/migrate', [SettingController::class, 'runMigrate'])->name('database.migrate');
        Route::post('/database/wipe', [SettingController::class, 'wipeData'])->name('database.wipe');

        Route::get('/tentang', [SettingController::class, 'tentang'])->name('tentang');
    });

    // Peringatan pemulihan login dipasang di SETIAP halaman, jadi tombol penutupnya juga
    // harus terjangkau dari setiap halaman - bukan hanya dari dalam menu Pengaturan.
    Route::delete('/pemulihan-login/tutup', [SettingController::class, 'tutupCatatanPemulihan'])
        ->name('pemulihan-login.tutup');
});

// Gambar produk & logo toko. Dua prefix dipertahankan karena keduanya sudah dipakai di
// berbagai view (url('media/...') dan asset('storage/...')).
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media');
Route::get('/storage/{path}', [MediaController::class, 'show'])->where('path', '.*');
