@php
    $laporanTabs = [
        'laporan.penjualan' => 'Penjualan',
        'laporan.omset' => 'Omset',
        'laporan.laba' => 'Keuangan Laba',
        'laporan.neraca' => 'Neraca',
        'laporan.per-pelanggan' => 'Penjualan per Pelanggan',
        'laporan.stok' => 'Stok Barang',
        'laporan.terlaris' => 'Produk Terlaris',
    ];
@endphp
<div class="flex flex-wrap gap-1 mb-4 border-b border-slate-200">
    @foreach($laporanTabs as $tabRoute => $label)
        @php
            $isActive = request()->routeIs($tabRoute) || ($tabRoute === 'laporan.stok' && request()->routeIs('laporan.stok.detail'));
        @endphp
        <a href="{{ route($tabRoute) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $isActive ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
