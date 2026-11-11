@extends('layouts.app')
@section('title', 'Modul Kasir F&B (Resto & Cafe)')

@section('kelas-main', 'lg:flex lg:flex-col')

@push('styles')
<style>
/* ==========================================================================
   STYLE KHUSUS MODUL KASIR F&B (FOOD & BEVERAGE)
   Layout fleksibel tanpa pixel height kaku agar tidak pernah tumpang tindih
   ========================================================================== */
.fnb-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    flex: 1 1 0%;
    min-height: 0;
    width: 100%;
    height: 100%;
}

@media (min-width: 1024px) {
    .fnb-grid {
        display: grid;
        grid-template-columns: 1fr 410px;
        gap: 1rem;
        flex: 1 1 0%;
        min-height: 0;
        height: 100%;
        max-height: calc(100vh - 7.5rem);
    }
}

@media (min-width: 1280px) {
    .fnb-grid {
        grid-template-columns: 1fr 440px;
    }
}

.fnb-menu-scroll {
    overflow-y: auto;
    scrollbar-width: thin;
    padding-right: 4px;
    flex: 1 1 0%;
    min-height: 0;
}

.fnb-order-scroll {
    overflow-y: auto;
    scrollbar-width: thin;
    padding-right: 2px;
    flex: 1 1 0%;
    min-height: 0;
}

.kasir-product-grid {
    align-content: start !important;
    grid-auto-rows: min-content !important;
}
.kasir-product-card {
    height: auto !important;
    min-height: 0 !important;
}

.fnb-card {
    transition: all 0.2s ease-in-out !important;
    user-select: none !important;
    background-color: #ffffff !important;
    border-radius: 1rem !important;
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    padding: 0.75rem !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    cursor: pointer !important;
    position: relative !important;
}
.fnb-card:hover {
    transform: translateY(-2px) !important;
    border-color: #93c5fd !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.05) !important;
}
.fnb-card:active {
    transform: scale(0.98) !important;
}

/* ==========================================================================
   STYLE SEARCH BAR & FILTER KATEGORI (MODERN KASIR STYLE)
   ========================================================================== */
.kasir-top-filter {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin-bottom: 0.75rem;
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
    padding: 2px 2px 4px 2px;
    width: 100%;
    scrollbar-width: none;
    -ms-overflow-style: none;
    user-select: none;
}
.kasir-cat-bar::-webkit-scrollbar {
    display: none;
}

.fnb-cat-pill {
    white-space: nowrap !important;
    transition: all 0.15s ease-in-out !important;
    cursor: pointer !important;
    user-select: none !important;
    background-color: #f1f5f9 !important;
    border: 1.5px solid #cbd5e1 !important;
    color: #334155 !important;
    font-weight: 700 !important;
    font-size: 0.75rem !important;
    padding: 0.4rem 0.75rem !important;
    border-radius: 0.75rem !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.35rem !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
}
.fnb-cat-pill:hover {
    background-color: #e2e8f0 !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
}
.fnb-cat-pill.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
    font-weight: 800 !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35) !important;
}

/* ==========================================================================
   STYLE KHUSUS KARTU PRODUK & GAMBAR (ASPECT-SQUARE PERSIS KASIR MODERN)
   ========================================================================== */
.fnb-card-img-wrap {
    aspect-ratio: 1 / 1 !important;
    width: 100% !important;
    border-radius: 0.75rem !important;
    background-color: #f1f5f9 !important;
    overflow: hidden !important;
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-bottom: 0.625rem !important;
    border: 1px solid #e2e8f0 !important;
}
.fnb-card-img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    transition: transform 0.3s ease-in-out !important;
}
.fnb-card:hover .fnb-card-img {
    transform: scale(1.05) !important;
}

/* Badge QTY di Kartu Menu */
.fnb-card-qty-badge {
    position: absolute !important;
    top: 0.375rem !important;
    right: 0.375rem !important;
    background-color: #2563eb !important;
    color: #ffffff !important;
    font-size: 10px !important;
    font-weight: 900 !important;
    padding: 0.15rem 0.5rem !important;
    border-radius: 9999px !important;
    border: 1.5px solid #ffffff !important;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.35) !important;
    z-index: 10 !important;
    line-height: 1 !important;
}

/* Tombol Tambah (+) pada Kartu Menu */
.fnb-card-add-btn {
    width: 1.75rem !important;
    height: 1.75rem !important;
    border-radius: 0.5rem !important;
    background-color: #eff6ff !important;
    color: #2563eb !important;
    font-weight: 800 !important;
    font-size: 0.875rem !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.15) !important;
    border: 1px solid #bfdbfe !important;
    transition: all 0.15s ease-in-out !important;
    flex-shrink: 0 !important;
}
.fnb-card:hover .fnb-card-add-btn {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
    transform: scale(1.05) !important;
}

/* ==========================================================================
   STYLE STEPPER QTY & KERANJANG (WARNA SOLID & KONTRAS TINGGI)
   ========================================================================== */
.fnb-btn-minus {
    width: 26px !important;
    height: 26px !important;
    border-radius: 0.45rem !important;
    background-color: #f1f5f9 !important;
    color: #1e293b !important;
    font-size: 1.1rem !important;
    font-weight: 900 !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    cursor: pointer !important;
    transition: all 0.1s ease-in-out !important;
    flex-shrink: 0 !important;
}
.fnb-btn-minus:hover {
    background-color: #e2e8f0 !important;
    border-color: #94a3b8 !important;
    color: #000000 !important;
    transform: scale(1.05) !important;
}

.fnb-qty-box {
    min-width: 28px !important;
    height: 26px !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 0.45rem !important;
    font-size: 0.8125rem !important;
    font-weight: 900 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 4px !important;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    flex-shrink: 0 !important;
}

.fnb-btn-plus {
    width: 26px !important;
    height: 26px !important;
    border-radius: 0.45rem !important;
    background-color: #2563eb !important;
    color: #ffffff !important;
    font-size: 1.1rem !important;
    font-weight: 900 !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #1d4ed8 !important;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3) !important;
    cursor: pointer !important;
    transition: all 0.1s ease-in-out !important;
    flex-shrink: 0 !important;
}
.fnb-btn-plus:hover {
    background-color: #1d4ed8 !important;
    transform: scale(1.05) !important;
}

.fnb-btn-delete {
    width: 26px !important;
    height: 26px !important;
    border-radius: 0.45rem !important;
    background-color: #fff1f2 !important;
    color: #e11d48 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #fecdd3 !important;
    box-shadow: 0 1px 2px rgba(225, 29, 72, 0.1) !important;
    cursor: pointer !important;
    margin-left: 2px !important;
    transition: all 0.1s ease-in-out !important;
    flex-shrink: 0 !important;
}
.fnb-btn-delete:hover {
    background-color: #e11d48 !important;
    color: #ffffff !important;
    border-color: #be123c !important;
    transform: scale(1.05) !important;
}

/* Card Item dalam Keranjang */
.fnb-cart-item-card {
    background-color: #f8fafc !important;
    border: 1.5px solid #cbd5e1 !important;
    border-radius: 0.6rem !important;
    padding: 0.4rem 0.55rem !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    transition: border-color 0.15s !important;
}
.fnb-cart-item-card:hover {
    border-color: #94a3b8 !important;
}

