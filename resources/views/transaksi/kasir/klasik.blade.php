@extends('layouts.app')
@section('title', 'Modul Kasir - Aplikasi Penjualan')
@section('kelas-main', '!p-0 !pb-0 !overflow-hidden bg-slate-100 flex flex-col h-full max-h-full')

@push('styles')
<style>
/* Kunci Viewport Kasir Klasik (Halaman Static & Tidak Bergerak Sama Sekali) */
html, body {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
}
main {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    padding: 0 !important;
}

/* Modul Kasir Klasik styling (Tampilan Kasir Jaman Lawas / Desktop POS) */
.klasik-top-nav {
    background-color: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
}
.klasik-menu-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #475569;
    border-radius: 0.375rem;
    transition: all 0.15s ease-in-out;
}
.klasik-menu-link:hover {
    color: #0f172a;
    background-color: #f1f5f9;
}
.klasik-menu-active {
    background-color: #f0fdf4 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
    font-weight: 600 !important;
}
/* Logo Brand JPOS di Pojok Kiri Atas (Menggantikan Info Toko, Ukuran Lebih Besar & Jelas) */
.klasik-main-logo-wrap {
    display: inline-flex !important;
    align-items: center !important;
    padding-right: 0.75rem !important;
    border-right: 1.5px solid #cbd5e1 !important;
    flex-shrink: 0 !important;
}
.klasik-main-logo {
    height: 42px !important;
    width: auto !important;
    max-height: 46px !important;
    object-fit: contain !important;
    flex-shrink: 0 !important;
    display: block !important;
}
@media (min-width: 640px) {
    .klasik-main-logo {
        height: 48px !important;
        max-height: 52px !important;
    }
}


/* Running Text Banner Toko (Gaya Kasir Jaman Lawas) */
.klasik-marquee-jadul-box {
    background-color: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 0.375rem !important;
    padding: 0.3rem 0.75rem !important;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    overflow: hidden;
}
.klasik-marquee-jadul-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background-color: #fef3c7 !important;
    border: 1px solid #fde68a !important;
    color: #92400e !important;
    font-weight: 800 !important;
    font-size: 0.6875rem !important;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.15rem 0.45rem !important;
    border-radius: 0.25rem !important;
    flex-shrink: 0;
}
.klasik-running-brand-jadul {
    font-size: 1.15rem !important;
    font-weight: 900 !important;
    letter-spacing: 0.02em !important;
    color: #1e3a8a !important; /* Teks tegas biru tua kasir klasik */
}
.klasik-running-addr-jadul {
    font-size: 1rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.01em !important;
    color: #475569 !important;
}
.klasik-running-div-jadul {
    color: #d97706 !important;
    margin: 0 1rem !important;
    font-size: 0.85rem !important;
}

/* Subheader Strip (Persis Gambar SID Retail) */
.klasik-faktur-box {
    background-color: #ffffff !important;
    border: 1px solid #94a3b8 !important;
    border-radius: 0.25rem !important;
    padding: 0.1rem 0.5rem !important;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
    font-weight: 700 !important;
    font-size: 0.8125rem !important;
    color: #0f172a !important;
}

/* Input Barcode & Dropdown Rekomendasi Instan */
.klasik-search-input {
    width: 100%;
    border-radius: 0.375rem !important;
    border: 1.5px solid #0284c7 !important;
    background-color: #ffffff !important;
    padding: 0.5rem 0.85rem !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    outline: none !important;
    box-shadow: 0 1px 2px rgba(2, 132, 199, 0.08) !important;
    transition: all 0.15s ease !important;
}
.klasik-search-input:focus {
    border-color: #0369a1 !important;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2) !important;
}
.klasik-quick-dropdown {
    box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
}
.klasik-quick-dropdown .quick-item-selected {
    background-color: #bae6fd !important; /* solid full light blue (sky-200) */
    color: #082f49 !important;
    border-left: 5px solid #0284c7 !important;
}
.klasik-quick-dropdown .quick-item-selected * {
    color: #082f49 !important;
}
.klasik-quick-dropdown .quick-item-selected .quick-sku-tag {
    background-color: #ffffff !important;
    color: #0369a1 !important;
    border-color: #0284c7 !important;
    font-weight: 900 !important;
}
.klasik-quick-dropdown .quick-item-selected .quick-grosir-tag {
    background-color: #047857 !important;
    color: #ffffff !important;
    border-color: #065f46 !important;
    font-weight: 900 !important;
}
.klasik-quick-dropdown .quick-item-selected .quick-hint-badge {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    padding: 0.15rem 0.45rem !important;
    border-radius: 0.25rem !important;
}
.klasik-btn-tambah-jadul {
    background-color: #16a34a !important;
    color: #ffffff !important;
    border-radius: 0.375rem !important;
    padding: 0.5rem 0.85rem !important;
    font-weight: 700 !important;
    border: none !important;
    transition: background-color 0.15s ease !important;
}
.klasik-btn-tambah-jadul:hover {
    background-color: #15803d !important;
}
.klasik-total-box-jadul {
    border: 1.5px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    border-radius: 0.375rem !important;
    padding: 0.45rem 1.25rem !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
}

/* Tabel Kasir Klasik Jaman Lawas (Persis Gambar SID Retail) */
.klasik-table-wrap-jadul {
    border: 1.5px solid #cbd5e1 !important;
    border-radius: 0.375rem !important;
    overflow: hidden;
    background-color: #ffffff !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
}
/* Scrollbar Klasik Desktop POS (Selalu Tampak & Terlihat Jelas) */
.klasik-cart-scroll {
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}
.klasik-cart-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
.klasik-cart-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-left: 1px solid #cbd5e1;
}
.klasik-cart-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 4px;
    border: 2px solid #f1f5f9;
}
.klasik-cart-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}
.klasik-table-jadul th,
.klasik-table-jadul td {
    vertical-align: middle !important;
}
.klasik-table-jadul th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.05em !important;
    padding: 0.5rem 0.65rem !important;
    border-bottom: 2px solid #cbd5e1 !important;
    text-transform: uppercase;
}
.klasik-table-jadul td {
    padding: 0.35rem 0.65rem !important;
    font-size: 0.8125rem !important;
    color: #1e293b !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.klasik-kode-text {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
    font-size: 0.8125rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
    letter-spacing: 0.03em !important;
    white-space: nowrap !important;
    display: inline-block !important;
}
.klasik-table-jadul tbody tr:hover {
    background-color: #f8fafc !important;
}
.klasik-qty-stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid #cbd5e1 !important;
    border-radius: 0.25rem !important;
    background-color: #ffffff !important;
    overflow: hidden;
}
.klasik-qty-btn {
    padding: 0.125rem 0.5rem !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    color: #475569 !important;
    background-color: #f8fafc !important;
    transition: background-color 0.1s !important;
    cursor: pointer !important;
}
.klasik-qty-btn:hover {
    background-color: #e2e8f0 !important;
    color: #0f172a !important;
}
.klasik-qty-input {
    width: 2.75rem !important;
    text-align: center !important;
    font-size: 0.8125rem !important;
    font-weight: 700 !important;
    border-left: 1px solid #cbd5e1 !important;
    border-right: 1px solid #cbd5e1 !important;
    padding: 0.125rem 0 !important;
    outline: none !important;
    transition: all 0.15s ease !important;
}
.klasik-qty-input:focus {
    background-color: #fef3c7 !important;
    color: #78350f !important;
    outline: 2px solid #f59e0b !important;
    outline-offset: -1px !important;
}

/* Highlight Full Bold Biru Muda Solid Baris Terpilih Kasir Klasik / Lawas */
.klasik-table-jadul tbody tr.row-selected,
.klasik-table-jadul tbody tr.row-selected td {
    background-color: #bae6fd !important; /* solid vivid light blue (sky-200) */
    color: #082f49 !important; /* text biru gelap tegas */
    font-weight: 800 !important;
    border-bottom: 2px solid #0284c7 !important;
}
.klasik-table-jadul tbody tr.row-selected td:first-child {
    border-left: 5px solid #0284c7 !important;
}
.klasik-table-jadul tbody tr.row-selected .klasik-kode-text {
    color: #082f49 !important;
    font-weight: 900 !important;
}
.klasik-table-jadul tbody tr.row-selected .klasik-qty-stepper {
    border: 1.5px solid #0284c7 !important;
    background-color: #e0f2fe !important;
    box-shadow: 0 1px 3px rgba(2, 132, 199, 0.25) !important;
}
.klasik-table-jadul tbody tr.row-selected .klasik-qty-btn {
    background-color: #e0f2fe !important;
    color: #0369a1 !important;
    font-weight: 900 !important;
}
.klasik-table-jadul tbody tr.row-selected .klasik-qty-btn:hover {
    background-color: #7dd3fc !important;
    color: #082f49 !important;
}
.klasik-table-jadul tbody tr.row-selected .klasik-qty-input {
    background-color: #ffffff !important;
    font-weight: 900 !important;
    color: #0369a1 !important;
    border-left: 1.5px solid #0284c7 !important;
    border-right: 1.5px solid #0284c7 !important;
}
.klasik-table-jadul tbody tr.row-selected .klasik-qty-input:focus {
    background-color: #f0f9ff !important;
    outline: 2px solid #0284c7 !important;
}

/* Tombol F2 Cari Produk */
.klasik-btn-cari-f2 {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background-color: #0284c7 !important;
    color: #ffffff !important;
    padding: 0.45rem 0.85rem !important;
    border-radius: 0.375rem !important;
    border: none !important;
    font-weight: 700 !important;
    transition: background-color 0.15s ease !important;
}
.klasik-btn-cari-f2:hover {
    background-color: #0369a1 !important;
}

/* Input Pencarian Produk F2 */
.klasik-f2-search-wrap {
    position: relative !important;
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
}
.klasik-f2-search-icon {
    position: absolute !important;
    left: 0.875rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 1.15rem !important;
    height: 1.15rem !important;
    color: #0284c7 !important;
    pointer-events: none !important;
    display: block !important;
    z-index: 5 !important;
}
.klasik-f2-search-input {
    width: 100% !important;
    padding-left: 2.75rem !important;
    padding-right: 2.5rem !important;
    padding-top: 0.625rem !important;
    padding-bottom: 0.625rem !important;
    border-radius: 0.375rem !important;
    border: 1.5px solid #0284c7 !important;
    background-color: #ffffff !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    outline: none !important;
    box-shadow: 0 1px 2px rgba(2, 132, 199, 0.08) !important;
    transition: all 0.15s ease !important;
}
.klasik-f2-search-input:focus {
    border-color: #0369a1 !important;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2) !important;
}
.klasik-f2-search-clear {
    position: absolute !important;
    right: 0.75rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: #94a3b8 !important;
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    cursor: pointer !important;
    border: none !important;
    background: transparent !important;
    padding: 0.25rem !important;
}
.klasik-f2-search-clear:hover {
    color: #334155 !important;
}

/* Container & Tabel Modal Pencarian F2 (Sticky thead solid & bebas tumpang tindih) */
.klasik-f2-table-container {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    overflow-x: auto !important;
    position: relative !important;
    background-color: #ffffff !important;
}
.klasik-f2-table {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    text-align: left !important;
    font-size: 0.75rem !important;
}
.klasik-f2-table thead {
    position: sticky !important;
    top: 0 !important;
    z-index: 30 !important;
}
.klasik-f2-table th {
    position: sticky !important;
    top: 0 !important;
    z-index: 30 !important;
    background-color: #f1f5f9 !important; /* solid opaque header agar tidak tumpang tindih */
    color: #334155 !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    padding: 0.65rem 0.75rem !important;
    border-top: none !important;
    border-bottom: 2px solid #cbd5e1 !important;
    text-transform: uppercase !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}
.klasik-f2-table td {
    padding: 0.5rem 0.75rem !important;
    font-size: 0.8125rem !important;
    color: #1e293b !important;
    border-bottom: 1px solid #e2e8f0 !important;
    vertical-align: middle !important;
    background-color: #ffffff;
}
.klasik-f2-table tbody tr:hover td {
    background-color: #f8fafc !important;
}

/* Highlight Full Biru Muda Solid untuk Modal Pencarian Produk F2 (Sama dengan Modul Kasir Scroll Produk) */
.klasik-f2-table tbody tr.row-selected,
.klasik-f2-table tbody tr.row-selected td {
    background-color: #bae6fd !important; /* solid vivid light blue (sky-200) persis tabel kasir */
    color: #082f49 !important; /* teks biru gelap tegas */
    font-weight: 800 !important;
    border-bottom: 2px solid #0284c7 !important;
}
.klasik-f2-table tbody tr.row-selected td:first-child {
    border-left: 5px solid #0284c7 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-prod-name {
    color: #082f49 !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-sku-code {
    color: #0369a1 !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-price {
    color: #082f49 !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-stock {
    color: #082f49 !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-stock-empty {
    color: #b91c1c !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-wholesale-tag {
    background-color: #047857 !important;
    color: #ffffff !important;
    border-color: #065f46 !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-wholesale-price {
    color: #047857 !important;
    font-weight: 900 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-wholesale-min {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
    border-color: #10b981 !important;
    font-weight: 800 !important;
}
.klasik-f2-table tbody tr.row-selected .f2-btn-pilih {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    box-shadow: 0 1px 3px rgba(2, 132, 199, 0.4) !important;
}

.klasik-kbd {
    display: inline-block;
    padding: 0.125rem 0.375rem !important;
    font-size: 0.6875rem !important;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    color: #1e293b !important;
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 0.25rem !important;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05) !important;
}

