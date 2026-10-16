@extends('layouts.app')
@section('title', 'Promo Produk')

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Promo Produk</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola promo diskon harga khusus dan cetak barcode label rak untuk kasir</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('promo.create') }}" class="btn btn-primary">+ Buat Promo Baru</a>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <form method="GET" action="{{ route('promo.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama promo..." class="form-input w-full">
            </div>
            <div class="w-44">
                <select name="status" class="form-select w-full">
                    <option value="">Semua Status</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Saring</button>
            @if(request()->hasAny(['q', 'status']))
                <a href="{{ route('promo.index') }}" class="btn btn-outline">Reset</a>
            @endif
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th>Nama Promo</th>
                        <th>Periode</th>
                        <th>Jumlah Produk</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promo)
                        @php
                            $today = now()->toDateString();
                            $expired = $promo->end_date && $promo->end_date->toDateString() < $today;
                            $upcoming = $promo->start_date && $promo->start_date->toDateString() > $today;
                        @endphp
                        <tr class="align-top border-b border-slate-100 hover:bg-slate-50/50">
                            <td>
                                <div class="font-bold text-slate-800">{{ $promo->name }}</div>
                                @if($promo->description)
                                    <div class="text-xs text-slate-500 mt-0.5">{{ Str::limit($promo->description, 60) }}</div>
                                @endif
                                <div class="mt-2 text-xs text-slate-600">
                                    <span class="font-medium">Contoh Produk:</span>
                                    @foreach($promo->items->take(3) as $item)
                                        <span class="inline-block bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded text-[11px] mr-1 mt-0.5">
                                            {{ $item->product->name ?? 'Produk' }} (Rp {{ number_format($item->promo_price, 0, ',', '.') }})
                                        </span>
                                    @endforeach
                                    @if($promo->items->count() > 3)
                                        <span class="text-slate-400 text-[11px]">+{{ $promo->items->count() - 3 }} lainnya</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-xs text-slate-600">
                                @if($promo->start_date || $promo->end_date)
                                    <div>{{ $promo->start_date ? $promo->start_date->format('d/m/Y') : 'Mulai sekarang' }}</div>
                                    <div class="text-slate-400">s/d</div>
                                    <div>{{ $promo->end_date ? $promo->end_date->format('d/m/Y') : 'Seterusnya' }}</div>
                                @else
                                    <span class="text-slate-500 italic">Berlaku seterusnya</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-blue-100 text-blue-700 border border-blue-200 font-semibold">
                                    {{ $promo->items_count }} Produk
                                </span>
                            </td>
                            <td>
                                @if(! $promo->is_active)
                                    <span class="badge bg-slate-100 text-slate-700 border border-slate-200">Nonaktif</span>
                                @elseif($expired)
                                    <span class="badge bg-amber-100 text-amber-700 border border-amber-200">Kedaluwarsa</span>
                                @elseif($upcoming)
                                    <span class="badge bg-blue-100 text-blue-700 border border-blue-200">Akan Datang</span>
                                @else
                                    <span class="badge bg-green-100 text-green-700 border border-green-200">Aktif</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <a href="{{ route('promo.cetak-barcode', $promo) }}" target="_blank" class="btn btn-sm btn-outline text-blue-700 border-blue-200 hover:bg-blue-50" title="Cetak Barcode Label Rak">
                                        <x-icon.barcode class="w-3.5 h-3.5 inline mr-1" /> Label Rak
                                    </a>
                                    <form action="{{ route('promo.toggle', $promo) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline {{ $promo->is_active ? 'text-amber-700 border-amber-200 hover:bg-amber-50' : 'text-green-700 border-green-200 hover:bg-green-50' }}">
                                            {{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('promo.edit', $promo) }}" class="btn btn-sm btn-outline">Edit</a>
                                    <form action="{{ route('promo.destroy', $promo) }}" method="POST" class="inline" onsubmit="return confirm('Hapus promo \'{{ addslashes($promo->name) }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline text-red-600 border-red-200 hover:bg-red-50">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-slate-500">
                                Belum ada promo produk. Klik <a href="{{ route('promo.create') }}" class="text-brand-600 font-semibold underline">Buat Promo Baru</a> untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($promotions->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $promotions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
