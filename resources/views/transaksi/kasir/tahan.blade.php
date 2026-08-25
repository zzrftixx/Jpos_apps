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
        <a href="{{ route('kasir.waiting-list') }}" class="btn btn-outline text-amber-700 border-amber-300">
            📋 Pesanan / DP
        </a>
    </div>
</div>

@if($tertahan->isEmpty())
    <div class="card p-10 text-center text-slate-400">
        Tidak ada transaksi yang sedang ditahan.
        <div class="text-sm mt-2">
            Tahan transaksi lewat tombol <strong>⏸ Tahan Transaksi</strong> di Modul Kasir.
        </div>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($tertahan as $t)
        @php
            // Yang tertahan berjam-jam hampir pasti ditinggal pelanggannya - dan stoknya
            // ikut tertahan selama itu. Ditandai supaya kasir tahu mana yang perlu dilepas.
            $menit = $t->parked_at ? $t->parked_at->diffInMinutes(now()) : 0;
            $lama = $menit >= 120;
        @endphp
        <div class="card p-4 space-y-3 {{ $lama ? 'border border-red-200' : '' }}">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="font-medium">{{ $t->invoice_no }}</div>
                    <div class="text-xs {{ $lama ? 'text-red-600 font-medium' : 'text-slate-400' }}">
                        Ditahan {{ $t->parked_at?->diffForHumans() }}
                        @if($lama) &middot; stok masih tertahan @endif
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-semibold">Rp {{ number_format($t->total, 0, ',', '.') }}</div>
                    <div class="text-xs text-slate-400">{{ $t->items->count() }} baris</div>
                </div>
            </div>

            <div class="text-sm text-slate-600 border-t pt-2 space-y-0.5 max-h-32 overflow-y-auto">
                @foreach($t->items as $item)
                <div class="flex justify-between gap-2">
                    <span class="truncate">{{ $item->product_name }}</span>
                    <span class="shrink-0 text-slate-400">
                        @qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }}
                    </span>
                </div>
                @endforeach
            </div>

            <div class="text-xs text-slate-400">Kasir: {{ $t->cashier->name ?? '-' }}</div>

            <div class="flex gap-2">
                <form method="POST" action="{{ route('kasir.tahan.ambil', $t) }}" class="flex-1">
                    @csrf
                    <button class="btn btn-primary w-full justify-center">▶ Ambil &amp; Lanjutkan</button>
                </form>
                {{-- Membatalkan mengembalikan stoknya. Dikonfirmasi karena isi keranjangnya
                     hilang dan kasir harus memasukkannya ulang dari nol. --}}
                <form method="POST" action="{{ route('kasir.waiting-list.cancel', $t) }}"
                      onsubmit="return confirm('Batalkan transaksi tertahan {{ $t->invoice_no }}? Isi keranjangnya hilang dan stoknya dikembalikan.')">
                    @csrf
                    <button class="btn btn-danger">Batalkan</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
