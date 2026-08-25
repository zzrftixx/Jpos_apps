@extends('layouts.app')
@section('title', 'Produk Terlaris')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', ['jenis' => 'terlaris', 'filter' => ['from' => $from, 'to' => $to]])
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

<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead><tr><th>#</th><th>Produk</th><th>Qty Terjual</th><th>Total Pendapatan</th></tr></thead>
        <tbody>
            @forelse($topProducts as $i => $p)
            <tr>
                <td>{{ $topProducts->firstItem() + $i }}</td>
                <td class="font-medium">{{ $p->product_name }}</td>
                <td>@qty($p->total_qty)</td>
                <td>Rp {{ number_format($p->total_revenue, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-slate-400 py-8">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $topProducts->links() }}</div>
@endsection
