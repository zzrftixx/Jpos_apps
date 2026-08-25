@extends('layouts.app')
@section('title', 'Laporan Penjualan per Pelanggan')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', ['jenis' => 'per-pelanggan', 'filter' => ['from' => $from, 'to' => $to]])
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

<p class="text-xs text-slate-400 -mt-2 mb-4">* Hanya menghitung transaksi berstatus Lunas/Selesai. "Umum" = transaksi tanpa pelanggan terdaftar.</p>

<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead><tr><th>#</th><th>Pelanggan</th><th>Jumlah Transaksi</th><th>Total Belanja</th><th>Rata-rata / Transaksi</th></tr></thead>
        <tbody>
            @forelse($data as $i => $row)
            <tr>
                <td>{{ $data->firstItem() + $i }}</td>
                <td class="font-medium">{{ $row->customer_name }}</td>
                <td>{{ $row->trx_count }}</td>
                <td>Rp {{ number_format($row->total_belanja, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row->trx_count > 0 ? $row->total_belanja / $row->trx_count : 0, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-slate-400 py-8">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $data->links() }}</div>
@endsection
