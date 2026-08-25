@extends('layouts.app')
@section('title', 'Laporan Stok')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', ['jenis' => 'stok', 'filter' => []])
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4" data-live-search>
    <div>
        <label class="form-label">Cari Produk</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-input">
    </div>
    <label class="flex items-center gap-2 text-sm pb-2">
        <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }}> Hanya stok menipis
    </label>
    <button class="btn btn-primary">Terapkan</button>
</form>

{{-- Blok ini yang ditukar saat pencarian langsung; lihat
     public/vendor/jpos-live-search.js --}}
<div data-live-results>
<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead><tr><th>Produk</th><th>Kategori</th><th>Lokasi Rak</th><th>Stok</th><th>Stok Minimum</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($products as $p)
            <tr>
                <td class="font-medium">{{ $p->name }}</td>
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
                <td>@qty($p->stock) {{ $p->unit }}</td>
                <td>@qty($p->min_stock)</td>
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
            <tr><td colspan="7" class="text-center text-slate-400 py-8">Tidak ada produk.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
</div>
@endsection