/* Footer Status Bar Jadul */
.klasik-footer-jadul {
    background-color: #ffffff !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 0.65rem 0 !important;
}
.klasik-btn-clear-jadul {
    border: 1px solid #f87171 !important;
    color: #dc2626 !important;
    background-color: #ffffff !important;
    padding: 0.35rem 0.85rem !important;
    border-radius: 0.25rem !important;
    font-weight: 700 !important;
    font-size: 0.8125rem !important;
    transition: all 0.1s ease !important;
}
.klasik-btn-clear-jadul:hover:not(:disabled) {
    background-color: #fee2e2 !important;
}
.klasik-btn-bayar-jadul {
    background-color: #16a34a !important;
    color: #ffffff !important;
    padding: 0.45rem 1.15rem !important;
    border-radius: 0.25rem !important;
    font-weight: 700 !important;
    font-size: 0.875rem !important;
    border: none !important;
    transition: background-color 0.15s ease !important;
}
.klasik-btn-bayar-jadul:hover:not(:disabled) {
    background-color: #15803d !important;
}

/* Pop-up Pembayaran Kasir Klasik (Warna & Grid Persis Gambar Contoh) */
.pop-bayar-modal {
    max-width: 580px;
    width: 100%;
    background-color: #ffffff !important;
    border: 2px solid #000000 !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
    overflow: hidden;
    color: #0f172a;
    user-select: none;
    border-radius: 0 !important;
}
.pop-bayar-top-bg {
    background-color: #016400 !important;
    padding: 0.75rem 0.85rem !important;
    color: #ffffff !important;
    border-bottom: 2px solid #000000 !important;
}
.pop-bayar-top-title {
    font-weight: 700;
    font-size: 0.9375rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #ffffff !important;
    line-height: 1.2;
}
.pop-bayar-top-subtitle {
    font-size: 0.75rem;
    font-style: italic;
    color: rgba(255, 255, 255, 0.95) !important;
    margin-top: 0.15rem;
    margin-bottom: 0.5rem;
}
.pop-bayar-mint-box {
    background-color: #dcfce7 !important;
    padding: 0.65rem 1rem !important;
    text-align: center;
    border-radius: 2px;
}
.pop-bayar-total-num {
    font-size: 3rem !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    letter-spacing: -0.03em !important;
    color: #027754 !important;
}
.pop-bayar-grid {
    display: grid !important;
    grid-template-columns: 56.5% 43.5% !important;
    border-bottom: 2px solid #000000 !important;
}
.pop-bayar-col-left {
    display: flex !important;
    flex-direction: column !important;
    border-right: 2px solid #000000 !important;
}
.pop-bayar-hdr-bayar {
    background-color: #cd0000 !important;
    color: #ffffff !important;
    padding: 0.25rem 0.65rem !important;
    font-weight: 700 !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
}
.pop-bayar-box-bayar {
    background-color: #fed6d6 !important;
    height: 5.25rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-bottom: 2px solid #000000 !important;
    padding: 0.5rem !important;
}
.pop-bayar-input-bayar {
    width: 100% !important;
    text-align: center !important;
    font-weight: 700 !important;
    font-size: 2.25rem !important;
    color: #000000 !important;
    background-color: transparent !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    line-height: 1 !important;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
}
.pop-bayar-hdr-kembali {
    background-color: #000080 !important;
    color: #ffffff !important;
    padding: 0.25rem 0.65rem !important;
    font-weight: 700 !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
}
.pop-bayar-box-kembali {
    background-color: #c7d2ff !important;
    height: 5.25rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0.5rem !important;
    flex: 1 !important;
}
.pop-bayar-num-kembali {
    font-size: 2.25rem !important;
    font-weight: 900 !important;
    color: #02007a !important;
    letter-spacing: -0.02em !important;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
}
.pop-bayar-col-right {
    display: flex !important;
    flex-direction: column !important;
    background-color: #c4c4c4 !important;
}
.pop-bayar-hdr-qty {
    background-color: #000000 !important;
    color: #ffffff !important;
    padding: 0.25rem 0.65rem !important;
    font-weight: 700 !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
    text-align: center !important;
}
.pop-bayar-box-qty {
    flex: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0.75rem !important;
}
.pop-bayar-num-qty {
    font-size: 4.5rem !important;
    font-weight: 700 !important;
    color: #000000 !important;
    line-height: 1 !important;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
}
.pop-bayar-footer {
    background-color: #f9fafc !important;
    padding: 0.55rem 0.75rem !important;
    text-align: center !important;
    font-size: 0.75rem !important;
    font-weight: 500 !important;
    color: #1e293b !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.35rem !important;
    flex-wrap: wrap !important;
    border-top: 2px solid #000000 !important;
}
.pop-bayar-kbd-btn {
    padding: 0.15rem 0.55rem !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 0.35rem !important;
    background-color: #ffffff !important;
    color: #1e293b !important;
    font-size: 0.75rem !important;
    font-weight: 500 !important;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05) !important;
    cursor: pointer !important;
    transition: background-color 0.1s !important;
}
.pop-bayar-kbd-btn:hover {
    background-color: #f1f5f9 !important;
}
</style>
@endpush

