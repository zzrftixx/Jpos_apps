@extends('layouts.app')
@section('title', 'Laporan Penjualan')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    {{-- order_status WAJIB ikut. Tanpa itu, pemilik toko yang menyaring "Dibatalkan" lalu
         menekan Unduh mendapat berkas berisi SELURUH transaksi - dan tidak akan sadar sampai
         ia memakainya untuk menagih atau melapor. Persis cacat yang sama pernah ada pada
         penyaring Metode Bayar. --}}
    @include('laporan._ekspor', ['jenis' => 'penjualan', 'filter' => array_filter([
        'from' => $from, 'to' => $to, 'metode' => $metode, 'order_status' => request('order_status'),
    ])])
</div>

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
        <label class="form-label">Status Pesanan</label>
        <select name="order_status" class="form-select">
            <option value="">Semua</option>
            <option value="completed" {{ request('order_status') == 'completed' ? 'selected' : '' }}>Lunas / Selesai</option>
            <option value="waiting" {{ request('order_status') == 'waiting' ? 'selected' : '' }}>Menunggu DP</option>
        </select>
    </div>
    <div>
        <label class="form-label">Metode Bayar</label>
        <select name="metode" class="form-select">
            <option value="">Semua</option>
            @foreach(\App\Support\MetodeBayar::pilihan() as $kunci => $labelMetode)
                <option value="{{ $kunci }}" {{ $metode === $kunci ? 'selected' : '' }}>{{ $labelMetode }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary">Terapkan</button>
</form>

<p class="text-xs text-slate-400 -mt-2 mb-4">* Ringkasan hanya menghitung transaksi aktif (transaksi yang dibatalkan tidak dimasukkan dalam perhitungan dan tidak dilaporkan).</p>

<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-4">
    <div class="card p-4"><div class="text-xs text-slate-500">Jumlah Transaksi</div><div class="text-xl font-bold">{{ $summary->trx_count ?? 0 }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Pendapatan</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_revenue ?? 0, 0, ',', '.') }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Diskon</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_discount ?? 0, 0, ',', '.') }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Pajak</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_tax ?? 0, 0, ',', '.') }}</div></div>
</div>

{{-- RINCIAN TRANSAKSI AKTIF --}}
@php
    $nLunas  = $rincianStatus['completed'] ?? null;
    $nTunggu = $rincianStatus['waiting'] ?? null;
    $vLunas  = (float) ($nLunas->nilai ?? 0);
    $vTunggu = (float) ($nTunggu->nilai ?? 0);
    $jLunas  = (int) ($nLunas->jumlah ?? 0);
    $jTunggu = (int) ($nTunggu->jumlah ?? 0);
@endphp
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
        <h2 class="font-semibold">Rincian Seluruh Transaksi</h2>
        <span class="text-sm text-slate-500 tabular-nums">
            Total Transaksi: Rp {{ number_format($vLunas + $vTunggu, 0, ',', '.') }} ({{ $jLunas + $jTunggu }} transaksi)
        </span>
    </div>

    <p class="text-xs text-slate-500 mb-3">
        Menampilkan ringkasan transaksi aktif. Dari jumlah di atas, <strong>hanya penjualan lunas</strong> yang dihitung sebagai omset pendapatan, sedangkan pesanan belum lunas masih berupa piutang barang/jasa.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="border rounded-lg px-3 py-2 bg-green-100 text-green-700 border-green-200">
            <div class="text-xs">Penjualan Lunas &middot; {{ $jLunas }} transaksi</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vLunas, 0, ',', '.') }}</div>
            <div class="text-xs">= omset pendapatan</div>
        </div>
        <div class="border rounded-lg px-3 py-2 bg-amber-100 text-amber-700 border-amber-200">
            <div class="text-xs">Belum Lunas &middot; {{ $jTunggu }} pesanan</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vTunggu, 0, ',', '.') }}</div>
            <div class="text-xs">piutang, belum jadi omset</div>
        </div>
        <div class="border rounded-lg px-3 py-2 bg-slate-100 text-slate-700 border-slate-200">
            <div class="text-xs">Total Transaksi Berjalan &middot; {{ $jLunas + $jTunggu }} transaksi</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vLunas + $vTunggu, 0, ',', '.') }}</div>
            <div class="text-xs">lunas + belum lunas</div>
        </div>
    </div>
