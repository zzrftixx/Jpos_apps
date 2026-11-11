<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Status Pesanan #{{ $sale->invoice_no }} - {{ $table->name }}</title>

    <link rel="stylesheet" href="@aset('vendor/jpos.css')">
    <script src="@aset('vendor/tailwind-play-3.4.17.js')"></script>
    <script defer src="@aset('vendor/alpine-3.15.12.min.js')"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen p-4 flex flex-col justify-between max-w-lg mx-auto">

    <div class="space-y-4">
        {{-- Kartu Sukses Atas --}}
        <div class="bg-white rounded-2xl border border-emerald-200 p-5 text-center shadow-xs">
            <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto shadow-xs animate-bounce">
                🎉
            </div>
            <h1 class="text-lg font-black text-slate-900 mt-3">Pesanan Berhasil Dikirim!</h1>
            <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                Pesanan Anda telah diterima oleh kasir &amp; sedang disiapkan di dapur.
            </p>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-center gap-2">
                <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 text-xs font-black px-3 py-1 rounded-full border border-amber-200">
                    🪑 {{ $table->name }}
                </span>
                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-xs font-mono font-bold px-3 py-1 rounded-full">
                    #{{ $sale->invoice_no }}
                </span>
            </div>

            {{-- Status Pemrosesan --}}
            <div class="mt-3.5 inline-block">
                @if($sale->order_status === 'waiting')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                        Status: Sedang Disiapkan / Menunggu Kasir
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Status: Selesai / Lunas
                    </span>
                @endif
            </div>
        </div>

        {{-- Rincian Menu Dipesan --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs space-y-3">
            <div class="flex items-center justify-between text-xs font-bold text-slate-700 pb-2 border-b border-slate-100">
                <span>Rincian Menu Dipesan</span>
                <span class="text-slate-500">{{ $sale->items->count() }} Menu</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($sale->items as $item)
                    <div class="py-2.5 flex items-start justify-between gap-3 text-xs">
                        <div class="flex-1">
                            <div class="font-bold text-slate-800">
                                {{ (int)$item->qty }}x {{ $item->product_name }}
                            </div>
                            @if($item->note)
                                <div class="text-[11px] text-amber-700 font-medium italic mt-0.5">
                                    Catatan: {{ $item->note }}
                                </div>
                            @endif
                        </div>
                        <div class="font-bold text-slate-900 text-right shrink-0">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-slate-200 flex items-center justify-between font-black text-sm text-slate-900">
                <span>Total Pesanan:</span>
                <span class="text-amber-600 text-base">Rp {{ number_format($sale->total, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- Petunjuk Bagi Pelanggan --}}
        <div class="bg-blue-50/70 border border-blue-200/80 rounded-xl p-3.5 text-xs text-blue-900 space-y-1.5">
            <div class="font-bold flex items-center gap-1.5">
                <span>ℹ️</span> Informasi Pembayaran &amp; Pengantaran:
            </div>
            <p class="text-[11px] text-blue-800 leading-relaxed">
                Mohon tetap duduk di <strong>{{ $table->name }}</strong>. Pesanan makanan dan minuman Anda akan segera diantar ke meja. Pembayaran dapat diselesaikan langsung di meja saat staf datang atau di meja kasir setelah selesai bersantap.
            </p>
        </div>
    </div>

    {{-- Tombol Aksi Bawah --}}
    <div class="mt-6 pt-4 border-t border-slate-200 space-y-2">
        <a href="{{ route('meja.order', $table->code) }}"
           class="w-full btn bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-2.5 px-4 rounded-xl shadow-xs text-center flex items-center justify-center gap-2">
            <span>+ Pesan Menu Tambahan</span>
        </a>
        <button type="button" onclick="window.location.reload()"
                class="w-full btn btn-outline bg-white border-slate-300 text-slate-600 hover:bg-slate-100 font-bold text-xs py-2 px-4 rounded-xl text-center">
            🔄 Perbarui Status Pesanan
        </button>
    </div>

</body>
</html>