/* Catatan Khusus Item */
.fnb-item-note-input {
    width: 100% !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    background-color: #f0f9ff !important;
    border: 1px solid #7dd3fc !important;
    color: #0369a1 !important;
    border-radius: 6px !important;
    padding: 3px 6px !important;
    outline: none !important;
    line-height: 1.3 !important;
}
.fnb-item-note-input:focus {
    border-color: #0284c7 !important;
    box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.25) !important;
}
.fnb-item-note-input::placeholder {
    color: #38bdf8 !important;
    opacity: 0.8 !important;
}

/* Kotak Total Tagihan */
.fnb-total-card {
    background-color: #0f172a !important;
    color: #ffffff !important;
    border: 2px solid #334155 !important;
    border-radius: 12px !important;
    padding: 0.75rem 1rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2) !important;
}

/* Tombol Aksi Bawah Keranjang */
.fnb-btn-openbill {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    border: 2px solid #0369a1 !important;
    border-radius: 12px !important;
    padding: 0.875rem 0.5rem !important;
    font-size: 0.8125rem !important;
    font-weight: 900 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.375rem !important;
    box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.4) !important;
    cursor: pointer !important;
    transition: background-color 0.15s, transform 0.15s !important;
}
.fnb-btn-openbill:hover:not(:disabled) {
    background-color: #0369a1 !important;
    transform: translateY(-1px) !important;
}
.fnb-btn-openbill:disabled {
    opacity: 0.45 !important;
    cursor: not-allowed !important;
}

.fnb-btn-pay {
    background-color: #059669 !important;
    color: #ffffff !important;
    border: 2px solid #047857 !important;
    border-radius: 12px !important;
    padding: 0.875rem 0.5rem !important;
    font-size: 0.8125rem !important;
    font-weight: 900 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.375rem !important;
    box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.4) !important;
    cursor: pointer !important;
    transition: background-color 0.15s, transform 0.15s !important;
}
.fnb-btn-pay:hover:not(:disabled) {
    background-color: #047857 !important;
    transform: translateY(-1px) !important;
}
.fnb-btn-pay:disabled {
    opacity: 0.45 !important;
    cursor: not-allowed !important;
}

/* Badge Mode Kasir Aktif */
.fnb-mode-badge {
    padding: 0.25rem 0.65rem !important;
    border-radius: 0.5rem !important;
    background-color: #2563eb !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    font-size: 0.75rem !important;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.3) !important;
}

/* Tombol Header Meja Dipilih */
.fnb-header-table-btn {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%) !important;
    border: 1px solid #1e40af !important;
    color: #ffffff !important;
    padding: 0.35rem 0.75rem !important;
    border-radius: 0.75rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.25) !important;
    cursor: pointer !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-header-table-btn:hover {
    background: #1d4ed8 !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.4) !important;
}

/* Tombol Header Open Bill */
.fnb-header-openbill-btn {
    background-color: #f0f9ff !important;
    border: 1px solid #7dd3fc !important;
    color: #0369a1 !important;
    padding: 0.35rem 0.75rem !important;
    border-radius: 0.75rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.4rem !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    cursor: pointer !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-header-openbill-btn:hover {
    background-color: #e0f2fe !important;
    border-color: #38bdf8 !important;
    color: #0284c7 !important;
}

/* Tombol Batal / Reset Keranjang */
.fnb-btn-reset {
    background-color: #fef2f2 !important;
    color: #dc2626 !important;
    border: 1px solid #fecaca !important;
    border-radius: 0.5rem !important;
    padding: 0.25rem 0.6rem !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.35rem !important;
    cursor: pointer !important;
    box-shadow: 0 1px 2px rgba(220, 38, 38, 0.06) !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-btn-reset:hover {
    background-color: #ef4444 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
}

/* Tombol Table Picker di Keranjang */
.fnb-btn-table-picker {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    border: 1px solid #bfdbfe !important;
    border-radius: 0.5rem !important;
    padding: 0.25rem 0.6rem !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.35rem !important;
    cursor: pointer !important;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.08) !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-btn-table-picker:hover {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
}

/* Input & Select di Kasir FNB */
.fnb-input-field {
    background-color: #f8fafc !important;
    border: 2px solid #cbd5e1 !important;
    border-radius: 0.75rem !important;
    padding: 0.4rem 0.65rem !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    outline: none !important;
    transition: border-color 0.15s, background-color 0.15s !important;
}
.fnb-input-field:focus {
    border-color: #2563eb !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2) !important;
}

/* Search Bar Input */
.fnb-search-input {
    background-color: #f8fafc !important;
    border: 2px solid #cbd5e1 !important;
    border-radius: 0.75rem !important;
    padding: 0.6rem 2.25rem 0.6rem 2.5rem !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    width: 100% !important;
    outline: none !important;
    transition: border-color 0.15s, background-color 0.15s !important;
}
.fnb-search-input:focus {
    border-color: #2563eb !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2) !important;
}

/* Modal Table Picker Elements */
.fnb-table-preset-btn {
    padding: 0.6rem 0.25rem !important;
    border-radius: 0.75rem !important;
    border: 1.5px solid #e2e8f0 !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    text-align: center !important;
    cursor: pointer !important;
    background-color: #ffffff !important;
    color: #1e293b !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-table-preset-btn:hover {
    border-color: #3b82f6 !important;
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    transform: translateY(-1px) !important;
}
.fnb-table-preset-btn.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35) !important;
}

.fnb-takeaway-btn {
    padding: 0.65rem 0.75rem !important;
    border-radius: 0.75rem !important;
    border: 1.5px solid #cbd5e1 !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.4rem !important;
    cursor: pointer !important;
    background-color: #f8fafc !important;
    color: #1e293b !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-takeaway-btn:hover {
    background-color: #e2e8f0 !important;
    border-color: #94a3b8 !important;
}
.fnb-takeaway-btn.active {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0369a1 !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.35) !important;
}

.fnb-delivery-btn {
    padding: 0.65rem 0.75rem !important;
    border-radius: 0.75rem !important;
    border: 1.5px solid #cbd5e1 !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.4rem !important;
    cursor: pointer !important;
    background-color: #f8fafc !important;
    color: #1e293b !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-delivery-btn:hover {
    background-color: #e2e8f0 !important;
    border-color: #94a3b8 !important;
}
.fnb-delivery-btn.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35) !important;
}

.fnb-btn-apply-table {
    font-size: 0.75rem !important;
    padding: 0.5rem 1rem !important;
    border-radius: 0.75rem !important;
    font-weight: 800 !important;
    background-color: #2563eb !important;
    color: #ffffff !important;
    border: 1px solid #1d4ed8 !important;
    cursor: pointer !important;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.25) !important;
    transition: all 0.15s ease-in-out !important;
}
.fnb-btn-apply-table:hover {
    background-color: #1d4ed8 !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35) !important;
}

