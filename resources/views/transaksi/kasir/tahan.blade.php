@extends('layouts.app')
@section('title', 'Transaksi Tertahan')

@section('content')
<div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
    <div>
        <h2 class="font-semibold text-lg">Transaksi Tertahan</h2>
        <p class="text-sm text-slate-500">
            Keranjang pelanggan yang ditahan sementara supaya antrean bisa dilayani dulu.
            Ambil kembali saat pelanggannya datang lagi.
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('kasir.index') }}" class="btn btn-outline">&larr; Kembali ke Kasir</a>
        <a href="{{ route('kasir.waiting-list') }}" class="btn btn-outline text-amber-700 border-amber-300 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            <span>Pesanan / DP</span>
        </a>
    </div>
</div>

@if($tertahan->isEmpty())
    <div class="card p-10 text-center text-slate-400">
        Tidak ada transaksi yang sedang ditahan.
        <div class="text-sm mt-2">
            Tahan transaksi lewat tombol <strong class="inline-flex items-center gap-1 text-slate-700 font-bold"><svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Tahan Transaksi</strong> di Modul Kasir.
        </div>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($tertahan as $t)
        @php
            // Yang tertahan berjam-jam hampir pasti ditinggal pelanggannya - dan stoknya
            // ikut tertahan selama itu. Ditandai supaya kasir tahu mana yang perlu dilepas.
            $menit = $t->parked_at ? $t->parked_at->diffInMinutes(now()) : 0;
            $lama = $menit >= 120;
        @endphp
        <div class="card-gerigi p-4 flex flex-col justify-between">
            <div>
                {{-- Aksen indikator status atas --}}
                <div class="h-1 {{ $lama ? 'bg-red-500' : 'bg-amber-400' }} rounded-t-xl -mt-4 -mx-4 mb-3"></div>

                {{-- Header: No Invoice & Status Badge (sesuai pesanan / DP) --}}
                <div class="flex items-center justify-between mb-2">
                    <span class="font-semibold text-slate-800">{{ $t->invoice_no }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $lama ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $lama ? 'Tertahan Lama' : 'Tertahan' }}
                    </span>
                </div>

                {{-- Info Meta: Waktu &middot; Pelanggan &middot; Kasir (sesuai pesanan / DP) --}}
                <div class="text-xs text-slate-400 mb-2">
                    {{ $t->parked_at ? $t->parked_at->format('d/m/Y H:i') : $t->created_at->format('d/m/Y H:i') }} &middot; <span class="font-medium text-slate-600">{{ $t->customer->name ?? 'Pelanggan Umum' }}</span> &middot; Kasir: {{ $t->cashier->name ?? '-' }}
                </div>

                {{-- Catatan transaksi jika ada --}}
                @if($t->note)
                    <div class="text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded px-2.5 py-1.5 italic mb-2">
                        {{ $t->note }}
                    </div>
                @endif

                {{-- Rincian Item (format identik dengan pesanan / DP) --}}
                <div class="text-sm space-y-1 border-t border-dashed border-slate-200 pt-2 max-h-36 overflow-y-auto">
                    @foreach($t->items as $item)
                    <div class="flex justify-between text-slate-600 text-xs sm:text-sm">
                        <span class="truncate pr-2">@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }}x {{ $item->product_name }}</span>
                        <span class="shrink-0">{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div>
                {{-- Rincian Total & Durasi (format identik dengan pesanan / DP) --}}
                <div class="border-t border-dashed border-slate-200 mt-2 pt-2 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Total</span>
                        <span class="font-medium text-slate-900">Rp {{ number_format($t->total, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs {{ $lama ? 'text-red-600 font-medium' : 'text-slate-500' }}">
                        <span>Ditahan</span>
                        <span>{{ $t->parked_at?->diffForHumans() }}@if($lama) &middot; stok tertahan @endif</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-400">
                        <span>Jumlah Barang</span>
                        <span>{{ $t->items->count() }} baris (@qty($t->items->sum('qty')) item)</span>
                    </div>
                </div>

                {{-- Tombol Aksi --}}
                <div class="flex items-center gap-2 mt-3 pt-1">
                    <form method="POST" action="{{ route('kasir.tahan.ambil', $t) }}" class="flex-1">
                        @csrf
                        <button class="btn btn-primary w-full justify-center text-sm flex items-center gap-1.5 py-2">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            <span>Ambil &amp; Lanjutkan</span>
                        </button>
                    </form>
                    {{-- Membatalkan mengembalikan stoknya. Dikonfirmasi karena isi keranjangnya
                         hilang dan kasir harus memasukkannya ulang dari nol. --}}
                    <form method="POST" action="{{ route('kasir.waiting-list.cancel', $t) }}"
                          onsubmit="return confirm('Batalkan transaksi tertahan {{ $t->invoice_no }}? Isi keranjangnya hilang dan stoknya dikembalikan.')">
                        @csrf
                        <button class="btn btn-danger justify-center text-sm py-2 px-3">Batalkan</button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