</div>

{{-- UANG MASUK PER METODE.

     Ditaruh SETELAH ringkasan omset dan diberi penjelasan sendiri, bukan disatukan sebagai
     kartu kelima di deretan atas. Alasannya penting: angka di sini menjawab pertanyaan yang
     BERBEDA dari kartu-kartu di atasnya, dan menaruhnya berdampingan tanpa keterangan akan
     membuat pemilik toko mengira salah satunya rusak ketika keduanya tidak berjumlah sama. --}}
<div class="card p-4 mb-4">
    @php
        $totalUangMasuk = array_sum($uangMasuk);
        $totalNonTunai = $totalUangMasuk - ($uangMasuk['tunai'] ?? 0);
    @endphp
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
        <h2 class="font-semibold">Uang Masuk per Metode</h2>
        <span class="text-sm font-semibold text-slate-700 tabular-nums">
            Total Rp {{ number_format($totalUangMasuk, 0, ',', '.') }}
        </span>
    </div>

    <p class="text-xs text-slate-500 mb-3">
        Uang yang <strong>benar-benar diterima</strong> pada rentang tanggal ini - inilah angka
        yang diadu dengan isi laci dan mutasi rekening. Sengaja <strong>tidak sama</strong>
        dengan Total Pendapatan di atas: DP yang diterima bulan ini untuk pesanan yang baru
        selesai bulan depan sudah dihitung di sini tapi belum jadi omset, dan sebaliknya.
        Transaksi yang dibatalkan tidak dihitung karena uangnya sudah kembali ke pembeli.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2">
        @foreach(\App\Support\MetodeBayar::pilihan() as $kunci => $labelMetode)
            @continue($kunci === 'lainnya' && empty($uangMasuk['lainnya']))
            <a href="{{ route('laporan.penjualan', array_merge(request()->except('page'), ['metode' => $kunci])) }}#tabel-transaksi"
               class="block border rounded-lg px-3 py-2 transition hover:shadow-md cursor-pointer {{ \App\Support\MetodeBayar::kelas($kunci) }} {{ $metode === $kunci ? 'ring-2 ring-brand-500' : '' }}"
               title="Klik untuk melihat transaksi {{ $labelMetode }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold">{{ $labelMetode }}</span>
                    @if($metode === $kunci)
                        <span class="text-[10px] font-bold uppercase tracking-wider bg-white/90 px-1.5 py-0.2 rounded text-brand-700">Aktif</span>
                    @else
                        <span class="text-[10px] text-slate-400">&rarr;</span>
                    @endif
                </div>
                <div class="font-bold tabular-nums">Rp {{ number_format($uangMasuk[$kunci] ?? 0, 0, ',', '.') }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5">Lihat transaksi &rarr;</div>
            </a>
        @endforeach
        <a href="{{ route('laporan.penjualan', array_merge(request()->except(['page', 'metode']))) }}#tabel-transaksi"
           class="block border rounded-lg px-3 py-2 bg-slate-100 text-slate-800 border-slate-300 transition hover:shadow-md cursor-pointer {{ empty($metode) ? 'ring-2 ring-slate-400' : '' }}"
           title="Klik untuk melihat seluruh transaksi">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-700">Total Semua Metode</span>
                @if(empty($metode))
                    <span class="text-[10px] font-bold uppercase tracking-wider bg-white px-1.5 py-0.2 rounded text-slate-600">Semua</span>
                @else
                    <span class="text-[10px] text-slate-400">&rarr;</span>
                @endif
            </div>
            <div class="font-bold tabular-nums text-slate-900">Rp {{ number_format($totalUangMasuk, 0, ',', '.') }}</div>
            <div class="text-[10px] text-slate-500 mt-0.5">Non-Tunai: Rp {{ number_format($totalNonTunai, 0, ',', '.') }}</div>
        </a>
    </div>
</div>

<div id="tabel-transaksi" class="card overflow-hidden">
    @if($metode)
        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <span class="text-slate-500">Menyaring transaksi metode:</span>
                <span class="font-bold px-2.5 py-0.5 rounded-full border {{ \App\Support\MetodeBayar::kelas($metode) }}">
                    {{ \App\Support\MetodeBayar::label($metode) }}
                </span>
            </div>
            <a href="{{ route('laporan.penjualan', array_merge(request()->except(['page', 'metode']))) }}#tabel-transaksi"
               class="text-brand-600 hover:text-brand-700 font-semibold hover:underline flex items-center gap-1">
                <span>&times; Reset ke Semua Metode</span>
            </a>
        </div>
    @endif
    <table class="data-table w-full">
        <thead><tr><th>Invoice</th><th>Tanggal</th><th>Kasir</th><th>Pelanggan</th><th>Total</th><th>Metode Bayar</th><th>Status Retur</th><th>Status Pesanan</th><th></th></tr></thead>
        <tbody>
            @forelse($sales as $s)
            <tr>
                <td class="font-medium">
                    <a href="{{ route('kasir.receipt', $s) }}" target="_blank" class="text-blue-600 hover:underline">{{ $s->invoice_no }}</a>
                </td>
                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $s->cashier->name ?? '-' }}</td>
                <td>{{ $s->customer->name ?? 'Umum' }}</td>
                <td>Rp {{ number_format($s->total, 0, ',', '.') }}</td>
                <td>
                    {{-- Satu lencana per penerimaan uang. Pesanan yang DP-nya tunai lalu
                         dilunasi lewat QRIS menampilkan dua - keadaan yang sampai versi ini
                         tidak pernah tersimpan di mana pun. --}}
                    <div class="flex flex-wrap gap-1">
                        @forelse($s->payments as $bayar)
                            <span class="px-2 py-0.5 rounded-full text-xs border {{ \App\Support\MetodeBayar::kelas($bayar->method) }}">{{ $bayar->label_metode }}</span>
                        @empty
                            <span class="text-xs text-slate-400">-</span>
                        @endforelse
                    </div>
                </td>
                <td>
                    <span class="px-2 py-0.5 rounded-full text-xs
                        {{ $s->status === 'completed' ? 'bg-green-100 text-green-700' : ($s->status === 'returned' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ ucfirst($s->status) }}
                    </span>
                </td>
                <td>
                    <span class="px-2 py-0.5 rounded-full text-xs
                        {{ $s->order_status === 'completed' ? 'bg-green-100 text-green-700' : ($s->order_status === 'waiting' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                        {{ $s->order_status === 'completed' ? 'Lunas' : ($s->order_status === 'waiting' ? 'Menunggu DP' : 'Dibatalkan') }}
                    </span>
                </td>
                <td class="text-right space-x-2">
                    @if($s->order_status === 'completed' && auth()->user()->can_access('retur'))
                    <a href="{{ route('retur.index', ['invoice_no' => $s->invoice_no]) }}" class="text-blue-600 text-sm hover:underline">Edit</a>
                    <form method="POST" action="{{ route('retur.cancel-sale', $s) }}" class="inline" onsubmit="return confirm('Batalkan transaksi {{ $s->invoice_no }}? Stok yang belum diretur akan dikembalikan.')">
                        @csrf
                        <button class="text-red-600 text-sm hover:underline">Batalkan</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-slate-400 py-8">Belum ada transaksi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
