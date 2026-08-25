@extends('layouts.app')
@section('title', 'Laporan Omset')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', ['jenis' => 'omset', 'filter' => ['from' => $from, 'to' => $to]])
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
    <button class="btn btn-primary">Terapkan</button>
</form>

<p class="text-xs text-slate-400 -mt-2 mb-4">
    * Hanya transaksi Lunas/Selesai, sudah dikurangi retur, dan tidak termasuk pajak yang dipungut dari pembeli.
    Angka ini sama persis dengan Omset di Laporan Laba Rugi.
</p>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
    <div class="card p-4"><div class="text-xs text-slate-500">Jumlah Transaksi</div><div class="text-xl font-bold">{{ $summary->trx_count ?? 0 }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500">Total Omset</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_omset ?? 0, 0, ',', '.') }}</div></div>
    <div class="card p-4">
        <div class="text-xs text-slate-500">Pajak Dipungut</div>
        <div class="text-xl font-bold text-slate-600">Rp {{ number_format($summary->pajak ?? 0, 0, ',', '.') }}</div>
        <div class="text-[11px] text-slate-400 mt-1">Titipan pembeli, bukan pendapatan toko</div>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead><tr><th>Tanggal</th><th>Jumlah Transaksi</th><th>Total Omset</th></tr></thead>
        <tbody>
            @forelse($daily as $row)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                <td>{{ $row->trx_count }}</td>
                <td>Rp {{ number_format($row->total_omset, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-center text-slate-400 py-8">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
