@extends('layouts.app')
@section('title', 'Laporan Stok')

@section('content')
@include('laporan._tabs')

<div class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <a href="{{ route('laporan.stok-opname') }}" class="btn btn-sm btn-outline flex items-center gap-1.5 text-xs text-brand-600 border-brand-200 hover:bg-brand-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        Buka Lembar Cek Stok Opname
    </a>
    @include('laporan._ekspor', ['jenis' => 'stok', 'filter' => array_filter(['q' => request('q'), 'low_stock' => request('low_stock')])])
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4" data-live-search>
    <div>
        <label class="form-label">Cari Produk</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Nama produk...">
    </div>
    <label class="flex items-center gap-2 text-sm pb-2 cursor-pointer">
        <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }}> Hanya stok menipis
    </label>
    <button class="btn btn-primary">Terapkan</button>
</form>

{{-- Blok ini yang ditukar saat pencarian langsung; lihat
     public/vendor/jpos-live-search.js --}}
<div data-live-results>
<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Barcode</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Lokasi Rak</th>
                <th class="text-right">Harga Modal</th>
                <th class="text-right">Harga Jual</th>
                <th class="text-right">Stok</th>
                <th class="text-right">Stok Min</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            <tr>
                <td class="font-mono text-xs text-slate-500">{{ $p->sku ?: '-' }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $p->barcode ?: '-' }}</td>
                <td class="font-medium text-slate-900">{{ $p->name }}</td>
                <td>{{ $p->category->name ?? '-' }}</td>
                {{-- Dipakai saat stok opname: barang menipis dikelompokkan per rak, jadi sekali
                     jalan bisa membawa semua yang perlu diisi di rak yang sama. --}}
                <td class="text-xs">
                    @if($p->rackSlot)
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600">{{ $p->rackSlot->label() }}</span>
                    @else
                        <span class="text-slate-300">belum ditaruh</span>
                    @endif
                </td>
                <td class="text-right font-mono text-xs text-slate-600">Rp {{ number_format($p->cost_price, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-xs text-slate-800 font-semibold">Rp {{ number_format($p->sell_price, 0, ',', '.') }}</td>
                <td class="text-right font-medium">@qty($p->stock) {{ $p->unit }}</td>
                <td class="text-right text-slate-400 text-xs">@qty($p->min_stock)</td>
                <td>
                    @if($p->isLowStock())
                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">Menipis</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Aman</span>
                    @endif
                </td>
                <td class="text-right"><a href="{{ route('laporan.stok.detail', $p) }}" class="text-blue-600 text-sm hover:underline">Riwayat</a></td>
            </tr>
            @empty
            <tr><td colspan="11" class="text-center text-slate-400 py-8">Tidak ada produk.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
</div>
@endsection
