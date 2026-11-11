@extends('layouts.app')
@section('title', 'Pengaturan Modul Kasir')

@section('content')
<div class="card p-6 max-w-2xl space-y-6" x-data="{ 
    currentLayout: '{{ $settings['layout'] ?? 'modern' }}',
    currentDefaultView: '{{ $settings['default_view'] ?? 'gambar' }}',
    currentFnbStockMode: '{{ $settings['fnb_stock_mode'] ?? 'per_product' }}'
}">
    <div>
        <h2 class="text-base font-bold text-slate-800">Pengaturan Modul Kasir</h2>
        <p class="text-sm text-slate-500 mt-1">
            Sesuaikan tata letak antarmuka kasir antara Tampilan Modern atau Modul Kasir Klasik sesuai alur transaksi toko Anda.
        </p>
    </div>

    <form method="POST" action="{{ route('pengaturan.tampilan-kasir.update') }}" class="space-y-6">
        @csrf

        {{-- PILIHAN TATA LETAK MODUL KASIR --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5">
                Tata Letak Modul Kasir
            </label>
            <div class="space-y-3">
                {{-- Opsi 1: Tampilan Kasir Modern --}}
                <label class="flex items-start gap-3.5 border-2 rounded-2xl p-4 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentLayout === 'modern' ? 'border-brand-500 bg-brand-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="layout" value="modern" x-model="currentLayout" class="mt-1 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 font-bold text-sm text-slate-800">
                                <svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                </svg>
                                <span>Tampilan Kasir Modern</span>
                            </div>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-brand-100 text-brand-700">2 Panel</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Tata letak modern dengan katalog visual produk (gambar/list) di panel kiri dan keranjang belanja dinamis di panel kanan. Cocok untuk toko dengan kasir layar sentuh maupun keyboard mouse.
                        </p>
                    </div>
                </label>

                {{-- Opsi 2: Modul Kasir Klasik --}}
                <label class="flex items-start gap-3.5 border-2 rounded-2xl p-4 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentLayout === 'klasik' ? 'border-emerald-500 bg-emerald-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="layout" value="klasik" x-model="currentLayout" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 font-bold text-sm text-slate-800">
                                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>Modul Kasir Klasik</span>
                            </div>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Layar Penuh &amp; Cepat</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Tata letak kasir ritel klasik layar penuh dengan tabel transaksi utama, input barcode/nama cepat, kuantitas stepper, dan bilah tombol bantuan cepat keyboard (<kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono">F2 Cari</kbd>, <kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono">Enter Pilih</kbd>, <kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono">Del/F7 Clear</kbd>, <kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono">F8 Bayar</kbd>).
                        </p>
                    </div>
                </label>

                {{-- Opsi 3: Mode Kasir F&B (Resto & Cafe) --}}
                <label class="flex items-start gap-3.5 border-2 rounded-2xl p-4 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentLayout === 'fnb' ? 'border-amber-500 bg-amber-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="layout" value="fnb" x-model="currentLayout" class="mt-1 text-amber-600 focus:ring-amber-500">
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 font-bold text-sm text-slate-800">
                                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <span>Mode Kasir F&B (Resto / Cafe / Warung Makan)</span>
                            </div>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Nomor Meja &amp; Struk Kitchen</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Khusus usaha makanan &amp; minuman dengan pemilihan nomor meja (Dine In / Take Away), catatan pesanan khusus per menu (pedas, manis, es, dll), cetak struk pesanan dapur (Kitchen Order Ticket / KOT), dan cetak struk kasir pelanggan.
                        </p>
                    </div>
                </label>
            </div>
        </div>

        {{-- MODE PRODUK KATALOG (KHUSUS TAMPILAN MODERN) --}}
        <div class="pt-2 border-t border-slate-100" x-show="currentLayout === 'modern'" x-transition>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5">
                Mode Tampilan Katalog Produk (Khusus Kasir Modern)
            </label>
            <div class="space-y-2.5">
                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentDefaultView === 'gambar' ? 'border-brand-500 bg-brand-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="default_view" value="gambar" x-model="currentDefaultView" class="mt-1 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            <span>Gambar saja</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Modul Kasir selalu menampilkan kartu produk bergambar.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentDefaultView === 'list' ? 'border-brand-500 bg-brand-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="default_view" value="list" x-model="currentDefaultView" class="mt-1 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span>List saja</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Modul Kasir selalu menampilkan tabel ringkas (kode, nama, harga, stok).</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentDefaultView === 'both' ? 'border-brand-500 bg-brand-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="default_view" value="both" x-model="currentDefaultView" class="mt-1 text-brand-600 focus:ring-brand-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Dua-duanya (Bisa Dialihkan)</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Kasir bisa beralih bebas antara Gambar dan List lewat tombol toggle di Modul Kasir.</span>
                    </div>
                </label>
            </div>
        </div>

        {{-- MODE PENGELOLAAN STOK MENU (KHUSUS KASIR F&B) --}}
        <div class="pt-2 border-t border-slate-100" x-show="currentLayout === 'fnb'" x-transition>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">
                Pengaturan Stok Produk / Menu (Khusus Kasir F&B)
            </label>
            <p class="text-xs text-slate-500 mb-2.5">
                Pilih bagaimana kasir F&B mengelola stok menu. Menu masakan dapur/racikan bisa diset tanpa batasan stok, sedangkan minuman kemasan/snack tetap dipantau stok fisiknya.
            </p>
            <div class="space-y-2.5">
                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentFnbStockMode === 'per_product' ? 'border-amber-500 bg-amber-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="fnb_stock_mode" value="per_product" x-model="currentFnbStockMode" class="mt-1 text-amber-600 focus:ring-amber-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <span>✨ Fleksibel per Produk (Bisa Ada Stok &amp; Bisa Tanpa Stok)</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Rekomendasi. Status stok diatur pada masing-masing produk di Master Produk (makanan olahan dapur bisa tanpa stok, minuman botol/kemasan ada stok).</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentFnbStockMode === 'all_nostock' ? 'border-amber-500 bg-amber-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="fnb_stock_mode" value="all_nostock" x-model="currentFnbStockMode" class="mt-1 text-amber-600 focus:ring-amber-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <span>🍳 Semua Menu Tanpa Stok (Mode Dapur Siap Saji)</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Semua transaksi di kasir F&B tidak membatasi dan tidak memotong stok inventori.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="currentFnbStockMode === 'all_stock' ? 'border-amber-500 bg-amber-50/40 shadow-xs' : 'border-slate-200'">
                    <input type="radio" name="fnb_stock_mode" value="all_stock" x-model="currentFnbStockMode" class="mt-1 text-amber-600 focus:ring-amber-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <span>📦 Semua Menu Wajib Ada Stok</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Semua menu di kasir F&B wajib memiliki stok fisik yang mencukupi dan dipotong saat transaksi.</span>
                    </div>
                </label>
            </div>
        </div>

        {{-- Pintasan Pengelolaan Barcode Meja F&B --}}
        <div x-show="currentLayout === 'fnb'" class="card p-5 bg-gradient-to-r from-amber-500/10 via-orange-500/5 to-amber-500/10 border-amber-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="text-2xl shrink-0">🪑</span>
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Pengelolaan Barcode Meja &amp; Pemesanan Mandiri (Self-Order)</h4>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola nomor meja, cetak stiker / tent card QR code meja untuk pelanggan pesan dari HP, dan sinkronkan otomatis ke kasir F&amp;B.</p>
                    </div>
                </div>
                <a href="{{ route('meja.index') }}" class="btn btn-outline border-amber-300 text-amber-800 bg-white hover:bg-amber-50 text-xs font-bold py-2 px-3.5 shrink-0 shadow-2xs">
                    Kelola Meja &amp; Barcode &rarr;
                </a>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="btn btn-primary px-6 py-2.5 rounded-xl font-semibold text-sm shadow-xs">
                Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection
