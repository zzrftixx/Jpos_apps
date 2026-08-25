@extends('layouts.app')
@section('title', 'Mode Produk')

@section('content')
<div class="card p-6 max-w-lg">
    <p class="text-sm text-slate-500 mb-4">
        Atur seberapa lengkap form <strong>Tambah/Edit Produk</strong>. <strong>Harga Grosir</strong>
        &amp; <strong>Min. Qty Grosir</strong> selalu tersedia di kedua mode — pengaturan ini hanya
        menentukan apakah section <strong>Satuan Tambahan</strong> (produk dengan lebih dari satu satuan
        jual, mis. per DUS/LUSIN/KG) ditampilkan atau tidak.
    </p>
    <form method="POST" action="{{ route('pengaturan.mode-produk.update') }}" class="space-y-3">
        @csrf
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="mode" value="sederhana" class="mt-1" {{ ($settings['mode'] ?? 'sederhana') == 'sederhana' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">✂️ Sederhana (Rekomendasi)</span>
                <span class="block text-xs text-slate-400">Form Tambah/Edit Produk cuma satu harga per produk (plus Harga Grosir kalau perlu). Section Satuan Tambahan disembunyikan. Cocok untuk kebanyakan toko.</span>
            </span>
        </label>
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="mode" value="lengkap" class="mt-1" {{ ($settings['mode'] ?? 'sederhana') == 'lengkap' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">📋 Lengkap</span>
                <span class="block text-xs text-slate-400">Form Tambah/Edit Produk juga menampilkan Satuan Tambahan, untuk produk yang dijual per DUS/LUSIN/KG dengan harga & konversi masing-masing.</span>
            </span>
        </label>
        <button class="btn btn-primary">Simpan</button>
    </form>
    <p class="text-xs text-slate-400 mt-3">Catatan: produk yang sudah punya Satuan Tambahan tetap bisa diedit satuannya walau mode diset Sederhana — pengaturan ini cuma menyembunyikan section-nya untuk produk baru/yang belum punya satuan tambahan.</p>
</div>
@endsection
