@extends('layouts.app')
@section('title', 'Pajak')

@section('content')
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('pengaturan.pajak.update') }}" class="space-y-4">
        @csrf
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="enabled" value="1" {{ ($settings['enabled'] ?? true) ? 'checked' : '' }}>
            Aktifkan Pajak pada Transaksi Kasir
        </label>
        <div>
            <label class="form-label">Nama Pajak</label>
            <input type="text" name="name" value="{{ $settings['name'] ?? 'PPN' }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Persentase (%)</label>
            <input type="text" data-jpos-number data-number-decimals="2" data-number-group="0" name="percent" value="{{ $settings['percent'] ?? 11 }}" class="form-input">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_in_price" value="1" {{ ($settings['include_in_price'] ?? false) ? 'checked' : '' }}>
            Harga produk sudah termasuk pajak
        </label>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
@endsection
