@extends('layouts.app')
@section('title', 'Promo Produk')

@section('content')
<div x-data="promoIndexApp()">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Promo Produk</h1>
            <p class="text-xs text-slate-500 mt-0.5">Kelola promo harga khusus dan cetak barcode label rak untuk kasir</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('promo.create') }}" class="btn btn-primary inline-flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Buat Promo Baru</span>
            </a>
        </div>
    </div>

    {{-- Filter & Pencarian --}}
    <div class="card p-4 mb-4 shadow-sm">
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

    {{-- Tabel Utama Daftar Promo --}}
    <div class="card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th style="min-width: 260px;">Nama Promo</th>
                        <th style="min-width: 170px;">Periode</th>
                        <th style="min-width: 130px;" class="text-center">Produk Promo</th>
                        <th style="min-width: 110px;" class="text-center">Status</th>
                        <th style="min-width: 240px;" class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($promotions as $promo)
                        @php
                            $today = now()->toDateString();
                            $expired = $promo->end_date && $promo->end_date->toDateString() < $today;
                            $upcoming = $promo->start_date && $promo->start_date->toDateString() > $today;
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            {{-- 1. Nama Promo & Deskripsi Singkat --}}
                            <td class="align-middle">
                                <div class="font-bold text-slate-800 text-sm leading-snug">{{ $promo->name }}</div>
                                @if($promo->description)
                                    <div class="text-xs text-slate-500 mt-0.5 truncate max-w-md">{{ $promo->description }}</div>
                                @endif
                            </td>

                            {{-- 2. Periode Promo --}}
                            <td class="align-middle text-xs text-slate-600 whitespace-nowrap">
                                @if($promo->start_date || $promo->end_date)
                                    <div class="font-medium text-slate-700">{{ $promo->start_date ? $promo->start_date->format('d/m/Y') : 'Mulai sekarang' }}</div>
                                    <div class="text-slate-400 text-[11px]">s/d {{ $promo->end_date ? $promo->end_date->format('d/m/Y') : 'Seterusnya' }}</div>
                                @else
                                    <span class="text-slate-500 italic">Berlaku seterusnya</span>
                                @endif
                            </td>

                            {{-- 3. Jumlah Produk Promo (Pill Rapi -> Klik untuk Buka Rincian) --}}
                            <td class="align-middle text-center whitespace-nowrap">
                                <button type="button" 
                                        @click="openPromo({{ $promo->id }})" 
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 hover:border-brand-300 transition-colors cursor-pointer shadow-sm"
                                        title="Klik untuk melihat rincian produk promo">
                                    <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>{{ $promo->items_count }} Produk</span>
                                </button>
                            </td>

                            {{-- 4. Status Promo --}}
                            <td class="align-middle text-center whitespace-nowrap">
                                @if(! $promo->is_active)
                                    <span class="badge bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold">Nonaktif</span>
                                @elseif($expired)
                                    <span class="badge bg-amber-100 text-amber-700 border border-amber-200 text-xs font-semibold">Kedaluwarsa</span>
                                @elseif($upcoming)
                                    <span class="badge bg-blue-100 text-blue-700 border border-blue-200 text-xs font-semibold">Akan Datang</span>
                                @else
                                    <span class="badge bg-green-100 text-green-700 border border-green-200 text-xs font-semibold">Aktif</span>
                                @endif
                            </td>

                            {{-- 5. Aksi --}}
                            <td class="align-middle text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <a href="{{ route('promo.cetak-barcode', $promo) }}" target="_blank" class="btn btn-sm btn-outline text-blue-700 border-blue-200 hover:bg-blue-50 py-1 px-2.5 text-xs font-medium inline-flex items-center gap-1.5 shrink-0" title="Cetak Barcode Label Rak">
                                        <svg style="width: 14px; height: 14px; min-width: 14px; max-width: 14px; flex-shrink: 0;" viewBox="0 0 24 24" fill="currentColor">
                                            <rect x="2" y="4" width="2" height="16" rx="0.75"/>
                                            <rect x="6" y="4" width="3" height="16" rx="0.75"/>
                                            <rect x="11" y="4" width="1.5" height="16" rx="0.75"/>
                                            <rect x="14.5" y="4" width="3" height="16" rx="0.75"/>
                                            <rect x="19.5" y="4" width="2" height="16" rx="0.75"/>
                                        </svg>
                                        <span class="whitespace-nowrap">Label Rak</span>
                                    </a>
                                    <form action="{{ route('promo.toggle', $promo) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline py-1 px-2.5 text-xs font-medium {{ $promo->is_active ? 'text-amber-700 border-amber-200 hover:bg-amber-50' : 'text-green-700 border-green-200 hover:bg-green-50' }}">
                                            {{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('promo.edit', $promo) }}" class="btn btn-sm btn-outline py-1 px-2.5 text-xs font-medium">Edit</a>
                                    <form action="{{ route('promo.destroy', $promo) }}" method="POST" class="inline" onsubmit="return confirm('Hapus promo \'{{ addslashes($promo->name) }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline text-red-600 border-red-200 hover:bg-red-50 py-1 px-2.5 text-xs font-medium">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-500">
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

    {{-- MODAL DETAIL PRODUK PROMO --}}
    <div x-show="detailModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="detailModalOpen = false">
        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform transition-all"
             @click.away="detailModalOpen = false">
            {{-- Header Modal --}}
            <div class="bg-brand-600 px-6 py-4 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span x-text="activePromo.name"></span>
                    </h3>
                    <p class="text-xs text-blue-100 mt-0.5" x-show="activePromo.description" x-text="activePromo.description"></p>
                    <div class="text-[11px] text-blue-100 mt-1 font-mono">
                        Periode: <span x-text="activePromo.start_date || 'Mulai sekarang'"></span> s/d <span x-text="activePromo.end_date || 'Seterusnya'"></span>
                    </div>
                </div>
                <button type="button" @click="detailModalOpen = false" class="text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10 transition" title="Tutup">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Isi Tabel Produk --}}
            <div class="p-5 max-h-96 overflow-y-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 uppercase text-[11px] font-semibold border-b border-slate-200">
                            <th class="py-2.5 px-3">#</th>
                            <th class="py-2.5 px-3">Nama Produk</th>
                            <th class="py-2.5 px-3 text-right">Harga Normal</th>
                            <th class="py-2.5 px-3 text-right">Harga Promo</th>
                            <th class="py-2.5 px-3 text-center">Hemat / Diskon</th>
                            <th class="py-2.5 px-3">Barcode Promo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, idx) in (activePromo.items || [])" :key="idx">
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 text-slate-400 font-bold" x-text="idx + 1"></td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800" x-text="item.name"></td>
                                <td class="py-2.5 px-3 text-right text-slate-400">
                                    <del>Rp <span x-text="formatNumber(item.normal_price)"></span></del>
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-green-700">
                                    Rp <span x-text="formatNumber(item.promo_price)"></span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <template x-if="item.normal_price > item.promo_price">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                            Hemat <span x-text="Math.round(((item.normal_price - item.promo_price) / item.normal_price) * 100) + '%'"></span>
                                        </span>
                                    </template>
                                    <template x-if="item.normal_price <= item.promo_price">
                                        <span class="text-slate-400">-</span>
                                    </template>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-slate-700 text-[11px]" x-text="item.barcode"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Footer Modal --}}
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <a :href="activePromo.cetak_url" target="_blank" class="btn btn-sm btn-outline text-blue-700 border-blue-200 hover:bg-blue-50 inline-flex items-center gap-1.5 font-medium shrink-0">
                    <svg style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="2" y="4" width="2" height="16" rx="0.75"/>
                        <rect x="6" y="4" width="3" height="16" rx="0.75"/>
                        <rect x="11" y="4" width="1.5" height="16" rx="0.75"/>
                        <rect x="14.5" y="4" width="3" height="16" rx="0.75"/>
                        <rect x="19.5" y="4" width="2" height="16" rx="0.75"/>
                    </svg>
                    <span>Cetak Label Rak Barcode</span>
                </a>
                <button type="button" @click="detailModalOpen = false" class="btn btn-sm btn-outline">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- Data Promosi untuk Modal (HUKUM 11: Memakai container script json murni) --}}
