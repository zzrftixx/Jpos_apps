<?php

use App\Http\Controllers\Api\TabletSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes untuk Aplikasi Kasir Tablet Android (Offline-First POS)
|--------------------------------------------------------------------------
*/

Route::prefix('tablet')->middleware('tablet.token')->group(function () {
    Route::get('/status', [TabletSyncController::class, 'status']);
    Route::get('/katalog', [TabletSyncController::class, 'katalog']);
    Route::post('/sync-transaksi', [TabletSyncController::class, 'syncTransaksi']);
});
