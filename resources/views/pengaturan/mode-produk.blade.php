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
        {{-- FITUR LANJUTAN.

             Ditaruh di halaman ini, bukan di halaman baru, karena pertanyaannya sama persis
             dengan yang sudah dijawab di atas: seberapa lengkap form produk. Halaman baru
             berarti satu kunci izin baru dan satu tempat lagi yang harus dicari orang -
             untuk satu centang. Kalau nanti centang seperti ini bertambah, halaman ini yang
             berganti nama, bukan bertambah saudara. --}}
        <div class="border-t border-slate-200 pt-4 mt-4 space-y-3">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Fitur Lanjutan</div>
            <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
                <input type="checkbox" name="harga_kategori_pelanggan" value="1" class="mt-1" {{ ($settings['harga_kategori_pelanggan'] ?? true) ? 'checked' : '' }}>
                <span>
                    <span class="block font-medium text-sm">🏷️ Harga Jual per Kategori Pelanggan (UMUM, Reseller, Grosir)</span>
                    <span class="block text-xs text-slate-400 mt-0.5">
                        Menampilkan kolom <strong>Harga Reseller</strong> dan <strong>Harga Grosir</strong> secara otomatis di form Tambah/Edit Produk. Kasir otomatis menyesuaikan harga saat pelanggan kategori Reseller atau Grosir dipilih.
                    </span>
                    <span class="block text-xs text-slate-400 mt-1">Jika dimatikan, form produk baru hanya menampilkan satu kolom Harga Jual biasa (namun tetap bisa diaktifkan kapan saja langsung lewat saklar di dalam form produk).</span>
                </span>
            </label>
            <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
                <input type="checkbox" name="grosir_jasa" value="1" class="mt-1" {{ ($settings['grosir_jasa'] ?? false) ? 'checked' : '' }}>
                <span>
                    <span class="block font-medium text-sm">Harga grosir untuk produk Jasa</span>
                    <span class="block text-xs text-slate-400 mt-0.5">
                        Biasanya jasa cuma punya satu harga. Nyalakan ini kalau harganya turun saat
                        jumlahnya banyak &mdash; mis. fotokopi Rp 500/lembar, jadi Rp 300/lembar mulai
                        100 lembar. Aturannya sama dengan grosir barang: begitu jumlah di Kasir
                        mencapai minimumnya, harga otomatis berganti.
                    </span>
                    <span class="block text-xs text-slate-400 mt-1">Dibiarkan mati, form Jasa tetap sesederhana sekarang.</span>
                </span>
            </label>
        {{-- PENGATURAN PRODUK KHUSUS MODUL KASIR F&B --}}
        <div class="border-t border-slate-200 pt-4 mt-4 space-y-3">
            <div class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1 flex items-center gap-1.5">
                <span>🍽️ Modul Kasir F&B (Resto &amp; Cafe)</span>
            </div>
            <p class="text-xs text-slate-500 mb-2 leading-relaxed">
                Pengaturan pengelolaan stok produk untuk kasir mode F&B. Menu masakan olahan dapur atau minuman racikan dapat diset tanpa batasan stok fisik, sedangkan produk kemasan fisik tetap dapat dipantau stoknya.
            </p>
            <div class="space-y-2.5">
                <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50 {{ ($settings['fnb_stock_mode'] ?? 'per_product') == 'per_product' ? 'border-amber-500 bg-amber-50/30' : '' }}">
                    <input type="radio" name="fnb_stock_mode" value="per_product" class="mt-1" {{ ($settings['fnb_stock_mode'] ?? 'per_product') == 'per_product' ? 'checked' : '' }}>
                    <span>
                        <span class="block font-medium text-sm">✨ Fleksibel per Produk (Bisa Ada Stok &amp; Bisa Tanpa Stok)</span>
                        <span class="block text-xs text-slate-400 mt-0.5">Rekomendasi. Status stok diatur pada masing-masing produk (di Master Produk): makanan olahan dapur bisa diset <em>Tanpa Stok</em> (unlimited), sedangkan minuman botol/kaleng atau kemasan bisa diset <em>Ada Stok</em> fisik.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50 {{ ($settings['fnb_stock_mode'] ?? 'per_product') == 'all_nostock' ? 'border-amber-500 bg-amber-50/30' : '' }}">
                    <input type="radio" name="fnb_stock_mode" value="all_nostock" class="mt-1" {{ ($settings['fnb_stock_mode'] ?? 'per_product') == 'all_nostock' ? 'checked' : '' }}>
                    <span>
                        <span class="block font-medium text-sm">🍳 Semua Menu Tanpa Stok (Mode Dapur Siap Saji)</span>
                        <span class="block text-xs text-slate-400 mt-0.5">Semua transaksi di kasir F&B tidak membatasi dan tidak memotong stok inventori. Sangat praktis untuk cafe/resto yang membuat seluruh menu langsung sesuai pesanan.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50 {{ ($settings['fnb_stock_mode'] ?? 'per_product') == 'all_stock' ? 'border-amber-500 bg-amber-50/30' : '' }}">
                    <input type="radio" name="fnb_stock_mode" value="all_stock" class="mt-1" {{ ($settings['fnb_stock_mode'] ?? 'per_product') == 'all_stock' ? 'checked' : '' }}>
                    <span>
                        <span class="block font-medium text-sm">📦 Semua Menu Wajib Ada Stok</span>
                        <span class="block text-xs text-slate-400 mt-0.5">Setiap menu di kasir F&B wajib memiliki stok fisik yang mencukupi dan stok akan selalu dipotong saat transaksi.</span>
                    </span>
                </label>
            </div>
        </div>

        <button class="btn btn-primary">Simpan</button>
    </form>
    <p class="text-xs text-slate-400 mt-3">Catatan: produk yang sudah punya Satuan Tambahan tetap bisa diedit satuannya walau mode diset Sederhana — pengaturan ini cuma menyembunyikan section-nya untuk produk baru/yang belum punya satuan tambahan.</p>
</div>
@endsection