@php
    $promosDataMap = [];
    foreach ($promotions as $promo) {
        $promosDataMap[$promo->id] = [
            'id' => $promo->id,
            'name' => $promo->name,
            'description' => $promo->description,
            'start_date' => $promo->start_date ? $promo->start_date->format('d/m/Y') : null,
            'end_date' => $promo->end_date ? $promo->end_date->format('d/m/Y') : null,
            'is_active' => (bool)$promo->is_active,
            'cetak_url' => route('promo.cetak-barcode', $promo),
            'items' => $promo->items->map(fn($it) => [
                'name' => $it->product->name ?? 'Produk',
                'normal_price' => (float)($it->product->sell_price ?? 0),
                'promo_price' => (float)$it->promo_price,
                'barcode' => $it->promo_barcode ?: '-',
            ]),
        ];
    }
@endphp
<script type="application/json" id="promos-modal-data">
    {!! json_encode($promosDataMap) !!}
</script>

<script>
function promoIndexApp() {
    var promosMap = {};
    try {
        var el = document.getElementById('promos-modal-data');
        if (el) promosMap = JSON.parse(el.textContent);
    } catch (e) {
        promosMap = {};
    }

    return {
        promosById: promosMap,
        detailModalOpen: false,
        activePromo: {
            id: null,
            name: '',
            description: '',
            start_date: '',
            end_date: '',
            cetak_url: '#',
            items: []
        },
        openPromo: function(id) {
            if (this.promosById[id]) {
                this.activePromo = this.promosById[id];
                this.detailModalOpen = true;
            }
        },
        formatNumber: function(val) {
            var num = Number(val) || 0;
            return num.toLocaleString('id-ID');
        }
    };
}
</script>
@endsection
