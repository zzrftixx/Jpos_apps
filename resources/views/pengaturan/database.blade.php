@extends('layouts.app')
@section('title', 'Database')

@section('content')
<div class="card p-6 mb-4">
    <h3 class="font-semibold mb-2">Informasi Database</h3>
    <div class="text-sm space-y-1 text-slate-600">
        <div>Driver: <span class="font-medium">SQLite</span></div>
        <div>Lokasi File: <span class="font-mono text-xs">{{ $dbPath }}</span></div>
        <div>Ukuran: <span class="font-medium">{{ $size }} KB</span></div>
    </div>
    <form method="POST" action="{{ route('pengaturan.database.migrate') }}" class="mt-4" onsubmit="return confirm('Jalankan migrasi database?')">
        @csrf
        <button class="btn btn-outline">🔄 Jalankan Migrasi</button>
    </form>
</div>

<div class="card p-6 mb-4 border border-emerald-200 bg-emerald-50/20">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                ⚡ Optimasi & Kompresi Database (Percepat JPos)
            </h3>
            <p class="text-sm text-slate-600 mt-1 max-w-2xl">
                Membersihkan cache sementara dan memadatkan file database SQLite (VACUUM) agar ukuran file kembali ringan, responsif, dan hemat ruang penyimpanan. Aman dijalankan kapan saja saat toko beroperasi tanpa mengganggu transaksi.
            </p>
        </div>
        <form method="POST" action="{{ route('pengaturan.database.optimize') }}" onsubmit="return confirm('Jalankan optimasi dan kompresi database sekarang?')">
            @csrf
            <button type="submit" class="btn btn-primary inline-flex items-center gap-2 whitespace-nowrap">
                ⚡ Optimasi Sekarang
            </button>
        </form>
    </div>
</div>

<div class="card p-6 mb-4 border border-blue-200 bg-blue-50/20">
    <h3 class="font-semibold mb-1 text-slate-800">⚖️ Sinkronisasi & Perbaiki HPP Laporan</h3>
    <p class="text-sm text-slate-600 mb-3">
        Memperbaiki dan menyeimbangkan nilai HPP (Harga Pokok Penjualan) pada laporan laba rugi akibat kesalahan input harga modal produk multi-satuan/renteng (misal harga modal 1 karung/dus terisi ke satuan dasar kg/pcs).
    </p>
    <form method="POST" action="{{ route('pengaturan.database.perbaiki-hpp') }}" onsubmit="return confirm('Jalankan perbaikan HPP untuk menyeimbangkan laporan?')">
        @csrf
        <button type="submit" class="btn btn-primary">
            Perbaiki & Seimbangkan HPP Sekarang
        </button>
    </form>
</div>

<div class="card p-6 mb-4">
    <h3 class="font-semibold mb-3">Jumlah Data per Tabel</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        @foreach($tableCounts as $table => $count)
        <div class="border rounded-lg p-3">
            <div class="text-xs text-slate-400">{{ $table }}</div>
            <div class="font-semibold">{{ $count }}</div>
        </div>
        @endforeach
    </div>
</div>

@if(auth()->user()->role?->slug === 'admin')
<div class="card p-6 border-2 border-red-200" x-data="{ confirmation: '' }">
    <h3 class="font-semibold mb-2 text-red-600">⚠️ Hapus Semua Data</h3>
    <p class="text-sm text-slate-500 mb-2">Aksi ini akan menghapus permanen:</p>
    <ul class="text-sm text-slate-500 list-disc list-inside mb-2">
        <li>Semua Transaksi, Item Transaksi, Retur, dan Riwayat Stok</li>
        <li>Semua Produk, Kategori, Supplier, dan Pelanggan</li>
    </ul>
    <p class="text-sm text-slate-500 mb-3">
        <strong>Tidak dihapus:</strong> User, Role Akses, dan seluruh Pengaturan (Profil Toko, Printer, Pajak, Template, dll) &mdash; jadi Anda tetap bisa login setelahnya.
        Sistem akan membuat backup otomatis sebelum menghapus.
    </p>
    <form method="POST" action="{{ route('pengaturan.database.wipe') }}"
          onsubmit="return confirm('Yakin? Semua transaksi & master data akan dihapus permanen (backup otomatis tetap dibuat).')">
        @csrf
        <label class="form-label">Ketik <span class="font-mono font-semibold">HAPUS SEMUA DATA</span> untuk mengaktifkan tombol</label>
        <input type="text" name="confirmation" x-model="confirmation" class="form-input mb-3" autocomplete="off">
        <button type="submit" class="btn btn-danger" :disabled="confirmation !== 'HAPUS SEMUA DATA'" :class="confirmation !== 'HAPUS SEMUA DATA' ? 'opacity-40 cursor-not-allowed' : ''">
            Hapus Semua Data Sekarang
        </button>
    </form>
</div>
@endif
@endsection
