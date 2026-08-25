@extends('layouts.app')
@section('title', 'Riwayat Stok - ' . $product->name)

@section('content')
@include('laporan._tabs')

<a href="{{ route('laporan.stok') }}" class="text-sm text-blue-600 hover:underline">&larr; Kembali</a>
<div class="card p-4 my-4">
    <div class="font-semibold text-lg">{{ $product->name }}</div>
    <div class="text-sm text-slate-500">Stok saat ini: @qty($product->stock) {{ $product->unit }}</div>
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
        <thead><tr><th>Tanggal</th><th>Tipe</th><th>Qty</th><th>Stok Sesudah</th><th>Catatan</th><th>User</th></tr></thead>
        <tbody>
            @forelse($movements as $m)
            <tr>
                <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td class="capitalize">{{ $m->type }}</td>
                <td class="{{ $m->qty < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $m->qty > 0 ? '+' : '' }}@qty($m->qty)</td>
                <td>{{ number_format($m->stock_after, 0, ',', '.') }}</td>
                <td class="text-slate-500">{{ $m->note ?: '-' }}</td>
                <td>{{ $m->user->name ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada riwayat.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $movements->links() }}</div>
@endsection
