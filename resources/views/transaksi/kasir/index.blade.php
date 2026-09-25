@extends('layouts.app')
@section('title', 'Modul Kasir')

{{-- Meminta <main> jadi kolom flex khusus di halaman ini, supaya isi di bawah ini bisa
     mengambil TINGGI SISA - bukan tinggi penuh yang mengabaikan spanduk di atasnya. --}}
@section('kelas-main', 'lg:flex lg:flex-col')

@push('styles')
<style>
/* ==========================================================================
   TATA LETAK PENCARIAN & FILTER KATEGORI MODUL KASIR
   ========================================================================== */
.kasir-top-filter {
    display: flex;
    flex-direction: column;
    gap: 0.75rem; /* 12px jarak bersih dan tegas antar baris */
    margin-bottom: 0.875rem;
    width: 100%;
}
.kasir-search-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}
.kasir-search-input {
    width: 100% !important;
    padding-left: 2.6rem !important;
    padding-right: 2.25rem !important;
    padding-top: 0.65rem !important;
    padding-bottom: 0.65rem !important;
    border-radius: 0.75rem !important;
    border: 1.5px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    font-size: 0.875rem !important;
    line-height: 1.25rem !important;
    color: #1e293b !important;
    outline: none !important;
    transition: all 0.15s ease-in-out !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
}
.kasir-search-input:focus {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
}
.kasir-search-icon {
    position: absolute !important;
    left: 0.875rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 1.1rem !important;
    height: 1.1rem !important;
    color: #94a3b8 !important;
    pointer-events: none !important;
    display: block !important;
}
.kasir-search-clear {
    position: absolute !important;
    right: 0.75rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 1.25rem !important;
    height: 1.25rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 9999px !important;
    color: #94a3b8 !important;
    font-size: 1.1rem !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    line-height: 1 !important;
    background: transparent !important;
    border: none !important;
}
.kasir-search-clear:hover {
    color: #475569 !important;
    background-color: #f1f5f9 !important;
}
.kasir-cat-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 4px 2px 6px 2px;
    width: 100%;
    scrollbar-width: none;
    -ms-overflow-style: none;
    user-select: none;
}
.kasir-cat-bar::-webkit-scrollbar {
    display: none;
}

/* ==========================================================================
   TOMBOL & WARNA KATEGORI PRODUK MODUL KASIR
   ========================================================================== */
.kat-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1rem;
    white-space: nowrap;
    cursor: pointer;
    border-width: 1.5px;
    border-style: solid;
    user-select: none;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.04);
    flex-shrink: 0;
}
.kat-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px -1px rgba(0, 0, 0, 0.08);
}
.kat-btn:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.04);
}
.kat-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 9999px;
    display: inline-block;
    flex-shrink: 0;
    transition: background-color 0.15s ease;
}
.kat-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    min-width: 1.25rem;
    text-align: center;
    line-height: 1;
    display: inline-block;
    transition: all 0.15s ease;
}

.kat-btn svg {
    width: 13px !important;
    height: 13px !important;
    max-width: 13px !important;
    max-height: 13px !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
}

/* Tombol Reset Filter */
.kat-btn-reset {
    background-color: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}
.kat-btn-reset:hover {
    background-color: #e2e8f0;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Semua Kategori */
.kat-btn-all {
    background-color: #ffffff;
    border-color: #cbd5e1;
    color: #334155;
}
.kat-btn-all .kat-dot {
    background-color: #64748b;
}
.kat-btn-all .kat-badge {
    background-color: #f1f5f9;
    color: #475569;
}
.kat-btn-all:hover {
    background-color: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}
.kat-btn-all.is-active {
    background-color: #2563eb !important;
    border-color: #1d4ed8 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35) !important;
}
.kat-btn-all.is-active .kat-dot {
    background-color: #bfdbfe !important;
}
.kat-btn-all.is-active .kat-badge {
    background-color: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}

/* Tema Spesial: Terlaris (Paling Depan) */
.kat-theme-terlaris {
    background-color: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}
.kat-theme-terlaris .kat-dot {
    background-color: #f59e0b;
}
.kat-theme-terlaris .kat-badge {
    background-color: #fef3c7;
    color: #78350f;
}
.kat-theme-terlaris:hover {
    background-color: #fef3c7;
    border-color: #fcd34d;
    color: #78350f;
}
.kat-theme-terlaris.is-active {
    background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%) !important;
    border-color: #d97706 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.35) !important;
}
.kat-theme-terlaris.is-active svg {
    color: #ffffff !important;
}
.kat-theme-terlaris.is-active .kat-dot {
    background-color: #fed7aa !important;
}
.kat-theme-terlaris.is-active .kat-badge {
    background-color: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}

