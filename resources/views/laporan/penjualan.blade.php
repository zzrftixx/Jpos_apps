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
            <option value="cancelled" {{ request('order_status') == 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
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

<p class="text-xs text-slate-400 -mt-2 mb-4">* Ringkasan di bawah hanya menghitung transaksi berstatus Lunas/Selesai (tidak termasuk pesanan yang masih DP atau dibatalkan).</p>

<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-4">
    <div class="card p-4"><div class="text-xs text-slate-500">Jumlah Transaksi</div><div class="text-xl font-bold">{{ $summary->trx_count ?? 0 }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Pendapatan</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_revenue ?? 0, 0, ',', '.') }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Diskon</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_discount ?? 0, 0, ',', '.') }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Pajak</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_tax ?? 0, 0, ',', '.') }}</div></div>
</div>

{{-- RINCIAN SELURUH TRANSAKSI - supaya tidak perlu kalkulator.

     Diminta pemilik toko sendiri: untuk tahu ke mana perginya selisih antara angka versi
     lama dan angka sekarang, ia harus menyaring status satu per satu lalu menjumlahkan
     barisnya dengan kalkulator. Penjelasan yang benar tapi memaksa orang membuka kalkulator
     sama saja dengan tidak menjelaskan.

     Kotak ringkasan di atas SENGAJA tetap hanya menghitung yang lunas - itu definisi omset
     (HUKUM 5) dan tidak boleh ikut berubah saat status disaring. Rincian ini menjawab
     pertanyaan yang berbeda: "kalau semuanya dijumlahkan, jadi berapa, dan isinya apa saja". --}}
@php
    $nLunas  = $rincianStatus['completed'] ?? null;
    $nTunggu = $rincianStatus['waiting'] ?? null;
    $nBatal  = $rincianStatus['cancelled'] ?? null;
    $vLunas  = (float) ($nLunas->nilai ?? 0);
    $vTunggu = (float) ($nTunggu->nilai ?? 0);
    $vBatal  = (float) ($nBatal->nilai ?? 0);
@endphp
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
        <h2 class="font-semibold">Rincian Seluruh Transaksi</h2>
        <span class="text-sm text-slate-500 tabular-nums">
            Jumlah semuanya Rp {{ number_format($vLunas + $vTunggu + $vBatal, 0, ',', '.') }}
        </span>
    </div>

    <p class="text-xs text-slate-500 mb-3">
        Dari jumlah di atas, <strong>hanya yang lunas</strong> yang dihitung sebagai omset.
        Pesanan yang belum lunas masih berupa piutang, dan transaksi yang dibatalkan uangnya
        tidak pernah masuk. Aplikasi versi lama menjumlahkan ketiganya jadi satu &mdash;
        itulah kenapa angkanya dulu terlihat jauh lebih besar.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
        <div class="border rounded-lg px-3 py-2 bg-green-100 text-green-700 border-green-200">
            <div class="text-xs">Penjualan Lunas &middot; {{ $nLunas->jumlah ?? 0 }} transaksi</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vLunas, 0, ',', '.') }}</div>
            <div class="text-xs">= omset</div>
        </div>
        <div class="border rounded-lg px-3 py-2 bg-amber-100 text-amber-700 border-amber-200">
            <div class="text-xs">Belum Lunas &middot; {{ $nTunggu->jumlah ?? 0 }} pesanan</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vTunggu, 0, ',', '.') }}</div>
            <div class="text-xs">piutang, belum jadi omset</div>
        </div>
        <div class="border rounded-lg px-3 py-2 bg-red-100 text-red-700 border-red-200">
            <div class="text-xs">Dibatalkan &middot; {{ $nBatal->jumlah ?? 0 }} transaksi</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vBatal, 0, ',', '.') }}</div>
            <div class="text-xs">uangnya tidak pernah masuk</div>
        </div>
        <div class="border rounded-lg px-3 py-2 bg-slate-100 text-slate-700 border-slate-200">
            <div class="text-xs">Kalau ketiganya dijumlahkan</div>
            <div class="font-bold tabular-nums">Rp {{ number_format($vLunas + $vTunggu + $vBatal, 0, ',', '.') }}</div>
            <div class="text-xs">cara hitung versi lama</div>
        </div>
    </div>
</div>

{{-- UANG MASUK PER METODE.

     Ditaruh SETELAH ringkasan omset dan diberi penjelasan sendiri, bukan disatukan sebagai
     kartu kelima di deretan atas. Alasannya penting: angka di sini menjawab pertanyaan yang
     BERBEDA dari kartu-kartu di atasnya, dan menaruhnya berdampingan tanpa keterangan akan
     membuat pemilik toko mengira salah satunya rusak ketika keduanya tidak berjumlah sama. --}}
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
        <h2 class="font-semibold">Uang Masuk per Metode</h2>
        <span class="text-sm text-slate-500 tabular-nums">
            Total Rp {{ number_format(array_sum($uangMasuk), 0, ',', '.') }}
        </span>
    </div>

    <p class="text-xs text-slate-500 mb-3">
        Uang yang <strong>benar-benar diterima</strong> pada rentang tanggal ini - inilah angka
        yang diadu dengan isi laci dan mutasi rekening. Sengaja <strong>tidak sama</strong>
        dengan Total Pendapatan di atas: DP yang diterima bulan ini untuk pesanan yang baru
        selesai bulan depan sudah dihitung di sini tapi belum jadi omset, dan sebaliknya.
        Transaksi yang dibatalkan tidak dihitung karena uangnya sudah kembali ke pembeli.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
        @foreach(\App\Support\MetodeBayar::pilihan() as $kunci => $labelMetode)
            @continue($kunci === 'lainnya' && empty($uangMasuk['lainnya']))
            <div class="border rounded-lg px-3 py-2 {{ \App\Support\MetodeBayar::kelas($kunci) }}">
                <div class="text-xs">{{ $labelMetode }}</div>
                <div class="font-bold tabular-nums">Rp {{ number_format($uangMasuk[$kunci] ?? 0, 0, ',', '.') }}</div>
            </div>
        @endforeach
    </div>
</div>

<div class="card overflow-hidden">
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
