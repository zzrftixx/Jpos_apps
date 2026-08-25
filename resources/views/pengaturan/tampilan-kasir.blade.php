@extends('layouts.app')
@section('title', 'Tampilan Kasir')

@section('content')
<div class="card p-6 max-w-lg">
    <p class="text-sm text-slate-500 mb-4">
        Menu <strong>Modul Kasir</strong> punya 2 tampilan produk: <strong>Gambar</strong> (kartu bergambar) dan <strong>List</strong> (tabel ringkas).
        Atur di sini tampilan mana yang dipakai.
    </p>
    <form method="POST" action="{{ route('pengaturan.tampilan-kasir.update') }}" class="space-y-3">
        @csrf
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="default_view" value="gambar" class="mt-1" {{ ($settings['default_view'] ?? 'gambar') == 'gambar' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">🖼️ Gambar saja</span>
                <span class="block text-xs text-slate-400">Modul Kasir selalu tampil kartu produk bergambar.</span>
            </span>
        </label>
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="default_view" value="list" class="mt-1" {{ ($settings['default_view'] ?? '') == 'list' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">📋 List saja</span>
                <span class="block text-xs text-slate-400">Modul Kasir selalu tampil tabel ringkas (kode, nama, harga, stok).</span>
            </span>
        </label>
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="default_view" value="both" class="mt-1" {{ ($settings['default_view'] ?? '') == 'both' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">🔀 Dua-duanya</span>
                <span class="block text-xs text-slate-400">Kasir bisa pilih sendiri lewat tombol toggle di halaman Modul Kasir.</span>
            </span>
        </label>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
@endsection