/* ==========================================================================
   POP-UP BAYAR PERSIS SID RETAIL KLASIK
   ========================================================================== */
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
<div class="fnb-container" x-data="kasirFnbApp()" x-init="init()" @keydown.window="handleWindowKey($event)" @jpos:barcode-dipindai.document="tangkapBarcode($event.detail.kode)">

    {{-- =========================================================================
         BARIS HEADER: NAVIGASI MODE, MEJA AKTIF, STATUS & SHIFT
         ========================================================================= --}}
    <div class="bg-white rounded-2xl border border-slate-200 px-3.5 py-2 shadow-2xs flex items-center justify-between gap-2.5 shrink-0">
        {{-- Kiri: Tombol Menu Drawer, Dashboard, Brand & Meja Aktif --}}
        <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
            {{-- Tombol Toggle Menu Sidebar --}}
            <button type="button" @click="sidebarOpen = !sidebarOpen"
                    class="h-9 px-3 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 transition flex items-center gap-1.5 text-xs font-bold shrink-0 shadow-2xs cursor-pointer"
                    title="Buka Menu Aplikasi">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span class="hidden md:inline">Menu</span>
            </button>

            {{-- Tombol Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="h-9 px-3 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 transition flex items-center gap-1.5 text-xs font-bold shrink-0 shadow-2xs"
               title="Kembali ke Dashboard">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="hidden xl:inline">Dashboard</span>
            </a>

            <div class="h-6 w-px bg-slate-200 hidden sm:block shrink-0"></div>

            {{-- Brand & Info Kasir F&B --}}
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center shadow-xs shrink-0 font-extrabold text-base"
                     style="background: linear-gradient(135deg, #1c6ff0 0%, #2563eb 100%) !important; color: #ffffff !important;">
                    🍽️
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="font-black text-slate-900 text-xs sm:text-sm tracking-tight truncate">KASIR F&amp;B</span>
                        <span class="text-[9px] font-black px-1.5 py-0.2 rounded shadow-2xs shrink-0"
                              style="background-color: #2563eb !important; color: #ffffff !important;">
                            Resto &amp; Cafe
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-500 flex items-center gap-1.5 truncate">
                        <span x-text="clockTime" class="font-mono font-bold text-slate-700">--:--:--</span>
                        <span>&bull;</span>
                        <span class="truncate">Faktur: <strong class="text-slate-800" x-text="invoiceNo">--</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kanan: Meja Dipilih & Meja Tertahan (Open Bill) --}}
        <div class="flex items-center gap-2 shrink-0">
            {{-- Tombol Nomor Meja Aktif --}}
            <button type="button" @click="showTableModal = true"
                    class="fnb-header-table-btn">
                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <div class="text-left">
                    <div class="text-[8px] uppercase tracking-wider text-blue-100 font-extrabold leading-none">Meja Dipilih</div>
                    <div class="text-xs font-black uppercase text-white tracking-wide truncate max-w-[110px] leading-tight" x-text="selectedTable ? selectedTable : 'PILIH MEJA'"></div>
                </div>
                <svg class="w-3.5 h-3.5 text-white/90 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
            </button>

            {{-- Tombol Buka Meja Tertahan / Open Bill --}}
            <a href="{{ route('kasir.tahan') }}"
               class="fnb-header-openbill-btn">
                <svg class="w-4 h-4 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="hidden sm:inline">Open Bill</span>
                @if($jumlahTertahan > 0)
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-sky-600 text-white leading-none shadow-2xs">
                    {{ $jumlahTertahan }}
                </span>
                @endif
            </a>

            {{-- Tombol Pesanan Meja / Waiting List (Self-Order) --}}
            <button type="button"
                    @click="$dispatch('jpos:buka-pesanan-meja')"
                    class="fnb-header-openbill-btn relative cursor-pointer" title="Lihat Notifikasi & Daftar Pesanan Meja Masuk">
                <span class="text-sm">🔔</span>
                <span class="hidden sm:inline">Pesanan Meja</span>
                <span x-show="(waitingOrdersCount || 0) > 0"
                      class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-purple-600 text-white leading-none shadow-2xs animate-pulse"
                      x-text="waitingOrdersCount">
                    {{ $jumlahPesanan > 0 ? $jumlahPesanan : '' }}
                </span>
                @if($jumlahPesanan > 0)
                <span x-show="typeof waitingOrdersCount === 'undefined'" class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-purple-600 text-white leading-none shadow-2xs animate-pulse">
                    {{ $jumlahPesanan }}
                </span>
                @endif
            </button>
        </div>
    </div>

    {{-- =========================================================================
         GRID UTAMA: KATALOG MENU (KIRI) & PANEL PESANAN MEJA (KANAN)
         ========================================================================= --}}
    <div class="fnb-grid flex-1 min-h-0">

        {{-- KOLOM KIRI: KATALOG MAKANAN & MINUMAN --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-col h-full shadow-xs min-h-0">
            {{-- PENCARIAN & FILTER KATEGORI (MODERN & RAPI) --}}
            <div class="kasir-top-filter mb-3 shrink-0">
                <div class="kasir-search-row">
                    <div class="relative flex-1">
                        <input type="text"
                               x-model.debounce.200ms="searchQuery"
                               x-ref="fnbSearchInput"
                               placeholder="Cari menu makanan, minuman, cemilan... (Ketik nama atau kode)"
                               class="kasir-search-input">
                        <svg class="kasir-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
                        </svg>
                        <button type="button" x-show="searchQuery" @click="searchQuery = ''; $refs.fnbSearchInput.focus()"
                                class="kasir-search-clear" title="Hapus pencarian">&times;</button>
                    </div>
                    <button type="button" @click="$refs.fnbSearchInput.focus()"
                            class="h-10 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shrink-0 shadow-2xs border border-blue-700 transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/></svg>
                        <span>Cari Menu</span>
                    </button>
                </div>

                {{-- Filter Kategori Horizontal Chips --}}
                <div class="kasir-cat-bar">
                    <button type="button"
                            @click="selectedCategory = 'all'"
                            class="fnb-cat-pill"
                            :class="selectedCategory === 'all' ? 'active' : ''">
                        <span>🍽️ Semua Menu</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-full"
                              :class="selectedCategory === 'all' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-700'"
                              x-text="categoryCounts['all'] || allProducts.length"></span>
                    </button>

                    @foreach($categories as $cat)
                    <button type="button"
                            @click="selectedCategory = (selectedCategory === '{{ $cat->id }}' ? 'all' : '{{ $cat->id }}')"
                            class="fnb-cat-pill"
                            :class="selectedCategory === '{{ $cat->id }}' ? 'active' : ''"
                            title="Filter kategori {{ $cat->name }}">
                        <span>{{ $cat->name }}</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-full"
                              :class="selectedCategory === '{{ $cat->id }}' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-700'"
                              x-text="categoryCounts['{{ $cat->id }}'] || 0"></span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Grid Kartu Menu Makanan & Minuman (Aspect-Square & Ukuran Persis Kasir Modern) --}}
            <div class="fnb-menu-scroll flex-1 min-h-0">
                <template x-if="filteredProducts.length === 0">
                    <div class="h-64 flex flex-col items-center justify-center text-slate-400 gap-2">
                        <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span class="text-sm font-bold text-slate-500">Menu tidak ditemukan.</span>
                    </div>
                </template>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3.5 pb-4 flex-1 overflow-y-auto content-start kasir-product-grid" style="align-content: flex-start;">
                    <template x-for="p in filteredProducts" :key="p.id">
                        <div @click="addToCart(p)"
                             class="fnb-card kasir-product-card cursor-pointer bg-white border border-slate-200/90 hover:border-blue-400 hover:shadow-lg rounded-2xl p-3 flex flex-col justify-between group relative"
                             :class="(p.tracks_stock && p.stock <= 0) ? 'opacity-65 hover:border-slate-200 hover:shadow-none' : ''">
                            {{-- Badge Indikator Qty di Keranjang (Warna Biru JPOS Solid dengan Border Putih) --}}
                            <div x-show="getItemQtyInCart(p.id) > 0"
                                 class="fnb-card-qty-badge"
                                 x-text="getItemQtyInCart(p.id) + 'x'">
                            </div>

                            <div>
                                {{-- Thumbnail / Gambar Menu (aspect-square persis kasir modern) --}}
                                <div class="fnb-card-img-wrap aspect-square rounded-xl overflow-hidden bg-slate-100 mb-2.5 relative">
                                    <template x-if="p.image || p.image_url">
                                        <img :src="p.image_url ? p.image_url : ('/media/' + p.image)" :alt="p.name" class="fnb-card-img w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                    </template>
                                    <template x-if="!p.image && !p.image_url">
                                        <div class="w-full h-full flex flex-col items-center justify-center text-blue-600 font-extrabold gap-1 bg-slate-100">
                                            <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                            <span class="text-[9px] uppercase font-black text-blue-800" x-text="p.category || 'Menu'"></span>
                                        </div>
                                    </template>

                                    {{-- Overlay jika Stok Habis --}}
                                    <template x-if="p.tracks_stock && p.stock <= 0">
                                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] flex flex-col items-center justify-center text-white z-20">
                                            <span class="px-2 py-0.5 rounded bg-rose-600 font-black text-[10px] tracking-wider uppercase shadow">Stok Habis</span>
                                        </div>
                                    </template>

                                    {{-- Badge Kategori di Pojok Kiri Atas Foto (Persis Kasir Modern) --}}
                                    <div class="absolute top-1.5 left-1.5 max-w-[70%] flex flex-col items-start gap-1 pointer-events-none z-10">
                                        <span class="inline-block px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-slate-900/70 backdrop-blur-xs text-white shadow-xs truncate max-w-full"
                                              x-text="p.category || 'Menu'"></span>
                                    </div>
                                </div>

                                {{-- Judul Menu --}}
                                <div class="font-bold text-xs sm:text-sm text-slate-800 line-clamp-2 leading-snug group-hover:text-blue-600 transition mb-1" x-text="p.name"></div>
                            </div>

                            {{-- Baris Bawah: Harga, Status Stok & Tombol Tambah (+) --}}
                            <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between gap-1">
                                <div class="min-w-0 flex-1">
                                    <span class="font-black text-xs sm:text-sm text-blue-600 tracking-tight block truncate" x-text="'Rp ' + formatNumber(p.sell_price)"></span>
                                    <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                                        <span class="text-[10px] text-slate-400 font-medium truncate" x-text="'/' + (p.unit || 'Porsi')"></span>
                                        <template x-if="p.tracks_stock">
                                            <span class="text-[9px] font-semibold px-1 py-0.2 rounded"
                                                  :class="p.stock > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                                  x-text="p.stock > 0 ? ('Stok ' + p.stock) : 'Habis'"></span>
                                        </template>
                                        <template x-if="!p.tracks_stock">
                                            <span class="text-[9px] font-medium text-slate-500 bg-slate-100 px-1 py-0.2 rounded border border-slate-200" title="Menu Dapur / Siap Saji (Tanpa Stok)">🍳 Siap Saji</span>
                                        </template>
                                    </div>
                                </div>
                                <div class="fnb-card-add-btn shrink-0"
                                     :class="(p.tracks_stock && p.stock <= 0) ? 'opacity-40 cursor-not-allowed bg-slate-100 text-slate-400 border-slate-200' : ''"
                                     :title="(p.tracks_stock && p.stock <= 0) ? 'Stok Habis' : 'Tambah ke Pesanan'">
                                    +
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: PANEL PESANAN MEJA (ORDER & BILLING) --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-col h-full shadow-xs min-h-0">
            {{-- Header Meja & Tipe Order --}}
            <div class="pb-3 border-b border-slate-200 shrink-0 space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0 animate-pulse"></span>
                        <span class="font-black text-xs text-slate-800 uppercase tracking-wide shrink-0">PESANAN MEJA:</span>
                        <button type="button" @click="showTableModal = true"
                                class="fnb-btn-table-picker truncate max-w-[160px]"
                                :title="selectedTable">
                            <span class="truncate" x-text="selectedTable || 'PILIH MEJA'"></span>
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    {{-- Tombol Batal Pesanan (Warna Merah Solid, Jelas & Terbaca) --}}
                    <button type="button" @click="resetCart()" x-show="cart.length > 0"
                            class="fnb-btn-reset shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        <span>Batal</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    {{-- Tipe Pesanan (Dine In / Take Away) --}}
                    <select x-model="orderType" class="fnb-input-field">
                        <option value="Dine In">Dine In (Makan di Tempat)</option>
                        <option value="Take Away">Take Away (Bungkus)</option>
                        <option value="Delivery">Delivery (Antar)</option>
                    </select>

                    {{-- Nama Tamu / Pelanggan --}}
                    <input type="text" x-model="customerName" placeholder="Nama Tamu / Pelanggan"
                           class="fnb-input-field placeholder:text-slate-400">
                </div>
            </div>

            {{-- Daftar Menu yang Dipesan --}}
            <div class="fnb-order-scroll flex-1 min-h-0 py-1.5 space-y-1.5">
                <template x-if="cart.length === 0">
                    <div class="h-44 flex flex-col items-center justify-center text-slate-400 gap-1.5">
                        <svg class="w-9 h-9 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span class="text-xs font-bold text-slate-600">Belum ada menu yang dipilih.</span>
                        <span class="text-[10.5px] text-slate-400">Klik menu di sebelah kiri untuk memesan.</span>
                    </div>
                </template>

                <template x-for="(item, index) in cart" :key="item.id">
                    <div class="fnb-cart-item-card">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex-1 min-w-0 pr-1">
                                <div class="font-extrabold text-xs text-slate-900 leading-tight truncate" x-text="item.name"></div>
                                <div class="text-[11px] font-black text-emerald-700 leading-none mt-0.5">
                                    <span x-text="'Rp ' + formatNumber(item.price)"></span>
                                    <span class="text-[9.5px] text-slate-500 font-bold" x-text="'/' + (item.unit || 'Porsi')"></span>
                                </div>
                            </div>

                            {{-- Stepper Qty & Tombol Hapus (Kompak, Warna Solid & Kontras Tinggi) --}}
                            <div class="flex items-center gap-1 shrink-0">
                                {{-- Tombol Kurang (Hitam/Slate Gelap) --}}
                                <button type="button" @click="kurangQty(index)"
                                        class="fnb-btn-minus" title="Kurangi Qty">
                                    &minus;
                                </button>
                                {{-- Kotak Qty --}}
                                <span class="fnb-qty-box" x-text="item.qty"></span>
                                {{-- Tombol Tambah (Oranye Solid) --}}
                                <button type="button" @click="tambahQty(index)"
                                        class="fnb-btn-plus" title="Tambah Qty">
                                    +
                                </button>
                                {{-- Tombol Hapus (Merah Solid) --}}
                                <button type="button" @click="hapusItem(index)" title="Hapus menu ini"
                                        class="fnb-btn-delete">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Catatan Khusus Item (Ringkas & Rapi) --}}
                        <div class="mt-1.5 pt-1 border-t border-slate-200/80 flex items-center gap-1">
                            <span class="text-[10px] shrink-0 leading-none">📝</span>
                            <input type="text" x-model="item.note"
                                   placeholder="Catatan (pedas, es dipisah, dll...)"
                                   class="fnb-item-note-input">
                        </div>
                    </div>
                </template>
            </div>

            {{-- Ringkasan Biaya & Total --}}
            <div class="pt-3 border-t-2 border-slate-200 shrink-0 space-y-2">
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-700 font-bold">
                        <span>Subtotal:</span>
                        <span class="font-extrabold text-slate-900" x-text="'Rp ' + formatNumber(subtotal())"></span>
                    </div>

                    <template x-if="discount > 0">
                        <div class="flex justify-between text-emerald-700 font-bold">
                            <span>Diskon:</span>
                            <span x-text="'-Rp ' + formatNumber(discount)"></span>
                        </div>
                    </template>

                    <template x-if="taxAmount() > 0">
                        <div class="flex justify-between text-slate-700 font-bold">
                            <span>Pajak Resto (PB1):</span>
                            <span class="font-extrabold text-slate-900" x-text="'Rp ' + formatNumber(taxAmount())"></span>
                        </div>
                    </template>
                </div>

                {{-- TOTAL BESAR (Hitam Solid, Nilai Biru Terang) --}}
                <div class="fnb-total-card">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-blue-400">Total Tagihan</div>
                        <div class="text-xl font-black tracking-tight text-white" x-text="'Rp ' + formatNumber(total())"></div>
                    </div>
                    <div class="text-right text-xs text-blue-300 font-extrabold" x-text="cartTotalQty() + ' Menu'"></div>
                </div>

                {{-- TOMBOL AKSI UTAMA KHUSUS FNB (Warna Cerah Solid, Jelas & Bebas Tabrakan) --}}
                <div class="grid grid-cols-2 gap-2 pt-1">
                    {{-- Tombol 1: Simpan / Open Bill (Tahan Pesanan Meja) --}}
                    <button type="button" @click="tahanPesananFnb()" :disabled="cart.length === 0 || processing"
                            class="fnb-btn-openbill">
                        <svg class="w-4 h-4 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="truncate">Simpan / Open Bill</span>
                    </button>

                    {{-- Tombol 2: Bayar & Selesai (F8) --}}
                    <button type="button" @click="bukaModalBayar()" :disabled="cart.length === 0 || processing"
                            class="fnb-btn-pay">
                        <svg class="w-4 h-4 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="truncate">Bayar &amp; Cetak [F8]</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         MODAL 1: PEMILIHAN NOMOR MEJA (TABLE PICKER MODAL)
         ========================================================================= --}}
    <div x-show="showTableModal" x-cloak
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
         @keydown.escape.window="showTableModal = false">
        <div class="bg-white rounded-2xl max-w-lg w-full p-5 shadow-2xl border border-slate-300"
             @click.outside="showTableModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-lg shadow-xs">
                        🪑
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-slate-900 uppercase tracking-tight">Pilih Nomor Meja / Area</h3>
                        <p class="text-xs text-slate-500 font-semibold">Tentukan meja makan tamu atau pesanan bungkus</p>
                    </div>
                </div>
                <button type="button" @click="showTableModal = false" class="text-slate-400 hover:text-slate-700 p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Input Meja Kustom --}}
            <div class="mt-4">
                <label class="block text-xs font-black text-slate-800 mb-1.5">Ketik Nomor Meja Bebas / Kustom:</label>
                <div class="flex gap-2">
                    <input type="text" x-model="customTableInput"
                           placeholder="Contoh: Meja 15, VIP 3, Gazebo 2..."
                           class="flex-1 fnb-input-field placeholder:text-slate-400">
                    <button type="button" @click="pilihMejaKustom()" class="fnb-btn-apply-table">
                        Terapkan
                    </button>
                </div>
            </div>

            {{-- Grid Tombol Cepat Meja Presets --}}
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs font-black text-slate-800">Daftar Meja &amp; Pesanan:</div>
                    <a href="{{ route('meja.index') }}" target="_blank" class="text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline inline-flex items-center gap-1">
                        ⚙️ Kelola Meja &amp; Barcode &rarr;
                    </a>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 max-h-56 overflow-y-auto p-1">
                    <template x-for="t in presetTablesData" :key="t.name">
                        <button type="button" @click="pilihMejaDariPreset(t)"
                                class="fnb-table-preset-btn relative flex flex-col items-center justify-center py-2 px-1 text-center transition"
                                :class="[
                                    selectedTable === t.name ? 'active ring-2 ring-amber-500' : '',
                                    t.order_count > 0 ? 'border-purple-400 bg-purple-50 text-purple-900 font-black' : ''
                                ]">
                            <span class="text-xs font-bold truncate max-w-full" x-text="t.name"></span>
                            <span class="text-[9px] font-mono text-slate-400" x-text="t.code"></span>
                            <template x-if="t.order_count > 0">
                                <span class="absolute -top-1 -right-1 bg-purple-600 text-white text-[9px] font-black px-1.5 py-0.2 rounded-full border border-white shadow-xs animate-pulse"
                                      x-text="t.order_count + ' Pesanan'"></span>
                            </template>
                        </button>
                    </template>
                </div>
                <div class="text-[11px] text-slate-500 mt-2 bg-slate-50 p-2 rounded-lg border border-slate-200/80 flex items-center justify-between">
                    <span>💡 Pindai barcode meja fisik dengan scanner kasir untuk memilih meja secara otomatis.</span>
                </div>
            </div>

            {{-- Tombol Take Away & Delivery (Warna Solid, Kontras Tinggi & Tidak Pudar) --}}
            <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-2 gap-2">
                <button type="button" @click="pilihMeja('Take Away / Bungkus'); orderType = 'Take Away'"
                        class="fnb-takeaway-btn"
                        :class="selectedTable === 'Take Away / Bungkus' ? 'active' : ''">
                    🛍️ Take Away (Bungkus)
                </button>
                <button type="button" @click="pilihMeja('Delivery / Pesan Antar'); orderType = 'Delivery'"
                        class="fnb-delivery-btn"
                        :class="selectedTable === 'Delivery / Pesan Antar' ? 'active' : ''">
                    🛵 Delivery (Antar)
                </button>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         MODAL 2: PEMBAYARAN TRANSAKSI F&B (PERSIS SESUAI MODE KLASIK)
         ========================================================================= --}}
    <div x-show="showPaymentModal" x-cloak
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-3 sm:p-5"
         @keydown.escape.window="if (showPaymentModal) tutupModalBayar()">
        <div class="pop-bayar-modal shadow-2xl"
             style="max-width: 580px; width: 100%; background-color: #ffffff !important; border: 2px solid #000000 !important; border-radius: 0 !important; overflow: hidden;"
             @click.outside="tutupModalBayar()">
            {{-- 1. BAGIAN ATAS: TOTAL BELANJA (Rp) (HIJAU #016400 DENGAN KOTAK MINT #dcfce7) --}}
            <div class="pop-bayar-top-bg" style="background-color: #016400 !important; padding: 0.75rem 0.85rem !important; color: #ffffff !important; border-bottom: 2px solid #000000 !important;">
                <div class="flex items-center justify-between">
                    <div class="pop-bayar-top-title" style="font-weight: 700; font-size: 0.9375rem; letter-spacing: 0.05em; text-transform: uppercase; color: #ffffff !important; line-height: 1.2;">TOTAL BELANJA (Rp)</div>
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-blue-600 text-white shadow-2xs">
                        Meja: <span x-text="selectedTable || 'Take Away'"></span>
                    </span>
                </div>
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

            {{-- PILIHAN CETAK STRUK KHUSUS F&B (CLEAN & INTEGRATED) --}}
            <div style="background-color: #f1f5f9; padding: 0.45rem 0.85rem; border-bottom: 2px solid #000000; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #475569;">Pilihan Cetak Struk:</span>
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; cursor: pointer; color: #0f172a;">
                        <input type="checkbox" x-model="printCustomerReceipt" style="width: 1rem; height: 1rem; accent-color: #0284c7; cursor: pointer;">
                        <span>🧾 Struk Pelanggan (Bill)</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; cursor: pointer; color: #0f172a;">
                        <input type="checkbox" x-model="printKitchenReceipt" style="width: 1rem; height: 1rem; accent-color: #2563eb; cursor: pointer;">
                        <span>👨‍🍳 Struk Dapur (KOT)</span>
                    </label>
                </div>
            </div>

            {{-- 4. BAGIAN BAWAH: PETUNJUK TOMBOL BANTUAN CEPAT (LATAR #f9fafc) --}}
            <div class="pop-bayar-footer" style="background-color: #f9fafc !important; padding: 0.55rem 0.75rem !important; text-align: center; font-size: 0.75rem; font-weight: 500; color: #1e293b; display: flex !important; align-items: center !important; justify-content: center !important; gap: 0.35rem !important; flex-wrap: wrap !important; border-top: 2px solid #000000 !important;">
                <span class="hidden md:inline" style="color: #64748b; font-size: 0.72rem;">Metode: <strong class="text-slate-800">[F1]</strong> Tunai &bull; <strong class="text-slate-800">[F2]</strong> Debit &bull; <strong class="text-slate-800">[F3]</strong> QRIS &bull; <strong class="text-slate-800">[F4]</strong> Transfer &bull;</span>
                <span>Tekan</span>
                <button type="button" @click="selesaiBayar()" title="Selesaikan transaksi &amp; cetak struk (Enter)"
                        class="pop-bayar-kbd-btn" style="padding: 0.15rem 0.55rem !important; border: 1px solid #cbd5e1 !important; border-radius: 0.35rem !important; background-color: #ffffff !important; color: #1e293b !important; font-size: 0.75rem; font-weight: 500; cursor: pointer !important;">
                    Enter
                </button>
                <span @click="selesaiBayar()" class="cursor-pointer hover:text-emerald-700">untuk Selesai &amp; Cetak</span>
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
         MODAL 3: TRANSAKSI BERHASIL & CETAK ULANG CEPAT (DENGAN TAMPILAN KEMBALIAN)
         ========================================================================= --}}
    <div x-show="showSuccessModal" x-cloak
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full p-5 text-center shadow-2xl"
             style="border: 2px solid #000000 !important; border-radius: 0 !important;"
             @keydown.enter.window="tutupModalSukses()"
             @keydown.escape.window="tutupModalSukses()">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto mb-2 border-2 border-emerald-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="font-black text-xl text-slate-900 tracking-tight uppercase">TRANSAKSI BERHASIL!</h3>
            <p class="text-xs text-slate-600 mt-0.5 font-bold" x-text="'Faktur: ' + lastSaleInvoice + ' • ' + (lastSaleTable || 'Take Away')"></p>

            {{-- Kotak Kembalian Classic --}}
            <div class="mt-3 p-3 text-center" style="background-color: #c7d2ff; border: 2px solid #000080;">
                <div class="text-[11px] font-black uppercase tracking-wider" style="color: #000080;">UANG KEMBALIAN:</div>
                <div class="text-3xl font-black my-0.5 tracking-tight" style="color: #02007a;"
                     x-text="'Rp ' + formatNumber(lastSaleChange)">Rp 0</div>
                <div class="text-[11px] font-bold pt-1 border-t border-blue-300 mt-1 flex justify-between px-2 text-slate-700">
                    <span>Total: <strong class="text-slate-900" x-text="'Rp ' + formatNumber(lastSaleTotal)"></strong></span>
                    <span>Bayar: <strong class="text-slate-900" x-text="'Rp ' + formatNumber(lastSalePaid) + ' (' + lastSaleMethod.toUpperCase() + ')'"></strong></span>
                </div>
            </div>

            {{-- Tombol Cetak Ulang Manual --}}
            <div class="mt-3.5 space-y-2">
                <div class="flex items-center gap-2">
                    <button type="button" @click="cetakStruk(lastReceiptUrl)"
                            class="flex-1 py-2 px-3 text-white font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer transition rounded-xl border border-sky-600 shadow-2xs"
                            style="background-color: #0284c7 !important; color: #ffffff !important;">
                        <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Cetak Struk Pelanggan</span>
                    </button>
                    <a :href="lastReceiptUrl ? (lastReceiptUrl + (lastReceiptUrl.includes('?') ? '&' : '?') + 'autoprint=1') : '#'" target="_blank"
                       class="py-2 px-3 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs shrink-0 rounded-xl shadow-2xs"
                       title="Buka Struk di Tab Baru">
                        Tab Baru ↗
                    </a>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="cetakStrukKitchen(lastKitchenReceiptUrl)"
                            class="flex-1 py-2 px-3 text-white font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer transition rounded-xl border border-blue-700 shadow-2xs"
                            style="background-color: #1d4ed8 !important; color: #ffffff !important;">
                        <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>Cetak Struk Dapur (KOT)</span>
                    </button>
                    <a :href="lastKitchenReceiptUrl ? (lastKitchenReceiptUrl + (lastKitchenReceiptUrl.includes('?') ? '&' : '?') + 'autoprint=1') : '#'" target="_blank"
                       class="py-2 px-3 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs shrink-0 rounded-xl shadow-2xs"
                       title="Buka Struk Dapur di Tab Baru">
                        Tab Baru ↗
                    </a>
                </div>
            </div>

            <div class="mt-3.5 pt-3 border-t border-slate-300">
                <button type="button" @click="tutupModalSukses()"
                        class="w-full text-xs py-2.5 font-bold text-white cursor-pointer transition rounded-xl border border-emerald-700 shadow-2xs"
                        style="background-color: #059669 !important; color: #ffffff !important;">
                    Pesanan Baru / Meja Berikutnya [Enter]
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function kasirFnbApp() {
    return {
        allProducts: @json($productsForCart),
        categories: @json($categories),
        customersList: @json($customers),
        tax: @json($tax),
        invoiceNo: @json($nextInvoiceNo ?? 'TRX-' . date('Ymd') . '-0001'),

        // State Order
        cart: [],
        selectedCategory: 'all',
        searchQuery: '',
        selectedTable: @json($tableNoDiambil ?? 'Meja 01'),
        customTableInput: '',
        orderType: 'Dine In',
        customerName: '',
        discount: @json($discountDiambil ?? 0),
        paidAmount: 0,
        paymentMethod: 'tunai',
        processing: false,
        clockTime: '',
        modalBayarOpenedAt: 0,

        // Pilihan Cetak Struk
        printCustomerReceipt: true,
        printKitchenReceipt: true,

        // Modals
        showTableModal: false,
        showPaymentModal: false,
        showSuccessModal: false,

        // Data Transaksi Terakhir (Termasuk Kembalian & Total)
        lastSaleInvoice: '',
        lastSaleTable: '',
        lastSaleTotal: 0,
        lastSalePaid: 0,
        lastSaleChange: 0,
        lastSaleMethod: 'tunai',
        lastReceiptUrl: '',
        lastKitchenReceiptUrl: '',

        // Preset Meja (Dinamis dari Database Meja F&B)
        @php
            $tablesData = collect($fnbTables ?? [])->map(function ($t) use ($waitingByTable) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'code' => $t->code,
                    'capacity' => $t->capacity,
                    'status' => $t->status,
                    'order_count' => (int) ($waitingByTable[$t->name] ?? 0),
                ];
            })->values()->all();

            $tablesNames = !empty($tablesData)
                ? collect($tablesData)->pluck('name')->all()
                : [
                    'Meja 01', 'Meja 02', 'Meja 03', 'Meja 04', 'Meja 05', 'Meja 06',
                    'Meja 07', 'Meja 08', 'Meja 09', 'Meja 10', 'Meja 11', 'Meja 12',
                    'VIP 1', 'VIP 2', 'Outdoor 1', 'Outdoor 2', 'Bar 1', 'Bar 2'
                ];
        @endphp
        presetTablesData: @json($tablesData),
        presetTables: @json($tablesNames),
        waitingOrdersCount: @json($jumlahPesanan ?? 0),

        init() {
            this.updateClock();
            setInterval(() => this.updateClock(), 1000);

            // Dengarkan pembaruan pesanan meja & sinkronkan indikator
            window.addEventListener('jpos:table-orders-updated', (e) => {
                const data = e.detail;
                if (data && data.tables) {
                    this.presetTablesData = data.tables;
                }
                if (data && typeof data.total_waiting !== 'undefined') {
                    this.waitingOrdersCount = data.total_waiting;
                }
            });

            // Pulihkan draf tertahan jika ada
            @if(!empty($keranjangDiambil))
                const diambil = @json($keranjangDiambil);
                this.cart = diambil.map((it, idx) => {
                    const prod = this.allProducts.find(p => p.id === it.product_id) || {};
                    return {
                        id: 'cart_' + Date.now() + '_' + idx,
                        product_id: it.product_id,
                        name: prod.name || it.name || 'Menu',
                        price: prod.sell_price || it.price || 0,
                        qty: it.qty || 1,
                        unit: it.unit_label || prod.unit || 'Porsi',
                        note: it.note || '',
                    };
                });
            @endif
        },

        updateClock() {
            const now = new Date();
            this.clockTime = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        get filteredProducts() {
            let list = this.allProducts;

            // Filter Kategori
            if (this.selectedCategory !== 'all') {
                list = list.filter(p => String(p.category_id) === String(this.selectedCategory));
            }

            // Filter Pencarian
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                list = list.filter(p =>
                    (p.name && p.name.toLowerCase().includes(q)) ||
                    (p.barcode && p.barcode.toLowerCase().includes(q)) ||
                    (p.sku && p.sku.toLowerCase().includes(q))
                );
            }

            return list;
        },

        get categoryCounts() {
            const counts = { all: this.allProducts.length };
            this.allProducts.forEach(p => {
                const cId = String(p.category_id || '');
                if (cId) counts[cId] = (counts[cId] || 0) + 1;
            });
            return counts;
        },

        getItemQtyInCart(productId) {
            const it = this.cart.find(item => item.product_id === productId);
            return it ? it.qty : 0;
        },

        pilihMeja(namaMeja) {
            this.selectedTable = namaMeja;
            this.showTableModal = false;
        },

        pilihMejaDariPreset(t) {
            this.selectedTable = t.name;
            this.orderType = 'Dine In';
            this.showTableModal = false;

            if (t.order_count > 0 && this.cart.length === 0) {
                if (confirm('Meja ' + t.name + ' memiliki ' + t.order_count + ' pesanan aktif. Buka dan muat pesanan ini ke kasir sekarang?')) {
                    window.location.href = '{{ url("kelola-meja") }}/' + t.id + '/buka-kasir';
                }
            }
        },

        tangkapBarcode(kode) {
            const raw = String(kode || '').trim();
            if (!raw) return;

            // 1. Cek apakah barcode yang dipindai adalah kode meja (misal 'M01', 'M05', atau nama meja)
            const tableMatch = this.presetTablesData.find(t =>
                (t.code && t.code.toLowerCase() === raw.toLowerCase()) ||
                (t.name && t.name.toLowerCase() === raw.toLowerCase())
            );

            if (tableMatch) {
                this.pilihMejaDariPreset(tableMatch);
                return;
            }

            // 2. Jika bukan meja, cek apakah barcode cocok dengan produk katalog kasir
            const prodMatch = this.allProducts.find(p =>
                (p.barcode && String(p.barcode).trim().toLowerCase() === raw.toLowerCase()) ||
                (p.sku && String(p.sku).trim().toLowerCase() === raw.toLowerCase())
            );

            if (prodMatch) {
                this.addToCart(prodMatch);
            }
        },

        pilihMejaKustom() {
            if (this.customTableInput.trim() !== '') {
                this.selectedTable = this.customTableInput.trim();
                this.customTableInput = '';
                this.showTableModal = false;
            }
        },

        addToCart(product) {
            const tracksStock = (product.tracks_stock !== undefined) ? !!product.tracks_stock : (product.track_stock !== undefined ? !!product.track_stock : true);
            const stockMax = Number(product.stock) || 0;

            if (tracksStock && stockMax <= 0) {
                alert('Menu "' + product.name + '" sedang habis (stok fisik kosong).');
                return;
            }

            const existing = this.cart.find(item => item.product_id === product.id);
            if (existing) {
                if (tracksStock && existing.qty >= stockMax) {
                    alert('Kuantitas pesanan "' + product.name + '" sudah mencapai batas sisa stok (' + stockMax + ' ' + (product.unit || 'Porsi') + ').');
                    return;
                }
                existing.qty++;
            } else {
                this.cart.push({
                    id: 'cart_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
                    product_id: product.id,
                    name: product.name,
                    price: product.sell_price || 0,
                    qty: 1,
                    unit: product.unit || 'Porsi',
                    note: '',
                    tracks_stock: tracksStock,
                    stock: stockMax,
                });
            }
        },

        tambahQty(index) {
            const item = this.cart[index];
            if (item && item.tracks_stock && item.qty >= item.stock) {
                alert('Kuantitas pesanan "' + item.name + '" sudah mencapai batas sisa stok (' + item.stock + ' ' + (item.unit || 'Porsi') + ').');
                return;
            }
            this.cart[index].qty++;
        },

        kurangQty(index) {
            if (this.cart[index].qty > 1) {
                this.cart[index].qty--;
            } else {
                this.hapusItem(index);
            }
        },

        hapusItem(index) {
            this.cart.splice(index, 1);
        },

        resetCart() {
            if (confirm('Yakin ingin membatalkan seluruh pesanan saat ini?')) {
                this.cart = [];
                this.discount = 0;
                this.paidAmount = 0;
            }
        },

        cartTotalQty() {
            return this.cart.reduce((sum, item) => sum + (Number(item.qty) || 0), 0);
        },

        totalPieces() {
            return this.cartTotalQty();
        },

        subtotal() {
            return this.cart.reduce((sum, item) => sum + ((item.price || 0) * (item.qty || 1)), 0);
        },

        taxAmount() {
            if (!this.tax || !this.tax.enabled || !this.tax.percent) return 0;
            const base = Math.max(0, this.subtotal() - this.discount);
            return Math.round(base * (this.tax.percent / 100));
        },

        total() {
            return Math.max(0, this.subtotal() - this.discount + this.taxAmount());
        },

        change() {
            return (Number(this.paidAmount) || 0) - this.total();
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
        },

        pasUang() {
            this.paidAmount = this.total();
            this.$nextTick(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.value = this.formatNumber(this.total());
                    this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.paidInput.focus();
                    this.$refs.paidInput.select();
                }
            });
        },

        tambahUang(nominal) {
            this.paidAmount = (Number(this.paidAmount) || 0) + nominal;
            this.$nextTick(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.value = this.formatNumber(this.paidAmount);
                    this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.paidInput.focus();
                }
            });
        },

        setNominal(nominal) {
            this.paidAmount = nominal;
            this.$nextTick(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.value = this.formatNumber(this.paidAmount);
                    this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.paidInput.focus();
                }
            });
        },

        bukaModalBayar() {
            if (this.cart.length === 0) return;
            this.modalBayarOpenedAt = Date.now();
            this.paidAmount = this.total();
            this.showPaymentModal = true;
            setTimeout(() => {
                if (this.$refs.paidInput) {
                    this.$refs.paidInput.value = this.formatNumber(this.total());
                    this.$refs.paidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.paidInput.focus();
                    this.$refs.paidInput.select();
                }
            }, 60);
        },

        tutupModalBayar() {
            this.showPaymentModal = false;
        },

        tutupModalSukses() {
            this.showSuccessModal = false;
            this.invoiceNo = 'TRX-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + String(Math.floor(Math.random() * 9000) + 1000);
        },

        handleWindowKey(e) {
            if (e.key === 'F8') {
                e.preventDefault();
                if (!this.showPaymentModal && !this.showTableModal && this.cart.length > 0) {
                    this.bukaModalBayar();
                }
            } else if (this.showPaymentModal) {
                if (e.key === 'F1') {
                    e.preventDefault();
                    this.setPaymentMethod('tunai');
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    this.setPaymentMethod('debit');
                } else if (e.key === 'F3') {
                    e.preventDefault();
                    this.setPaymentMethod('qris');
                } else if (e.key === 'F4') {
                    e.preventDefault();
                    this.setPaymentMethod('transfer');
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (Date.now() - this.modalBayarOpenedAt < 300) return;
                    this.selesaiBayar();
                } else if (e.key === ' ' && e.target !== this.$refs.paidInput) {
                    e.preventDefault();
                    this.pasUang();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.tutupModalBayar();
                }
            } else if (this.showSuccessModal) {
                if (e.key === 'Enter' || e.key === 'Escape') {
                    e.preventDefault();
                    this.tutupModalSukses();
                }
            }
        },

        async tahanPesananFnb() {
            if (this.cart.length === 0 || this.processing) return;
            const konfirmasi = confirm('Simpan pesanan Meja: ' + (this.selectedTable || 'Take Away') + ' sebagai Open Bill (Tertahan)?');
            if (!konfirmasi) return;

            this.processing = true;
            try {
                const res = await fetch('{{ route("kasir.store", [], false) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_id: null,
                        items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty, note: i.note || null })),
                        discount: this.discount || 0,
                        paid_amount: 0,
                        payment_method: 'tunai',
                        is_parked: true,
                        layout: 'fnb',
                        table_no: this.selectedTable || 'Take Away',
                        note: (this.orderType ? '[' + this.orderType + '] ' : '') + (this.customerName ? 'Tamu: ' + this.customerName : ''),
                    }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    alert(data.message || 'Gagal menyimpan pesanan tertahan.');
                    this.processing = false;
                    return;
                }

                // Cetak struk kitchen otomatis jika dipilih
                if (data.kitchen_receipt_url) {
                    this.cetakStrukKitchen(data.kitchen_receipt_url);
                }

                alert('Pesanan ' + (this.selectedTable || 'Take Away') + ' berhasil disimpan sebagai Open Bill dan dikirim ke dapur!');
                this.cart = [];
                this.discount = 0;
                this.paidAmount = 0;
                window.location.reload();
            } catch (err) {
                alert('Gagal menghubungi server.');
            } finally {
                this.processing = false;
            }
        },

        async selesaiBayar() {
            if (this.processing) return;
            const totalTrx = this.total();
            const bayar = this.paymentMethod === 'tunai' ? (Number(this.paidAmount) || 0) : totalTrx;

            if (this.paymentMethod === 'tunai' && totalTrx > 0 && bayar < totalTrx) {
                alert('Jumlah uang pembayaran masih kurang dari total tagihan!');
                if (this.$refs.paidInput) this.$refs.paidInput.focus();
                return;
            }

            this.processing = true;
            try {
                const res = await fetch('{{ route("kasir.store", [], false) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_id: null,
                        items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty, note: i.note || null })),
                        discount: this.discount || 0,
                        paid_amount: bayar,
                        payment_method: this.paymentMethod || 'tunai',
                        is_waiting_list: false,
                        layout: 'fnb',
                        table_no: this.selectedTable || 'Take Away',
                        note: (this.orderType ? '[' + this.orderType + '] ' : '') + (this.customerName ? 'Tamu: ' + this.customerName : ''),
                    }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    alert(data.message || 'Gagal memproses transaksi.');
                    this.processing = false;
                    return;
                }

                // Simpan data transaksi untuk pop-up sukses
                this.lastSaleInvoice = data.invoice_no || this.invoiceNo;
                this.lastSaleTable = this.selectedTable || 'Take Away';
                this.lastSaleTotal = data.total !== undefined ? data.total : totalTrx;
                this.lastSalePaid = data.paid_amount !== undefined ? data.paid_amount : bayar;
                this.lastSaleChange = data.change_amount !== undefined ? data.change_amount : Math.max(0, bayar - totalTrx);
                this.lastSaleMethod = data.payment_method || this.paymentMethod;
                this.lastReceiptUrl = data.receipt_url || '';
                this.lastKitchenReceiptUrl = data.kitchen_receipt_url || '';

                // Eksekusi cetak struk via iframe terpisah
                if (this.printCustomerReceipt && data.receipt_url) {
                    this.cetakDokumen(data.receipt_url, 'jpos-fnb-customer-frame');
                }

                if (this.printKitchenReceipt && data.kitchen_receipt_url) {
                    setTimeout(() => {
                        this.cetakDokumen(data.kitchen_receipt_url, 'jpos-fnb-kitchen-frame');
                    }, 750);
                }

                // Reset state pesanan
                this.cart = [];
                this.discount = 0;
                this.paidAmount = 0;
                this.showPaymentModal = false;
                this.showSuccessModal = true;
            } catch (err) {
                alert('Gagal memproses pembayaran ke server. Periksa koneksi aplikasi.');
            } finally {
                this.processing = false;
            }
        },

        cetakDokumen(url, frameId = 'jpos-fnb-customer-frame') {
            if (!url) return;
            const sep = url.includes('?') ? '&' : '?';
            const fullUrl = url + sep + 'autoprint=1&_t=' + Date.now();
            let frame = document.getElementById(frameId);
            if (!frame) {
                frame = document.createElement('iframe');
                frame.id = frameId;
                frame.name = frameId;
                frame.style.position = 'fixed';
                frame.style.right = '0';
                frame.style.bottom = '0';
                frame.style.width = '10px';
                frame.style.height = '10px';
                frame.style.opacity = '0.01';
                frame.style.pointerEvents = 'none';
                frame.style.border = '0';
                document.body.appendChild(frame);
            }
            frame.src = fullUrl;
        },

        cetakStruk(url) {
            this.cetakDokumen(url, 'jpos-fnb-customer-frame');
        },

        cetakStrukKitchen(url) {
            this.cetakDokumen(url, 'jpos-fnb-kitchen-frame');
        },

        formatNumber(n) {
            return new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
        }
    };
}
</script>
@endpush