@section('content')
<div x-data="kasirKlasikApp()" x-init="init()"
     @jpos:barcode-dipindai.document="pindai($event.detail.kode)"
     class="flex-1 flex flex-col h-full max-h-screen overflow-hidden bg-slate-100 text-slate-800">

    {{-- 1. TOP NAVBAR PERSIS SESUAI GAMBAR --}}
    <header class="klasik-top-nav shrink-0 px-3 sm:px-4 py-1.5 flex flex-wrap items-center justify-between gap-2 shadow-2xs z-30">
        <div class="flex items-center gap-1 sm:gap-2 flex-wrap">
            <span class="klasik-brand-badge mr-2 sm:mr-3">
                <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                <span>Aplikasi Penjualan</span>
            </span>

            {{-- Menu File --}}
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false" class="klasik-menu-link">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    <span>File</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak class="absolute left-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1.5 z-40 text-xs">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>Dashboard Utama</span>
                    </a>
                    <button type="button" @click="sidebarOpen = true; open = false" class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <span>Buka Menu Navigasi</span>
                    </button>
                    <div class="my-1 border-t border-slate-100"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-red-600 hover:bg-red-50">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            <span>Logout / Keluar</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Menu Master --}}
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false" class="klasik-menu-link">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Master</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak class="absolute left-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1.5 z-40 text-xs">
                    <a href="{{ route('produk.index') }}" class="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">Data Produk</a>
                    <a href="{{ route('kategori.index') }}" class="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">Data Kategori</a>
                    <a href="{{ route('satuan.index') }}" class="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">Data Satuan</a>
                    <a href="{{ route('pelanggan.index') }}" class="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">Data Pelanggan</a>
                    <a href="{{ route('supplier.index') }}" class="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50">Data Supplier</a>
                </div>
            </div>

            {{-- Modul Kasir (Active Tab Hijau) --}}
            <a href="{{ route('kasir.index') }}" class="klasik-menu-link klasik-menu-active">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Modul Kasir</span>
            </a>

            {{-- Angsuran / Waiting List --}}
            <a href="{{ route('kasir.waiting-list') }}" class="klasik-menu-link">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Angsuran</span>
                @if($jumlahPesanan > 0)
                    <span class="w-4 h-4 rounded-full bg-amber-500 text-white text-[10px] font-bold inline-flex items-center justify-center">{{ $jumlahPesanan }}</span>
                @endif
            </a>

            {{-- Laporan --}}
            <a href="{{ route('laporan.penjualan') }}" class="klasik-menu-link">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Laporan</span>
            </a>

            {{-- Pengaturan --}}
            <a href="{{ route('pengaturan.tampilan-kasir') }}" class="klasik-menu-link">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Pengaturan</span>
            </a>

            {{-- Help --}}
            <button type="button" @click="showHelpModal = true" class="klasik-menu-link text-blue-600 hover:text-blue-800">
                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Help</span>
            </button>
        </div>

        {{-- Kanan: Toggle Layout ke Modern & Fullscreen --}}
        <div class="flex items-center gap-2 ml-auto">
            <a href="{{ route('kasir.index', ['layout' => 'modern']) }}"
               class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition flex items-center gap-1.5"
               title="Beralih ke Tampilan Kasir Modern (2 Panel)">
                <svg class="w-3.5 h-3.5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                <span class="hidden md:inline">Tampilan Modern</span>
            </a>
            <button type="button" @click="toggleFullscreen()"
                    class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                    title="Layar Penuh (F11)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
            </button>
        </div>
    </header>

    {{-- KONTEN UTAMA KASIR KLASIK (GAYA DESKTOP POS LAWAS / SID RETAIL) --}}
    <div class="max-w-[1600px] mx-auto w-full px-2 sm:px-4 pt-1.5 pb-1 flex-1 flex flex-col min-h-0 overflow-hidden">
        <div class="flex-1 flex flex-col min-h-0 bg-white p-2 sm:p-2.5 rounded border border-slate-300 shadow-2xs gap-1.5 overflow-hidden">
            {{-- 2. RUNNING TEXT BANNER DENGAN LOGO JPOS DI POJOK KIRI ATAS --}}
            @php
                $storeName = !empty($storeProfile['name']) ? $storeProfile['name'] : 'JPOS by JaylaTech';
                $storeAddress = !empty($storeProfile['address']) ? $storeProfile['address'] : '';
                $storePhone = !empty($storeProfile['phone']) ? $storeProfile['phone'] : '';
            @endphp
            <div class="klasik-marquee-jadul-box shrink-0">
                <div class="klasik-main-logo-wrap">
                    <img src="{{ !empty($storeProfile['logo']) ? url('media/'.$storeProfile['logo']) : asset('images/logo-jpos.png') }}"
                         alt="Logo JPOS - {{ $storeName }}"
                         class="klasik-main-logo"
                         title="{{ $storeName }} - Point of Sale">
                </div>
                <div class="flex-1 overflow-hidden relative">
                    <marquee scrollamount="5" behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();" class="py-0.5">
                        @for($i = 0; $i < 4; $i++)
                            <span class="inline-flex items-center">
                                <strong class="klasik-running-brand-jadul">{{ $storeName }}</strong>
                                @if(!empty($storeAddress))
                                    <span class="klasik-running-addr-jadul ml-1.5">({{ $storeAddress }})</span>
                                @endif
                                @if(!empty($storePhone))
                                    <span class="klasik-running-addr-jadul ml-1.5 text-slate-500">• Telp: {{ $storePhone }}</span>
                                @endif
                            </span>
                            <span class="klasik-running-div-jadul">✦</span>
                        @endfor
                    </marquee>
                </div>
            </div>

            {{-- 3. SUBHEADER FAKTUR, KASIR, PELANGGAN, WAKTU (PERSIS CONTOH SID RETAIL) --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-xs shrink-0">
                {{-- Faktur --}}
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-sm border border-slate-300 bg-white shadow-2xs">
                    <span class="font-bold text-slate-600 uppercase tracking-wider shrink-0">No. Faktur:</span>
                    <span class="klasik-faktur-box" x-text="noFaktur"></span>
                </div>

                {{-- Kasir --}}
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-sm border border-slate-300 bg-white shadow-2xs">
                    <span class="font-bold text-slate-600 uppercase tracking-wider shrink-0">Kasir:</span>
                    <strong class="font-bold text-slate-900 bg-slate-100 border border-slate-300 px-2 py-0.5 rounded-xs truncate">{{ auth()->user()->username ?? auth()->user()->name ?? '01' }}</strong>
                </div>

                {{-- Pelanggan --}}
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-sm border border-slate-300 bg-white shadow-2xs">
                    <span class="font-bold text-slate-600 uppercase tracking-wider shrink-0">Pelanggan:</span>
                    <button type="button" @click="showCustomerModal = true"
                            class="font-bold text-blue-700 hover:text-blue-900 underline decoration-dotted transition truncate"
                            title="Klik untuk mengganti pelanggan">
                        <span x-text="selectedCustomerName()"></span>
                        <span x-show="customerType() !== 'UMUM'" class="ml-1 text-[10px] font-bold px-1 py-0.2 rounded bg-amber-500 text-white" x-text="customerType()"></span>
                    </button>
                </div>

                {{-- Waktu --}}
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-sm border border-slate-300 bg-white shadow-2xs">
                    <span class="font-bold text-slate-600 uppercase tracking-wider shrink-0">Waktu:</span>
                    <strong class="font-mono text-slate-800 font-bold tracking-wide truncate" x-text="waktuSekarang"></strong>
                </div>
            </div>

            {{-- 4. INPUT ROW: CARI/BARCODE DENGAN REKOMENDASI DROPDOWN, QTY, + TAMBAH, TOTAL DISPLAY --}}
            <div class="flex flex-wrap items-center gap-2 pt-0.5 shrink-0">
                {{-- Input Barcode / Nama Barang & Pop-up Dropdown Rekomendasi Instan --}}
                <div class="flex-1 min-w-[280px] flex items-center gap-2 relative"
                     @click.outside="showQuickDropdown = false">
                    <div class="relative flex-1">
                        <input type="text"
                               x-model="inputBarcodeOrSearch"
                               x-ref="barcodeInput"
                               @input="onSearchInput($event.target.value)"
                               @focus="if ((inputBarcodeOrSearch || '').trim().length >= 1) showQuickDropdown = true"
                               @click="if ((inputBarcodeOrSearch || '').trim().length >= 1) showQuickDropdown = true"
                               @keydown.enter.prevent="handleSearchEnter()"
                               @keydown.tab="if (showQuickDropdown && quickRecommendations.length > 0) { $event.preventDefault(); handleSearchEnter(); }"
                               @keydown.down.stop.prevent="navigasiQuickDown()"
                               @keydown.up.stop.prevent="navigasiQuickUp()"
                               @keydown.escape="if (showQuickDropdown) { showQuickDropdown = false; } else { inputBarcodeOrSearch = ''; }"
                               @keydown.f2.prevent="openSearchModal()"
                               placeholder="Ketik nama barang / barcode lalu Enter..."
                               class="klasik-search-input"
                               style="padding-right: 2.25rem !important;">
                        <button type="button" x-show="inputBarcodeOrSearch"
                                @click="inputBarcodeOrSearch = ''; showQuickDropdown = false; $refs.barcodeInput.focus()"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 font-bold text-lg px-1 leading-none cursor-pointer"
                                title="Hapus teks">&times;</button>

                        {{-- POP-UP DROPDOWN REKOMENDASI PRODUK INSTAN --}}
                        <div x-show="showQuickDropdown && quickRecommendations.length > 0"
                             x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="klasik-quick-dropdown absolute left-0 right-0 top-full mt-1 bg-white border-2 border-sky-400 rounded shadow-xl z-50 overflow-hidden text-xs">
                            <div class="bg-sky-50 px-3 py-1.5 border-b border-sky-200 flex items-center justify-between text-sky-950 font-bold">
                                <span>Rekomendasi Produk (Gunakan &uarr; &darr; lalu Enter)</span>
                                <span class="text-[11px] text-sky-700 font-bold" x-text="quickRecommendations.length + ' produk ditemukan'"></span>
                            </div>
                            <div x-ref="quickDropdownList" class="max-h-72 overflow-y-auto divide-y divide-slate-100 relative">
                                <template x-for="(prod, qIdx) in quickRecommendations" :key="prod.id">
                                    <div :id="'quick-item-' + qIdx"
                                         @click="pilihRekomendasi(prod)"
                                         @mousemove="onItemMouseMove($event, qIdx)"
                                         class="px-3 py-2 cursor-pointer transition flex items-center justify-between gap-3"
                                         :class="quickHighlightedIndex === qIdx ? 'quick-item-selected font-bold' : 'hover:bg-slate-50 text-slate-800'">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="quick-sku-tag font-mono font-bold text-[11px] px-1.5 py-0.5 rounded border shrink-0"
                                                      :class="quickHighlightedIndex === qIdx ? 'border-sky-500' : 'bg-slate-100 text-slate-700 border-slate-300'"
                                                      x-text="prod.sku || prod.barcode || ('B' + String(prod.id).padStart(2,'0'))"></span>
                                                <span class="font-bold text-sm truncate" x-text="prod.name"></span>
                                                <template x-if="prod.wholesale_price && prod.wholesale_min_qty">
                                                    <span class="quick-grosir-tag text-[10px] px-1.5 py-0.2 rounded font-black border shrink-0 shadow-2xs"
                                                          :class="quickHighlightedIndex === qIdx ? '' : 'bg-emerald-100 text-emerald-800 border-emerald-300'">
                                                        Grosir &ge; <span x-text="prod.wholesale_min_qty + ' ' + (prod.unit || 'pcs')"></span>: Rp <span x-text="formatNumber(prod.wholesale_price)"></span>
                                                    </span>
                                                </template>
                                            </div>
                                            <div class="text-[11px] mt-0.5 flex items-center gap-2"
                                                 :class="quickHighlightedIndex === qIdx ? 'text-sky-950 font-bold' : 'text-slate-500'">
                                                <span x-text="prod.category_name || '-'"></span>
                                                <span>&bull;</span>
                                                <span x-text="'Satuan: ' + (prod.unit || 'Pcs')"></span>
                                                <span>&bull;</span>
                                                <span :class="prod.stock <= 0 ? 'text-red-600 font-bold' : (quickHighlightedIndex === qIdx ? 'text-sky-950 font-bold' : 'text-slate-600')" x-text="'Stok: ' + formatQty(prod.stock)"></span>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-mono font-bold text-sm"
                                                 :class="quickHighlightedIndex === qIdx ? 'text-sky-950 font-black' : 'text-slate-900'"
                                                 x-text="'Rp ' + formatNumber(prod.sell_price)"></div>
                                            <template x-if="prod.wholesale_price && prod.wholesale_min_qty">
                                                <div class="font-mono text-[11px] font-bold"
                                                     :class="quickHighlightedIndex === qIdx ? 'text-sky-950 font-black' : 'text-emerald-700'"
                                                     x-text="'Grosir: Rp ' + formatNumber(prod.wholesale_price)"></div>
                                            </template>
                                            <div class="quick-hint-badge text-[10px] font-bold mt-0.5 inline-block" x-show="quickHighlightedIndex === qIdx">Tekan Enter &crarr;</div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Tombol F2 Cari --}}
                    <button type="button" @click="openSearchModal()" class="klasik-btn-cari-f2 shrink-0 cursor-pointer" title="Cari Produk (F2)">
                        <span class="klasik-badge-f2" style="background-color: #ffffff !important; color: #1d4ed8 !important; padding: 0.15rem 0.45rem !important; border-radius: 0.25rem !important;">F2</span>
                        <span class="font-bold text-xs sm:text-sm text-white">Cari</span>
                    </button>
                </div>

                {{-- Total Box Klasik Jaman Lawas (Diperlebar Sesuai Permintaan) --}}
                <div class="klasik-total-box-jadul shrink-0 ml-auto flex items-center justify-between gap-4 min-w-[280px] sm:min-w-[360px] px-4 py-2 border border-slate-300 rounded bg-white shadow-2xs">
                    <span class="text-xs sm:text-sm font-bold text-slate-600 tracking-wider">TOTAL:</span>
                    <span class="font-mono font-black text-2xl sm:text-3xl text-red-600 tracking-tight" x-text="'Rp ' + formatNumber(total())">Rp 0</span>
                </div>
            </div>

            {{-- Pesan Hasil Scan / Notifikasi --}}
            <div x-show="scanPesan" x-cloak x-transition.opacity
                 class="text-xs rounded px-3 py-1.5 flex items-center justify-between gap-2 border"
                 :class="scanGagal ? 'bg-red-50 border-red-300 text-red-700' : 'bg-emerald-50 border-emerald-300 text-emerald-800'">
                <div class="flex items-center gap-2">
                    <template x-if="scanGagal">
                        <svg class="w-4 h-4 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </template>
                    <template x-if="!scanGagal">
                        <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <span class="font-medium" x-text="scanPesan"></span>
                </div>
                <button type="button" @click="scanPesan = ''" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            {{-- 5. TABEL BELANJA KASIR KLASIK (GAYA TABEL DESKTOP POS SID RETAIL) --}}
            <div class="klasik-table-wrap-jadul flex-1 flex flex-col min-h-0 overflow-hidden">
                {{-- Sub-bar Info Jumlah Item & Indikator Navigasi (Mencegah Bingung saat Item Banyak) --}}
                <div class="bg-slate-100 border-b border-slate-300 px-3 py-1 flex items-center justify-between text-xs text-slate-700 font-bold select-none shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-800 uppercase tracking-wide">Daftar Belanja Transaksi</span>
                        <span class="px-2 py-0.2 rounded text-[11px] font-black"
                              :class="cart.length > 9 ? 'bg-amber-200 text-amber-950 border border-amber-400' : 'bg-white text-slate-700 border border-slate-300'"
                              x-text="cart.length + ' item'"></span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px]">
                        <template x-if="cart.length > 8">
                            <span class="text-amber-900 bg-amber-50 px-2 py-0.5 rounded border border-amber-300 font-bold hidden sm:inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <span>Gunakan &uarr; &darr; (atau scroll) untuk melihat produk lainnya</span>
                            </span>
                        </template>
                        <span class="text-slate-600" x-show="cart.length > 0">
                            Baris aktif: <strong class="font-mono text-emerald-800 font-black text-xs" x-text="(selectedCartIndex + 1) + '/' + cart.length"></strong>
                        </span>
                    </div>
                </div>

                <div x-ref="cartTableContainer"
                     class="klasik-cart-scroll flex-1 min-h-0 overflow-y-auto overflow-x-auto relative">
                    <table class="w-full klasik-table-jadul text-left border-collapse">
                        <thead class="sticky top-0 z-10 shadow-2xs">
                            <tr>
                                <th class="w-12 text-center whitespace-nowrap">NO</th>
                                <th class="w-28 sm:w-32 text-left whitespace-nowrap px-3">KODE</th>
                                <th>NAMA BARANG</th>
                                <th class="w-20 text-center whitespace-nowrap px-3">SATUAN</th>
                                <th class="w-28 text-right whitespace-nowrap px-3">HARGA (RP)</th>
                                <th class="w-28 text-center whitespace-nowrap px-3">QTY</th>
                                <th class="w-32 text-right whitespace-nowrap px-3">SUBTOTAL (RP)</th>
                                <th class="w-14 text-center whitespace-nowrap px-2">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(item, idx) in cart" :key="item.id || (item.product_id + '-' + item.unit_type + '-' + idx)">
                                <tr :id="'cart-row-' + idx"
                                    class="transition cursor-pointer"
                                    :class="selectedCartIndex === idx ? 'row-selected font-bold bg-sky-200 text-sky-950' : 'hover:bg-slate-50/80 text-slate-800'"
                                    @click="pilihBarisCart(idx)">
                                    <td class="text-center text-xs" :class="selectedCartIndex === idx ? 'font-black text-sky-950' : 'font-bold text-slate-500'" x-text="idx + 1"></td>
                                    <td class="whitespace-nowrap px-3">
                                        <span class="klasik-kode-text" :class="selectedCartIndex === idx ? '!text-sky-950 font-black' : ''" x-text="item.sku || item.barcode || ('B' + String(item.product_id).padStart(2,'0'))"></span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold" :class="selectedCartIndex === idx ? 'text-sky-950 font-black' : 'text-slate-900'" x-text="item.name"></span>
                                            <template x-if="activeTierBadge(item)">
                                                <span class="text-[10px] px-1.5 py-0.5 rounded font-black shadow-2xs"
                                                      :class="activeTierBadge(item).class"
                                                      x-text="activeTierBadge(item).label"></span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="text-center px-3" :class="selectedCartIndex === idx ? 'text-sky-950 font-bold' : 'text-slate-700 font-semibold'" x-text="item.unit_label || item.base_unit || 'Pcs'"></td>
                                    <td class="text-right font-mono px-3" :class="selectedCartIndex === idx ? 'text-sky-950 font-black' : 'text-slate-800 font-bold'" x-text="formatNumber(linePrice(item))"></td>
                                    <td class="text-center px-3" @click.stop>
                                        <div class="klasik-qty-stepper">
                                            <button type="button" @click="decrQty(idx)" class="klasik-qty-btn" title="Kurangi 1">&minus;</button>
                                            <input type="text"
                                                   :id="'cart-qty-' + idx"
                                                   :value="item.qty"
                                                   @focus="selectedCartIndex = idx; scrollCartRowIntoView(idx); $event.target.select()"
                                                   @click="selectedCartIndex = idx; scrollCartRowIntoView(idx); $event.target.select()"
                                                   @change="setQty(idx, $event.target.value)"
                                                   @keydown.enter.prevent="selesaiEditQty(idx, $event.target.value)"
                                                   @keydown.up.prevent="navigasiCartPrev(idx)"
                                                   @keydown.down.prevent="navigasiCartNext(idx)"
                                                   @keydown.page-up.prevent="navigasiCartJump(idx, -5)"
                                                   @keydown.page-down.prevent="navigasiCartJump(idx, 5)"
                                                   @keydown.home.prevent="navigasiCartJump(idx, -9999)"
                                                   @keydown.end.prevent="navigasiCartJump(idx, 9999)"
                                                   @keydown.delete.stop.prevent="hapusItemTerpilih(idx)"
                                                   @keydown.escape="focusBarcode()"
                                                   class="klasik-qty-input font-mono">
                                            <button type="button" @click="incrQty(idx)" class="klasik-qty-btn" title="Tambah 1">&plus;</button>
                                        </div>
                                    </td>
                                    <td class="text-right font-mono font-black text-sm px-3" :class="selectedCartIndex === idx ? 'text-sky-950' : 'text-slate-900'" x-text="formatNumber(linePrice(item) * item.qty)"></td>
                                    <td class="text-center px-2" @click.stop>
                                        <button type="button"
                                                @click.stop.prevent="hapusItemTerpilih(idx)"
                                                class="w-6 h-6 rounded bg-red-100 hover:bg-red-600 hover:text-white text-red-700 border border-red-300 transition font-bold text-sm inline-flex items-center justify-center cursor-pointer shadow-2xs"
                                                title="Hapus baris ini (Del)">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            {{-- Keadaan Kosong --}}
                            <tr x-show="cart.length === 0">
                                <td colspan="8" class="text-center py-16 text-slate-400 text-sm bg-white">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 mb-1 border border-slate-200">
                                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </div>
                                        <p class="font-bold text-slate-700 text-sm">Belum ada barang di keranjang belanja</p>
                                        <p class="text-xs text-slate-400">Ketik nama barang / barcode di kolom atas atau tekan <kbd class="klasik-kbd">F2</kbd> untuk mencari produk.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 6. FOOTER STATUS BAR DENGAN TOMBOL BANTUAN CEPAT (KLASIK LAWAS) --}}
        <div class="klasik-footer-jadul shrink-0 mt-1 flex flex-wrap items-center justify-between gap-2.5 text-xs z-20">
            {{-- Kiri: Jumlah Item & Tombol Bantuan Cepat --}}
            <div class="flex items-center gap-2 sm:gap-4 flex-wrap text-slate-600">
                <div class="bg-white border border-slate-300 px-3 py-1 rounded-sm shadow-2xs">
                    <span class="font-bold text-slate-800 text-xs sm:text-sm" x-text="totalItems() + ' item (' + totalPieces() + ' pcs)'"></span>
                </div>

                <div class="h-4 w-px bg-slate-300 hidden sm:block"></div>

                {{-- Bilah Tombol Bantuan Cepat Keyboard --}}
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                    <button type="button" @click="openSearchModal()" class="inline-flex items-center gap-1.5 hover:opacity-80 transition cursor-pointer" title="Cari produk (F2)">
                        <span class="klasik-badge-f2">F2</span>
                        <span class="font-bold text-slate-700">Cari</span>
                    </button>
                    <span class="text-slate-300">&bull;</span>
                    <span class="inline-flex items-center gap-1.5 text-slate-700" title="Gunakan tombol panah atas/bawah untuk menggeser item">
                        <span class="klasik-badge-enter">&uarr; &darr;</span>
                        <span class="font-bold">Geser Item</span>
                    </span>
                    <span class="text-slate-300">&bull;</span>
                    <button type="button" @click.stop.prevent="hapusItemTerpilih(selectedCartIndex)" :disabled="cart.length === 0" class="inline-flex items-center gap-1.5 hover:opacity-80 transition cursor-pointer" title="Hapus produk yang dipilih (Del)">
                        <span class="klasik-badge-f7" style="background-color: #fee2e2 !important; color: #dc2626 !important; border-color: #fca5a5 !important;">Del</span>
                        <span class="font-bold text-slate-700">Hapus Item</span>
                    </button>
                    <span class="text-slate-300">&bull;</span>
                    <button type="button" @click="confirmKosongkan()" :disabled="cart.length === 0" class="inline-flex items-center gap-1.5 hover:opacity-80 transition cursor-pointer" title="Kosongkan seluruh keranjang (F7)">
                        <span class="klasik-badge-f7">F7</span>
                        <span class="font-bold text-slate-700">Clear</span>
                    </button>
                    <span class="text-slate-300">&bull;</span>
                    <button type="button" @click="bukaModalBayar()" class="inline-flex items-center gap-1.5 hover:opacity-80 transition cursor-pointer" title="Buka pembayaran (F8)">
                        <span class="klasik-badge-f8">F8</span>
                        <span class="font-bold text-slate-700">Bayar</span>
                    </button>
                </div>
            </div>

            {{-- Kanan: TOTAL, [F7] Clear, [F8] Bayar --}}
            <div class="flex items-center gap-3 sm:gap-4 ml-auto">
                <div class="flex items-baseline gap-2 bg-white border border-slate-300 px-3 py-1 rounded-sm shadow-2xs">
                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">TOTAL:</span>
                    <span class="text-xl sm:text-2xl font-black text-red-600 font-mono tracking-tight" x-text="'Rp ' + formatNumber(total())">Rp 0</span>
                </div>

                {{-- Tombol [F7] Clear --}}
                <button type="button"
                        @click="confirmKosongkan()"
                        :disabled="cart.length === 0"
                        class="klasik-btn-clear-jadul disabled:opacity-40 font-bold transition shadow-2xs active:scale-95 cursor-pointer"
                        title="Kosongkan keranjang (F7)">
                    <span>[F7] Clear</span>
                </button>

                {{-- Tombol [F8] Bayar --}}
                <button type="button"
                        @click="bukaModalBayar()"
                        :disabled="cart.length === 0 || processing"
                        class="klasik-btn-bayar-jadul disabled:opacity-40 transition flex items-center gap-1.5 shadow-2xs active:scale-95 cursor-pointer"
                        title="Proses Pembayaran (F8)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span>[F8] Bayar</span>
                </button>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         MODAL 1: PENCARIAN PRODUK [F2]
         ========================================================================= --}}
    <div x-show="showSearchModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5"
         @keydown.escape.window="showSearchModal = false">
        <div class="bg-white rounded-xl max-w-5xl w-full max-h-[85vh] flex flex-col shadow-2xl border-2 border-sky-400 overflow-hidden"
             @click.outside="showSearchModal = false">
            {{-- Header Modal Pencarian --}}
            <div class="px-5 py-3 border-b border-sky-200 flex items-center justify-between bg-sky-50 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-600"></span>
                    <h3 class="font-bold text-sm text-sky-950">Pencarian Barang / Produk <kbd class="klasik-kbd ml-1 border-sky-300 text-sky-700 font-bold">F2</kbd></h3>
                </div>
                <button type="button" @click="showSearchModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl leading-none cursor-pointer" title="Tutup [Esc]">&times;</button>
            </div>

            {{-- Kolom Cari di Modal --}}
            <div class="px-5 py-3 border-b border-slate-200 bg-white shrink-0">
                <div class="klasik-f2-search-wrap">
                    <svg class="klasik-f2-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
                    </svg>
                    <input type="text"
                           x-model="modalSearch"
                           x-ref="modalSearchInput"
                           @input="highlightedIndex = 0"
                           @keydown.down.stop.prevent="highlightNext()"
                           @keydown.up.stop.prevent="highlightPrev()"
                           @keydown.enter.stop.prevent="selectHighlighted()"
                           placeholder="Ketik kata kunci produk (misal: 'masih es', barcode, SKU, kategori)..."
                           class="klasik-f2-search-input">
                    <button type="button" x-show="modalSearch"
                            @click="modalSearch = ''; highlightedIndex = 0; $refs.modalSearchInput.focus()"
                            class="klasik-f2-search-clear"
                            title="Hapus teks pencarian">&times;</button>
                </div>
            </div>

            {{-- Daftar Hasil Produk (Tanpa padding luar agar thead sticky solid tanpa celah / tumpang tindih) --}}
            <div x-ref="modalTableContainer" class="klasik-f2-table-container klasik-cart-scroll">
                <table class="klasik-f2-table">
                    <thead>
                        <tr>
                            <th class="w-32">Kode / Barcode</th>
                            <th>Nama Produk</th>
                            <th class="w-28">Kategori</th>
                            <th class="w-20 text-center">Satuan</th>
                            <th class="w-28 text-right">Harga Jual</th>
                            <th class="w-28 text-right text-emerald-800">Harga Grosir</th>
                            <th class="w-28 text-center text-emerald-800">Min. Pembelian</th>
                            <th class="w-20 text-right">Stok</th>
                            <th class="w-20 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(prod, pIdx) in modalFilteredProducts" :key="prod.id">
                            <tr :id="'search-row-' + pIdx"
                                class="cursor-pointer transition"
                                :class="highlightedIndex === pIdx ? 'row-selected font-bold' : 'hover:bg-slate-50 text-slate-700'"
                                @mousemove="onF2MouseMove($event, pIdx)"
                                @click="pilihProduk(prod)"
                                @dblclick="pilihProduk(prod)">
                                <td class="font-mono font-medium f2-sku-code whitespace-nowrap" x-text="prod.sku || prod.barcode || '-'"></td>
                                <td>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold f2-prod-name text-slate-900" x-text="prod.name"></span>
                                        <template x-if="prod.wholesale_price && prod.wholesale_min_qty">
                                            <span class="f2-wholesale-tag text-[10px] px-1.5 py-0.2 rounded font-black bg-emerald-100 text-emerald-800 border border-emerald-300 shrink-0">
                                                Grosir &ge; <span x-text="prod.wholesale_min_qty + ' ' + (prod.unit || 'pcs')"></span>
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap" :class="highlightedIndex === pIdx ? 'text-sky-950 font-bold' : 'text-slate-500'" x-text="prod.category_name || prod.category?.name || '-'"></td>
                                <td class="text-center font-semibold whitespace-nowrap" :class="highlightedIndex === pIdx ? 'text-sky-950 font-bold' : 'text-slate-700'" x-text="prod.unit || 'pcs'"></td>
                                <td class="text-right font-mono font-bold f2-price text-slate-900 whitespace-nowrap" x-text="'Rp ' + formatNumber(prod.sell_price)"></td>
                                <td class="text-right font-mono font-bold whitespace-nowrap">
                                    <template x-if="prod.wholesale_price && prod.wholesale_min_qty">
                                        <span class="f2-wholesale-price text-emerald-700 font-bold" x-text="'Rp ' + formatNumber(prod.wholesale_price)"></span>
                                    </template>
                                    <template x-if="!prod.wholesale_price || !prod.wholesale_min_qty">
                                        <span class="text-slate-400 font-normal">-</span>
                                    </template>
                                </td>
                                <td class="text-center whitespace-nowrap">
                                    <template x-if="prod.wholesale_price && prod.wholesale_min_qty">
                                        <span class="f2-wholesale-min inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold"
                                              x-text="prod.wholesale_min_qty + ' ' + (prod.unit || 'pcs')"></span>
                                    </template>
                                    <template x-if="!prod.wholesale_price || !prod.wholesale_min_qty">
                                        <span class="text-slate-400 font-mono">-</span>
                                    </template>
                                </td>
                                <td class="text-right font-mono font-bold whitespace-nowrap"
                                    :class="prod.stock <= 0 ? 'text-red-600 font-black f2-stock-empty' : (highlightedIndex === pIdx ? 'text-sky-950 font-black f2-stock' : 'text-slate-600 f2-stock')"
                                    x-text="formatQty(prod.stock)"></td>
                                <td class="text-center whitespace-nowrap">
                                    <button type="button" @click.stop="pilihProduk(prod)"
                                            class="f2-btn-pilih px-2.5 py-1 rounded text-[11px] font-bold transition shadow-2xs cursor-pointer"
                                            :class="highlightedIndex === pIdx ? 'bg-sky-600 text-white hover:bg-sky-700 ring-2 ring-sky-300' : 'bg-brand-600 text-white hover:bg-brand-700'">
                                        <span x-show="highlightedIndex === pIdx">Pilih &crarr;</span>
                                        <span x-show="highlightedIndex !== pIdx">Pilih</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="modalFilteredProducts.length === 0">
                            <td colspan="9" class="py-12 text-center text-slate-400">Tidak ada produk yang cocok dengan pencarian.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Footer Modal Pencarian --}}
            <div class="px-4 py-2.5 bg-sky-50/60 border-t border-sky-200 flex items-center justify-between text-xs text-sky-950 font-medium shrink-0">
                <div>Gunakan <kbd class="klasik-kbd font-bold">&uarr;</kbd> <kbd class="klasik-kbd font-bold">&darr;</kbd> untuk navigasi dan <kbd class="klasik-kbd font-bold">Enter &crarr;</kbd> untuk memilih.</div>
                <button type="button" @click="showSearchModal = false" class="btn btn-outline text-xs px-3 py-1 bg-white hover:bg-slate-100 font-semibold cursor-pointer">Tutup [Esc]</button>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         MODAL 2: PEMBAYARAN TRANSAKSI (WARNA PERSIS SESUAI CONTOH)
         ========================================================================= --}}
    <div x-show="showPaymentModal" x-cloak
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-3 sm:p-5"
         @keydown.escape.window="if (showPaymentModal) tutupModalBayar()">
        <div class="pop-bayar-modal shadow-2xl"
             style="max-width: 580px; width: 100%; background-color: #ffffff !important; border: 2px solid #000000 !important; border-radius: 0 !important; overflow: hidden;"
             @click.outside="tutupModalBayar()">
            {{-- 1. BAGIAN ATAS: TOTAL BELANJA (Rp) (HIJAU #016400 DENGAN KOTAK MINT #dcfce7) --}}
            <div class="pop-bayar-top-bg" style="background-color: #016400 !important; padding: 0.75rem 0.85rem !important; color: #ffffff !important; border-bottom: 2px solid #000000 !important;">
                <div class="pop-bayar-top-title" style="font-weight: 700; font-size: 0.9375rem; letter-spacing: 0.05em; text-transform: uppercase; color: #ffffff !important; line-height: 1.2;">TOTAL BELANJA (Rp)</div>
                <div class="pop-bayar-top-subtitle" style="font-size: 0.75rem; font-style: italic; color: rgba(255, 255, 255, 0.95) !important; margin-top: 0.15rem; margin-bottom: 0.5rem;">
                    Tampilan standart : untuk menyelesaikan transaksi tekan Enter, Esc untuk batal
                </div>
                <div class="pop-bayar-mint-box" style="background-color: #dcfce7 !important; padding: 0.65rem 1rem !important; text-align: center; border-radius: 2px;">
                    <span class="pop-bayar-total-num" style="font-size: 3rem !important; line-height: 1 !important; font-weight: 900 !important; letter-spacing: -0.03em !important; color: #027754 !important;"
                          x-text="formatNumber(total())">0</span>
                </div>
            </div>

            {{-- 2. METODE PEMBAYARAN (TUNAI, DEBIT, QRIS, TRANSFER) --}}
            <div class="pop-bayar-metode-wrap" style="background-color: #f8fafc !important; padding: 0.55rem 0.85rem !important; border-bottom: 2px solid #000000 !important;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="font-weight: 800; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; color: #0f172a;">
                        METODE PEMBAYARAN:
                    </span>
                    <span class="text-xs font-bold" style="color: #0284c7;" x-text="'Aktif: ' + (paymentMethod === 'tunai' ? 'TUNAI' : (paymentMethod === 'debit' ? 'KARTU DEBIT' : (paymentMethod === 'qris' ? 'QRIS' : 'TRANSFER BANK')))"></span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.45rem;">
                    {{-- F1. Tunai --}}
                    <button type="button"
                            @click="setPaymentMethod('tunai')"
                            class="cursor-pointer transition flex flex-col items-center justify-center p-1.5 rounded"
                            :style="paymentMethod === 'tunai' ? 'background-color: #0284c7 !important; color: #ffffff !important; border: 2px solid #0369a1 !important; font-weight: 800;' : 'background-color: #ffffff !important; color: #334155 !important; border: 1.5px solid #cbd5e1 !important; font-weight: 600;'">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs px-1.5 py-0.2 rounded font-extrabold shadow-2xs" :style="paymentMethod === 'tunai' ? 'background: #ffffff; color: #0284c7;' : 'background: #e2e8f0; color: #1e293b;'">F1</span>
                            <span class="text-xs font-bold uppercase">Tunai</span>
                        </div>
                        <span class="text-[10px] mt-0.5 opacity-90">Uang Cash</span>
                    </button>

                    {{-- F2. Debit --}}
                    <button type="button"
                            @click="setPaymentMethod('debit')"
                            class="cursor-pointer transition flex flex-col items-center justify-center p-1.5 rounded"
                            :style="paymentMethod === 'debit' ? 'background-color: #0284c7 !important; color: #ffffff !important; border: 2px solid #0369a1 !important; font-weight: 800;' : 'background-color: #ffffff !important; color: #334155 !important; border: 1.5px solid #cbd5e1 !important; font-weight: 600;'">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs px-1.5 py-0.2 rounded font-extrabold shadow-2xs" :style="paymentMethod === 'debit' ? 'background: #ffffff; color: #0284c7;' : 'background: #e2e8f0; color: #1e293b;'">F2</span>
                            <span class="text-xs font-bold uppercase">Debit</span>
                        </div>
                        <span class="text-[10px] mt-0.5 opacity-90">Kartu EDC</span>
                    </button>

                    {{-- F3. QRIS --}}
                    <button type="button"
                            @click="setPaymentMethod('qris')"
                            class="cursor-pointer transition flex flex-col items-center justify-center p-1.5 rounded"
                            :style="paymentMethod === 'qris' ? 'background-color: #0284c7 !important; color: #ffffff !important; border: 2px solid #0369a1 !important; font-weight: 800;' : 'background-color: #ffffff !important; color: #334155 !important; border: 1.5px solid #cbd5e1 !important; font-weight: 600;'">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs px-1.5 py-0.2 rounded font-extrabold shadow-2xs" :style="paymentMethod === 'qris' ? 'background: #ffffff; color: #0284c7;' : 'background: #e2e8f0; color: #1e293b;'">F3</span>
                            <span class="text-xs font-bold uppercase">QRIS</span>
                        </div>
                        <span class="text-[10px] mt-0.5 opacity-90">Scan QR</span>
                    </button>

                    {{-- F4. Transfer --}}
                    <button type="button"
                            @click="setPaymentMethod('transfer')"
                            class="cursor-pointer transition flex flex-col items-center justify-center p-1.5 rounded"
                            :style="paymentMethod === 'transfer' ? 'background-color: #0284c7 !important; color: #ffffff !important; border: 2px solid #0369a1 !important; font-weight: 800;' : 'background-color: #ffffff !important; color: #334155 !important; border: 1.5px solid #cbd5e1 !important; font-weight: 600;'">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs px-1.5 py-0.2 rounded font-extrabold shadow-2xs" :style="paymentMethod === 'transfer' ? 'background: #ffffff; color: #0284c7;' : 'background: #e2e8f0; color: #1e293b;'">F4</span>
                            <span class="text-xs font-bold uppercase">Transfer</span>
                        </div>
                        <span class="text-[10px] mt-0.5 opacity-90">Bank / VA</span>
                    </button>
                </div>
            </div>

            {{-- 3. BAGIAN TENGAH: GRID JUMLAH BAYAR, JUMLAH KEMBALI & JUMLAH QTY --}}
            <div class="pop-bayar-grid" style="display: grid !important; grid-template-columns: 56.5% 43.5% !important; border-bottom: 2px solid #000000 !important;">
                {{-- Kolom Kiri: Jumlah Bayar & Jumlah Kembali (56.5% Lebar) --}}
                <div class="pop-bayar-col-left" style="display: flex !important; flex-direction: column !important; border-right: 2px solid #000000 !important;">
                    {{-- Baris 1: JUMLAH BAYAR (MERAH #cd0000 & PINK #fed6d6) --}}
                    <div class="pop-bayar-hdr-bayar" style="background-color: #cd0000 !important; color: #ffffff !important; padding: 0.25rem 0.65rem !important; font-weight: 700; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between;">
                        <span>JUMLAH BAYAR</span>
                        <span class="text-[11px] font-normal uppercase" x-text="'(' + (paymentMethod === 'tunai' ? 'Tunai' : (paymentMethod === 'debit' ? 'Debit' : (paymentMethod === 'qris' ? 'QRIS' : 'Transfer'))) + ')'"></span>
                    </div>
                    <div class="pop-bayar-box-bayar" style="background-color: #fed6d6 !important; height: 5.25rem !important; display: flex !important; align-items: center !important; justify-content: center !important; border-bottom: 2px solid #000000 !important; padding: 0.5rem !important;">
                        <input type="text"
                               data-jpos-number
                               x-ref="paidInput"
                               x-number="paidAmount"
                               @focus="$event.target.select()"
                               @click="$event.target.select()"
                               @keydown.enter.prevent="selesaiBayar()"
                               @keydown.space.prevent="pasUang()"
                               @keydown.escape.prevent="tutupModalBayar()"
                               @keydown.f1.prevent="setPaymentMethod('tunai')"
                               @keydown.f2.prevent="setPaymentMethod('debit')"
                               @keydown.f3.prevent="setPaymentMethod('qris')"
                               @keydown.f4.prevent="setPaymentMethod('transfer')"
                               class="pop-bayar-input-bayar"
                               style="width: 100% !important; text-align: center !important; font-weight: 700 !important; font-size: 2.25rem !important; color: #000000 !important; background-color: transparent !important; border: none !important; outline: none !important; box-shadow: none !important; padding: 0 !important; line-height: 1 !important;">
                    </div>

                    {{-- Baris 2: JUMLAH KEMBALI (BIRU TUA #000080 & BIRU MUDA #c7d2ff) --}}
                    <div class="pop-bayar-hdr-kembali" style="background-color: #000080 !important; color: #ffffff !important; padding: 0.25rem 0.65rem !important; font-weight: 700; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase;">
                        JUMLAH KEMBALI
                    </div>
                    <div class="pop-bayar-box-kembali" style="background-color: #c7d2ff !important; height: 5.25rem !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 0.5rem !important; flex: 1 !important;">
                        <span class="pop-bayar-num-kembali" style="font-size: 2.25rem !important; font-weight: 900 !important; color: #02007a !important; letter-spacing: -0.02em !important;"
                              x-text="formatNumber(change() >= 0 ? change() : 0)">0</span>
                    </div>
                </div>

                {{-- Kolom Kanan: JUMLAH QTY (HITAM #000000 & ABU-ABU #c4c4c4) (43.5% Lebar) --}}
                <div class="pop-bayar-col-right" style="display: flex !important; flex-direction: column !important; background-color: #c4c4c4 !important;">
                    <div class="pop-bayar-hdr-qty" style="background-color: #000000 !important; color: #ffffff !important; padding: 0.25rem 0.65rem !important; font-weight: 700; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; text-align: center !important;">
                        JUMLAH QTY
                    </div>
                    <div class="pop-bayar-box-qty" style="flex: 1 !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 0.75rem !important;">
                        <span class="pop-bayar-num-qty" style="font-size: 4.5rem !important; font-weight: 700 !important; color: #000000 !important; line-height: 1 !important;"
                              x-text="totalPieces()">0</span>
                    </div>
                </div>
            </div>

            {{-- 3. BAGIAN BAWAH: PETUNJUK TOMBOL BANTUAN CEPAT (LATAR #f9fafc) --}}
            <div class="pop-bayar-footer" style="background-color: #f9fafc !important; padding: 0.55rem 0.75rem !important; text-align: center; font-size: 0.75rem; font-weight: 500; color: #1e293b; display: flex !important; align-items: center !important; justify-content: center !important; gap: 0.35rem !important; flex-wrap: wrap !important; border-top: 2px solid #000000 !important;">
                <span class="hidden md:inline" style="color: #64748b; font-size: 0.72rem;">Metode: <strong class="text-slate-800">[F1]</strong> Tunai &bull; <strong class="text-slate-800">[F2]</strong> Debit &bull; <strong class="text-slate-800">[F3]</strong> QRIS &bull; <strong class="text-slate-800">[F4]</strong> Transfer &bull;</span>
                <span>Tekan</span>
                <button type="button" @click="selesaiBayar()" title="Selesaikan transaksi &amp; cetak struk (Enter)"
                        class="pop-bayar-kbd-btn" style="padding: 0.15rem 0.55rem !important; border: 1px solid #cbd5e1 !important; border-radius: 0.35rem !important; background-color: #ffffff !important; color: #1e293b !important; font-size: 0.75rem; font-weight: 500; cursor: pointer !important;">
                    Enter
                </button>
                <span @click="selesaiBayar()" class="cursor-pointer hover:text-emerald-700">untuk Selesai &amp; Cetak Struk</span>
                <span style="color: #94a3b8; font-weight: 700; margin: 0 0.15rem;">&bull;</span>
                <button type="button" @click="pasUang()" title="Isi otomatis uang pas (Space)"
                        class="pop-bayar-kbd-btn" style="padding: 0.15rem 0.55rem !important; border: 1px solid #cbd5e1 !important; border-radius: 0.35rem !important; background-color: #ffffff !important; color: #1e293b !important; font-size: 0.75rem; font-weight: 500; cursor: pointer !important;">
                    Space
                </button>
                <span @click="pasUang()" class="cursor-pointer hover:text-emerald-700">Uang Pas</span>
                <span style="color: #94a3b8; font-weight: 700; margin: 0 0.15rem;">&bull;</span>
                <button type="button" @click="tutupModalBayar()" title="Batalkan pembayaran (Esc)"
                        class="pop-bayar-kbd-btn" style="padding: 0.15rem 0.55rem !important; border: 1px solid #cbd5e1 !important; border-radius: 0.35rem !important; background-color: #ffffff !important; color: #1e293b !important; font-size: 0.75rem; font-weight: 500; cursor: pointer !important;">
                    Esc
                </button>
                <span @click="tutupModalBayar()" class="cursor-pointer hover:text-red-700">Batal</span>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         MODAL 3: BANTUAN CEPAT KEYBOARD [HELP]
         ========================================================================= --}}
    <div x-show="showHelpModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5"
         @keydown.escape.window="showHelpModal = false">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200"
             @click.outside="showHelpModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Bantuan Tombol Cepat Keyboard</span>
                </h3>
                <button type="button" @click="showHelpModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Membuka Pencarian Barang Cepat</span>
                    <kbd class="klasik-kbd text-xs">F2</kbd>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Menambah Barcode / Memilih Barang</span>
                    <kbd class="klasik-kbd text-xs">Enter</kbd>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Menggeser Pilihan Produk di Keranjang</span>
                    <span class="flex items-center gap-1"><kbd class="klasik-kbd text-xs">&uarr;</kbd> <kbd class="klasik-kbd text-xs">&darr;</kbd></span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Scroll Cepat Keranjang (Lompat 5 Item)</span>
                    <span class="flex items-center gap-1"><kbd class="klasik-kbd text-xs">PgUp</kbd> <kbd class="klasik-kbd text-xs">PgDn</kbd></span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Hapus Produk yang Dipilih Saja</span>
                    <kbd class="klasik-kbd text-xs" style="color: #dc2626 !important; background-color: #fee2e2 !important; border-color: #fca5a5 !important;">Del</kbd>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Kosongkan Semua Keranjang (Clear)</span>
                    <kbd class="klasik-kbd text-xs">F7</kbd>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Buka Modal Bayar / Simpan Pembayaran</span>
                    <kbd class="klasik-kbd text-xs">F8</kbd>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Pilih Metode Bayar (di Pop-up Bayar)</span>
                    <span class="flex items-center gap-1 font-mono text-[11px]">
                        <kbd class="klasik-kbd text-[10px]">F1</kbd> Tunai &bull;
                        <kbd class="klasik-kbd text-[10px]">F2</kbd> Debit &bull;
                        <kbd class="klasik-kbd text-[10px]">F3</kbd> QRIS &bull;
                        <kbd class="klasik-kbd text-[10px]">F4</kbd> Transfer
                    </span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="font-medium text-slate-700">Tutup Jendela Modal / Batalkan</span>
                    <kbd class="klasik-kbd text-xs">Esc</kbd>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="font-medium text-slate-700">Layar Penuh (Fullscreen)</span>
                    <kbd class="klasik-kbd text-xs">F11</kbd>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 text-right">
                <button type="button" @click="showHelpModal = false" class="btn btn-primary text-xs px-4 py-2 rounded-xl">Saya Mengerti</button>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         MODAL 4: PILIH PELANGGAN
         ========================================================================= --}}
    <div x-show="showCustomerModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5"
         @keydown.escape.window="showCustomerModal = false">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl border border-slate-200"
             @click.outside="showCustomerModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                <h3 class="font-bold text-sm text-slate-800">Pilih Data Pelanggan</h3>
                <button type="button" @click="showCustomerModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <div class="space-y-1.5 max-h-72 overflow-y-auto">
                <button type="button" @click="customerId = ''; showCustomerModal = false"
                        class="w-full text-left px-3.5 py-2.5 rounded-xl border text-xs font-semibold flex items-center justify-between transition"
                        :class="!customerId ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-slate-200 hover:bg-slate-50 text-slate-700'">
                    <span>Pelanggan Umum (Tunai)</span>
                    <span class="text-[10px] bg-slate-200 px-1.5 py-0.5 rounded text-slate-700">UMUM</span>
                </button>
                @foreach($customers as $c)
                    <button type="button" @click="customerId = '{{ $c->id }}'; showCustomerModal = false"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl border text-xs font-semibold flex items-center justify-between transition"
                            :class="customerId == '{{ $c->id }}' ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-slate-200 hover:bg-slate-50 text-slate-700'">
                        <div>
                            <div>{{ $c->name }}</div>
                            <div class="text-[10px] text-slate-400 font-normal">{{ $c->phone ?? '-' }}</div>
                        </div>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-bold {{ ($c->customer_type ?? 'UMUM') === 'GROSIR' ? 'bg-amber-100 text-amber-800' : (($c->customer_type ?? 'UMUM') === 'RESELLER' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700') }}">
                            {{ $c->customer_type ?? 'UMUM' }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function kasirKlasikApp() {
    return {
        allProducts: @json($productsForCart),
        noFaktur: @json($nextInvoiceNo ?? 'TRX-' . date('Ymd') . '-0001'),
        inputBarcodeOrSearch: '',
        inputQty: 1,
        cart: [],
        customerId: '',
        discount: 0,
        paidAmount: 0,
        paymentMethod: 'tunai',
        waktuSekarang: '',
        scanPesan: '',
        scanGagal: false,
        scanTimer: null,
        processing: false,
        errorMsg: '',

        // Modals
        showSearchModal: false,
        modalSearch: '',
        highlightedIndex: 0,
        showPaymentModal: false,
        showHelpModal: false,
        showCustomerModal: false,
        modalBayarOpenedAt: 0,

        // Rekomendasi Instan Autocomplete
        showQuickDropdown: false,
        quickHighlightedIndex: 0,
        lastMouseX: null,
        lastMouseY: null,
        isKeyboardNavigating: false,
        selectedCartIndex: 0,

        // Customer dictionary
        customersList: @json($customers),

        init() {
            this.updateClock();
            setInterval(() => this.updateClock(), 1000);

            // Listen shortcut keys
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    if (this.showPaymentModal) {
                        this.setPaymentMethod('debit');
                    } else {
                        this.openSearchModal();
                    }
                } else if (e.key === 'F7') {
                    e.preventDefault();
                    this.confirmKosongkan();
                } else if (e.key === 'Delete') {
                    if (e.repeat) return;
                    if (!this.showPaymentModal && !this.showSearchModal && !this.showHelpModal && !this.showCustomerModal) {
                        if (e.target !== this.$refs.barcodeInput && e.target !== this.$refs.modalSearchInput) {
                            e.preventDefault();
                            e.stopPropagation();
                            this.hapusItemTerpilih(this.selectedCartIndex);
                        }
                    }
                } else if (e.key === 'ArrowUp') {
                    if (this.showSearchModal) {
                        if (e.target !== this.$refs.modalSearchInput) {
                            e.preventDefault();
                            this.highlightPrev();
                        }
                        return;
                    }
                    if (e.target === this.$refs.barcodeInput) {
                        return;
                    }
                    if (this.showQuickDropdown && this.quickRecommendations.length > 0) {
                        e.preventDefault();
                        this.navigasiQuickUp();
                        return;
                    }
                    if (!this.showPaymentModal && !this.showSearchModal && !this.showHelpModal && !this.showCustomerModal) {
                        if (e.target !== this.$refs.barcodeInput && e.target !== this.$refs.modalSearchInput && !(e.target && e.target.id && e.target.id.startsWith('cart-qty-'))) {
                            if (this.cart.length > 0) {
                                e.preventDefault();
                                this.navigasiCartPrev(this.selectedCartIndex);
                            }
                        }
                    }
                } else if (e.key === 'ArrowDown') {
                    if (this.showSearchModal) {
                        if (e.target !== this.$refs.modalSearchInput) {
                            e.preventDefault();
                            this.highlightNext();
                        }
                        return;
                    }
                    if (e.target === this.$refs.barcodeInput) {
                        return;
                    }
                    if (this.showQuickDropdown && this.quickRecommendations.length > 0) {
                        e.preventDefault();
                        this.navigasiQuickDown();
                        return;
                    }
                    if (!this.showPaymentModal && !this.showSearchModal && !this.showHelpModal && !this.showCustomerModal) {
                        if (e.target !== this.$refs.barcodeInput && e.target !== this.$refs.modalSearchInput && !(e.target && e.target.id && e.target.id.startsWith('cart-qty-'))) {
                            if (this.cart.length > 0) {
                                e.preventDefault();
                                this.navigasiCartNext(this.selectedCartIndex);
                            }
                        }
                    }
                } else if (e.key === 'PageUp' || e.key === 'PageDown' || e.key === 'Home' || e.key === 'End') {
                    if (this.showSearchModal) {
                        e.preventDefault();
                        const delta = e.key === 'PageUp' ? -5 : (e.key === 'PageDown' ? 5 : (e.key === 'Home' ? -9999 : 9999));
                        const prods = this.modalFilteredProducts;
                        if (prods.length > 0) {
                            this.highlightedIndex = Math.max(0, Math.min(prods.length - 1, this.highlightedIndex + delta));
                            this.scrollHighlightedIntoView();
                        }
                        return;
                    }
                    if (!this.showPaymentModal && !this.showSearchModal && !this.showHelpModal && !this.showCustomerModal) {
                        if (e.target !== this.$refs.barcodeInput && e.target !== this.$refs.modalSearchInput) {
                            if (this.cart.length > 0) {
                                e.preventDefault();
                                const delta = e.key === 'PageUp' ? -5 : (e.key === 'PageDown' ? 5 : (e.key === 'Home' ? -9999 : 9999));
                                this.navigasiCartJump(this.selectedCartIndex, delta);
                            }
                        }
                    }
                } else if (e.key === 'Enter' && this.showSearchModal) {
                    if (e.target !== this.$refs.modalSearchInput && e.target.tagName !== 'BUTTON') {
                        e.preventDefault();
                        this.selectHighlighted();
                        return;
                    }
                } else if (e.key === 'Enter' && this.showQuickDropdown && this.quickRecommendations.length > 0 && e.target === this.$refs.barcodeInput) {
                    e.preventDefault();
                    this.handleSearchEnter();
                    return;
                } else if (e.key === 'F8') {
                    e.preventDefault();
                    if (e.repeat) return;
                    if (!this.showPaymentModal) {
                        if (this.cart.length > 0) {
                            this.bukaModalBayar();
                        } else {
                            this.beriTahu('Keranjang belanja masih kosong.', true);
                        }
                    }
                } else if (e.key === 'F1' && this.showPaymentModal) {
                    e.preventDefault();
                    this.setPaymentMethod('tunai');
                } else if (e.key === 'F2' && this.showPaymentModal) {
                    e.preventDefault();
                    this.setPaymentMethod('debit');
                } else if (e.key === 'F3' && this.showPaymentModal) {
                    e.preventDefault();
                    this.setPaymentMethod('qris');
                } else if (e.key === 'F4' && this.showPaymentModal) {
                    e.preventDefault();
                    this.setPaymentMethod('transfer');
                } else if (e.key === 'Enter' && this.showPaymentModal) {
                    e.preventDefault();
                    if (Date.now() - this.modalBayarOpenedAt < 400) return;
                    this.selesaiBayar();
                } else if (e.key === ' ' && this.showPaymentModal && e.target !== this.$refs.paidInput) {
                    e.preventDefault();
                    this.pasUang();
                } else if (e.key === 'Escape') {
                    if (this.showPaymentModal) {
                        this.tutupModalBayar();
                    } else if (this.showSearchModal) {
                        this.showSearchModal = false;
                        this.focusBarcode();
                    } else if (this.showHelpModal) {
                        this.showHelpModal = false;
                    } else if (this.showCustomerModal) {
                        this.showCustomerModal = false;
                    }
                }
            });

            this.pulihkanDraf();
            this.focusBarcode();
        },

        updateClock() {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const d = pad(now.getDate());
            const m = pad(now.getMonth() + 1);
            const y = now.getFullYear();
            const h = pad(now.getHours());
            const min = pad(now.getMinutes());
            const s = pad(now.getSeconds());
            this.waktuSekarang = `${d}/${m}/${y}, ${h}:${min}:${s}`;
        },

        focusBarcode() {
            this.$nextTick(() => {
                if (this.$refs.barcodeInput) {
                    this.$refs.barcodeInput.focus();
                    this.$refs.barcodeInput.select();
                }
            });
        },

        selectedCustomerName() {
            if (!this.customerId) return 'Umum (Tunai)';
            const c = this.customersList.find(x => String(x.id) === String(this.customerId));
            return c ? c.name : 'Umum (Tunai)';
        },

        customerType() {
            if (!this.customerId) return 'UMUM';
            const c = this.customersList.find(x => String(x.id) === String(this.customerId));
            return (c && c.customer_type) ? c.customer_type : 'UMUM';
        },

        // Rekomendasi Instan Autocomplete saat mengetik di kolom pencarian
        get quickRecommendations() {
            const raw = (this.inputBarcodeOrSearch || '').trim().toLowerCase();
            if (!raw) return [];
            const words = raw.split(/\s+/).filter(Boolean);
            if (words.length === 0) return [];

            const matches = this.allProducts.filter(p => {
                const target = [
                    p.name || '',
                    p.sku || '',
                    p.barcode || '',
                    p.category_name || (p.category ? p.category.name : '') || '',
                    p.brand || ''
                ].join(' ').toLowerCase();

                return words.every(w => target.includes(w));
            });

            if (matches.length > 0) {
                return matches.slice(0, 10);
            }

            return this.allProducts.filter(p => {
                const target = [
                    p.name || '',
                    p.sku || '',
                    p.barcode || '',
                    p.category_name || (p.category ? p.category.name : '') || '',
                    p.brand || ''
                ].join(' ').toLowerCase();

                return words.some(w => target.includes(w));
            }).slice(0, 10);
        },

        onSearchInput(val) {
            const q = (val !== undefined ? val : this.inputBarcodeOrSearch || '').trim();
            if (q.length >= 1) {
                this.showQuickDropdown = true;
                this.quickHighlightedIndex = 0;
                this.scrollQuickIntoView(0);
            } else {
                this.showQuickDropdown = false;
                this.quickHighlightedIndex = 0;
            }
        },

        navigasiQuickDown() {
            const list = this.quickRecommendations;
            if (list.length === 0) {
                if (this.cart.length > 0 && !(this.inputBarcodeOrSearch || '').trim()) {
                    this.pilihBarisCart(0);
                }
                return;
            }

            this.isKeyboardNavigating = true;
            clearTimeout(this._navTimer);
            this._navTimer = setTimeout(() => { this.isKeyboardNavigating = false; }, 250);

            this.showQuickDropdown = true;
            if (this.quickHighlightedIndex >= list.length - 1) {
                this.quickHighlightedIndex = 0;
            } else {
                this.quickHighlightedIndex++;
            }
            this.scrollQuickIntoView(this.quickHighlightedIndex);
        },

        navigasiQuickUp() {
            const list = this.quickRecommendations;
            if (list.length === 0) {
                if (this.cart.length > 0 && !(this.inputBarcodeOrSearch || '').trim()) {
                    this.pilihBarisCart(this.cart.length - 1);
                }
                return;
            }

            this.isKeyboardNavigating = true;
            clearTimeout(this._navTimer);
            this._navTimer = setTimeout(() => { this.isKeyboardNavigating = false; }, 250);

            this.showQuickDropdown = true;
            if (this.quickHighlightedIndex <= 0 || this.quickHighlightedIndex >= list.length) {
                this.quickHighlightedIndex = list.length - 1;
            } else {
                this.quickHighlightedIndex--;
            }
            this.scrollQuickIntoView(this.quickHighlightedIndex);
        },

        highlightQuickNext() {
            this.navigasiQuickDown();
        },

        highlightQuickPrev() {
            this.navigasiQuickUp();
        },

        scrollQuickIntoView(idx) {
            this.$nextTick(() => {
                const container = this.$refs.quickDropdownList;
                const el = document.getElementById('quick-item-' + idx);
                if (!container || !el) return;

                if (idx === 0) {
                    container.scrollTop = 0;
                    return;
                }
                const list = this.quickRecommendations;
                if (idx === list.length - 1) {
                    container.scrollTop = container.scrollHeight;
                    return;
                }

                const cRect = container.getBoundingClientRect();
                const eRect = el.getBoundingClientRect();

                if (eRect.top < cRect.top) {
                    container.scrollTop -= (cRect.top - eRect.top);
                } else if (eRect.bottom > cRect.bottom) {
                    container.scrollTop += (eRect.bottom - cRect.bottom);
                }
            });
        },

        onItemMouseMove(e, idx) {
            if (this.isKeyboardNavigating) return;
            if (this.lastMouseX === e.clientX && this.lastMouseY === e.clientY) {
                return;
            }
            this.lastMouseX = e.clientX;
            this.lastMouseY = e.clientY;
            this.quickHighlightedIndex = idx;
        },

        pilihRekomendasi(prod) {
            this.showQuickDropdown = false;
            this.pilihProduk(prod);
        },

        handleSearchEnter() {
            const list = this.quickRecommendations;
            if (this.showQuickDropdown && list.length > 0) {
                const sel = list[this.quickHighlightedIndex] || list[0];
                if (sel) {
                    this.pilihRekomendasi(sel);
                    return;
                }
            }
            this.tambahBarang();
        },

        // Menambahkan barang dari input bar
        tambahBarang() {
            const teks = (this.inputBarcodeOrSearch || '').trim();
            const qty = Math.max(1, Number(this.inputQty) || 1);
            if (!teks) {
                this.openSearchModal();
                return;
            }

            // 1. Cek exact match barcode / SKU di katalog lokal
            const hit = this.allProducts.find(p =>
                (p.barcode && p.barcode.toLowerCase() === teks.toLowerCase()) ||
                (p.sku && p.sku.toLowerCase() === teks.toLowerCase()) ||
                (p.promo_barcode && p.promo_barcode.toLowerCase() === teks.toLowerCase())
            );

            if (hit) {
                const targetIdx = this.addItemToCart(hit, qty);
                this.inputBarcodeOrSearch = '';
                this.inputQty = 1;
                this.beriTahu(hit.name + ' (' + qty + ' pcs) ditambahkan ke keranjang.', false);
                if (targetIdx >= 0) {
                    this.focusQtyRow(targetIdx);
                } else {
                    this.focusBarcode();
                }
                return;
            }

            // 2. Cek filter nama
            const qLower = teks.toLowerCase();
            const matches = this.allProducts.filter(p =>
                (p.name && p.name.toLowerCase().includes(qLower)) ||
                (p.sku && p.sku.toLowerCase().includes(qLower)) ||
                (p.barcode && p.barcode.toLowerCase().includes(qLower))
            );

            if (matches.length === 1) {
                const targetIdx = this.addItemToCart(matches[0], qty);
                this.inputBarcodeOrSearch = '';
                this.inputQty = 1;
                this.beriTahu(matches[0].name + ' (' + qty + ' pcs) ditambahkan ke keranjang.', false);
                if (targetIdx >= 0) {
                    this.focusQtyRow(targetIdx);
                } else {
                    this.focusBarcode();
                }
                return;
            }

            // 3. Jika lebih dari 1 atau tidak ditemukan, buka Search Modal
            this.modalSearch = teks;
            this.openSearchModal();
        },

        async pindai(barcode) {
            const bersih = (barcode || '').trim();
            if (!bersih) return;

            const hit = this.allProducts.find(p =>
                (p.barcode && p.barcode.toLowerCase() === bersih.toLowerCase()) ||
                (p.sku && p.sku.toLowerCase() === bersih.toLowerCase())
            );

            if (hit) {
                this.addItemToCart(hit, 1);
                this.beriTahu(hit.name + ' ditambahkan ke keranjang.', false);
                return;
            }

            try {
                const res = await fetch('{{ route('kasir.scan', [], false) }}?barcode=' + encodeURIComponent(bersih));
                const data = await res.json().catch(() => ({}));
                if (data.found && data.product) {
                    let lokal = this.allProducts.find(p => p.id === data.product.id);
                    if (!lokal) {
                        this.allProducts.push(data.product);
                        lokal = data.product;
                    }
                    this.addItemToCart(lokal, 1);
                    this.beriTahu(data.product.name + ' ditambahkan ke keranjang.', false);
                } else {
                    this.beriTahu('Barcode "' + bersih + '" tidak terdaftar.', true);
                }
            } catch (e) {
                this.beriTahu('Gagal memindai barcode.', true);
            }
        },

        addItemToCart(p, qty = 1) {
            const unitType = 'base';
            const unitLabel = p.unit || 'pcs';
            const sellPrice = p.sell_price;
            const unitConversion = 1;

            let targetIdx = -1;
            const existingIdx = this.cart.findIndex(i => i.product_id === p.id && i.unit_type === unitType);
            if (existingIdx >= 0) {
                const item = this.cart[existingIdx];
                const newQty = item.qty + qty;
                if (p.type !== 'jasa' && (newQty * unitConversion) > p.stock) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + unitLabel);
                    return -1;
                }
                item.qty = newQty;
                targetIdx = existingIdx;
            } else {
                if (p.type !== 'jasa' && (qty * unitConversion) > p.stock) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + unitLabel);
                    return -1;
                }
                this.cart.push({
                    id: 'cart_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
                    product_id: p.id,
                    name: p.name,
                    sku: p.sku || '',
                    barcode: p.barcode || '',
                    unit_label: unitLabel,
                    unit_type: unitType,
                    unit_conversion: unitConversion,
                    sell_price: sellPrice,
                    reseller_price: p.reseller_price,
                    grosir_price: p.grosir_price,
                    wholesale_price: p.wholesale_price,
                    wholesale_min_qty: p.wholesale_min_qty,
                    stock: p.stock,
                    base_unit: p.unit || 'pcs',
                    type: p.type,
                    promo_active: Boolean(p.promo_active),
                    promo_price: p.promo_price ? Number(p.promo_price) : 0,
                    qty: qty,
                });
                targetIdx = this.cart.length - 1;
            }
            this.selectedCartIndex = targetIdx;
            this.scrollCartRowIntoView(targetIdx);
            this.simpanDraf();
            return targetIdx;
        },

        scrollCartRowIntoView(idx) {
            this.$nextTick(() => {
                const container = this.$refs.cartTableContainer;
                if (!container) return;
                const row = document.getElementById('cart-row-' + idx);
                if (!row) {
                    if (idx >= this.cart.length - 1) {
                        container.scrollTop = container.scrollHeight;
                    }
                    return;
                }

                if (idx === 0) {
                    container.scrollTop = 0;
                    return;
                }

                const thead = container.querySelector('thead');
                const headerHeight = thead ? thead.offsetHeight : 34;
                const cRect = container.getBoundingClientRect();
                const rRect = row.getBoundingClientRect();

                if (rRect.top < cRect.top + headerHeight) {
                    container.scrollTop -= ((cRect.top + headerHeight) - rRect.top);
                } else if (rRect.bottom > cRect.bottom) {
                    container.scrollTop += (rRect.bottom - cRect.bottom + 4);
                }
            });
        },

        focusQtyRow(idx) {
            if (idx < 0 || idx >= this.cart.length) return;
            this.selectedCartIndex = idx;
            this.scrollCartRowIntoView(idx);
            this.$nextTick(() => {
                const el = document.getElementById('cart-qty-' + idx);
                if (el) {
                    el.focus();
                    el.select();
                }
            });
        },

        pilihBarisCart(idx) {
            if (idx < 0 || idx >= this.cart.length) return;
            this.selectedCartIndex = idx;
            this.focusQtyRow(idx);
        },

        navigasiCartPrev(idx) {
            if (this.cart.length <= 1) return;
            const target = idx > 0 ? idx - 1 : this.cart.length - 1;
            this.pilihBarisCart(target);
        },

        navigasiCartNext(idx) {
            if (this.cart.length <= 1) return;
            const target = idx < this.cart.length - 1 ? idx + 1 : 0;
            this.pilihBarisCart(target);
        },

        navigasiCartJump(idx, delta) {
            if (this.cart.length <= 1) return;
            let target = idx + delta;
            if (target < 0) target = 0;
            if (target >= this.cart.length) target = this.cart.length - 1;
            this.pilihBarisCart(target);
        },

        hapusItemTerpilih(idx = null) {
            if (this.cart.length === 0) return;

            // Debounce guard: memastikan hanya 1 item yang terhapus per aksi tombol/klik
            const now = Date.now();
            if (this._lastDeleteTime && (now - this._lastDeleteTime) < 300) {
                return;
            }
            this._lastDeleteTime = now;

            let target = this.selectedCartIndex;
            if (idx !== null && idx !== undefined && idx !== '') {
                const parsed = Number(idx);
                if (!isNaN(parsed) && parsed >= 0 && parsed < this.cart.length) {
                    target = parsed;
                }
            }
            if (target < 0 || target >= this.cart.length) return;

            const nama = this.cart[target].name;
            // Hapus HANYA 1 item pada indeks target
            this.cart.splice(target, 1);
            this.simpanDraf();
            this.beriTahu(nama + ' dihapus dari keranjang.', false);

            if (this.cart.length > 0) {
                const nextIdx = Math.min(target, this.cart.length - 1);
                this.selectedCartIndex = nextIdx;
                this.focusQtyRow(nextIdx);
            } else {
                this.selectedCartIndex = 0;
                this.focusBarcode();
            }
        },

        selesaiEditQty(idx, val) {
            this.setQty(idx, val);
            this.focusBarcode();
        },

        incrQty(idx) {
            const item = this.cart[idx];
            const p = this.allProducts.find(x => x.id === item.product_id);
            if (p && p.type !== 'jasa') {
                if ((item.qty + 1) * item.unit_conversion > p.stock) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + item.unit_label);
                    return;
                }
            }
            item.qty++;
            this.simpanDraf();
        },

        decrQty(idx) {
            const item = this.cart[idx];
            if (item.qty > 1) {
                item.qty--;
            } else {
                this.hapusItemTerpilih(idx);
            }
            this.simpanDraf();
        },

        setQty(idx, val) {
            const num = parseInt(val, 10);
            if (isNaN(num) || num < 1) {
                this.cart[idx].qty = 1;
            } else {
                const item = this.cart[idx];
                const p = this.allProducts.find(x => x.id === item.product_id);
                if (p && p.type !== 'jasa' && (num * item.unit_conversion > p.stock)) {
                    alert('Stok tidak mencukupi! Sisa stok: ' + this.formatQty(p.stock) + ' ' + item.unit_label);
                    item.qty = Math.max(1, Math.floor(p.stock / item.unit_conversion));
                } else {
                    item.qty = num;
                }
            }
            this.simpanDraf();
        },

        removeItem(idx) {
            this.hapusItemTerpilih(idx);
        },

        confirmKosongkan() {
            if (this.cart.length === 0) return;
            if (confirm('Kosongkan semua daftar belanja kasir?')) {
                this.cart = [];
                this.selectedCartIndex = 0;
                this.simpanDraf();
                this.beriTahu('Keranjang telah dikosongkan.', false);
                this.focusBarcode();
            }
        },

        linePrice(item) {
            const tipe = this.customerType();
            let price = Number(item.sell_price);
            if (tipe === 'RESELLER' && item.reseller_price && Number(item.reseller_price) > 0) {
                price = Number(item.reseller_price);
            } else if (tipe === 'GROSIR' && item.grosir_price && Number(item.grosir_price) > 0) {
                price = Number(item.grosir_price);
            } else if (item.wholesale_price && item.wholesale_min_qty && item.qty >= item.wholesale_min_qty) {
                price = Number(item.wholesale_price);
            }
            if (item.promo_active && item.promo_price && Number(item.promo_price) > 0) {
                price = Math.min(price, Number(item.promo_price));
            }
            return price;
        },

        activeTierBadge(item) {
            if (item.promo_active && item.promo_price && this.linePrice(item) === Number(item.promo_price)) {
                return { label: 'Promo', class: 'bg-red-100 text-red-700' };
            }
            const tipe = this.customerType();
            if (tipe === 'RESELLER' && item.reseller_price) {
                return { label: 'Reseller', class: 'bg-blue-100 text-blue-700' };
            }
            if (tipe === 'GROSIR' && item.grosir_price) {
                return { label: 'Grosir', class: 'bg-amber-100 text-amber-700' };
            }
            if (item.wholesale_price && item.wholesale_min_qty && item.qty >= item.wholesale_min_qty) {
                return { label: 'Grosir Qty', class: 'bg-green-100 text-green-700' };
            }
            return null;
        },

        subtotal() {
            return this.cart.reduce((sum, item) => sum + (this.linePrice(item) * item.qty), 0);
        },

        total() {
            return Math.max(0, this.subtotal() - (this.discount || 0));
        },

        change() {
            return (this.paidAmount || 0) - this.total();
        },

        totalItems() {
            return this.cart.length;
        },

        totalPieces() {
            return this.cart.reduce((sum, item) => sum + item.qty, 0);
        },

        // Modal Pencarian [F2]
        openSearchModal() {
            if (this.inputBarcodeOrSearch && !this.modalSearch) {
                this.modalSearch = this.inputBarcodeOrSearch;
            }
            this.showSearchModal = true;
            this.highlightedIndex = 0;
            this.$nextTick(() => {
                if (this.$refs.modalSearchInput) {
                    this.$refs.modalSearchInput.focus();
                    this.$refs.modalSearchInput.select();
                }
            });
        },

        get modalFilteredProducts() {
            const raw = (this.modalSearch || '').trim().toLowerCase();
            if (!raw) return this.allProducts.slice(0, 50);

            const words = raw.split(/\s+/).filter(Boolean);
            if (words.length === 0) return this.allProducts.slice(0, 50);

            // 1. Pencarian multi-kata: cocokkan jika SEMUA kata ada (e.g. "masih es")
            const andMatches = this.allProducts.filter(p => {
                const target = [
                    p.name || '',
                    p.sku || '',
                    p.barcode || '',
                    p.category?.name || '',
                    p.brand || ''
                ].join(' ').toLowerCase();

                return words.every(w => target.includes(w));
            });

            if (andMatches.length > 0) {
                return andMatches.slice(0, 60);
            }

            // 2. Fallback jika tidak ada yang cocok semua kata: cocokkan SALAH SATU kata
            return this.allProducts.filter(p => {
                const target = [
                    p.name || '',
                    p.sku || '',
                    p.barcode || '',
                    p.category?.name || '',
                    p.brand || ''
                ].join(' ').toLowerCase();

                return words.some(w => target.includes(w));
            }).slice(0, 60);
        },

        highlightNext() {
            const prods = this.modalFilteredProducts;
            if (prods.length === 0) return;
            this.isKeyboardNavigating = true;
            clearTimeout(this.f2NavTimer);
            this.f2NavTimer = setTimeout(() => { this.isKeyboardNavigating = false; }, 250);

            if (this.highlightedIndex < prods.length - 1) {
                this.highlightedIndex++;
            } else {
                this.highlightedIndex = 0;
            }
            this.scrollHighlightedIntoView();
        },

        highlightPrev() {
            const prods = this.modalFilteredProducts;
            if (prods.length === 0) return;
            this.isKeyboardNavigating = true;
            clearTimeout(this.f2NavTimer);
            this.f2NavTimer = setTimeout(() => { this.isKeyboardNavigating = false; }, 250);

            if (this.highlightedIndex > 0) {
                this.highlightedIndex--;
            } else {
                this.highlightedIndex = prods.length - 1;
            }
            this.scrollHighlightedIntoView();
        },

        onF2MouseMove(e, idx) {
            if (this.isKeyboardNavigating) return;
            if (e && e.movementX === 0 && e.movementY === 0) return;
            this.highlightedIndex = idx;
        },

        scrollHighlightedIntoView() {
            this.$nextTick(() => {
                const container = this.$refs.modalTableContainer;
                const row = document.getElementById('search-row-' + this.highlightedIndex);
                if (!container || !row) return;

                if (this.highlightedIndex === 0) {
                    container.scrollTop = 0;
                    return;
                }

                const thead = container.querySelector('thead');
                const headerHeight = thead ? thead.offsetHeight : 36;
                const cRect = container.getBoundingClientRect();
                const rRect = row.getBoundingClientRect();

                if (rRect.top < cRect.top + headerHeight) {
                    container.scrollTop -= ((cRect.top + headerHeight) - rRect.top);
                } else if (rRect.bottom > cRect.bottom) {
                    container.scrollTop += (rRect.bottom - cRect.bottom + 4);
                }
            });
        },

        selectHighlighted() {
            const prods = this.modalFilteredProducts;
            if (prods.length > 0 && prods[this.highlightedIndex]) {
                this.pilihProduk(prods[this.highlightedIndex]);
            }
        },

        pilihProduk(prod) {
            const qty = Math.max(1, Number(this.inputQty) || 1);
            const targetIdx = this.addItemToCart(prod, qty);
            this.showSearchModal = false;
            this.modalSearch = '';
            this.inputBarcodeOrSearch = '';
            this.inputQty = 1;

            if (targetIdx >= 0) {
                this.beriTahu(prod.name + ' ditambahkan. Silakan ubah Qty lalu tekan Enter.', false);
                this.focusQtyRow(targetIdx);
            } else {
                this.focusBarcode();
            }
        },

        // Modal Pembayaran (Klasik)
        bukaModalBayar() {
            if (this.cart.length === 0) {
                alert('Keranjang belanja masih kosong.');
                return;
            }
            if (this.showPaymentModal) return;
            this.showPaymentModal = true;
            this.modalBayarOpenedAt = Date.now();
            if (!this.paymentMethod) this.paymentMethod = 'tunai';
            this.paidAmount = this.total();
            this.errorMsg = '';
            setTimeout(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.value = this.formatNumber(this.total());
                    this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.paidInput.focus();
                    this.$refs.paidInput.select();
                }
            }, 50);
        },

        setPaymentMethod(method) {
            this.paymentMethod = method;
            if (method !== 'tunai') {
                this.paidAmount = this.total();
                this.$nextTick(() => {
                    if (this.$refs.paidInput) {
                        this.$refs.paidInput.value = this.formatNumber(this.total());
                        this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }
            this.$nextTick(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.focus();
                    this.$refs.paidInput.select();
                }
            });
        },

        tutupModalBayar() {
            this.showPaymentModal = false;
            this.focusBarcode();
        },

        pasUang() {
            this.paidAmount = this.total();
            this.$nextTick(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.value = this.formatNumber(this.total());
                    this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.paidInput.select();
                }
            });
        },

        selesaiBayar() {
            if (this.processing) return;
            // Cegah auto-enter jika modal baru saja dibuka (< 400ms)
            if (Date.now() - this.modalBayarOpenedAt < 400) return;
            if (this.total() > 0 && (this.paidAmount || 0) < this.total()) {
                alert('Jumlah uang yang dibayarkan kurang dari total belanja! Total: Rp ' + this.formatNumber(this.total()));
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.focus();
                    this.$refs.paidInput.select();
                }
                return;
            }
            this.checkout(true);
        },

        async checkout(cetakStruk = true) {
            if (this.total() > 0 && (this.paidAmount || 0) < this.total()) {
                alert('Jumlah uang yang dibayarkan kurang dari total belanja.');
                this.errorMsg = 'Jumlah uang yang dibayarkan kurang dari total belanja.';
                return;
            }

            this.processing = true;
            this.errorMsg = '';

            try {
                const res = await fetch('{{ route('kasir.store', [], false) }}', {
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
                        payment_method: this.paymentMethod || 'tunai',
                        is_waiting_list: false,
                    }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    alert(data.message || ('Terjadi kesalahan server (Status ' + res.status + ').'));
                    this.errorMsg = data.message || ('Terjadi kesalahan server (Status ' + res.status + ').');
                    this.processing = false;
                    return;
                }

                // Cetak jika dipilih
                if (cetakStruk && data.receipt_url) {
                    this.cetakStruk(data.receipt_url);
                }

                // Berhasil: reset keranjang & faktur
                this.cart = [];
                this.discount = 0;
                this.paidAmount = 0;
                this.customerId = '';
                this.paymentMethod = 'tunai';
                this.showPaymentModal = false;
                this.hapusDraf();
                this.beriTahu('Transaksi #' + (data.sale_id || '') + ' berhasil disimpan!', false);
                this.noFaktur = 'TRX-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '-' + String(Math.floor(Math.random()*9000)+1000);
                this.focusBarcode();
            } catch (e) {
                this.errorMsg = 'Gagal menghubungi server aplikasi.';
            } finally {
                this.processing = false;
            }
        },

        cetakStruk(url) {
            const sep = url.includes('?') ? '&' : '?';
            const fullUrl = url + sep + 'autoprint=1&_t=' + Date.now();
            let frame = document.getElementById('jpos-silent-print-frame');
            if (!frame) {
                frame = document.createElement('iframe');
                frame.id = 'jpos-silent-print-frame';
                frame.style.position = 'fixed';
                frame.style.right = '0';
                frame.style.bottom = '0';
                frame.style.width = '10px';
                frame.style.height = '10px';
                frame.style.opacity = '0.01';
                frame.style.pointerEvents = 'none';
                document.body.appendChild(frame);
            }
            frame.src = fullUrl;
        },

        beriTahu(pesan, isError = false) {
            this.scanPesan = pesan;
            this.scanGagal = isError;
            clearTimeout(this.scanTimer);
            this.scanTimer = setTimeout(() => {
                this.scanPesan = '';
            }, 3500);
        },

        simpanDraf() {
            try {
                localStorage.setItem('jpos_kasir_klasik_draft', JSON.stringify({
                    cart: this.cart,
                    customerId: this.customerId,
                    discount: this.discount,
                }));
            } catch (_) {}
        },

        pulihkanDraf() {
            try {
                const raw = localStorage.getItem('jpos_kasir_klasik_draft');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed.cart) && parsed.cart.length > 0) {
                        this.cart = parsed.cart.map((it, i) => ({
                            ...it,
                            id: it.id || ('cart_' + Date.now() + '_' + i + '_' + Math.random().toString(36).substr(2, 6)),
                        }));
                        this.customerId = parsed.customerId || '';
                        this.discount = parsed.discount || 0;
                    }
                }
            } catch (_) {}
        },

        hapusDraf() {
            try {
                localStorage.removeItem('jpos_kasir_klasik_draft');
            } catch (_) {}
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

        formatNumber(n) {
            return new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
        },

        formatQty(n) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(n || 0);
        },

        formatShort(n) {
            if (n >= 1000000) return (n / 1000000) + ' Jt';
            if (n >= 1000) return (n / 1000) + ' Rb';
            return String(n);
        }
    };
}
</script>
@endpush
