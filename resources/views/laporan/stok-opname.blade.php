@extends('layouts.app')
@section('title', 'Cek Stok Opname')

@section('content')
@include('laporan._tabs')

<style>
@media print {
    body { background: #fff !important; color: #000 !important; font-size: 10px !important; }
    header, aside, nav, .sidebar, .btn, form, [data-live-search], .pagination, .no-print, [data-live-results] > .mt-4 {
        display: none !important;
    }
    .card { box-shadow: none !important; border: 1px solid #94a3b8 !important; padding: 0 !important; }
    .print-header { display: block !important; margin-bottom: 12px; }
    .print-signatures { display: flex !important; justify-content: space-between; margin-top: 30px; page-break-inside: avoid; }
    table { width: 100% !important; border-collapse: collapse !important; font-size: 9px !important; }
    th, td { border: 1px solid #94a3b8 !important; padding: 4px 5px !important; }
    th { background-color: #f1f5f9 !important; color: #000 !important; }
}
.print-header, .print-signatures { display: none; }
</style>

{{-- Header Cetak Khusus Saat Dicetak ke Kertas / PDF Browser --}}
@php
    $profilToko = \App\Models\Setting::get('store_profile');
@endphp
<div class="print-header text-center border-b pb-3 mb-4">
    <div class="text-xl font-bold uppercase tracking-wider text-slate-800">{{ $profilToko['name'] ?? 'Toko Jayla' }}</div>
    <div class="text-xs text-slate-600">{{ $profilToko['address'] ?? '' }} {{ !empty($profilToko['phone']) ? '• Telp: ' . $profilToko['phone'] : '' }}</div>
    <div class="text-base font-bold mt-2 uppercase text-slate-900 border-t border-b border-dashed border-slate-300 py-1 inline-block px-4">
        Lembar Kerja Cek Stok Opname
    </div>
    <div class="text-[11px] text-slate-500 mt-1">
        Tanggal Cetak: {{ now()->translatedFormat('d F Y H:i') }} • Petugas: {{ auth()->user()->name ?? 'Admin' }}
        @if(!empty($q)) • Cari: "{{ $q }}" @endif
        @if(!empty($categoryId)) • Filter Kategori Aktif @endif
        @if(!empty($rackId)) • Filter Rak Aktif @endif
    </div>
</div>

<div class="no-print">
    {{-- Ringkasan Indikator Metrik --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">Total Jenis Produk</div>
            <div class="text-xl font-bold text-slate-800">{{ number_format($summary->total_items ?? 0, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Item barang aktif</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">Total Stok Sistem</div>
            <div class="text-xl font-bold text-blue-600">@qty($summary->total_stock ?? 0)</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Posisi tercatat di komputer</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">Total Nilai Modal (HPP)</div>
            <div class="text-xl font-bold text-slate-800">Rp {{ number_format($summary->total_cost_value ?? 0, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Valuasi aset modal fisik</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">Total Potensi Nilai Jual</div>
            <div class="text-xl font-bold text-green-600">Rp {{ number_format($summary->total_sell_value ?? 0, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Potensi omset penjualan</div>
        </div>
    </div>

    {{-- Toolbar Aksi dan Ekspor --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-base font-semibold text-slate-800">Lembar Cek Stok Opname</h2>
            <p class="text-xs text-slate-500">Pencocokan kuantitas stok fisik di rak toko dengan stok tercatat pada komputer.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()" class="btn btn-sm btn-primary flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Lembar Kerja
            </button>
            @include('laporan._ekspor', [
                'jenis' => 'stok-opname',
                'filter' => array_filter([
                    'q' => request('q'),
                    'category_id' => request('category_id'),
                    'rack_id' => request('rack_id'),
                    'low_stock' => request('low_stock'),
                    'sort_by' => request('sort_by'),
                    'semua' => request('semua'),
                ])
            ])
        </div>
    </div>

    {{-- Form Filter Pencarian --}}
    <form method="GET" class="card p-4 mb-4 flex flex-wrap items-end gap-3" data-live-search>
        <div class="w-56">
            <label class="form-label text-xs">Cari Produk / Barcode / SKU</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Ketik nama, SKU, barcode..." class="form-input text-xs w-full">
        </div>
        <div class="w-40">
            <label class="form-label text-xs">Kategori</label>
            <select name="category_id" class="form-select text-xs w-full">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-36">
            <label class="form-label text-xs">Lokasi Rak</label>
            <select name="rack_id" class="form-select text-xs w-full">
                <option value="">Semua Rak</option>
                @foreach($racks as $r)
                    <option value="{{ $r->id }}" {{ request('rack_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
            <label class="form-label text-xs">Urutan</label>
            <select name="sort_by" class="form-select text-xs w-full">
                <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>Nama Produk (A-Z)</option>
                <option value="category" {{ request('sort_by') == 'category' ? 'selected' : '' }}>Berdasarkan Kategori</option>
                <option value="stock_asc" {{ request('sort_by') == 'stock_asc' ? 'selected' : '' }}>Stok Paling Sedikit</option>
                <option value="stock_desc" {{ request('sort_by') == 'stock_desc' ? 'selected' : '' }}>Stok Paling Banyak</option>
            </select>
        </div>
        <div class="flex items-center gap-4 pb-2">
            <label class="flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }}>
                Stok Menipis
            </label>
            <label class="flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer" title="Tampilkan seluruh data tanpa paginasi untuk kemudahan cetak">
                <input type="checkbox" name="semua" value="1" {{ request('semua') ? 'checked' : '' }}>
                Tampilkan Semua (Cetak)
            </label>
        </div>
        <div>
            <button class="btn btn-primary text-xs">Terapkan</button>
            @if(request()->anyFilled(['q', 'category_id', 'rack_id', 'low_stock', 'sort_by', 'semua']))
                <a href="{{ route('laporan.stok-opname') }}" class="btn btn-secondary text-xs ml-1">Reset</a>
            @endif
        </div>
    </form>
</div>

{{-- Tabel Data Cek Stok Opname --}}
<div data-live-results>
<div class="card overflow-hidden">
    <table class="data-table w-full text-left text-xs">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-700">
                <th class="py-2.5 px-2 text-center w-10">No</th>
                <th class="py-2.5 px-2">SKU</th>
                <th class="py-2.5 px-2">Barcode</th>
                <th class="py-2.5 px-2">Nama Produk</th>
                <th class="py-2.5 px-2">Kategori</th>
                <th class="py-2.5 px-2">Lokasi Rak</th>
                <th class="py-2.5 px-2 text-right">Harga Modal</th>
                <th class="py-2.5 px-2 text-right">Harga Jual</th>
                <th class="py-2.5 px-2 text-right bg-blue-50/50">Stok Sistem</th>
                <th class="py-2.5 px-2 text-center w-20 bg-amber-50/50">Cek Fisik</th>
                <th class="py-2.5 px-2 text-center w-16 bg-slate-50">Selisih</th>
                <th class="py-2.5 px-2 text-right">Nilai Modal</th>
                <th class="py-2.5 px-2 text-right">Nilai Jual</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @php
                $currentPage = method_exists($products, 'currentPage') ? $products->currentPage() : 1;
                $perPage = method_exists($products, 'perPage') ? $products->perPage() : $products->count();
                $no = ($currentPage - 1) * $perPage;
            @endphp
            @forelse($products as $p)
            @php
                $no++;
                $nilaiModal = (float) $p->stock * (float) $p->cost_price;
                $nilaiJual = (float) $p->stock * (float) $p->sell_price;
            @endphp
            <tr class="hover:bg-slate-50/75 transition">
                <td class="text-center text-slate-500 font-mono py-2">{{ $no }}</td>
                <td class="font-mono text-slate-600 text-[11px]">{{ $p->sku ?: '-' }}</td>
                <td class="font-mono text-slate-600 text-[11px]">{{ $p->barcode ?: '-' }}</td>
                <td class="font-medium text-slate-900">
                    <div>{{ $p->name }}</div>
                    @if($p->isLowStock())
                        <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[10px] bg-red-100 text-red-700">Stok Menipis</span>
                    @endif
                </td>
                <td class="text-slate-600">{{ $p->category->name ?? '-' }}</td>
                <td class="text-xs">
                    @if($p->rackSlot)
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-[11px]">{{ $p->rackSlot->label() }}</span>
                    @else
                        <span class="text-slate-300">-</span>
                    @endif
                </td>
                <td class="text-right font-mono text-slate-700">Rp {{ number_format($p->cost_price, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-slate-900 font-medium">Rp {{ number_format($p->sell_price, 0, ',', '.') }}</td>
                <td class="text-right font-mono font-bold text-blue-700 bg-blue-50/30">
                    @qty($p->stock) <span class="text-[10px] font-normal text-slate-500">{{ $p->unit ?: 'Pcs' }}</span>
                </td>
                {{-- Kolom Cek Fisik untuk ditulis tangan saat stok opname --}}
                <td class="text-center bg-amber-50/20 py-1.5">
                    <div class="w-16 h-6 mx-auto border border-dashed border-amber-300 rounded bg-white print:border-solid print:border-black print:h-5"></div>
                </td>
                {{-- Kolom Selisih --}}
                <td class="text-center py-1.5">
                    <div class="w-12 h-6 mx-auto border border-dashed border-slate-300 rounded bg-white print:border-solid print:border-black print:h-5"></div>
                </td>
                <td class="text-right font-mono text-slate-600">Rp {{ number_format($nilaiModal, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-green-700 font-medium">Rp {{ number_format($nilaiJual, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="13" class="text-center py-8 text-slate-400">Tidak ada produk yang sesuai dengan filter pencarian.</td>
            </tr>
            @endforelse
        </tbody>
        @if($products->count() > 0)
        <tfoot>
            <tr class="bg-slate-100 font-semibold border-t-2 border-slate-300 text-slate-900">
                <td colspan="8" class="text-right py-2.5 px-3">TOTAL KESELURUHAN (HALAMAN INI):</td>
                <td class="text-right font-mono font-bold text-blue-700 py-2.5 px-2">@qty($products->sum('stock'))</td>
                <td colspan="2" class="text-center text-slate-400 text-[10px] italic">Hasil Cek Rak</td>
                <td class="text-right font-mono text-slate-800 py-2.5 px-2">Rp {{ number_format($products->sum(fn($p) => (float)$p->stock * (float)$p->cost_price), 0, ',', '.') }}</td>
                <td class="text-right font-mono text-green-800 py-2.5 px-2">Rp {{ number_format($products->sum(fn($p) => (float)$p->stock * (float)$p->sell_price), 0, ',', '.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@if(method_exists($products, 'hasPages') && $products->hasPages())
    <div class="mt-4 no-print">{{ $products->links() }}</div>
@endif
</div>

{{-- Blok Tanda Tangan Cetak Fisik Saat Di-print ke Kertas --}}
<div class="print-signatures text-xs text-center">
    <div class="w-48">
        <div>Petugas Pelaksana Opname,</div>
        <div class="h-16"></div>
        <div class="border-b border-slate-400 font-semibold">( ............................................ )</div>
        <div class="text-[10px] text-slate-500 mt-1">Nama &amp; Tanda Tangan</div>
    </div>
    <div class="w-48">
        <div>Petugas Saksi / Verifikator,</div>
        <div class="h-16"></div>
        <div class="border-b border-slate-400 font-semibold">( ............................................ )</div>
        <div class="text-[10px] text-slate-500 mt-1">Nama &amp; Tanda Tangan</div>
    </div>
    <div class="w-48">
        <div>Pemilik Toko / Penanggung Jawab,</div>
        <div class="h-16"></div>
        <div class="border-b border-slate-400 font-semibold">( ............................................ )</div>
        <div class="text-[10px] text-slate-500 mt-1">Tanda Tangan &amp; Stempel</div>
    </div>
</div>
@endsection
