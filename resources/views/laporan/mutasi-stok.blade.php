@extends('layouts.app')
@section('title', 'Laporan Rekap Mutasi Stok')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', [
        'jenis' => 'mutasi-stok',
        'filter' => array_filter([
            'from' => $from,
            'to' => $to,
            'category_id' => request('category_id'),
            'q' => request('q'),
            'hanya_ada_mutasi' => request('hanya_ada_mutasi'),
        ])
    ])
</div>

{{-- Filter & Rentang Tanggal --}}
<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <div>
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="from" value="{{ $from }}" class="form-input">
    </div>
    <div>
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="to" value="{{ $to }}" class="form-input">
    </div>
    <div>
        <label class="form-label">Kategori</label>
        <select name="category_id" class="form-select">
            <option value="">Semua Kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex-1 min-w-[200px]">
        <label class="form-label">Cari Produk / Barcode</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Ketik nama produk atau barcode..." class="form-input">
    </div>
    <div class="pb-2">
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer select-none">
            <input type="checkbox" name="hanya_ada_mutasi" value="1" {{ request('hanya_ada_mutasi') ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span>Hanya yang ada pergerakan stok</span>
        </label>
    </div>
    <button class="btn btn-primary">Terapkan</button>
</form>

{{-- Keterangan / Panduan Singkat untuk Pemilik Toko / Klien Petshop --}}
<div class="mb-4 bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-900 flex items-start gap-2.5">
    <span class="text-base shrink-0 mt-0.5">ℹ️</span>
    <div>
        <span class="font-bold">Panduan Pemantauan Stok:</span>
        <span class="text-blue-800">
            Kolom <strong>Stok Awal</strong> memperkirakan posisi stok sebelum pergerakan pada periode terpilih (Stok Akhir - Masuk + Keluar).
            Kolom <strong>Masuk (Kulakan)</strong> merangkum seluruh pembelian barang dari distributor dalam periode terpilih.
            Kolom <strong>Keluar (Terjual Kasir)</strong> merangkum total produk yang selesai dibayar kasir dalam periode terpilih.
            Kolom <strong>Sisa Stok Sekarang</strong> adalah jumlah fisik barang riil yang saat ini siap dijual di toko.
        </span>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead>
            <tr>
                <th>#</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th class="text-right">Stok Awal</th>
                <th class="text-right">Masuk (Kulakan)</th>
                <th class="text-right">Keluar (Terjual Kasir)</th>
                <th class="text-right">Sisa Stok Sekarang</th>
                <th class="text-right">Kartu Stok</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $i => $p)
            <tr>
                <td class="text-slate-400 text-xs">{{ $products->firstItem() + $i }}</td>
                <td>
                    <div class="font-semibold text-slate-800">{{ $p->name }}</div>
                    @if($p->barcode)
                        <div class="text-[11px] font-mono text-slate-400">{{ $p->barcode }}</div>
                    @endif
                </td>
                <td>
                    @if($p->category)
                        <span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-600">{{ $p->category->name }}</span>
                    @else
                        <span class="text-slate-300 text-xs">-</span>
                    @endif
                </td>
                <td class="text-xs text-slate-600 font-medium">{{ $p->unit ?? 'Pcs' }}</td>
                <td class="text-right font-mono font-medium text-slate-700">@qty($p->stok_awal)</td>
                <td class="text-right font-mono font-bold text-emerald-700">
                    @if($p->total_masuk > 0)
                        +@qty($p->total_masuk)
                    @else
                        <span class="text-slate-300 font-normal">0</span>
                    @endif
                </td>
                <td class="text-right font-mono font-bold text-red-600">
                    @if($p->total_keluar > 0)
                        -@qty($p->total_keluar)
                    @else
                        <span class="text-slate-300 font-normal">0</span>
                    @endif
                </td>
                <td class="text-right font-mono font-bold text-slate-800">
                    @qty($p->stock)
                </td>
                <td class="text-right">
                    <a href="{{ route('laporan.stok.detail', $p) }}" class="text-brand-600 text-xs font-semibold hover:underline">
                        Riwayat &rarr;
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-slate-400 py-8">
                    Tidak ada data produk yang cocok dengan penyaring yang dipilih.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
