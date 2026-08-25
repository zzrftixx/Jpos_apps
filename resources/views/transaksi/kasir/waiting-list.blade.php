@extends('layouts.app')
@section('title', 'Pesanan / Waiting List')

@section('content')
<div x-data="{ showPayModal: false, payOrder: null, openPay(o){ this.payOrder = o; this.showPayModal = true } }">

    <a href="{{ route('kasir.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Kembali ke Kasir</a>

    <div class="flex flex-wrap items-center justify-between gap-3 my-4">
        <div class="flex gap-2">
            <a href="{{ route('kasir.waiting-list', ['status' => 'waiting']) }}"
               class="btn {{ $status === 'waiting' ? 'btn-primary' : 'btn-outline' }}">Menunggu DP</a>
            <a href="{{ route('kasir.waiting-list', ['status' => 'completed']) }}"
               class="btn {{ $status === 'completed' ? 'btn-primary' : 'btn-outline' }}">Sudah Lunas</a>
            <a href="{{ route('kasir.waiting-list', ['status' => 'cancelled']) }}"
               class="btn {{ $status === 'cancelled' ? 'btn-primary' : 'btn-outline' }}">Dibatalkan</a>
        </div>
        <form method="GET" class="flex gap-2" data-live-search>
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari no. invoice..." class="form-input w-56">
            <button class="btn btn-outline">Cari</button>
        </form>
    @if(auth()->user()->can_access('laporan'))
        @include('laporan._ekspor', ['jenis' => 'piutang', 'filter' => []])
    @endif

    </div>

    {{-- Blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($orders as $o)
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="font-semibold">{{ $o->invoice_no }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs
                    {{ $o->order_status === 'waiting' ? 'bg-amber-100 text-amber-700' : ($o->order_status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') }}">
                    {{ $o->order_status === 'waiting' ? 'Menunggu DP' : ($o->order_status === 'completed' ? 'Lunas' : 'Dibatalkan') }}
                </span>
            </div>
            <div class="text-xs text-slate-400 mb-2">
                {{ $o->created_at->format('d/m/Y H:i') }} &middot; {{ $o->customer->name ?? 'Pelanggan Umum' }} &middot; Kasir: {{ $o->cashier->name ?? '-' }}
            </div>

            <div class="text-sm space-y-1 border-t pt-2">
                @foreach($o->items as $item)
                <div class="flex justify-between text-slate-600">
                    <span class="truncate">@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }}x {{ $item->product_name }}</span>
                    <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                </div>
                @endforeach
            </div>

            <div class="border-t mt-2 pt-2 text-sm space-y-1">
                <div class="flex justify-between"><span>Total</span><span class="font-medium">Rp {{ number_format($o->total, 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span>Dibayar (DP)</span><span>Rp {{ number_format($o->paid_amount, 0, ',', '.') }}</span></div>
                @if($o->order_status === 'waiting')
                <div class="flex justify-between text-amber-700 font-medium"><span>Sisa</span><span>Rp {{ number_format($o->remaining, 0, ',', '.') }}</span></div>
                @endif
                @if($o->due_date)
                <div class="flex justify-between text-slate-400"><span>Jatuh Tempo</span><span>{{ $o->due_date->format('d/m/Y') }}</span></div>
                @endif
            </div>

            <div class="flex items-center gap-2 mt-3">
                <a href="{{ route('kasir.receipt', $o) }}" target="_blank" class="btn btn-outline flex-1 justify-center text-sm">🖨️ Struk</a>
                @if($o->order_status === 'waiting')
                <a href="{{ route('kasir.waiting-list.edit', $o) }}" class="btn btn-outline justify-center text-sm">✏️ Edit</a>
                <button @click='openPay(@json(["id" => $o->id, "invoice_no" => $o->invoice_no, "remaining" => $o->remaining]))' class="btn btn-primary flex-1 justify-center text-sm">Lunasi</button>
                <form method="POST" action="{{ route('kasir.waiting-list.cancel', $o) }}" onsubmit="return confirm('Batalkan pesanan ini? Stok akan dikembalikan.')">
                    @csrf
                    <button class="btn btn-danger justify-center text-sm">Batal</button>
                </form>
                @endif

                @php
                    /* Peringatannya sengaja berbeda per status, karena akibatnya juga berbeda:
                       pesanan yang belum lunas mengembalikan stok, transaksi lunas ikut
                       menghilang dari Laporan Penjualan, dan yang sudah dibatalkan stoknya
                       memang sudah kembali sejak tadi. */
                    $konfirmasiHapus = match ($o->order_status) {
                        'waiting' => "Hapus PERMANEN pesanan {$o->invoice_no}?\\n\\nStok yang belum diambil akan dikembalikan, dan pesanan ini hilang dari daftar untuk selamanya.",
                        'completed' => "Hapus PERMANEN transaksi {$o->invoice_no}?\\n\\nTransaksi ini akan HILANG DARI LAPORAN PENJUALAN, stok yang belum diretur dikembalikan, dan retur yang menempel padanya ikut terhapus. Kalau barangnya dikembalikan pelanggan, gunakan Retur - bukan Hapus.",
                        default => "Hapus PERMANEN catatan {$o->invoice_no}?\\n\\nStoknya sudah dikembalikan saat dibatalkan, jadi tidak ada stok yang berubah. Catatan ini hilang untuk selamanya.",
                    };
                @endphp
                @if($o->order_status !== 'completed' || auth()->user()->can_access('laporan'))
                <form method="POST" action="{{ route('kasir.waiting-list.destroy', $o) }}" onsubmit="return confirm('{{ $konfirmasiHapus }}')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline justify-center text-sm text-red-600 border-red-200 hover:bg-red-50" title="Hapus permanen">🗑️</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-full text-center text-slate-400 py-16">Tidak ada pesanan pada status ini.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
    </div>

    {{-- Pay Modal --}}
    <div x-show="showPayModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showPayModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
            <h3 class="font-semibold text-lg mb-1">Pelunasan Pesanan</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="payOrder ? payOrder.invoice_no : ''"></p>
            <template x-if="payOrder">
                <form :action="'{{ url('kasir/waiting-list') }}/' + payOrder.id + '/pay'" method="POST" class="space-y-3">
                    @csrf
                    <div class="text-sm bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Sisa tagihan: <strong x-text="'Rp ' + Math.round(payOrder.remaining).toLocaleString('id-ID')"></strong>
                    </div>
                    <div>
                        <label class="form-label">Jumlah Bayar</label>
                        <input type="text" data-jpos-number data-number-decimals="2" name="amount" x-number.oneway="payOrder.remaining" required class="form-input">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showPayModal=false" class="btn btn-outline">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
@endsection
