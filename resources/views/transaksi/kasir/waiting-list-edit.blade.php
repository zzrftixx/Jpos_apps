@extends('layouts.app')
@section('title', 'Edit Pesanan - ' . $sale->invoice_no)

@section('content')
<div x-data="waitingEditApp()" class="flex flex-col lg:flex-row gap-4 h-full">

    <a href="{{ route('kasir.waiting-list') }}" class="text-sm text-blue-600 hover:underline lg:hidden mb-2">&larr; Kembali</a>

    {{-- LEFT: search & add product --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between mb-3">
            <div>
                <a href="{{ route('kasir.waiting-list') }}" class="text-sm text-blue-600 hover:underline hidden lg:inline">&larr; Kembali</a>
                <h2 class="font-semibold text-lg">Edit Pesanan {{ $sale->invoice_no }}</h2>
            </div>
        </div>

        <div class="card p-4 mb-4 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <div><span class="text-slate-400">Pelanggan:</span> {{ $sale->customer->name ?? 'Umum' }}</div>
                <div><span class="text-slate-400">Tanggal:</span> {{ $sale->created_at->format('d/m/Y H:i') }}</div>
                <div><span class="text-slate-400">DP sudah dibayar:</span> Rp {{ number_format($sale->paid_amount, 0, ',', '.') }}</div>
                <div><span class="text-slate-400">Kasir:</span> {{ $sale->cashier->name ?? '-' }}</div>
            </div>
        </div>

        @if($orphanedItems->isNotEmpty())
        <div class="card p-4 mb-4 text-sm bg-slate-50">
            <div class="text-xs text-slate-400 mb-2">Item berikut produknya sudah dihapus dari Master Data, tidak bisa diubah/dihapus di sini (tetap dihitung ke total):</div>
            @foreach($orphanedItems as $item)
            <div class="flex justify-between text-slate-500">
                <span>@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }}x {{ $item->product_name }}</span>
                <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <div class="flex items-center gap-2 mb-3">
            <div class="relative flex-1">
                <input type="text" x-model="search" placeholder="Cari produk untuk ditambahkan..." class="form-input pl-9">
                <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/></svg>
            </div>
            @if($allowToggle)
            <div class="flex rounded-lg border overflow-hidden text-sm">
                <button type="button" @click="setViewMode('gambar')" :class="viewMode === 'gambar' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'" class="px-3 py-2">🖼️</button>
                <button type="button" @click="setViewMode('list')" :class="viewMode === 'list' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'" class="px-3 py-2 border-l">📋</button>
            </div>
            @endif
        </div>

        <div x-show="viewMode === 'gambar'" class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-4">
            <template x-for="p in filteredProducts" :key="p.id">
                <button type="button" @click="onProductClick(p)" :disabled="p.type !== 'jasa' && productStock(p.id) <= 0"
                    class="card p-2 text-left hover:shadow-md transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <div class="aspect-square rounded-lg overflow-hidden bg-slate-100 mb-2">
                        <img :src="p.image_url" class="w-full h-full object-cover">
                    </div>
                    <div class="text-sm font-medium truncate" x-text="p.name"></div>
                    <div class="text-xs" :class="p.type === 'jasa' ? 'text-blue-600' : 'text-slate-400'" x-text="p.type === 'jasa' ? '🛠️ Jasa' : ('Stok: ' + productStock(p.id))"></div>
                    <div class="text-sm font-semibold text-blue-600" x-text="'Rp ' + formatNumber(p.sell_price)"></div>
                </button>
            </template>
            <template x-if="search && filteredProducts.length === 0">
                <div class="col-span-full text-center text-slate-400 py-10">Produk tidak ditemukan.</div>
            </template>
            <template x-if="!search">
                <div class="col-span-full text-center text-slate-400 py-10">Ketik untuk mencari produk yang ingin ditambahkan.</div>
            </template>
        </div>

        <div x-show="viewMode === 'list'" class="card overflow-hidden mb-4">
            <table class="data-table w-full">
                <thead><tr><th>Nama Produk</th><th class="text-right">Harga</th><th class="text-right">Stok</th><th></th></tr></thead>
                <tbody>
                    <template x-for="p in filteredProducts" :key="p.id">
                        <tr :class="(p.type !== 'jasa' && productStock(p.id) <= 0) ? 'opacity-40' : ''">
                            <td>
                                <div class="font-medium" x-text="p.name"></div>
                                <template x-if="(p.additional_units && p.additional_units.length > 0) || p.wholesale_price">
                                    <div class="text-[10px] text-slate-400">
                                        <span x-show="p.additional_units && p.additional_units.length > 0" x-text="'atau per ' + p.additional_units.map(u => u.unit_name).join('/')"></span>
                                        <span x-show="(p.additional_units && p.additional_units.length > 0) && p.wholesale_price"> &middot; </span>
                                        <span x-show="p.wholesale_price" x-text="'grosir min ' + formatNumber(p.wholesale_min_qty)"></span>
                                    </div>
                                </template>
                            </td>
                            <td class="text-right" x-text="'Rp ' + formatNumber(p.sell_price)"></td>
                            <td class="text-right">
                                <span x-show="p.type === 'jasa'" class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">🛠️ Jasa</span>
                                <span x-show="p.type !== 'jasa'" x-text="productStock(p.id)"></span>
                            </td>
                            <td class="text-right">
                                <button type="button" @click="onProductClick(p)" :disabled="p.type !== 'jasa' && productStock(p.id) <= 0"
                                    class="w-7 h-7 rounded bg-brand-500 text-white text-sm font-bold hover:bg-brand-600 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="search && filteredProducts.length === 0">
                        <tr><td colspan="4" class="text-center text-slate-400 py-6">Produk tidak ditemukan.</td></tr>
                    </template>
                    <template x-if="!search">
                        <tr><td colspan="4" class="text-center text-slate-400 py-6">Ketik untuk mencari produk yang ingin ditambahkan.</td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- RIGHT: editable item list --}}
    <div class="w-full lg:w-96 shrink-0 flex flex-col card p-4">
        <h3 class="font-semibold mb-3">Item Pesanan</h3>

        <div class="flex-1 overflow-y-auto space-y-2 min-h-[120px] max-h-[50vh]">
            <template x-for="(item, idx) in cart" :key="item.product_id + '-' + item.unit_type">
                <div class="flex items-center gap-2 border-b pb-2">
                    <img :src="item.image_url" class="w-10 h-10 rounded object-cover bg-slate-100">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate" x-text="item.name"></div>
                        <div class="text-xs text-slate-400 flex items-center gap-1">
                            <span x-text="'Rp ' + formatNumber(linePrice(item))"></span>
                            <span x-show="item.unit_label" class="text-amber-600" x-text="'/ ' + item.unit_label"></span>
                            <span x-show="isWholesaleActive(item)" class="text-emerald-600 font-medium">Grosir</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button @click="decrQty(idx)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-sm">-</button>
                        <input type="text" data-jpos-number :data-number-decimals="item.is_weighable ? 3 : 0" :data-number-min="item.is_weighable ? 0.001 : 1" x-number="item.qty" @change="clampQty(idx)" class="w-16 text-center text-sm border rounded">
                        <button @click="incrQty(idx)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-sm">+</button>
                    </div>
                    <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600 text-sm">&times;</button>
                </div>
            </template>
            <template x-if="cart.length === 0">
                <p class="text-sm text-slate-400 text-center py-6">Semua item dihapus.<br>Tambah produk dari daftar kiri.</p>
            </template>
        </div>

        <div class="border-t mt-3 pt-3 space-y-2 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span x-text="'Rp ' + formatNumber(subtotal())"></span></div>
            <div class="flex justify-between items-center">
                <span>Diskon</span>
                <input type="text" data-jpos-number x-number="discount" class="w-28 text-right form-input py-1">
            </div>
            @if($tax['enabled'] ?? false)
            <div class="flex justify-between text-slate-500"><span>{{ $tax['name'] ?? 'Pajak' }} ({{ $tax['percent'] ?? 0 }}%)</span><span x-text="'Rp ' + formatNumber(taxAmount())"></span></div>
            @endif
            <div class="flex justify-between font-semibold text-base pt-1"><span>Total Baru</span><span x-text="'Rp ' + formatNumber(total())"></span></div>
            <div class="flex justify-between text-slate-500"><span>DP sudah dibayar</span><span>Rp {{ number_format($sale->paid_amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-medium" :class="total() - {{ (float) $sale->paid_amount }} <= 0 ? 'text-green-600' : 'text-amber-700'">
                <span x-text="total() - {{ (float) $sale->paid_amount }} <= 0 ? 'Otomatis jadi Lunas' : 'Sisa yang harus dilunasi'"></span>
                <span x-text="'Rp ' + formatNumber(Math.max(total() - {{ (float) $sale->paid_amount }}, 0))"></span>
            </div>
        </div>

        <button @click="save()" :disabled="cart.length === 0 || processing"
            class="w-full btn btn-primary justify-center py-3 text-base disabled:opacity-50 mt-3">
            <span x-show="!processing">Simpan Perubahan</span>
            <span x-show="processing">Menyimpan...</span>
        </button>
        <p class="text-xs text-red-500 mt-1" x-text="errorMsg"></p>
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
</div>

@push('scripts')
<script>
function waitingEditApp() {
    return {
        allProducts: @json($productsForCart),
        initialReserved: @json($initialReserved),
        search: '',
        cart: @json($initialCart),
        discount: {{ (float) $sale->discount }},
        unitPickerProduct: null,
        processing: false,
        errorMsg: '',
        taxPercent: {{ ($tax['enabled'] ?? false) ? ($tax['percent'] ?? 0) : 0 }},
        viewMode: '{{ $defaultView }}',
        allowToggle: {{ $allowToggle ? 'true' : 'false' }},

        init() {
            if (this.allowToggle) {
                this.viewMode = localStorage.getItem('kasir_view_mode') || this.viewMode;
            }
        },

        setViewMode(mode) {
            this.viewMode = mode;
            if (this.allowToggle) localStorage.setItem('kasir_view_mode', mode);
        },

        get filteredProducts() {
            if (!this.search) return [];
            return this.allProducts.filter(p => p.name.toLowerCase().includes(this.search.toLowerCase()));
        },

        onProductClick(p) {
            if (p.additional_units && p.additional_units.length > 0) {
                this.unitPickerProduct = p;
            } else {
                this.addToCartWithUnit(p, 'base');
            }
        },

        chooseUnit(unitType) {
            const p = this.unitPickerProduct;
            this.unitPickerProduct = null;
            if (p) this.addToCartWithUnit(p, unitType);
        },

        // Stok tersedia untuk diedit = stok saat ini + yang sudah direservasi order ini sejak awal,
        // karena reservasi lama akan dikembalikan dulu di server sebelum item baru divalidasi.
        productStock(productId) {
            const p = this.allProducts.find(x => x.id === productId);
            const base = p ? p.stock : 0;
            return base + (this.initialReserved[productId] || 0);
        },

        usedBaseUnits(productId, excludeIdx = -1) {
            return this.cart.reduce((sum, item, idx) => {
                if (idx === excludeIdx || item.product_id !== productId) return sum;
                return sum + item.qty * item.conversion;
            }, 0);
        },

        maxQtyForLine(idx) {
            const item = this.cart[idx];
            if (item.product_type === 'jasa') return Infinity;
            const remainingBaseUnits = Math.max(this.productStock(item.product_id) - this.usedBaseUnits(item.product_id, idx), 0);
            const maks = remainingBaseUnits / item.conversion;
            // Satuan timbangan boleh menyisakan pecahan; satuan hitung dibulatkan ke bawah.
            return item.is_weighable ? Math.floor(maks * 1000) / 1000 : Math.floor(maks);
        },

        addToCartWithUnit(p, unitType) {
            let conversion = 1, unitLabel = null, price = p.sell_price;
            let wholesalePrice = p.wholesale_price, wholesaleMinQty = p.wholesale_min_qty;
            let bolehPecahan = !!p.is_weighable;

            if (unitType !== 'base') {
                const unitId = parseInt(unitType.substring(5));
                const pu = (p.additional_units || []).find(u => u.id === unitId);
                if (!pu) {
                    this.errorMsg = `Satuan yang dipilih untuk ${p.name} tidak ditemukan.`;
                    return;
                }
                conversion = pu.conversion;
                unitLabel = pu.unit_name;
                price = pu.price;
                wholesalePrice = pu.wholesale_price;
                wholesaleMinQty = pu.wholesale_min_qty;
                bolehPecahan = !!pu.is_weighable;
            }

            const existingIdx = this.cart.findIndex(i => i.product_id === p.id && i.unit_type === unitType);

            if (existingIdx >= 0) {
                const sebelum = this.cart[existingIdx].qty;
                this.incrQty(existingIdx);
                if (this.cart[existingIdx].qty === sebelum) this.errorMsg = `Stok ${p.name} tidak cukup.`;
            } else {
                if (p.type !== 'jasa') {
                    const remaining = Math.max(this.productStock(p.id) - this.usedBaseUnits(p.id), 0);
                    if (remaining / conversion < 1) {
                        this.errorMsg = `Stok ${p.name} tidak cukup.`;
                        return;
                    }
                }
                this.errorMsg = '';
                this.cart.push({
                    product_id: p.id, name: p.name, image_url: p.image_url, product_type: p.type,
                    unit_type: unitType, unit_label: unitLabel, conversion: conversion,
                    is_weighable: bolehPecahan,
                    qty: 1,
                    price: price, wholesale_price: wholesalePrice, wholesale_min_qty: wholesaleMinQty,
                });
            }
        },

        linePrice(item) {
            if (this.isWholesaleActive(item)) return item.wholesale_price;
            return item.price;
        },
        isWholesaleActive(item) {
            return item.wholesale_price != null && item.wholesale_min_qty != null && item.qty >= item.wholesale_min_qty;
        },

        bulatQty(n) { return Math.round((Number(n) || 0) * 1000) / 1000; },
        langkahQty(item) { return item.is_weighable ? 0.1 : 1; },
        minQty(item) { return item.is_weighable ? 0.001 : 1; },

        incrQty(idx) {
            const item = this.cart[idx];
            const berikut = this.bulatQty(item.qty + this.langkahQty(item));
            if (berikut <= this.maxQtyForLine(idx)) item.qty = berikut;
        },
        decrQty(idx) {
            const item = this.cart[idx];
            const berikut = this.bulatQty(item.qty - this.langkahQty(item));
            if (berikut >= this.minQty(item)) item.qty = berikut; else this.removeItem(idx);
        },
        clampQty(idx) {
            const item = this.cart[idx];
            const max = this.maxQtyForLine(idx);
            let qty = Number(item.qty);
            if (!isFinite(qty)) qty = this.minQty(item);
            if (!item.is_weighable) qty = Math.round(qty);
            qty = this.bulatQty(qty);
            if (qty < this.minQty(item)) qty = this.minQty(item);
            if (qty > max) qty = max;
            item.qty = qty;
        },
        removeItem(idx) { this.cart.splice(idx, 1); },

        subtotal() { return this.cart.reduce((s, i) => s + this.linePrice(i) * i.qty, 0) + {{ (float) $orphanedItems->sum('subtotal') }}; },
        taxAmount() { return Math.round((this.subtotal() - this.discount) * this.taxPercent / 100); },
        total() { return Math.max(this.subtotal() - this.discount + this.taxAmount(), 0); },

        // Delegasi ke helper bersama di public/vendor/jpos-number.js supaya format angka
        // di seluruh aplikasi berasal dari satu sumber.
        formatNumber(n) { return window.JposNumber ? window.JposNumber.format(n) : Math.round(n || 0).toLocaleString('id-ID'); },
        // Kuantitas & stok boleh pecahan sejak barang timbangan bisa dijual per Kg atau
        // per Gram. formatNumber() membulatkan ke bilangan bulat, jadi memakainya untuk
        // qty akan menampilkan sisa stok 9,6 Kg sebagai "10".
        formatQty(n) { return window.JposNumber ? window.JposNumber.formatQty(n) : String(n ?? 0); },

        async save() {
            if (this.cart.length === 0) return;
            this.errorMsg = '';
            this.processing = true;
            try {
                const res = await fetch('{{ route('kasir.waiting-list.update', $sale) }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty, unit_type: i.unit_type })),
                        discount: this.discount || 0,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.errorMsg = data.message || 'Terjadi kesalahan.';
                    this.processing = false;
                    return;
                }
                window.location.href = data.redirect_url;
            } catch (e) {
                this.errorMsg = 'Gagal menghubungi server.';
                this.processing = false;
            }
        },
    }
}
</script>
@endpush
@endsection
