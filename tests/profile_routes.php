<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$user = User::first();
if ($user) {
    auth()->login($user);
}

$routes = [
    'GET /login' => Request::create('/login', 'GET'),
    'GET /dashboard' => Request::create('/dashboard', 'GET'),
    'GET /kasir' => Request::create('/kasir', 'GET'),
    'GET /master/produk' => Request::create('/master/produk', 'GET'),
    'GET /master/kategori' => Request::create('/master/kategori', 'GET'),
    'GET /master/satuan' => Request::create('/master/satuan', 'GET'),
    'GET /master/supplier' => Request::create('/master/supplier', 'GET'),
    'GET /master/pelanggan' => Request::create('/master/pelanggan', 'GET'),
    'GET /laporan/penjualan' => Request::create('/laporan/penjualan', 'GET'),
    'GET /laporan/mutasi-stok' => Request::create('/laporan/mutasi-stok', 'GET'),
    'GET /laporan/neraca' => Request::create('/laporan/neraca', 'GET'),
    'GET /pengaturan/toko' => Request::create('/pengaturan/toko', 'GET'),
    'GET /pengaturan/printer-struk' => Request::create('/pengaturan/printer-struk', 'GET'),
];

echo "========================================================================================\n";
echo "                   JPOS FULL ROUTE PERFORMANCE PROFILING REPORT                         \n";
echo "========================================================================================\n";
printf("%-30s | %8s | %10s | %10s | %8s\n", "Route", "Status", "Cold (1st)", "Warm (2nd)", "Queries");
echo str_repeat("-", 80) . "\n";

foreach ($routes as $label => $request) {
    // 1st run (Cold)
    DB::enableQueryLog();
    DB::flushQueryLog();
    $t0 = microtime(true);
    $responseCold = $httpKernel->handle($request);
    $t1 = microtime(true);
    $coldDuration = ($t1 - $t0) * 1000;
    $log = DB::getQueryLog();
    $queryCount = count($log);
    $status = $responseCold->getStatusCode();
    $httpKernel->terminate($request, $responseCold);

    // 2nd run (Warm)
    DB::flushQueryLog();
    $req2 = Request::create($request->getRequestUri(), 'GET');
    $t0 = microtime(true);
    $responseWarm = $httpKernel->handle($req2);
    $t1 = microtime(true);
    $warmDuration = ($t1 - $t0) * 1000;
    $httpKernel->terminate($req2, $responseWarm);

    printf("%-30s | %8d | %8.2f ms | %8.2f ms | %8d\n", $label, $status, $coldDuration, $warmDuration, $queryCount);
}

echo "========================================================================================\n";
