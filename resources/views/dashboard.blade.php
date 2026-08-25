@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Penjualan Hari Ini</div>
        <div class="text-2xl font-bold mt-1">Rp {{ number_format($todayRevenue, 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ $todayTransactions }} transaksi</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Penjualan Bulan Ini</div>
        <div class="text-2xl font-bold mt-1">Rp {{ number_format($monthRevenue, 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Total Produk Aktif</div>
        <div class="text-2xl font-bold mt-1">{{ number_format($totalProducts, 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Stok Menipis</div>
        <div class="text-2xl font-bold mt-1 {{ $lowStockCount > 0 ? 'text-red-600' : '' }}">{{ $lowStockCount }}</div>
        <a href="{{ route('laporan.stok', ['low_stock' => 1]) }}" class="text-xs text-blue-600 hover:underline">Lihat detail &rarr;</a>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Pesanan Menunggu DP</div>
        <div class="text-2xl font-bold mt-1 {{ $waitingCount > 0 ? 'text-amber-600' : '' }}">{{ $waitingCount }}</div>
        <div class="text-xs text-slate-400 mt-1">Sisa tagihan: Rp {{ number_format($waitingOutstanding, 0, ',', '.') }}</div>
        <a href="{{ route('kasir.waiting-list') }}" class="text-xs text-blue-600 hover:underline">Lihat semua &rarr;</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="card p-5 lg:col-span-2">
        <h3 class="font-semibold mb-4">Tren Penjualan 7 Hari Terakhir</h3>
        <canvas id="salesChart" height="110"></canvas>
    </div>
    <div class="card p-5">
        <h3 class="font-semibold mb-4">Produk Terlaris (Bulan Ini)</h3>
        <div class="space-y-3">
            @forelse($topProducts as $i => $p)
                <div class="flex items-center justify-between text-sm">
                    <span class="truncate">{{ $i + 1 }}. {{ $p->product_name }}</span>
                    <span class="text-slate-500 shrink-0">@qty($p->total_qty) terjual</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Belum ada data penjualan.</p>
            @endforelse
        </div>
    </div>
</div>

@if($lowStockProducts->count())
<div class="card p-5 mt-4">
    <h3 class="font-semibold mb-4 text-red-600">⚠ Produk Stok Menipis</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach($lowStockProducts as $p)
        <div class="border border-red-200 bg-red-50 rounded-lg p-3">
            <div class="font-medium text-sm truncate">{{ $p->name }}</div>
            <div class="text-xs text-red-600">Sisa: @qty($p->stock) {{ $p->unit }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

@push('scripts')
<script src="@aset('vendor/chart-4.5.1.umd.min.js')"></script>
<script>
    const chartData = @json($salesChart);
    const labels = chartData.map(d => new Date(d.date).toLocaleDateString('id-ID', {day:'2-digit', month:'short'}));
    const values = chartData.map(d => d.total);

    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Penjualan',
                data: values,
                borderColor: '#1c6ff0',
                backgroundColor: 'rgba(28,111,240,0.1)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: (v) => 'Rp' + v.toLocaleString('id-ID') } } }
        }
    });
</script>
@endpush
@endsection