/* Tema 0: Indigo */
.kat-theme-0 { background-color: #eef2ff; border-color: #c7d2fe; color: #3730a3; }
.kat-theme-0 .kat-dot { background-color: #6366f1; }
.kat-theme-0 .kat-badge { background-color: #e0e7ff; color: #312e81; }
.kat-theme-0:hover { background-color: #e0e7ff; border-color: #a5b4fc; }
.kat-theme-0.is-active {
    background-color: #4f46e5 !important;
    border-color: #4338ca !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35) !important;
}
.kat-theme-0.is-active .kat-dot { background-color: #a5b4fc !important; }
.kat-theme-0.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 1: Emerald */
.kat-theme-1 { background-color: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.kat-theme-1 .kat-dot { background-color: #10b981; }
.kat-theme-1 .kat-badge { background-color: #d1fae5; color: #064e3b; }
.kat-theme-1:hover { background-color: #d1fae5; border-color: #6ee7b7; }
.kat-theme-1.is-active {
    background-color: #059669 !important;
    border-color: #047857 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35) !important;
}
.kat-theme-1.is-active .kat-dot { background-color: #6ee7b7 !important; }
.kat-theme-1.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 2: Amber */
.kat-theme-2 { background-color: #fffbeb; border-color: #fde68a; color: #92400e; }
.kat-theme-2 .kat-dot { background-color: #f59e0b; }
.kat-theme-2 .kat-badge { background-color: #fef3c7; color: #78350f; }
.kat-theme-2:hover { background-color: #fef3c7; border-color: #fcd34d; }
.kat-theme-2.is-active {
    background-color: #d97706 !important;
    border-color: #b45309 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35) !important;
}
.kat-theme-2.is-active .kat-dot { background-color: #fde68a !important; }
.kat-theme-2.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 3: Purple */
.kat-theme-3 { background-color: #faf5ff; border-color: #e9d5ff; color: #6b21a8; }
.kat-theme-3 .kat-dot { background-color: #a855f7; }
.kat-theme-3 .kat-badge { background-color: #f3e8ff; color: #581c87; }
.kat-theme-3:hover { background-color: #f3e8ff; border-color: #d8b4fe; }
.kat-theme-3.is-active {
    background-color: #7c3aed !important;
    border-color: #6d28d9 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35) !important;
}
.kat-theme-3.is-active .kat-dot { background-color: #e9d5ff !important; }
.kat-theme-3.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 4: Rose */
.kat-theme-4 { background-color: #fff1f2; border-color: #fecdd3; color: #9f1239; }
.kat-theme-4 .kat-dot { background-color: #f43f5e; }
.kat-theme-4 .kat-badge { background-color: #ffe4e6; color: #881337; }
.kat-theme-4:hover { background-color: #ffe4e6; border-color: #fda4af; }
.kat-theme-4.is-active {
    background-color: #e11d48 !important;
    border-color: #be123c !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.35) !important;
}
.kat-theme-4.is-active .kat-dot { background-color: #fecdd3 !important; }
.kat-theme-4.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 5: Cyan */
.kat-theme-5 { background-color: #ecfeff; border-color: #a5f3fc; color: #155e75; }
.kat-theme-5 .kat-dot { background-color: #06b6d4; }
.kat-theme-5 .kat-badge { background-color: #cffafe; color: #164e63; }
.kat-theme-5:hover { background-color: #cffafe; border-color: #67e8f9; }
.kat-theme-5.is-active {
    background-color: #0891b2 !important;
    border-color: #0e7490 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(8, 145, 178, 0.35) !important;
}
.kat-theme-5.is-active .kat-dot { background-color: #a5f3fc !important; }
.kat-theme-5.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 6: Orange */
.kat-theme-6 { background-color: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.kat-theme-6 .kat-dot { background-color: #f97316; }
.kat-theme-6 .kat-badge { background-color: #ffedd5; color: #7c2d12; }
.kat-theme-6:hover { background-color: #ffedd5; border-color: #fdba74; }
.kat-theme-6.is-active {
    background-color: #ea580c !important;
    border-color: #c2410c !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.35) !important;
}
.kat-theme-6.is-active .kat-dot { background-color: #fed7aa !important; }
.kat-theme-6.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 7: Teal */
.kat-theme-7 { background-color: #f0fdfa; border-color: #99f6e4; color: #115e59; }
.kat-theme-7 .kat-dot { background-color: #14b8a6; }
.kat-theme-7 .kat-badge { background-color: #ccfbf1; color: #134e4a; }
.kat-theme-7:hover { background-color: #ccfbf1; border-color: #5eead4; }
.kat-theme-7.is-active {
    background-color: #0d9488 !important;
    border-color: #0f766e !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35) !important;
}
.kat-theme-7.is-active .kat-dot { background-color: #99f6e4 !important; }
.kat-theme-7.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 8: Sky */
.kat-theme-8 { background-color: #f0f9ff; border-color: #bae6fd; color: #0369a1; }
.kat-theme-8 .kat-dot { background-color: #38bdf8; }
.kat-theme-8 .kat-badge { background-color: #e0f2fe; color: #0c4a6e; }
.kat-theme-8:hover { background-color: #e0f2fe; border-color: #7dd3fc; }
.kat-theme-8.is-active {
    background-color: #0284c7 !important;
    border-color: #0369a1 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35) !important;
}
.kat-theme-8.is-active .kat-dot { background-color: #bae6fd !important; }
.kat-theme-8.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* Tema 9: Fuchsia */
.kat-theme-9 { background-color: #fdf4ff; border-color: #f5d0fe; color: #86198f; }
.kat-theme-9 .kat-dot { background-color: #d946ef; }
.kat-theme-9 .kat-badge { background-color: #fae8ff; color: #701a75; }
.kat-theme-9:hover { background-color: #fae8ff; border-color: #f0abfc; }
.kat-theme-9.is-active {
    background-color: #c026d3 !important;
    border-color: #a21caf !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(192, 38, 211, 0.35) !important;
}
.kat-theme-9.is-active .kat-dot { background-color: #f5d0fe !important; }
.kat-theme-9.is-active .kat-badge { background-color: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; }

/* ==========================================================================
   ITEM KERANJANG BELANJA (MURNI LIST TANPA CARD)
   ========================================================================== */
/* ==========================================================================
   DAFTAR KERANJANG (Cart Items)
   Didesain ultra-kompak, rapi, dan padat: tanpa border box tebal, padding
   efisien, cukup garis tipis pemisah. Kasir bisa melihat banyak item
   sekaligus tanpa harus terus-menerus menggulung layar.
   ========================================================================== */
.cart-item {
    background-color: transparent !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid #f1f5f9 !important;
    border-radius: 0 !important;
    padding: 0.25rem 0.25rem 0.3rem 0.25rem !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    box-shadow: none !important;
    transition: background-color 0.15s ease-in-out;
}
.cart-item:last-child {
    border-bottom: none !important;
}
.cart-item:hover {
    background-color: #f8fafc !important;
}
.cart-item.is-highlighted {
    background-color: #eff6ff !important;
    border-left: 2.5px solid #3b82f6 !important;
    padding-left: 0.45rem !important;
}
div[x-ref="daftarKeranjang"] > :not([hidden]) ~ :not([hidden]).cart-item,
div[x-ref="daftarKeranjang"] .cart-item + .cart-item {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}
.cart-item-title {
    font-size: 0.875rem; /* 14px */
    font-weight: 700;
    line-height: 1.18;
    color: #0f172a;
    word-break: break-word;
}
.cart-item-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.125rem; /* 18px */
    height: 1.125rem; /* 18px */
    border-radius: 0.25rem;
    color: #94a3b8;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
    padding: 0;
}
.cart-item-del:hover {
    color: #ef4444;
    background-color: #fee2e2;
}
.cart-item-divider {
    margin-top: 0.15rem; /* ~2.4px */
    padding-top: 0;
    border-top: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem;
}
.cart-price-info {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
    font-size: 0.75rem; /* 12px */
    color: #475569;
    min-width: 0;
    line-height: 1.2;
}
.cart-unit-price {
    font-size: 0.75rem; /* 12px */
    font-weight: 600;
    color: #475569;
}
.cart-unit-badge {
    font-size: 0.5625rem; /* 9px */
    font-weight: 600;
    color: #b45309;
    background-color: #fef3c7;
    padding: 0.05rem 0.25rem;
    border-radius: 0.2rem;
    line-height: 1.1;
}
.cart-grosir-badge {
    font-size: 0.5625rem; /* 9px */
    font-weight: 600;
    color: #047857;
    background-color: #d1fae5;
    padding: 0.05rem 0.25rem;
    border-radius: 0.2rem;
    line-height: 1.1;
}
.cart-stepper {
    display: flex;
    align-items: center;
    gap: 0.15rem;
    flex-shrink: 0;
}
.cart-stepper-btn {
    width: 1.25rem; /* 20px */
    height: 1.25rem; /* 20px */
    border-radius: 0.25rem; /* 4px */
    background-color: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    flex-shrink: 0;
    padding: 0;
}
.cart-stepper-btn:hover {
    background-color: #e2e8f0;
    color: #0f172a;
    border-color: #cbd5e1;
}
.cart-stepper-btn:active {
    transform: scale(0.9);
    background-color: #cbd5e1;
}
.cart-stepper-btn svg {
    width: 0.625rem; /* 10px */
    height: 0.625rem;
}
.cart-stepper-input {
    width: 2.25rem !important; /* 36px */
    height: 1.25rem !important; /* 20px */
    text-align: center !important;
    font-size: 0.75rem !important; /* 12px */
    font-weight: 700 !important;
    color: #0f172a !important;
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 0.25rem !important;
    padding: 0 !important;
    outline: none !important;
    box-shadow: none !important;
    transition: border-color 0.15s ease !important;
}
.cart-stepper-input:focus {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 1.5px rgba(37, 99, 235, 0.15) !important;
}
.cart-subtotal {
    font-size: 0.875rem; /* 14px */
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    text-align: right;
    line-height: 1.15;
    min-width: 4rem; /* 64px */
    flex-shrink: 0;
}

/* Mencegah kartu produk meregang panjang ke bawah jika produk sedikit */
.kasir-product-grid {
    align-content: start !important;
    grid-auto-rows: min-content !important;
}
.kasir-product-card {
    height: auto !important;
    min-height: 0 !important;
}
</style>
@endpush

@section('content')
{{-- Pindaian didengarkan dari DOKUMEN, bukan dari kolom cari. Alat pindai barcode adalah
     papan ketik: ia menembakkan karakter ke elemen mana pun yang sedang terfokus, dan kasir
     terus-menerus menyentuh hal lain (jumlah, nominal bayar, tombol). Penangkapnya ada di
     public/vendor/jpos-pemindai.js beserta seluruh alasannya. --}}
<div x-data="kasirApp()" x-init="init()"
     @jpos:barcode-dipindai.document="pindai($event.detail.kode)"
     class="flex flex-col gap-2.5 lg:flex-1 lg:min-h-0">

    {{-- DEDICATED FULLSCREEN POS HEADER BAR (FULL WIDTH AT TOP) --}}
    <div class="bg-white border border-slate-200/80 rounded-2xl px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-2xs">
        <div class="flex items-center gap-2 sm:gap-3">
            {{-- Tombol Toggle Sidebar Drawer --}}
            <button type="button" @click="sidebarOpen = !sidebarOpen"
                    class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition flex items-center gap-1.5 text-xs font-semibold"
                    title="Buka Menu Aplikasi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span class="hidden sm:inline">Menu</span>
            </button>

            <a href="{{ route('dashboard') }}"
               class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-900 border border-slate-200/80 transition flex items-center gap-1.5 text-xs font-medium"
               title="Kembali ke Dashboard">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="hidden md:inline">Dashboard</span>
            </a>

            <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-brand-600 text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                    {{ strtoupper(substr($storeProfile['name'] ?? 'J', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-xs sm:text-sm text-slate-800 leading-tight truncate max-w-[120px] sm:max-w-[180px]">{{ $storeProfile['name'] ?? 'JPOS' }}</div>
                    <div class="text-[11px] text-slate-400 truncate">Kasir: <strong class="text-slate-600 font-semibold">{{ auth()->user()->name ?? 'Kasir' }}</strong></div>
                </div>
            </div>
        </div>

        {{-- Tengah: Jam Realtime & Tanggal --}}
        <div class="hidden lg:flex items-center gap-2.5 bg-slate-50 border border-slate-200/70 px-3 py-1 rounded-full text-xs text-slate-600">
            <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
            <span class="font-medium" x-text="tanggalHariIni"></span>
            <span class="text-slate-300">&bull;</span>
            <span class="font-mono font-bold text-slate-800 text-sm" x-text="jamSekarang"></span>
        </div>

        {{-- Kanan: Aksi Cepat, Toggle Tampilan Produk & Tombol Fullscreen --}}
        <div class="flex items-center gap-1.5 sm:gap-2 ml-auto">
            <a href="{{ route('pengaturan.tampilan-kasir') }}"
               class="p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition flex items-center justify-center shadow-2xs"
               title="Pengaturan Tampilan Kasir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </a>

            @if($allowToggle)
            <div class="flex rounded-xl border border-slate-200 p-0.5 bg-slate-100 text-xs font-medium">
                <button type="button" @click="setViewMode('gambar')"
                        :class="viewMode === 'gambar' ? 'bg-white text-brand-700 shadow-2xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        class="px-2.5 py-1 rounded-lg transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span>Gambar</span>
                </button>
                <button type="button" @click="setViewMode('list')"
                        :class="viewMode === 'list' ? 'bg-white text-brand-700 shadow-2xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
                        class="px-2.5 py-1 rounded-lg transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    <span>List</span>
                </button>
            </div>
            @endif

            {{-- Tombol Shift Kasir --}}
            @if($shiftKasirEnabled)
                @if($activeShift)
                    <button type="button" @click="bukaModalTutupShift({{ $activeShift->id }})"
                            class="h-9 px-2.5 rounded-xl border border-green-300 bg-green-50 hover:bg-green-100 text-green-800 font-semibold text-xs transition inline-flex items-center gap-1.5 shrink-0 select-none cursor-pointer"
                            title="Shift Kasir Aktif. Klik untuk melihat ringkasan uang laci atau tutup shift.">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="whitespace-nowrap">Shift: <strong class="font-mono">Rp {{ number_format($activeShift->starting_cash, 0, ',', '.') }}</strong></span>
                        <span class="text-[10px] text-green-700 bg-green-100 px-1.5 py-0.5 rounded font-bold">Tutup &rarr;</span>
                    </button>
                @else
                    <button type="button" @click="showBukaShiftModal = true"
                            class="h-9 px-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs transition inline-flex items-center gap-1.5 shrink-0 select-none cursor-pointer"
                            title="Shift belum dibuka. Klik untuk mulai shift kasir dan masukkan modal awal laci.">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="whitespace-nowrap">Buka Shift</span>
                    </button>
                @endif
            @endif

            {{-- Tombol Tahan Pesanan (Card Gerigi & seukuran Waiting List) --}}
            <a href="{{ route('kasir.tahan') }}"
               class="h-9 px-2.5 rounded-xl border border-blue-300 bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold text-xs transition inline-flex items-center gap-1.5 shrink-0"
               title="Lihat Daftar Transaksi Tertahan / Tahan Pesanan">
                {{-- Ikon Card Gerigi (Nota / Struk Ber-gerigi) --}}
                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6M9 11h6M9 14h3"/>
                </svg>
                <span class="whitespace-nowrap">Tahan Pesanan</span>
                <span x-show="jumlahTertahan > 0" x-text="jumlahTertahan"
                      class="px-1.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-bold leading-none"></span>
                <span x-show="!jumlahTertahan"
                      class="px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-semibold leading-none">0</span>
                @if(($jumlahTertahan ?? 0) > 0)
                    <span x-show="false" class="px-1.5 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-bold leading-none">{{ $jumlahTertahan }}</span>
                @endif
            </a>

            {{-- Tombol Waiting List --}}
            <a href="{{ route('kasir.waiting-list') }}"
               class="h-9 px-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-200 text-amber-800 font-semibold text-xs transition inline-flex items-center gap-1.5 shrink-0"
               title="Lihat Pesanan / Waiting List & DP">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span class="whitespace-nowrap">Waiting List</span>
                <span x-show="jumlahPesanan > 0" x-text="jumlahPesanan"
                      class="px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-bold leading-none"></span>
                <span x-show="!jumlahPesanan"
                      class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-semibold leading-none">0</span>
                @if(($jumlahPesanan ?? 0) > 0)
                    <span x-show="false" class="px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-bold leading-none">{{ $jumlahPesanan }}</span>
                @endif
            </a>

            {{-- Tombol Fullscreen Browser (F11) --}}
            <button type="button" @click="toggleFullscreen()"
                    class="p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition flex items-center gap-1 text-xs font-medium shadow-2xs"
                    :title="isFullscreen ? 'Keluar Fullscreen (Esc)' : 'Layar Penuh (F11)'">
                <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                <svg x-show="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m6 5l5-5m0 0v4m0-4h-4m-6 6l-5 5m0 0v-4m0 4h4m6-5l5 5m0 0v-4m0 4h-4"/></svg>
                <span class="hidden xl:inline font-semibold" x-text="isFullscreen ? 'Normal' : 'Layar Penuh'"></span>
            </button>
        </div>
    </div>

    {{-- Banner Notifikasi Transaksi Tertahan --}}
    <div x-show="pesanSuksesTahan" x-cloak x-transition
         class="mb-3 p-3 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6M9 11h6M9 14h3"/>
            </svg>
            <span class="font-medium" x-text="pesanSuksesTahan"></span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('kasir.tahan') }}" class="btn btn-xs bg-white border border-blue-300 text-blue-700 hover:bg-blue-100 font-bold px-2.5 py-1 rounded-lg">
                Lihat Transaksi Tertahan (<span x-text="jumlahTertahan"></span>) &rarr;
            </a>
            <button type="button" @click="pesanSuksesTahan = ''" class="text-blue-400 hover:text-blue-600 font-bold px-1.5 py-0.5 rounded">&times;</button>
        </div>
    </div>


    {{-- MAIN POS WORKSPACE: Products & Cart --}}
    <div class="flex flex-col lg:flex-row gap-4 lg:flex-1 lg:min-h-0">

        {{-- LEFT: Product grid --}}
        <div class="flex-1 min-w-0 flex flex-col">
            {{-- PENCARIAN & FILTER KATEGORI CHIPS --}}
            <div class="kasir-top-filter">
                {{-- Baris Input Pencarian & Pindai Barcode --}}
                <div class="kasir-search-row">
                    <div class="relative flex-1">
                        <input type="text" x-model="search" x-ref="searchInput" @keydown.enter.prevent="cariAtauPindai()"
                            placeholder="Cari nama produk, SKU, atau scan barcode... (Enter)"
                            class="kasir-search-input">
                        <svg class="kasir-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/></svg>
                        <button type="button" x-show="search" @click="search = ''; $refs.searchInput.focus()"
                                class="kasir-search-clear" title="Hapus pencarian">&times;</button>
                    </div>
                    <button type="button" @click="cariAtauPindai()" class="btn btn-primary px-4 py-2.5 rounded-xl shadow-xs font-semibold text-xs flex items-center gap-1.5 shrink-0">
                        <span>Pindai / Cari</span>
                        <span class="text-[10px] opacity-75 bg-black/15 px-1 py-0.5 rounded">Enter</span>
                    </button>
                </div>

                {{-- Hasil pindaian barcode --}}
                <div x-show="scanPesan" x-cloak x-transition.opacity
                     class="text-xs rounded-xl px-3.5 py-2 flex items-center gap-2"
                     :class="scanGagal ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-800'">
                    <template x-if="scanGagal">
                        <svg class="w-4 h-4 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </template>
                    <template x-if="!scanGagal">
                        <svg class="w-4 h-4 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <span class="font-medium" x-text="scanPesan"></span>
                </div>

                {{-- Filter Kategori Horizontal Chips --}}
                <div class="kasir-cat-bar">
                    {{-- Tombol Reset (muncul jika ada filter kategori aktif) --}}
                    <template x-if="categoryId !== ''">
                        <button type="button" @click="categoryId = ''"
                                class="kat-btn kat-btn-reset px-2.5 py-1.5 rounded-xl whitespace-nowrap transition-all flex items-center gap-1 shrink-0 font-semibold text-xs"
                                title="Reset filter ke Semua Kategori">
                            <svg style="width: 12px; height: 12px;" class="shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Reset</span>
                        </button>
                    </template>

                    {{-- Tombol Kategori Terlaris (Paling Depan) --}}
                    <button type="button"
                            @click="categoryId = (categoryId === 'terlaris' ? '' : 'terlaris')"
                            class="kat-btn kat-theme-terlaris px-3 py-1.5 rounded-xl whitespace-nowrap transition-all flex items-center gap-2 shrink-0 font-semibold"
                            :class="categoryId === 'terlaris' ? 'is-active' : ''"
                            title="Filter Produk Terlaris">
                        <span class="kat-dot"></span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.316.492-.474.966-.567 1.344-.199.805-.308 1.488-.308 2.008 0 .426-.06.77-.168 1.05-.083.216-.208.384-.367.508-.159.123-.346.19-.548.19-.344 0-.671-.137-.91-.383a1 1 0 00-1.636.877c.074 1.157.483 2.14 1.168 2.924C7.545 12.44 8.7 13 10 13c1.657 0 3-1.343 3-3 0-1.503-.69-2.766-1.564-4.04-.492-.718-.958-1.464-1.041-2.407zM6.28 10.28a1 1 0 00-1.414 0A5.98 5.98 0 004 14c0 3.314 2.686 6 6 6s6-2.686 6-6a5.98 5.98 0 00-.866-3.72 1 1 0 00-1.638 1.144C13.82 12.186 14 13.067 14 14a4 4 0 11-8 0c0-.933.18-1.814.504-2.576a1 1 0 00-.224-1.144z" clip-rule="evenodd"/>
                            </svg>
                            <span>Terlaris</span>
                        </span>
                        <span class="kat-badge" x-text="allProducts.filter(p => topProductIds.includes(p.id)).length"></span>
                    </button>

                    {{-- Tombol Semua Kategori --}}
                    <button type="button" @click="categoryId = ''"
                            class="kat-btn kat-btn-all px-3 py-1.5 rounded-xl whitespace-nowrap transition-all flex items-center gap-2 shrink-0"
                            :class="categoryId === '' ? 'is-active' : ''">
                        <span class="kat-dot"></span>
                        <span>Semua Kategori</span>
                        <span class="kat-badge" x-text="allProducts.length"></span>
                    </button>

                    {{-- Tombol Tiap Kategori Berwarna --}}
                    @foreach($categories as $cat)
                    @php
                        $themeIdx = $loop->index % 10;
                    @endphp
                    <button type="button"
                            @click="categoryId = (String(categoryId) === '{{ $cat->id }}' ? '' : '{{ $cat->id }}')"
                            class="kat-btn kat-theme-{{ $themeIdx }} px-3 py-1.5 rounded-xl whitespace-nowrap transition-all flex items-center gap-2 shrink-0"
                            :class="String(categoryId) === '{{ $cat->id }}' ? 'is-active' : ''"
                            title="Klik untuk menyaring / klik lagi untuk batal">
                        <span class="kat-dot"></span>
                        <span>{{ $cat->name }}</span>
                        <span class="kat-badge" x-text="allProducts.filter(p => String(p.category_id) === '{{ $cat->id }}').length"></span>
                    </button>
                    @endforeach

                    {{-- Dropdown hidden untuk kompatibilitas form --}}
                    <select x-model="categoryId" class="hidden">
                        <option value="">Semua Kategori</option>
                        <option value="terlaris">Terlaris</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- MODE GAMBAR (Grid Kartu Produk) --}}
            <div x-show="viewMode === 'gambar'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3.5 pb-4 flex-1 overflow-y-auto content-start kasir-product-grid" style="align-content: flex-start;">
                <template x-for="p in filteredProducts" :key="p.id">
                    <button @click="onProductClick(p)" :disabled="p.type !== 'jasa' && p.stock <= 0"
                        class="card kasir-product-card p-3 text-left hover:shadow-lg hover:border-brand-300 hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0 flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white group">
                        <div>
                            <div class="aspect-square rounded-xl overflow-hidden bg-slate-100 mb-2.5 relative">
                                <img :src="p.image_url" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                {{-- Badge Kategori & Terlaris di pojok kiri atas foto --}}
                                <div class="absolute top-1.5 left-1.5 max-w-[70%] flex flex-col items-start gap-1 pointer-events-none">
                                    <template x-if="topProductIds.includes(p.id)">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-amber-500/95 text-white shadow-xs">
                                            <svg class="w-2.5 h-2.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.316.492-.474.966-.567 1.344-.199.805-.308 1.488-.308 2.008 0 .426-.06.77-.168 1.05-.083.216-.208.384-.367.508-.159.123-.346.19-.548.19-.344 0-.671-.137-.91-.383a1 1 0 00-1.636.877c.074 1.157.483 2.14 1.168 2.924C7.545 12.44 8.7 13 10 13c1.657 0 3-1.343 3-3 0-1.503-.69-2.766-1.564-4.04-.492-.718-.958-1.464-1.041-2.407zM6.28 10.28a1 1 0 00-1.414 0A5.98 5.98 0 004 14c0 3.314 2.686 6 6 6s6-2.686 6-6a5.98 5.98 0 00-.866-3.72 1 1 0 00-1.638 1.144C13.82 12.186 14 13.067 14 14a4 4 0 11-8 0c0-.933.18-1.814.504-2.576a1 1 0 00-.224-1.144z" clip-rule="evenodd"/></svg>
                                            <span>Terlaris</span>
                                        </span>
                                    </template>
                                    <template x-if="p.category_name">
                                        <span class="inline-block px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/70 backdrop-blur-xs text-white shadow-xs truncate max-w-full"
                                              x-text="p.category_name"></span>
                                    </template>
                                </div>
                                {{-- Badge Stok di pojok foto --}}
                                <div class="absolute top-1.5 right-1.5">
                                    <template x-if="p.type === 'jasa'">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-600/90 text-white shadow-xs">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span>Jasa</span>
                                        </span>
                                    </template>
                                    <template x-if="p.type !== 'jasa'">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shadow-xs"
                                              :class="p.stock <= 0 ? 'bg-red-600/90 text-white' : (p.stock <= 5 ? 'bg-amber-500/90 text-white' : 'bg-slate-900/70 text-white')"
                                              x-text="p.stock <= 0 ? 'Habis' : (formatQty(p.stock) + ' ' + (p.unit || 'pcs'))"></span>
                                    </template>
                                </div>
                            </div>
                            <div class="text-xs sm:text-sm font-semibold text-slate-800 line-clamp-2 leading-snug group-hover:text-brand-600 transition mb-1" x-text="p.name"></div>
                            <template x-if="p.rack_location">
                                <div class="text-[10px] text-slate-400 truncate mt-1 flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span x-text="'Rak: ' + p.rack_location"></span>
                                </div>
                            </template>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-slate-100">
                            <div class="text-sm sm:text-base font-black text-brand-600 tracking-tight" x-text="'Rp ' + formatNumber(p.sell_price)"></div>
                            <template x-if="p.additional_units && p.additional_units.length > 0">
                                <div class="text-[10px] font-medium text-amber-700 truncate mt-1" x-text="'atau per ' + p.additional_units.map(u => u.unit_name).join('/')"></div>
                            </template>
                            <template x-if="p.wholesale_price">
                                <div class="text-[10px] font-semibold text-green-700 bg-green-50 px-1.5 py-0.5 rounded border border-green-200/60 inline-block mt-1" x-text="'Grosir min ' + formatNumber(p.wholesale_min_qty)"></div>
                            </template>
                        </div>
                    </button>
                </template>
                <template x-if="filteredProducts.length === 0">
                    <div class="col-span-full text-center text-slate-400 py-20 bg-white rounded-2xl border border-slate-200/60">
                        <div class="w-12 h-12 mx-auto mb-2 text-slate-300 flex items-center justify-center rounded-2xl bg-slate-50">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <p class="font-medium text-sm text-slate-600">Produk tidak ditemukan</p>
                        <p class="text-xs text-slate-400 mt-1">Coba kata kunci lain atau pilih kategori Semua Kategori.</p>
                    </div>
                </template>
            </div>

            {{-- MODE LIST (Tabel Ringkas) --}}
            <div x-show="viewMode === 'list'" class="card overflow-hidden mb-4 rounded-2xl border border-slate-200/80 shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <tr>
                                <th class="py-2.5 px-3 text-left">Kode</th>
                                <th class="py-2.5 px-3 text-left">Nama Produk</th>
                                <th class="py-2.5 px-3 text-right">Harga</th>
                                <th class="py-2.5 px-3 text-right">Stok</th>
                                <th class="py-2.5 px-3 text-center w-12">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="p in filteredProducts" :key="p.id">
                                <tr @click="(p.type === 'jasa' || p.stock > 0) && onProductClick(p)"
                                    :class="(p.type !== 'jasa' && p.stock <= 0) ? 'opacity-40' : 'cursor-pointer hover:bg-brand-50/40 transition'">
                                    <td class="py-2 px-3 text-slate-500 text-xs font-mono" x-text="p.sku || '-'"></td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-semibold text-slate-800" x-text="p.name"></span>
                                            <template x-if="topProductIds.includes(p.id)">
                                                <span class="inline-flex items-center gap-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200/80 px-1.5 py-0.5 rounded-md">
                                                    <span>🔥 Terlaris</span>
                                                </span>
                                            </template>
                                            <template x-if="p.category_name">
                                                <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 border border-slate-200/80 px-1.5 py-0.5 rounded-md" x-text="p.category_name"></span>
                                            </template>
                                        </div>
                                        <template x-if="p.rack_location">
                                            <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                <span x-text="'Rak: ' + p.rack_location"></span>
                                            </div>
                                        </template>
                                        <template x-if="(p.additional_units && p.additional_units.length > 0) || p.wholesale_price">
                                            <div class="text-[10px] text-slate-500 mt-0.5">
                                                <span class="text-amber-600 font-medium" x-show="p.additional_units && p.additional_units.length > 0" x-text="'atau per ' + p.additional_units.map(u => u.unit_name).join('/')"></span>
                                                <span x-show="(p.additional_units && p.additional_units.length > 0) && p.wholesale_price"> &middot; </span>
                                                <span class="text-green-600 font-medium" x-show="p.wholesale_price" x-text="'grosir min ' + formatNumber(p.wholesale_min_qty)"></span>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="'Rp ' + formatNumber(p.sell_price)"></td>
                                    <td class="py-2 px-3 text-right">
                                        <span x-show="p.type === 'jasa'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span>Jasa</span>
                                        </span>
                                        <span x-show="p.type !== 'jasa'" class="font-medium text-xs" :class="p.stock <= 0 ? 'text-red-600 font-bold' : (p.stock <= 5 ? 'text-amber-600 font-bold' : 'text-slate-700')" x-text="formatQty(p.stock)"></span>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <button type="button" @click.stop="onProductClick(p)" :disabled="p.type !== 'jasa' && p.stock <= 0"
                                            class="w-8 h-8 rounded-xl bg-brand-600 text-white text-base font-bold hover:bg-brand-700 transition shadow-xs flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed mx-auto">+</button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="filteredProducts.length === 0">
                                <tr><td colspan="5" class="text-center text-slate-400 py-12">Produk tidak ditemukan.</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- RIGHT: Cart --}}
        {{-- Panel keranjang dibagi DUA ZONA.

             Yang di atas menggulung: daftar barang beserta seluruh isian pembayaran. Yang di
             bawah tidak pernah: Total dan tombol Bayar.

             Sebelumnya daftar barang punya gulungan sendiri DI DALAM panel yang juga bisa
             tergulung - dua gulungan bersarang. Yang terjadi berulang kali: kasir memutar roda
             tetikus di tempat yang salah, dan yang bergerak bukan yang ia maksud. Sekarang
             hanya ada satu gulungan, daftarnya bebas setinggi isinya, dan dua angka yang paling
             sering dicari tidak pernah hilang dari layar. --}}
        <div class="w-full lg:w-96 xl:w-[27rem] shrink-0 flex flex-col card p-0 lg:overflow-hidden">

            {{-- Dua zona ini hanya berlaku di layar lebar. Di layar sempit panelnya menumpuk
                 di bawah daftar produk dan HALAMANNYA yang menggulung - kalau kaki tetapnya
                 dipaksakan di sana, tombol Bayar justru terpotong dan tidak bisa diklik. --}}
            <div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto px-4 pt-4 pb-3">
                <div class="space-y-3">

                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-semibold flex items-center gap-2 text-slate-800">
                        <span>Keranjang</span>
                        {{-- Jumlah baris ditampilkan supaya kasir tahu ada berapa barang tanpa
                             menggulung daftarnya sampai habis. --}}
                        <span x-show="cart.length > 0" x-cloak x-text="cart.length"
                              class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 rounded-full bg-brand-500 text-white text-xs font-bold">0</span>
                    </h3>
                    <div class="flex items-center gap-2">
                        {{-- Penanda bahwa isi keranjang aman kalau kasir perlu pindah halaman dulu --}}
                        <span x-show="cart.length > 0" x-cloak class="text-[11px] text-slate-400" title="Keranjang tersimpan otomatis, aman kalau Anda pindah halaman dulu">Tersimpan</span>
                        <button type="button" x-show="cart.length > 0" x-cloak
                            @click="confirm('Kosongkan keranjang? Semua item akan dihapus.') && kosongkanKeranjang()"
                            class="text-xs text-red-500 hover:text-red-700 hover:underline">Kosongkan</button>
                    </div>
                </div>

                {{-- Muncul hanya kalau ada yang perlu diketahui kasir: harga/stok berubah, item
                     dikeluarkan, atau drafnya sudah lama ditinggal. --}}
                <template x-if="drafDipulihkan">
                    <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium">Keranjang sebelumnya dipulihkan.</span>
                            <button type="button" @click="drafDipulihkan = false" class="text-amber-500 hover:text-amber-700 shrink-0">&times;</button>
                        </div>
                        <ul x-show="catatanDraf.length > 0" class="list-disc list-inside mt-1 space-y-0.5">
                            <template x-for="c in catatanDraf" :key="c"><li x-text="c"></li></template>
                        </ul>
                        <p x-show="catatanDraf.length === 0" class="mt-0.5">Harga dan stok masih sama seperti sebelumnya.</p>
                    </div>
                </template>

                {{-- Customer dropdown --}}
                <div class="mb-3">
                    <select x-model="customerId" class="form-select w-full">
                        <option value="">Pelanggan Umum</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>


                    {{-- Tanpa batas tinggi dan tanpa gulungan sendiri: daftarnya tumbuh setinggi
                         isinya, dan yang menggulung adalah zona di atasnya. Tinggi minimum dipasang
                         supaya keranjang kosong tidak membuat panelnya melompat-lompat saat barang
                         pertama masuk. --}}
                    <div x-ref="daftarKeranjang" class="space-y-2 min-h-[200px]">
                        {{-- Jenis satuan ikut jadi kunci: kotak qty membaca opsi desimalnya SEKALI saat
                             dipasang, jadi baris yang berubah dari satuan hitung ke satuan timbangan
                             harus benar-benar menghasilkan elemen baru, bukan elemen lama yang dipakai
                             ulang dengan aturan lama. --}}
                        <template x-for="(item, idx) in cart" :key="item.product_id + '-' + item.unit_type + '-' + (item.is_weighable ? 'p' : 'b')">
                            {{-- Baris yang baru disentuh disorot sebentar (lihat sorotBaris()). Nama
                                 produk dibiarkan sampai dua baris, bukan dipotong: dua barang yang nama
                                 depannya sama - "Indomie Goreng" dan "Indomie Goreng Jumbo" - terlihat
                                 persis sama kalau dipotong, dan yang salah baru ketahuan di struk. --}}
                            <div :data-baris="idx"
                                 class="cart-item"
                                 :class="idx === barisTersorot ? 'is-highlighted' : ''">
                                {{-- Baris Atas: Nama Produk Full Width & Tombol Hapus --}}
                                <div class="flex items-start justify-between gap-1.5">
                                    <div class="flex-1 min-w-0">
                                        <span class="cart-item-title" x-text="item.name"></span>
                                    </div>
                                    <button type="button" @click="removeItem(idx)" title="Keluarkan dari keranjang"
                                            class="cart-item-del" aria-label="Hapus item">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Baris Bawah: Rincian Harga Satuan, Stepper Kuantitas, & Subtotal Baris --}}
                                <div class="cart-item-divider">
                                    <div class="cart-price-info">
                                        <span class="cart-unit-price" x-text="'@ Rp ' + formatNumber(linePrice(item))"></span>
                                        <span x-show="item.unit_label" class="cart-unit-badge" x-text="'/ ' + item.unit_label"></span>
                                        <span x-show="isWholesaleActive(item)" class="cart-grosir-badge">Grosir</span>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        {{-- Stepper Qty Presisi --}}
                                        <div class="cart-stepper">
                                            <button type="button" @click="decrQty(idx)"
                                                    class="cart-stepper-btn" title="Kurangi kuantitas" aria-label="Kurangi">
                                                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/>
                                                </svg>
                                            </button>
                                            <input type="text" data-jpos-number
                                                   :data-number-decimals="item.is_weighable ? 3 : 0"
                                                   :data-number-min="item.is_weighable ? 0.001 : 1"
                                                   x-number="item.qty"
                                                   @change="clampQty(idx)"
                                                   class="cart-stepper-input">
                                            <button type="button" @click="incrQty(idx)"
                                                    class="cart-stepper-btn" title="Tambah kuantitas" aria-label="Tambah">
                                                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                </svg>
                                            </button>
                                        </div>

                                        {{-- Total per baris --}}
                                        <span class="cart-subtotal"
                                              x-text="'Rp ' + formatNumber(linePrice(item) * item.qty)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="cart.length === 0">
                            <div class="text-center py-12 text-slate-400 select-none bg-white rounded-2xl border border-dashed border-slate-200">
                                <div class="w-16 h-16 mx-auto mb-3 text-slate-300 flex items-center justify-center rounded-2xl bg-slate-50">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-600">Keranjang masih kosong</p>
                                <p class="text-xs text-slate-400 mt-1">Pindai barcode atau klik produk untuk mulai transaksi.</p>
                            </div>
                        </template>
                    </div>

                    <div class="border-t border-slate-200/80 pt-3 space-y-2 text-sm">
                        <div class="flex justify-between text-slate-600"><span>Subtotal</span><span class="font-semibold text-slate-800" x-text="'Rp ' + formatNumber(subtotal())"></span></div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-600">Diskon</span>
                            <input type="text" data-jpos-number x-number="discount" class="w-28 text-right form-input py-1 bg-white">
                        </div>
                        @if($tax['enabled'] ?? false)
                        <div class="flex justify-between text-slate-500"><span>{{ $tax['name'] ?? 'Pajak' }} ({{ $tax['percent'] ?? 0 }}%)</span><span class="font-semibold" x-text="'Rp ' + formatNumber(taxAmount())"></span></div>
                        @endif
                    </div>

                    <div class="border-t border-slate-200/80 pt-3 space-y-2.5">
                        <label class="flex items-center gap-2 text-sm bg-amber-50/80 border border-amber-200/80 rounded-xl px-3 py-2 cursor-pointer transition hover:bg-amber-50">
                            <input type="checkbox" x-model="isWaitingList" class="rounded text-amber-600 focus:ring-amber-500">
                            <span class="text-amber-900">Jadikan <strong>Pesanan / Waiting List</strong></span>
                        </label>

                        {{-- Niat kasir DIPILIH, bukan ditebak dari nominal.
                             Keduanya menghasilkan pesanan `waiting` yang sama; yang berbeda cuma
                             nominal DP-nya (0 atau sekian). Radio ini ada supaya kasir tidak perlu
                             tahu bahwa "tanpa DP" berarti mengetik angka 0 - itu aturan tersembunyi. --}}
                        <template x-if="isWaitingList">
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 text-sm border rounded-xl px-3 py-2 cursor-pointer transition"
                                       :class="modePesanan === 'tanpa_dp' ? 'border-amber-400 bg-amber-50 font-semibold text-amber-900' : 'border-slate-200 bg-white'">
                                    <input type="radio" value="tanpa_dp" x-model="modePesanan" @change="paidAmount = 0">
                                    <span>Tanpa DP</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm border rounded-xl px-3 py-2 cursor-pointer transition"
                                       :class="modePesanan === 'dp' ? 'border-amber-400 bg-amber-50 font-semibold text-amber-900' : 'border-slate-200 bg-white'">
                                    <input type="radio" value="dp" x-model="modePesanan">
                                    <span>Bayar DP dulu</span>
                                </label>
                            </div>
                        </template>

                        <template x-if="isWaitingList">
                            <div>
                                <label class="form-label text-xs">Tanggal Pengambilan / Jatuh Tempo (opsional)</label>
                                <input type="date" x-model="dueDate" class="form-input bg-white">
                            </div>
                        </template>

                        <div>
                            <label class="form-label text-xs font-semibold text-slate-600 mb-1.5 block">Metode Pembayaran</label>
                            <div class="grid grid-cols-4 gap-1.5">
                                <button type="button" @click="setPaymentMethod('tunai')"
                                        class="py-2 px-1 rounded-xl text-xs font-semibold border flex flex-col items-center gap-1 transition"
                                        :class="paymentMethod === 'tunai' ? 'bg-brand-50 border-brand-500 text-brand-700 shadow-2xs font-bold ring-1 ring-brand-400' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2" stroke-width="2"/><circle cx="12" cy="12" r="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h.01M18 12h.01"/></svg>
                                    <span>Tunai</span>
                                </button>
                                <button type="button" @click="setPaymentMethod('debit')"
                                        class="py-2 px-1 rounded-xl text-xs font-semibold border flex flex-col items-center gap-1 transition"
                                        :class="paymentMethod === 'debit' ? 'bg-brand-50 border-brand-500 text-brand-700 shadow-2xs font-bold ring-1 ring-brand-400' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 15h2m4 0h4"/></svg>
                                    <span>Debit</span>
                                </button>
                                <button type="button" @click="setPaymentMethod('qris')"
                                        class="py-2 px-1 rounded-xl text-xs font-semibold border flex flex-col items-center gap-1 transition"
                                        :class="paymentMethod === 'qris' ? 'bg-brand-50 border-brand-500 text-brand-700 shadow-2xs font-bold ring-1 ring-brand-400' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" stroke-width="2" rx="1"/><rect x="14" y="3" width="7" height="7" stroke-width="2" rx="1"/><rect x="3" y="14" width="7" height="7" stroke-width="2" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 14h3v3h-3zm4 4h3v3h-3zm0-4h3m-3 7v-3"/></svg>
                                    <span>QRIS</span>
                                </button>
                                <button type="button" @click="setPaymentMethod('transfer')"
                                        class="py-2 px-1 rounded-xl text-xs font-semibold border flex flex-col items-center gap-1 transition"
                                        :class="paymentMethod === 'transfer' ? 'bg-brand-50 border-brand-500 text-brand-700 shadow-2xs font-bold ring-1 ring-brand-400' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M14 10v11M4 10h16L12 3 4 10z"/></svg>
                                    <span>Transfer</span>
                                </button>
                            </div>
                            <select x-model="paymentMethod" class="hidden">
                                <option value="tunai">Tunai</option>
                                <option value="debit">Kartu Debit</option>
                                <option value="qris">QRIS</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>

                        {{-- Disembunyikan di mode Tanpa DP: tidak ada nominal yang perlu diisi, dan kolom
                             kosong yang tetap tampil hanya membuat kasir ragu apakah ada yang terlewat. --}}
                        <template x-if="!isWaitingList || modePesanan === 'dp'">
                            <div>
                                <label class="form-label text-xs font-semibold text-slate-600 mb-1 block" x-text="isWaitingList ? 'Jumlah DP Yang Dibayar' : 'Jumlah Uang Diterima'"></label>
                                <input type="text" data-jpos-number x-number="paidAmount" :placeholder="isWaitingList ? 'Jumlah DP' : 'Jumlah bayar'" class="form-input text-lg font-bold py-2.5 bg-white">
                            </div>
                        </template>

                        <template x-if="isWaitingList && modePesanan === 'dp'">
                            <div class="grid grid-cols-4 gap-1">
                                <template x-for="pct in [25, 50, 75, 90]">
                                    <button @click="paidAmount = Math.round(total() * pct / 100)" class="text-xs py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800 font-semibold transition" x-text="pct + '%'"></button>
                                </template>
                            </div>
                        </template>
                        <template x-if="!isWaitingList">
                            <div class="space-y-2 pt-1">
                                {{-- Tombol Uang Pas --}}
                                <button type="button" @click="paidAmount = total()"
                                        class="w-full py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition border flex items-center justify-center gap-2 shadow-2xs cursor-pointer active:scale-95"
                                        :class="paidAmount === total() && total() > 0 ? 'bg-green-600 text-white border-green-600 ring-2 ring-green-300' : 'bg-green-50 hover:bg-green-100 text-green-800 border-green-300'">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Uang Pas</span>
                                    <span class="font-extrabold" x-show="total() > 0" x-text="'(Rp ' + formatNumber(total()) + ')'"></span>
                                </button>

                                {{-- Tombol Pilihan Nominal Cepat --}}
                                <div>
                                    <label class="form-label text-xs font-semibold text-slate-600 mb-1.5 block">Pilihan Nominal Cepat:</label>
                                    <div class="grid grid-cols-4 gap-1.5">
                                        <template x-for="amt in quickAmounts()" :key="amt">
                                            <button type="button" @click="paidAmount = amt"
                                                    class="py-2.5 px-1 text-xs sm:text-sm rounded-xl border transition font-bold shadow-2xs flex items-center justify-center active:scale-95 cursor-pointer"
                                                    :class="paidAmount === amt ? 'bg-brand-600 text-white border-brand-600 ring-2 ring-brand-300' : 'bg-white hover:bg-brand-50 hover:text-brand-700 border-slate-200/90 text-slate-700'"
                                                    x-text="formatShort(amt)"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!isWaitingList">
                            <div class="flex items-baseline justify-between py-1.5 border-t border-slate-100">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Kembalian</span>
                                    <span x-show="change() < 0 && (paidAmount || 0) > 0" x-cloak class="text-[11px] text-red-500 font-medium block">
                                        Kurang Rp <span x-text="formatNumber(Math.abs(change()))"></span>
                                    </span>
                                </div>
                                <span class="text-3xl sm:text-4xl font-black tabular-nums tracking-tight"
                                      :class="change() < 0 ? 'text-red-500' : 'text-green-600'"
                                      x-text="'Rp ' + formatNumber(Math.max(change(),0))">Rp 0</span>
                            </div>
                        </template>
                        <template x-if="isWaitingList">
                            <div class="flex items-baseline justify-between py-1.5 border-t border-slate-100">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Sisa Pelunasan</span>
                                <span class="text-3xl sm:text-4xl font-black text-amber-600 tabular-nums tracking-tight"
                                      x-text="'Rp ' + formatNumber(Math.max(total() - (paidAmount||0), 0))">Rp 0</span>
                            </div>
                        </template>
                </div>
            </div>{{-- akhir zona gulung --}}

        {{-- KAKI TETAP.

             Total dan tombol Bayar adalah dua hal yang paling sering dicari kasir, dan dua
             hal yang paling mahal kalau harus dicari dulu sambil pelanggan menunggu di
             depan meja. Keduanya sengaja dikeluarkan dari zona yang menggulung. --}}
        <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-3 pb-4 space-y-2">
            <div>
                <div class="flex items-baseline justify-between py-1.5 border-b border-slate-100 mb-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Belanja</span>
                    <span class="text-3xl sm:text-4xl font-black text-slate-900 tabular-nums tracking-tight" x-text="'Rp ' + formatNumber(total())">Rp 0</span>
                </div>
                <div class="space-y-2">
                    @if($pilihDokumen)
                    {{-- PILIHAN BENTUK DOKUMEN, tepat di atas tombol yang mencetaknya.

                         Ditaruh di sini dan bukan di layar terpisah karena keputusannya baru diambil
                         saat pelanggan sudah di depan meja: yang belanja tiga barang cukup struk,
                         yang belanja sekeranjang minta rinciannya. Menaruhnya di Pengaturan berarti
                         kasir harus keluar dari transaksi untuk mengubahnya.

                         Muncul hanya kalau dinyalakan di Pengaturan > Template Struk. Kalau mati,
                         tidak ada satu piksel pun yang berubah dari sebelumnya. --}}
                    <div>
                        <div class="mb-1">
                            <span class="text-xs font-medium text-slate-500">Cetak</span>
                        </div>
                        <div class="flex rounded-lg border border-slate-200 overflow-hidden text-xs">
                            <template x-for="pilihan in [
                                { nilai: 'struk',   label: 'Struk' },
                                { nilai: 'invoice', label: 'Invoice' }
                            ]" :key="pilihan.nilai">
                                <button type="button" @click="dokumenCetak = pilihan.nilai"
                                        class="flex-1 py-1.5 font-medium transition-colors border-l first:border-l-0 border-slate-200"
                                        :class="dokumenCetak === pilihan.nilai ? 'bg-brand-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                                        x-text="pilihan.label"></button>
                            </template>
                        </div>
                    </div>
                    @endif

                    {{-- Jalur TERTAHAN, terpisah dari Pesanan/DP di atas. Bedanya bukan mesinnya -
                         keduanya sama-sama menahan keranjang beserta stoknya - melainkan niatnya:
                         ini untuk pelanggan yang masih berdiri di depan kasir dan mau ambil barang
                         lain, supaya antrean di belakangnya bisa dilayani lebih dulu. --}}
                    <div class="space-y-2">
                        <template x-if="!isWaitingList">
                            <button @click="tahanTransaksi()" :disabled="cart.length === 0 || processing"
                                class="w-full btn justify-center py-2 border border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shrink-0">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6M9 11h6M9 14h3"/>
                                </svg>
                                <span>Tahan Transaksi &mdash; layani pelanggan berikutnya</span>
                            </button>
                        </template>

                        <button @click="checkout()" :disabled="cart.length === 0 || processing"
                            class="w-full btn justify-center py-3.5 text-base font-bold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 disabled:opacity-50"
                            :class="isWaitingList ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'btn-primary bg-green-600 hover:bg-green-700 text-white'">
                            <span x-show="!processing && !isWaitingList">Bayar &amp; Cetak Struk</span>
                            <span x-show="!processing && isWaitingList" x-text="modePesanan === 'dp' ? 'Simpan Sebagai Pesanan (DP)' : 'Simpan Sebagai Pesanan (Tanpa DP)'"></span>
                            <span x-show="processing">Memproses...</span>
                            <span x-show="!processing" class="text-xs opacity-75 font-normal bg-black/20 px-1.5 py-0.5 rounded">F9</span>
                        </button>
                    </div>
                </div>
            </div>
            <p class="text-xs text-red-500 font-medium" x-text="errorMsg"></p>
        </div>
    </div>
    </div>

    {{-- Unit picker modal --}}
    <div x-show="unitPickerProduct" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="unitPickerProduct = null" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5">
            <h3 class="font-semibold mb-1" x-text="unitPickerProduct ? unitPickerProduct.name : ''"></h3>
            <p class="text-xs text-slate-400 mb-4">Pilih satuan jual</p>
            <div class="space-y-2 max-h-[50vh] overflow-y-auto">
                <button @click="chooseUnit('base')" class="w-full flex justify-between items-center border rounded-lg px-4 py-3 hover:bg-slate-50">
                    <span class="uppercase text-sm font-medium" x-text="unitPickerProduct ? (unitPickerProduct.unit || 'pcs') : ''"></span>
                    <span class="font-semibold text-blue-600" x-text="unitPickerProduct ? 'Rp ' + formatNumber(unitPickerProduct.sell_price) : ''"></span>
                </button>
                <template x-for="u in (unitPickerProduct ? unitPickerProduct.additional_units : [])" :key="u.id">
                    <button @click="chooseUnit('unit_' + u.id)" class="w-full flex justify-between items-center border rounded-lg px-4 py-3 hover:bg-slate-50">
                        <span class="text-sm font-medium">
                            <span x-text="u.unit_name"></span>
                            <span class="text-slate-400 font-normal" x-text="'(isi ' + formatQty(u.conversion) + ' ' + (unitPickerProduct.unit || 'pcs') + ')'"></span>
                        </span>
                        <span class="font-semibold text-blue-600" x-text="'Rp ' + formatNumber(u.price)"></span>
                    </button>
                </template>
            </div>
            <button @click="unitPickerProduct = null" class="w-full mt-3 text-sm text-slate-500 hover:text-slate-700">Batal</button>
        </div>
    </div>

    @if($shiftKasirEnabled)
    {{-- Form Logout saat kasir membatalkan modal shift awal --}}
    <form id="logout-form-pos" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    {{-- MODAL BUKA SHIFT KASIR --}}
    <div x-show="showBukaShiftModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200"
             @click.outside="if (activeShiftId) showBukaShiftModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Mulai Shift Kasir Baru</span>
                </h3>
                <button type="button" @click="batalBukaShift()"
                        class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 text-sm font-bold transition cursor-pointer"
                        :title="activeShiftId ? 'Tutup popup' : 'Batal & Kembali ke Halaman Login'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('shift.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kasir Bertugas</label>
                    <input type="text" value="{{ auth()->user()->name }}" readonly class="form-input text-xs bg-slate-50 font-bold text-slate-700">
                </div>

                {{-- Waktu Buka Shift: Otomatis vs Manual --}}
                @if(($shiftKasirSettings['time_mode'] ?? 'auto') === 'manual')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                        <span>Tanggal &amp; Jam Buka Shift <span class="text-red-500">*</span></span>
                        <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Input Manual</span>
                    </label>
                    <input type="datetime-local" name="opened_at" value="{{ now()->format('Y-m-d\TH:i') }}" required
                           class="form-input text-xs w-full bg-amber-50/30 border-amber-300 font-mono">
                    <p class="text-[11px] text-slate-500 mt-1">Ubah tanggal &amp; jam jika shift dibuka susulan / di waktu berbeda.</p>
                </div>
                @else
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5 flex items-center justify-between text-xs text-slate-600">
                    <span class="flex items-center gap-1.5 font-medium">
                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Waktu Buka Shift:
                    </span>
                    <span class="font-semibold text-slate-800 bg-white border px-2 py-0.5 rounded shadow-2xs">
                        Otomatis ({{ now()->format('d/m/Y H:i') }})
                    </span>
                </div>
                @endif

                @if(!$cashDrawerEnabled)
                <input type="hidden" name="starting_cash" value="0">
                <div class="bg-amber-50/80 border border-amber-200/80 rounded-xl p-3.5 flex items-start gap-2.5 text-xs text-amber-900">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold block text-slate-800">Mode Kas Laci Dinonaktifkan</span>
                        <span class="text-slate-600 leading-relaxed">Anda tidak perlu memasukkan uang modal awal kas laci. Shift akan langsung dibuka untuk mencatat riwayat transaksi dan jam kerja Anda.</span>
                    </div>
                </div>
                @else
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                        <span>Modal Awal Uang Laci (Opening Cash Float) <span class="text-red-500">*</span></span>
                        @if(($shiftKasirSettings['starting_cash_mode'] ?? 'fixed') === 'last_closing')
                            <span class="text-[10px] font-bold text-green-700 bg-green-100 px-1.5 py-0.5 rounded">Ikuti Kas Terakhir</span>
                        @endif
                    </label>
                    <div class="flex items-stretch rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                        <span class="inline-flex items-center px-3 bg-slate-100 text-slate-600 font-bold text-xs border-r border-slate-200 select-none">Rp</span>
                        <input type="number" name="starting_cash" required
                               min="{{ !empty($shiftKasirSettings['require_positive_starting_cash']) ? 1 : 0 }}"
                               value="{{ (int) ($defaultStartingCash ?? 0) }}"
                               step="100" placeholder="0" autofocus
                               class="w-full py-2 px-3 text-sm font-bold text-slate-900 border-0 focus:ring-0 font-mono">
                    </div>
                    @if(($shiftKasirSettings['starting_cash_mode'] ?? 'fixed') === 'last_closing')
                        <p class="text-[11px] text-green-600 font-medium mt-1">
                            💡 Otomatis mengambil sisa kas fisik dari shift terakhir: Rp {{ number_format($defaultStartingCash ?? 0, 0, ',', '.') }}
                        </p>
                    @else
                        <p class="text-[11px] text-slate-500 mt-1">Uang kembalian yang dimasukkan ke laci kasir saat awal shift dimulai.</p>
                    @endif
                </div>
                @endif
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan (Opsional)</label>
                    <textarea name="notes" rows="2" placeholder="Catatan shift pagi / siang..." class="form-textarea text-xs"></textarea>
                </div>

                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-4">
                    <button type="button" @click="batalBukaShift()"
                            class="btn btn-outline text-xs font-semibold px-3 py-2 flex items-center gap-1.5 transition text-slate-600 hover:text-rose-600 hover:border-rose-300 hover:bg-rose-50/50 cursor-pointer">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span x-text="activeShiftId ? 'Batal' : 'Batal / Logout'"></span>
                    </button>
                    <button type="submit" class="btn btn-primary text-xs font-bold px-4 py-2">Buka Shift Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL TUTUP SHIFT KASIR --}}
    <div x-show="showTutupShiftModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 shadow-2xl border border-slate-200"
             @click.outside="showTutupShiftModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                    <span>Tutup Shift Kasir #<span x-text="tutupShiftData.shift?.id || activeShiftId"></span></span>
                </h3>
                <button type="button" @click="showTutupShiftModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <template x-if="shiftLoading">
                <div class="py-12 text-center text-slate-400 text-xs">
                    Memuat ringkasan shift terkini...
                </div>
            </template>

            <template x-if="!shiftLoading && tutupShiftData.summary">
                <div class="space-y-4">
                    {{-- Waktu Tutup Shift (Manual vs Otomatis) --}}
                    @if(($shiftKasirSettings['time_mode'] ?? 'auto') === 'manual')
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Tanggal &amp; Jam Tutup Shift <span class="text-red-500">*</span></span>
                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Input Manual</span>
                        </label>
                        <input type="datetime-local" x-model="tutupWaktuShift" required
                               class="form-input text-xs w-full bg-amber-50/30 border-amber-300 font-mono">
                        <p class="text-[11px] text-slate-500 mt-1">Ubah tanggal &amp; jam bila penutupan shift dicatat susulan.</p>
                    </div>
                    @endif

                    {{-- Kotak Rincian Penjualan --}}
                    <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200 text-xs space-y-2">
                        @if($cashDrawerEnabled)
                        <div class="flex justify-between text-slate-600">
                            <span>Modal Awal Kasir:</span>
                            <span class="font-mono font-bold text-slate-800">Rp <span x-text="formatRupiahKasir(tutupShiftData.summary.starting_cash)"></span></span>
                        </div>
                        @endif
                        <div class="flex justify-between text-slate-600">
                            <span>(+) Penjualan Tunai:</span>
                            <span class="font-mono font-bold text-green-700">+ Rp <span x-text="formatRupiahKasir(tutupShiftData.summary.cash_sales)"></span></span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>(+) Penjualan Non-Tunai:</span>
                            <span class="font-mono font-semibold text-blue-700">Rp <span x-text="formatRupiahKasir(tutupShiftData.summary.non_cash_sales)"></span></span>
                        </div>
                        <template x-if="tutupShiftData.summary.cash_refunds > 0">
                            <div class="flex justify-between text-red-600">
                                <span>(-) Refund Retur Tunai:</span>
                                <span class="font-mono font-bold">- Rp <span x-text="formatRupiahKasir(tutupShiftData.summary.cash_refunds)"></span></span>
                            </div>
                        </template>
                        @if($cashDrawerEnabled)
                        <div class="pt-2 border-t border-slate-200 flex justify-between text-slate-900 font-bold text-sm">
                            <span>Uang Seharusnya di Laci:</span>
                            <span class="font-mono text-green-800">Rp <span x-text="formatRupiahKasir(tutupShiftData.summary.expected_cash)"></span></span>
                        </div>
                        @else
                        <div class="pt-2 border-t border-slate-200 flex justify-between text-slate-900 font-bold text-sm">
                            <span>Total Penjualan Shift:</span>
                            <span class="font-mono text-green-800">Rp <span x-text="formatRupiahKasir(tutupShiftData.summary.total_sales)"></span></span>
                        </div>
                        @endif
                    </div>

                    @if($cashDrawerEnabled)
                    {{-- Input Penghitungan Uang Fisik Kasir --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">
                            Uang Fisik Laci Aktual (Hasil Hitung Fisik) <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-stretch rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                            <span class="inline-flex items-center px-3 bg-slate-100 text-slate-600 font-bold text-xs border-r border-slate-200 select-none">Rp</span>
                            <input type="number" x-model.number="tutupUangFisikKasir" required min="0" step="100" placeholder="0"
                                   class="w-full py-2.5 px-3 text-base font-bold font-mono text-slate-900 border-0 focus:ring-0">
                        </div>
                    </div>

                    {{-- Indikator Selisih Kas --}}
                    <div class="p-3 rounded-xl border text-xs flex items-center justify-between"
                         :class="selisihKelasKasir()">
                        <span class="font-semibold" x-text="selisihLabelKasir()"></span>
                        <span class="font-mono font-bold text-sm" x-text="selisihNominalKasir()"></span>
                    </div>
                    @else
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-600 flex items-center gap-2">
                        <span class="p-1 rounded bg-slate-200 text-slate-700">ℹ️</span>
                        <span>Mode Kas Laci Nonaktif — Rekapitulasi shift akan ditutup tanpa menghitung fisik uang kas laci.</span>
                    </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Penutup Shift (Opsional)</label>
                        <textarea x-model="tutupCatatanShift" rows="2" placeholder="Keterangan bila ada selisih uang atau serah terima..." class="form-textarea text-xs"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="showTutupShiftModal = false" class="btn btn-outline text-xs">Batal</button>
                        <button type="button" @click="submitTutupShift()" class="btn btn-primary bg-green-600 hover:bg-green-700 text-white font-bold text-xs px-4 py-2">
                            Tutup Shift &amp; Cetak Laporan
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
    @endif

    </div>

@push('scripts')
<script>
function kasirApp() {
    return {
        allProducts: @json($productsForCart),
        topProductIds: @json($topProductIds),
        search: '',
        // Umpan balik hasil pindaian. Ada karena versi sebelumnya sama sekali tidak
        // menampilkan apa pun saat kode tidak ketemu - kasir memindai berulang kali tanpa
        // petunjuk, lalu menyimpulkan alat pindainya rusak.
        scanPesan: '',
        scanGagal: false,
        scanTimer: null,
        _searchBarcodeTimer: null,
        categoryId: @json(count($topProductIds) > 0 ? 'terlaris' : ''),
        cart: [],
        // Baris keranjang yang baru saja disentuh - lihat sorotBaris() untuk alasannya.
        barisTersorot: -1,
        sorotTimer: null,
        // Bentuk dokumen yang akan dicetak sesudah bayar. Nilai awalnya dari Pengaturan.
        dokumenCetak: @json($dokumenDefault ?? 'struk'),
        customerId: '',
        discount: 0,
        paymentMethod: 'tunai',
        paidAmount: 0,
        isWaitingList: false,
        // 'tanpa_dp' | 'dp' - hanya menentukan nominal DP, bukan status pesanannya.
        // Keduanya tetap menghasilkan order_status = 'waiting'.
        modePesanan: 'tanpa_dp',
        dueDate: '',
        processing: false,
        errorMsg: '',
        unitPickerProduct: null,
        viewMode: '{{ $defaultView }}',
        allowToggle: {{ $allowToggle ? 'true' : 'false' }},
        taxPercent: {{ ($tax['enabled'] ?? false) ? ($tax['percent'] ?? 0) : 0 }},
        jumlahTertahan: {{ (int) ($jumlahTertahan ?? 0) }},
        jumlahPesanan: {{ (int) ($jumlahPesanan ?? 0) }},
        pesanSuksesTahan: '',

        // Shift Kasir
        shiftKasirEnabled: {{ $shiftKasirEnabled ? 'true' : 'false' }},
        cashDrawerEnabled: {{ $cashDrawerEnabled ? 'true' : 'false' }},
        activeShiftId: {{ $activeShift ? $activeShift->id : 'null' }},
        showBukaShiftModal: false,
        showTutupShiftModal: false,
        shiftLoading: false,
        tutupShiftData: {},
        tutupUangFisikKasir: 0,
        tutupWaktuShift: '',
        tutupCatatanShift: '',

        // Live Clock & Fullscreen
        jamSekarang: '',
        tanggalHariIni: '',
        isFullscreen: false,

        // --- Draf keranjang -------------------------------------------------
        // Keranjang disimpan otomatis supaya tidak hilang saat kasir pindah halaman -
        // mis. ke Master Produk untuk menambah barang yang belum terdaftar, lalu kembali
        // untuk melanjutkan transaksi yang sama.
        //
        // Kuncinya diberi id user supaya draf kasir A tidak muncul di layar kasir B yang
        // login di komputer yang sama.
        kunciDraf: 'jpos_draf_kasir_{{ auth()->id() }}',
        UMUR_DRAF_JAM: 12,
        JEDA_PEMULIHAN_SENYAP_MENIT: 30,
        catatanDraf: [],
        drafDipulihkan: false,
        _timerDraf: null,

        init() {
            if (this.allowToggle) {
                this.viewMode = localStorage.getItem('kasir_view_mode') || '{{ $defaultView }}';
            }

            if (this.shiftKasirEnabled && !this.activeShiftId) {
                setTimeout(() => {
                    this.showBukaShiftModal = true;
                }, 400);
            }

            this.updateClock();
            setInterval(() => this.updateClock(), 1000);
            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F7') {
                    e.preventDefault();
                    if (this.cart.length > 0 && !this.processing && !this.isWaitingList) {
                        this.tahanTransaksi();
                    }
                } else if (e.key === 'F9') {
                    e.preventDefault();
                    this.checkout();
                } else if (e.key === 'Escape') {
                    if (this.cart.length > 0 && confirm('Kosongkan keranjang? Semua item akan dihapus.')) {
                        this.kosongkanKeranjang();
                    }
                }
            });

            this.pulihkanDraf();
            this.pulihkanKeranjangTertahan();

            // Disimpan setiap kali isi transaksi berubah. $watch di Alpine 3 memantau
            // sampai ke dalam isi array, jadi perubahan qty per baris ikut tersimpan.
            ['cart', 'customerId', 'discount', 'paymentMethod', 'paidAmount', 'isWaitingList', 'dueDate']
                .forEach(k => this.$watch(k, () => this.simpanDraf()));

            // Sinkronisasi otomatis nilai pembayaran jika metode non-tunai dipilih
            this.$watch('paymentMethod', (val) => {
                if (val !== 'tunai' && (!this.isWaitingList || this.modePesanan === 'dp')) {
                    this.paidAmount = this.total();
                }
            });
            this.$watch('cart', () => {
                if (this.paymentMethod !== 'tunai' && (!this.isWaitingList || this.modePesanan === 'dp')) {
                    this.paidAmount = this.total();
                }
            });
            this.$watch('discount', () => {
                if (this.paymentMethod !== 'tunai' && (!this.isWaitingList || this.modePesanan === 'dp')) {
                    this.paidAmount = this.total();
                }
            });

            // Auto-pindai saat teks pencarian cocok persis dengan barcode terdaftar (untuk scanner fisik tanpa akhiran Enter)
            this.$watch('search', (val) => {
                clearTimeout(this._searchBarcodeTimer);
                const teks = (val || '').trim();
                if (!teks || teks.length < 3) return;

                const adaBarcodePersis = this.allProducts.some(p =>
                    (p.barcode && this.samaBarcode(p.barcode, teks)) ||
                    (p.additional_units && p.additional_units.some(u => u.barcode && this.samaBarcode(u.barcode, teks)))
                );

                if (adaBarcodePersis) {
                    this._searchBarcodeTimer = setTimeout(() => {
                        if ((this.search || '').trim() === teks) {
                            this.cariAtauPindai();
                        }
                    }, 120);
                }
            });

            this.focusSearch();
        },

        updateClock() {
            const now = new Date();
            this.jamSekarang = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.tanggalHariIni = now.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(() => {});
                }
            }
        },

        /**
         * Sorot baris keranjang yang baru saja ditambah atau diubah jumlahnya, dan
         * gulung panelnya supaya baris itu benar-benar terlihat oleh kasir.
         *
         * Tanpa ini, kasir yang memindai barang ke-10 tidak melihat apa pun berubah di
         * layar: barangnya masuk di bawah lipatan yang tergulung, kasir mengira pindaian
         * gagal, memindai lagi, dan barangnya masuk dua kali.
         */
        sorotBaris(idx) {
            this.barisTersorot = idx;
            clearTimeout(this.sorotTimer);
            this.sorotTimer = setTimeout(() => { this.barisTersorot = -1; }, 1600);

            this.$nextTick(() => {
                const baris = this.$refs.daftarKeranjang?.querySelector(`[data-baris="${idx}"]`);
                if (baris) {
                    baris.scrollIntoView({ block: 'nearest' });
                }
            });
        },

        simpanDraf() {
            clearTimeout(this._timerDraf);
            this._timerDraf = setTimeout(() => {
                if (this.cart.length === 0) {
                    localStorage.removeItem(this.kunciDraf);
                    return;
                }
                const data = {
                    cart: this.cart,
                    customerId: this.customerId,
                    discount: this.discount,
                    paymentMethod: this.paymentMethod,
                    paidAmount: this.paidAmount,
                    isWaitingList: this.isWaitingList,
                    dueDate: this.dueDate,
                    tersimpanPada: Date.now(),
                };
                try {
                    localStorage.setItem(this.kunciDraf, JSON.stringify(data));
                } catch (e) {
                    // localStorage penuh / dinonaktifkan peramban: transaksi tetap berjalan normal
                }
            }, 300);
        },

        hapusDraf() {
            clearTimeout(this._timerDraf);
            try {
                localStorage.removeItem(this.kunciDraf);
            } catch (e) {}
            this.drafDipulihkan = false;
            this.catatanDraf = [];
        },

        pulihkanDraf() {
            let mentah;
            try {
                mentah = localStorage.getItem(this.kunciDraf);
            } catch (e) {
                return;
            }
            if (!mentah) return;

            let data;
            try {
                data = JSON.parse(mentah);
            } catch (e) {
                this.hapusDraf();
                return;
            }

            if (!data || !Array.isArray(data.cart) || data.cart.length === 0) {
                this.hapusDraf();
                return;
            }

            // Draf kedaluwarsa dibuang diam-diam supaya kasir shift pagi tidak kaget
            // melihat sisa belanjaan orang kemarin sore.
            const umurJam = (Date.now() - (data.tersimpanPada || 0)) / (1000 * 60 * 60);
            if (umurJam > this.UMUR_DRAF_JAM) {
                this.hapusDraf();
                return;
            }

            // Tiap barang diverifikasi ulang ke katalog yang BARU: harga bisa saja sudah
            // diubah di Master Produk, atau barangnya sudah dihapus/dinonaktifkan sementara
            // kasir pergi. Yang tidak valid tidak boleh ikut dipulihkan.
            const keranjangValid = [];
            const catatan = [];

            for (const item of data.cart) {
                const live = this.allProducts.find(p => p.id === item.product_id);
                if (!live) {
                    catatan.push(`"${item.name}" dihapus dari draf karena tidak lagi tersedia.`);
                    continue;
                }

                if (item.unit_type === 'base') {
                    if (live.sell_price !== item.sell_price) {
                        catatan.push(`Harga "${item.name}" diperbarui dari Rp ${this.formatNumber(item.sell_price)} ke Rp ${this.formatNumber(live.sell_price)}.`);
                        item.sell_price = live.sell_price;
                    }
                    item.stock = live.stock;
                    item.image_url = live.image_url;
                    keranjangValid.push(item);
                } else if (item.unit_type && item.unit_type.startsWith('unit_')) {
                    const unitId = parseInt(item.unit_type.replace('unit_', ''));
                    const unitLive = (live.additional_units || []).find(u => u.id === unitId);
                    if (!unitLive) {
                        catatan.push(`Satuan untuk "${item.name}" tidak lagi tersedia.`);
                        continue;
                    }
                    if (unitLive.price !== item.sell_price) {
                        catatan.push(`Harga "${item.name}" (${unitLive.unit_name}) diperbarui.`);
                        item.sell_price = unitLive.price;
                    }
                    item.stock = live.stock;
                    item.image_url = live.image_url;
                    keranjangValid.push(item);
                } else {
                    keranjangValid.push(item);
                }
            }

            if (keranjangValid.length === 0) {
                this.hapusDraf();
                return;
            }

            this.cart = keranjangValid;
            this.customerId = data.customerId || '';
            this.discount = data.discount || 0;
            this.paymentMethod = data.paymentMethod || 'tunai';
            this.paidAmount = data.paidAmount || 0;
            this.isWaitingList = !!data.isWaitingList;
            this.dueDate = data.dueDate || '';

            const menitBerlalu = Math.round((Date.now() - (data.tersimpanPada || 0)) / 60000);
            this.catatanDraf = catatan;
            // Spanduk hanya ditampilkan kalau ada barang yang berubah/dibuang, ATAU kalau
            // jedanya cukup lama sampai kasir mungkin sudah lupa ia pernah meninggalkan draf.
            this.drafDipulihkan = catatan.length > 0 || menitBerlalu > this.JEDA_PEMULIHAN_SENYAP_MENIT;
        },

        setViewMode(mode) {
            this.viewMode = mode;
            if (this.allowToggle) localStorage.setItem('kasir_view_mode', mode);
        },

        focusSearch() {
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        cocokBarcode(kode, term) {
            if (!kode || !term) return false;
            const k = String(kode).trim().toLowerCase();
            const t = String(term).trim().toLowerCase();
            if (k.includes(t) || t.includes(k)) return true;
            const kTanpaNol = k.replace(/^0+/, '');
            const tTanpaNol = t.replace(/^0+/, '');
            return Boolean(kTanpaNol && tTanpaNol && (kTanpaNol.includes(tTanpaNol) || tTanpaNol.includes(kTanpaNol)));
        },

        samaBarcode(a, b) {
            if (!a || !b) return false;
            const strA = String(a).trim().toLowerCase();
            const strB = String(b).trim().toLowerCase();
            if (strA === strB) return true;
            const aTanpaNol = strA.replace(/^0+/, '');
            const bTanpaNol = strB.replace(/^0+/, '');
            return aTanpaNol !== '' && aTanpaNol === bTanpaNol;
        },

        get filteredProducts() {
            const hasSearch = (this.search || '').trim().length > 0;
            const term = (this.search || '').trim().toLowerCase();

            let list = this.allProducts.filter(p => {
                const matchSearch = !hasSearch ||
                    (p.name && p.name.toLowerCase().includes(term)) ||
                    (p.barcode && this.cocokBarcode(p.barcode, term)) ||
                    (p.sku && String(p.sku).toLowerCase().includes(term)) ||
                    (p.additional_units && p.additional_units.some(u => u.barcode && this.cocokBarcode(u.barcode, term)));

                let matchCat = true;
                if (!hasSearch) {
                    if (this.categoryId === 'terlaris') {
                        matchCat = this.topProductIds.includes(p.id);
                    } else if (this.categoryId !== '') {
                        matchCat = String(p.category_id) === String(this.categoryId);
                    }
                }
                return matchSearch && matchCat;
            });

            if (this.categoryId === 'terlaris' && !hasSearch) {
                list.sort((a, b) => {
                    const idxA = this.topProductIds.indexOf(a.id);
                    const idxB = this.topProductIds.indexOf(b.id);
                    return (idxA === -1 ? 999 : idxA) - (idxB === -1 ? 999 : idxB);
                });
            }

            return list;
        },

        onProductClick(p) {
            if (p.additional_units && p.additional_units.length > 0) {
                this.unitPickerProduct = p;
            } else {
                this.addToCartWithUnit(p, 'base');
            }
        },

        chooseUnit(unitType) {
            if (this.unitPickerProduct) {
                this.addToCartWithUnit(this.unitPickerProduct, unitType);
                this.unitPickerProduct = null;
            }
        },

        addToCartWithUnit(p, unitType) {
            let unitLabel = p.unit || 'pcs';
            let sellPrice = p.sell_price;
            let unitConversion = 1;
            let isWeighable = !!p.is_weighable;

            if (unitType && unitType.startsWith('unit_')) {
                const unitId = parseInt(unitType.replace('unit_', ''));
                const u = p.additional_units ? p.additional_units.find(x => x.id === unitId) : null;
                if (u) {
                    unitLabel = u.unit_name;
                    sellPrice = u.price;
                    unitConversion = u.conversion;
                    isWeighable = !!u.is_weighable;
                }
            }

            const existingIdx = this.cart.findIndex(i => i.product_id === p.id && i.unit_type === unitType);
            if (existingIdx >= 0) {
                const item = this.cart[existingIdx];
                const currentBase = (item.qty + 1) * item.unit_conversion;
                if (p.type !== 'jasa' && currentBase > p.stock) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + (p.unit || 'pcs'));
                    return;
                }
                item.qty++;
                this.sorotBaris(existingIdx);
            } else {
                if (p.type !== 'jasa' && unitConversion > p.stock) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + (p.unit || 'pcs'));
                    return;
                }
                this.cart.push({
                    product_id: p.id,
                    name: p.name,
                    image_url: p.image_url,
                    unit_label: unitLabel,
                    unit_type: unitType,
                    unit_conversion: unitConversion,
                    sell_price: sellPrice,
                    wholesale_price: p.wholesale_price,
                    wholesale_min_qty: p.wholesale_min_qty,
                    stock: p.stock,
                    base_unit: p.unit || 'pcs',
                    type: p.type,
                    is_weighable: isWeighable,
                    qty: 1,
                });
                this.sorotBaris(this.cart.length - 1);
            }
            this.focusSearch();
        },

        incrQty(idx) {
            const item = this.cart[idx];
            const p = this.allProducts.find(x => x.id === item.product_id);
            const step = item.is_weighable ? 0.1 : 1;
            const newQty = item.is_weighable ? Math.round((item.qty + step) * 1000) / 1000 : item.qty + step;
            if (p && p.type !== 'jasa') {
                const newBase = newQty * item.unit_conversion;
                if (newBase > p.stock) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + (p.unit || 'pcs'));
                    return;
                }
            }
            item.qty = newQty;
            this.sorotBaris(idx);
        },

        decrQty(idx) {
            const item = this.cart[idx];
            const minQty = item.is_weighable ? 0.001 : 1;
            const step = item.is_weighable ? 0.1 : 1;
            if (item.qty > minQty) {
                item.qty = item.is_weighable ? Math.round((item.qty - step) * 1000) / 1000 : item.qty - step;
                if (item.qty < minQty) item.qty = minQty;
                this.sorotBaris(idx);
            } else {
                this.removeItem(idx);
            }
        },

        clampQty(idx) {
            const item = this.cart[idx];
            const p = this.allProducts.find(x => x.id === item.product_id);
            const minQty = item.is_weighable ? 0.001 : 1;
            if (isNaN(item.qty) || item.qty < minQty) {
                item.qty = minQty;
            }
            if (p && p.type !== 'jasa') {
                const neededBase = item.qty * item.unit_conversion;
                if (neededBase > p.stock) {
                    const maxUnits = Math.floor(p.stock / item.unit_conversion);
                    item.qty = Math.max(minQty, maxUnits);
                    alert('Stok disesuaikan ke maksimal: ' + item.qty + ' ' + item.unit_label);
                }
            }
            this.sorotBaris(idx);
        },

        removeItem(idx) {
            this.cart.splice(idx, 1);
            if (this.barisTersorot === idx) this.barisTersorot = -1;
            this.focusSearch();
        },

        kosongkanKeranjang() {
            this.cart = [];
            this.hapusDraf();
            this.barisTersorot = -1;
            this.focusSearch();
        },

        isWholesaleActive(item) {
            if (!item.wholesale_price || !item.wholesale_min_qty) return false;
            // Harga grosir hanya berlaku untuk satuan dasar
            if (item.unit_type !== 'base') return false;
            return item.qty >= item.wholesale_min_qty;
        },

        linePrice(item) {
            if (this.isWholesaleActive(item)) {
                return Number(item.wholesale_price);
            }
            return Number(item.sell_price);
        },

        subtotal() {
            return this.cart.reduce((sum, item) => sum + (this.linePrice(item) * item.qty), 0);
        },

        taxAmount() {
            const afterDiscount = Math.max(0, this.subtotal() - (this.discount || 0));
            return Math.round(afterDiscount * this.taxPercent / 100);
        },

        total() {
            const base = Math.max(0, this.subtotal() - (this.discount || 0));
            return base + this.taxAmount();
        },

        change() {
            return (this.paidAmount || 0) - this.total();
        },

        setPaymentMethod(method) {
            this.paymentMethod = method;
            if (method !== 'tunai' && (!this.isWaitingList || this.modePesanan === 'dp')) {
                this.paidAmount = this.total();
            }
        },

        quickAmounts() {
            const tot = this.total();
            if (tot <= 0) return [10000, 20000, 50000, 100000];

            const candidates = [];

            // 1. Pembulatan logis ke atas berdasarkan rentang nominal belanja
            if (tot < 20000) {
                const r5 = Math.ceil((tot + 1) / 5000) * 5000;
                if (r5 > tot) candidates.push(r5);
                const r10 = Math.ceil((tot + 1) / 10000) * 10000;
                if (r10 > tot) candidates.push(r10);
            } else if (tot < 50000) {
                const r10 = Math.ceil((tot + 1) / 10000) * 10000;
                if (r10 > tot) candidates.push(r10);
                const r20 = Math.ceil((tot + 1) / 20000) * 20000;
                if (r20 > tot) candidates.push(r20);
            } else if (tot < 100000) {
                if (tot % 50000 !== 0) {
                    const r10 = Math.ceil((tot + 1) / 10000) * 10000;
                    if (r10 > tot) candidates.push(r10);
                    const r50 = Math.ceil((tot + 1) / 50000) * 50000;
                    if (r50 > tot) candidates.push(r50);
                }
            } else {
                const r50 = Math.ceil((tot + 1) / 50000) * 50000;
                if (r50 > tot) candidates.push(r50);
                const r100 = Math.ceil((tot + 1) / 100000) * 100000;
                if (r100 > tot) candidates.push(r100);
            }

            // 2. Lembaran uang baku ATM & pecahan uang umum di Indonesia
            const standardNotes = [
                10000, 20000, 50000, 100000, 150000, 200000, 300000, 400000, 500000, 1000000
            ];
            for (const s of standardNotes) {
                if (s > tot) {
                    candidates.push(s);
                }
            }

            const unique = Array.from(new Set(candidates))
                .filter(v => v > tot)
                .sort((a, b) => a - b);

            const result = unique.slice(0, 4);

            while (result.length < 4) {
                const last = result[result.length - 1] || tot;
                const step = last >= 200000 ? 100000 : (last >= 100000 ? 50000 : 10000);
                result.push(last + step);
            }

            return result.slice(0, 4);
        },

        formatNumber(n) {
            return new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
        },

        formatQty(n) {
            const num = parseFloat(n) || 0;
            return num % 1 === 0 ? num.toString() : num.toFixed(3).replace(/\.?0+$/, '');
        },

        formatShort(n) {
            if (n >= 1000000) return (n / 1000000) + 'jt';
            if (n >= 1000) return (n / 1000) + 'rb';
            return n.toString();
        },

        beriTahuPindai(pesan, gagal = false) {
            this.scanPesan = pesan;
            this.scanGagal = gagal;
            clearTimeout(this.scanTimer);
            this.scanTimer = setTimeout(() => {
                this.scanPesan = '';
                this.scanGagal = false;
            }, 4000);
        },

        /**
         * Pindai barcode dari alat pindai fisik.
         *
         * Endpoint mengembalikan {found, product, unit_id, unit_name, message}.
         * Barcode satuan langsung membawa unit_id-nya, jadi kasir tidak perlu memilih lagi.
         */
        async pindai(kode) {
            const bersih = (kode || '').trim();
            if (!bersih) return;

            // 1. Cek langsung di katalog lokal (allProducts) tanpa menunggu latency network
            for (const p of this.allProducts) {
                if (p.additional_units && p.additional_units.length > 0) {
                    const matchedUnit = p.additional_units.find(u => u.barcode && this.samaBarcode(u.barcode, bersih));
                    if (matchedUnit) {
                        this.addToCartWithUnit(p, 'unit_' + matchedUnit.id);
                        this.beriTahuPindai(p.name + ' (' + matchedUnit.unit_name + ') ditambahkan ke keranjang.', false);
                        this.search = '';
                        this.focusSearch();
                        return;
                    }
                }
            }

            const exactProduct = this.allProducts.find(p =>
                (p.barcode && this.samaBarcode(p.barcode, bersih)) ||
                (p.sku && String(p.sku).trim().toLowerCase() === bersih.toLowerCase())
            );
            if (exactProduct) {
                this.addToCartWithUnit(exactProduct, 'base');
                this.beriTahuPindai(exactProduct.name + ' ditambahkan ke keranjang.', false);
                this.search = '';
                this.focusSearch();
                return;
            }

            // 2. Jika tidak ada di katalog lokal, hubungi server lewat endpoint kasir.scan (fallback)
            try {
                const res = await fetch('{{ route('kasir.scan') }}?barcode=' + encodeURIComponent(bersih));
                const data = await res.json();

                if (!data.found) {
                    this.beriTahuPindai(data.message || ('Barcode "' + bersih + '" tidak terdaftar.'), true);
                    return;
                }

                let lokal = this.allProducts.find(p => p.id === data.product.id);
                if (!lokal) {
                    this.allProducts.push(data.product);
                    lokal = data.product;
                }
                const unitType = data.unit_id ? ('unit_' + data.unit_id) : 'base';

                this.addToCartWithUnit(lokal, unitType);

                const labelSatuan = data.unit_name ? (' (' + data.unit_name + ')') : '';
                this.beriTahuPindai(data.product.name + labelSatuan + ' ditambahkan ke keranjang.', false);
            } catch (e) {
                this.beriTahuPindai('Gagal menghubungi server untuk memindai.', true);
            } finally {
                this.search = '';
                this.focusSearch();
            }
        },

        /**
         * Jalur saat kasir menekan Enter di kolom pencarian.
         *
         * Bisa berupa barcode yang diketik manual, atau nama produk. Yang diperiksa dulu
         * adalah apakah ada barcode / SKU yang COCOK PERSIS di katalog lokal - ini penting
         * untuk barang yang barcodenya sobek dan kodenya diketik tangan.
         *
         * Kalau bukan kode, TIDAK langsung dimasukkan ke keranjang. Nama produk bisa cocok ke
         * banyak barang, dan memasukkan tebakan ke keranjang berarti menjual barang yang salah.
         * Yang dilakukan: kalau hasil saringan tinggal SATU barang, barang itu dimasukkan -
         * di titik itu tidak ada yang perlu ditebak. Kalau lebih dari satu, kasir diberi tahu
         * berapa yang cocok dan memilih sendiri.
         */
        async cariAtauPindai() {
            const teks = (this.search || '').trim();

            if (!teks) return;

            const teksRendah = teks.toLowerCase();

            // 1. Cek kecocokan PERSIS barcode satuan di katalog lokal
            for (const p of this.allProducts) {
                if (p.additional_units && p.additional_units.length > 0) {
                    const matchedUnit = p.additional_units.find(u => u.barcode && this.samaBarcode(u.barcode, teks));
                    if (matchedUnit) {
                        this.search = '';
                        this.addToCartWithUnit(p, 'unit_' + matchedUnit.id);
                        this.beriTahuPindai(p.name + ' (' + matchedUnit.unit_name + ') ditambahkan ke keranjang.', false);
                        return;
                    }
                }
            }

            // 2. Cek kecocokan PERSIS barcode atau SKU produk di katalog lokal
            const exactProduct = this.allProducts.find(p =>
                (p.barcode && this.samaBarcode(p.barcode, teks)) ||
                (p.sku && String(p.sku).trim().toLowerCase() === teksRendah)
            );
            if (exactProduct) {
                this.search = '';
                this.addToCartWithUnit(exactProduct, 'base');
                this.beriTahuPindai(exactProduct.name + ' ditambahkan ke keranjang.', false);
                return;
            }

            const cocok = this.filteredProducts;

            if (cocok.length === 1) {
                this.search = '';
                this.onProductClick(cocok[0]);
                return;
            }

            if (cocok.length > 1) {
                this.beriTahuPindai(cocok.length + ' barang cocok dengan "' + teks + '". Pilih salah satu di daftar.', false);
                return;
            }

            // Tidak ada yang cocok di katalog lokal - panggil server lewat endpoint pindai.
            await this.pindai(teks);
        },

        /**
         * Tahan keranjang ini, kosongkan layar, lanjut ke pelanggan berikutnya.
         *
         * Memakai endpoint checkout yang SAMA - bukan jalur simpan tersendiri. Semua
         * penjagaannya (kunci stok, periksa ulang di dalam transaksi, StockMovement)
         * sudah ada di sana; jalur kedua berarti seluruh penjagaan itu harus ditulis
         * dan dijaga dua kali.
         */
        /**
         * Isi keranjang yang baru diambil dari tahanan, dititipkan lewat sesi oleh server.
         *
         * Dijalankan SESUDAH pulihkanDraf() dan menimpanya: keranjang yang diambil kembali
         * adalah niat kasir yang paling baru, sementara draf hanyalah sisa layar sebelumnya.
         */
        pulihkanKeranjangTertahan() {
            const diambil = @json($keranjangDiambil ?? session('kasir_keranjang_diambil') ?? session('keranjang_tertahan'));

            if (!Array.isArray(diambil) || diambil.length === 0) return;

            this.cart = [];
            const custId = @json($customerDiambil ?? session('kasir_customer_id') ?? '');
            if (custId) {
                this.customerId = custId;
            }
            const disc = Number(@json($discountDiambil ?? session('kasir_discount') ?? 0));
            if (disc > 0) {
                this.discount = disc;
            }

            diambil.forEach(baris => {
                const p = this.allProducts.find(x => x.id === baris.product_id);
                if (!p) return;   // produknya sudah dihapus sejak ditahan

                // Satuan dicocokkan lewat konversinya, bukan lewat namanya: nama satuan bisa
                // diubah di Master Data, angka konversinya yang menentukan harga.
                const satuan = (p.additional_units || []).find(u => Number(u.conversion) === Number(baris.unit_conversion));
                const unitType = satuan ? ('unit_' + satuan.id) : 'base';

                this.addToCartWithUnit(p, unitType);
                const idx = this.cart.length - 1;
                if (idx >= 0) {
                    this.cart[idx].qty = Number(baris.qty);
                }
            });

            this.hapusDraf();
            this.simpanDraf();
            this.focusSearch();
        },

        async tahanTransaksi() {
            if (this.cart.length === 0 || this.processing) return;

            if (!confirm('Tahan transaksi keranjang ini dan layani antrean pelanggan berikutnya?')) {
                return;
            }

            this.errorMsg = '';
            this.processing = true;

            try {
                const res = await fetch('{{ route('kasir.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        customer_id: this.customerId || null,
                        items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty, unit_type: i.unit_type })),
                        discount: this.discount || 0,
                        paid_amount: 0,
                        payment_method: this.paymentMethod || 'tunai',
                        is_parked: true,
                    }),
                });

                if (!res.ok) {
                    const galat = await res.json().catch(() => ({}));
                    this.errorMsg = galat.message || 'Gagal menahan transaksi.';
                    this.processing = false;
                    return;
                }

                // 1. Draf lokal wajib dibuang agar keranjang tidak muncul lagi
                this.hapusDraf();

                // 2. Kosongkan keranjang kasir untuk melayani pelanggan berikutnya
                this.cart = [];
                this.discount = 0;
                this.paidAmount = 0;
                this.customerId = '';
                this.isWaitingList = false;
                this.dueDate = '';

                // 3. Tambah hitungan tertahan secara reaktif
                this.jumlahTertahan = (this.jumlahTertahan || 0) + 1;

                // 4. Berikan umpan balik sukses di layar kasir
                this.pesanSuksesTahan = 'Transaksi berhasil ditahan. Keranjang telah dikosongkan untuk melayani pelanggan berikutnya.';
                setTimeout(() => {
                    this.pesanSuksesTahan = '';
                }, 8000);

                this.focusSearch();
            } catch (e) {
                this.errorMsg = 'Gagal menahan transaksi. Periksa sambungan lalu ulangi.';
            } finally {
                this.processing = false;
            }
        },

        async checkout() {
            this.errorMsg = '';
            if (this.isWaitingList) {
                if (this.modePesanan === 'dp' && (this.paidAmount || 0) <= 0) {
                    this.errorMsg = 'Pilih "Tanpa DP" kalau pelanggan belum membayar, atau isi jumlah DP-nya.';
                    return;
                }
                // DP 0 SENGAJA DIIZINKAN: pelanggan memesan barang tanpa membayar muka dulu.
                // Barangnya tetap direservasi (stok dipotong), piutangnya sebesar total, dan
                // pembukuannya sudah benar tanpa perlakuan khusus - pesanan `waiting` memang
                // belum jadi omset, dan uang muka Rp 0 tidak menambah kewajiban apa pun.
                if ((this.paidAmount || 0) >= this.total()) {
                    this.errorMsg = 'Jumlah DP tidak boleh sama dengan/melebihi total. Matikan opsi Pesanan untuk bayar lunas.';
                    return;
                }
            } else if (this.total() > 0 && (this.paidAmount || 0) < this.total()) {
                this.errorMsg = 'Jumlah bayar kurang dari total belanja.';
                return;
            }
            this.processing = true;
            try {
                const res = await fetch('{{ route('kasir.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_id: this.customerId || null,
                        items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty, unit_type: i.unit_type })),
                        discount: this.discount || 0,
                        paid_amount: this.paidAmount || 0,
                        payment_method: this.paymentMethod,
                        is_waiting_list: this.isWaitingList,
                        due_date: this.isWaitingList ? (this.dueDate || null) : null,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.errorMsg = data.message || 'Terjadi kesalahan.';
                    this.processing = false;
                    return;
                }
                this.bukaDokumen(data.receipt_url);

                // Draf dibuang lebih dulu, sebelum halaman dimuat ulang - kalau tidak,
                // transaksi yang baru saja dibayar akan muncul lagi sebagai keranjang.
                this.hapusDraf();
                this.cart = [];
                this.discount = 0;
                this.paidAmount = 0;
                this.customerId = '';
                this.isWaitingList = false;
                this.dueDate = '';

                window.location.reload();
            } catch (e) {
                this.errorMsg = 'Gagal menghubungi server.';
            }
            this.processing = false;
        },

        bukaDokumen(alamatStruk) {
            @if($pilihDokumen)
            window.open(alamatStruk + '?dokumen=' + this.dokumenCetak, '_blank');
            @else
            window.open(alamatStruk, '_blank');
            @endif
        },

        // --- Shift Kasir Helpers --------------------------------------------
        batalBukaShift() {
            if (this.activeShiftId) {
                this.showBukaShiftModal = false;
            } else {
                if (confirm('Batalkan buka shift dan kembali ke halaman login?')) {
                    const form = document.getElementById('logout-form-pos');
                    if (form) {
                        form.submit();
                    } else {
                        window.location.href = '{{ route('login') }}';
                    }
                }
            }
        },

        bukaModalTutupShift(shiftId) {
            this.showTutupShiftModal = true;
            this.shiftLoading = true;
            this.tutupWaktuShift = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            const targetId = shiftId || this.activeShiftId;
            fetch('/shift/' + targetId + '/summary', { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    this.tutupShiftData = data;
                    this.tutupUangFisikKasir = data.summary ? (data.summary.expected_cash || 0) : 0;
                    this.shiftLoading = false;
                })
                .catch(err => {
                    this.shiftLoading = false;
                    alert('Gagal memuat ringkasan shift: ' + err);
                });
        },

        hitungSelisihKasir() {
            if (!this.tutupShiftData || !this.tutupShiftData.summary) return 0;
            return (this.tutupUangFisikKasir || 0) - (this.tutupShiftData.summary.expected_cash || 0);
        },

        selisihNominalKasir() {
            const diff = this.hitungSelisihKasir();
            if (Math.abs(diff) < 0.01) return 'Rp 0 (Pas)';
            if (diff > 0) return '+ Rp ' + this.formatRupiahKasir(diff);
            return '- Rp ' + this.formatRupiahKasir(Math.abs(diff));
        },

        selisihLabelKasir() {
            const diff = this.hitungSelisihKasir();
            if (Math.abs(diff) < 0.01) return 'Uang Laci Sesuai (Tepat Nol):';
            if (diff > 0) return 'Selisih Kas Lebih (Over):';
            return 'Selisih Kas Kurang (Short):';
        },

        selisihKelasKasir() {
            const diff = this.hitungSelisihKasir();
            if (Math.abs(diff) < 0.01) return 'bg-green-50 border-green-200 text-green-800';
            if (diff > 0) return 'bg-blue-50 border-blue-200 text-blue-800';
            return 'bg-red-50 border-red-200 text-red-800';
        },

        formatRupiahKasir(num) {
            return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
        },

        submitTutupShift() {
            const shiftId = this.tutupShiftData.shift?.id || this.activeShiftId;
            if (!shiftId) return;

            const payload = {
                actual_cash: this.cashDrawerEnabled ? this.tutupUangFisikKasir : (this.tutupUangFisikKasir !== null && this.tutupUangFisikKasir !== '' ? this.tutupUangFisikKasir : null),
                notes: this.tutupCatatanShift
            };
            if (this.tutupWaktuShift) {
                payload.closed_at = this.tutupWaktuShift;
            }

            fetch('/shift/' + shiftId + '/close', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.print_url) {
                        window.open(data.print_url, '_blank');
                    }
                    window.location.reload();
                } else {
                    alert(data.message || 'Gagal menutup shift.');
                }
            })
            .catch(err => {
                alert('Gagal menutup shift: ' + err);
            });
        }
    }
}
</script>
@endpush
@endsection
