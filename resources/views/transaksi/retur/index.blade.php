@extends('layouts.app')
@section('title', 'Retur & Edit Transaksi')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-1" x-data="returApp()" x-init="if (allowToggle) viewMode = localStorage.getItem('kasir_view_mode') || viewMode; if (invoiceNo) findSale()">
        <div class="card p-4">
            <h3 class="font-semibold mb-1">Cari Transaksi</h3>
            <p class="text-xs text-slate-400 mb-3">Retur item, atau tambah item yang lupa di-scan, pada transaksi yang sudah lunas.</p>
            <div class="flex gap-2">
                <input type="text" x-model="invoiceNo" @keydown.enter="findSale()" placeholder="No. Invoice (INV...)" class="form-input">
                <button @click="findSale()" class="btn btn-primary">Cari</button>
            </div>
            <p class="text-xs text-red-500 mt-2" x-text="errorMsg"></p>

            <template x-if="sale">
                <div class="mt-4 border-t pt-4">
                    <div class="text-sm mb-2"><span class="font-medium" x-text="sale.invoice_no"></span> &middot; <span x-text="sale.created_at"></span></div>

                    <div class="text-xs font-semibold text-slate-500 mb-1">Item Saat Ini (isi qty untuk retur)</div>
                    <div class="space-y-2">
                        <template x-for="(item, idx) in sale.items" :key="item.id">
                            <div class="flex items-center gap-2 text-sm border-b pb-2">
                                <div class="flex-1">
                                    <div x-text="item.product_name + (item.unit_label ? ' (' + item.unit_label + ')' : '')"></div>
                                    <div class="text-xs text-slate-400" x-text="'Tersedia retur: ' + formatQty(item.available)"></div>
                                </div>
                                <input type="text" data-jpos-number x-number="returnQty[idx]" class="w-16 text-center border rounded py-1">
                            </div>
                        </template>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Alasan Retur</label>
                        <textarea x-model="reason" class="form-textarea" rows="2" placeholder="Contoh: barang rusak / salah pesan"></textarea>
                    </div>

                    <div class="mt-4 pt-3 border-t">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs font-semibold text-slate-500">Tambah Item Baru (misal lupa di-scan)</div>
                            @if($allowToggle)
                            <div class="flex rounded border overflow-hidden text-xs">
                                <button type="button" @click="setViewMode('gambar')" :class="viewMode === 'gambar' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'" class="px-2 py-1">🖼️</button>
                                <button type="button" @click="setViewMode('list')" :class="viewMode === 'list' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'" class="px-2 py-1 border-l">📋</button>
                            </div>
                            @endif
                        </div>
                        <div class="relative mb-2">
                            <input type="text" x-model="productSearch" placeholder="Cari produk..." class="form-input text-sm">
                        </div>
                        <template x-if="productSearch && viewMode === 'list'">
                            <div class="border rounded-lg max-h-40 overflow-y-auto mb-2">
                                <template x-for="p in filteredProducts" :key="p.id">
                                    <button type="button" @click="onProductClick(p)" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 border-b last:border-b-0 flex justify-between">
                                        <span x-text="p.name"></span>
                                        <span class="text-slate-400" x-text="'Rp ' + formatNumber(p.sell_price)"></span>
                                    </button>
                                </template>
                                <template x-if="filteredProducts.length === 0">
                                    <div class="px-3 py-2 text-sm text-slate-400">Tidak ditemukan.</div>
                                </template>
                            </div>
                        </template>
                        <template x-if="productSearch && viewMode === 'gambar'">
                            <div class="grid grid-cols-3 gap-2 max-h-48 overflow-y-auto mb-2 border rounded-lg p-2">
                                <template x-for="p in filteredProducts" :key="p.id">
                                    <button type="button" @click="onProductClick(p)" class="text-left hover:bg-slate-50 rounded p-1">
                                        <div class="aspect-square rounded bg-slate-100 overflow-hidden mb-1">
                                            <img :src="p.image_url" class="w-full h-full object-cover">
                                        </div>
                                        <div class="text-[11px] font-medium truncate" x-text="p.name"></div>
                                        <div class="text-[10px] text-slate-400" x-text="'Rp ' + formatNumber(p.sell_price)"></div>
                                    </button>
                                </template>
                                <template x-if="filteredProducts.length === 0">
                                    <div class="col-span-3 text-center text-sm text-slate-400 py-4">Tidak ditemukan.</div>
                                </template>
                            </div>
                        </template>

                        <div class="space-y-2">
                            <template x-for="(item, idx) in addItems" :key="idx">
                                <div class="flex items-center gap-2 text-sm border-b pb-2">
                                    <div class="flex-1">
                                        <div x-text="item.name + (item.unit_label ? ' (' + item.unit_label + ')' : '')"></div>
                                        <div class="text-xs text-slate-400" x-text="'Rp ' + formatNumber(item.price) + ' / ' + (item.unit_label || 'satuan')"></div>
                                    </div>
                                    <input type="text" data-jpos-number data-number-min="1" x-number="item.qty" class="w-16 text-center border rounded py-1">
                                    <button type="button" @click="addItems.splice(idx,1)" class="text-red-400 hover:text-red-600">&times;</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t text-sm space-y-1">
                        <div class="flex justify-between"><span>Total Retur</span><span class="text-red-600" x-text="'- Rp ' + formatNumber(returnTotal())"></span></div>
                        <div class="flex justify-between"><span>Total Item Tambahan</span><span class="text-green-600" x-text="'+ Rp ' + formatNumber(addTotal())"></span></div>
                        <template x-if="addTotal() > 0">
                            <div class="flex justify-between font-semibold">
                                <span>Selisih Harus Dibayar</span>
                                <span x-text="'Rp ' + formatNumber(addTotal())"></span>
                            </div>
                        </template>
                    </div>

                    <template x-if="addTotal() > 0">
                        <div class="mt-2">
                            <label class="form-label">Jumlah Bayar Selisih</label>
                            <input type="text" data-jpos-number data-number-decimals="2" x-number="additionalPayment" :placeholder="'Minimal ' + formatNumber(addTotal())" class="form-input">
                            {{-- Metode DIMINTA, bukan ditebak dari transaksi aslinya. Pelanggan
                                 yang dulu bayar tunai bisa menambah lewat QRIS; menebaknya
                                 menaruh uang di ember yang salah saat rekonsiliasi laci. --}}
                            <select x-model="additionalPaymentMethod" class="form-select mt-2">
                                @foreach(\App\Support\MetodeBayar::DAFTAR as $kunci => $labelMetode)
                                    <option value="{{ $kunci }}">{{ $labelMetode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <button @click="submit()" :disabled="processing || (returnItemsCount() === 0 && addItems.length === 0)" class="w-full btn btn-primary justify-center mt-3 disabled:opacity-50">
                        <span x-show="!processing">Simpan Perubahan</span>
                        <span x-show="processing">Memproses...</span>
                    </button>
                </div>
            </template>
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

    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <table class="data-table w-full">
                <thead><tr><th>No. Retur</th><th>No. Invoice</th><th>Total</th><th>Alasan</th><th>Tanggal</th></tr></thead>
                <tbody>
                    @forelse($returns as $r)
                    <tr>
                        <td class="font-medium">{{ $r->return_no }}</td>
                        <td>{{ $r->sale->invoice_no ?? '-' }}</td>
                        <td>Rp {{ number_format($r->total, 0, ',', '.') }}</td>
                        <td class="text-slate-500">{{ $r->reason ?: '-' }}</td>
                        <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-slate-400 py-8">Belum ada data retur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $returns->links() }}</div>
    </div>
</div>

@push('scripts')
<script>
function returApp() {
    return {
        allProducts: @json($productsForCart),
        invoiceNo: @js($prefillInvoice),
        sale: null,
        returnQty: [],
        reason: '',
        productSearch: '',
        addItems: [],
        additionalPayment: 0,
        additionalPaymentMethod: 'tunai',
        errorMsg: '',
        processing: false,
        viewMode: '{{ $defaultView }}',
        allowToggle: {{ $allowToggle ? 'true' : 'false' }},
        unitPickerProduct: null,

        setViewMode(mode) {
            this.viewMode = mode;
            if (this.allowToggle) localStorage.setItem('kasir_view_mode', mode);
        },

        get filteredProducts() {
            if (!this.productSearch) return [];
            return this.allProducts.filter(p => p.name.toLowerCase().includes(this.productSearch.toLowerCase())).slice(0, 15);
        },

        onProductClick(p) {
            if (p.additional_units && p.additional_units.length > 0) {
                this.unitPickerProduct = p;
            } else {
                this.pushAddItem(p, 'base');
                this.productSearch = '';
            }
        },

        chooseUnit(unitType) {
            const p = this.unitPickerProduct;
            this.unitPickerProduct = null;
            if (p) this.pushAddItem(p, unitType);
            this.productSearch = '';
        },

        pushAddItem(p, unitType) {
            let price = p.sell_price, unitLabel = null;
            if (unitType !== 'base') {
                const unitId = parseInt(unitType.substring(5));
                const pu = (p.additional_units || []).find(u => u.id === unitId);
                if (!pu) {
                    this.errorMsg = `Satuan yang dipilih untuk ${p.name} tidak ditemukan.`;
                    return;
                }
                price = pu.price;
                unitLabel = pu.unit_name;
            }
            this.addItems.push({ product_id: p.id, name: p.name, unit_type: unitType, unit_label: unitLabel, price: price, qty: 1 });
        },

        returnItemsCount() {
            return this.returnQty.filter(q => q > 0).length;
        },

        returnTotal() {
            if (!this.sale) return 0;
            return this.sale.items.reduce((sum, item, idx) => sum + (item.price * (this.returnQty[idx] || 0)), 0);
        },

        addTotal() {
            return this.addItems.reduce((sum, item) => sum + item.price * item.qty, 0);
        },

        // Delegasi ke helper bersama di public/vendor/jpos-number.js supaya format angka
        // di seluruh aplikasi berasal dari satu sumber.
        formatNumber(n) { return window.JposNumber ? window.JposNumber.format(n) : Math.round(n || 0).toLocaleString('id-ID'); },
        // Kuantitas & stok boleh pecahan sejak barang timbangan bisa dijual per Kg atau
        // per Gram. formatNumber() membulatkan ke bilangan bulat, jadi memakainya untuk
        // qty akan menampilkan sisa stok 9,6 Kg sebagai "10".
        formatQty(n) { return window.JposNumber ? window.JposNumber.formatQty(n) : String(n ?? 0); },

        async findSale() {
            this.errorMsg = '';
            this.sale = null;
            this.addItems = [];
            this.additionalPayment = 0;
            this.additionalPaymentMethod = 'tunai';
            if (!this.invoiceNo) return;
            const res = await fetch(`{{ route('retur.find') }}?invoice_no=${encodeURIComponent(this.invoiceNo)}`);
            const data = await res.json();
            if (!data.found) {
                this.errorMsg = 'Transaksi tidak ditemukan.';
                return;
            }
            this.sale = data.sale;
            this.returnQty = this.sale.items.map(() => 0);
        },

        async submit() {
            this.errorMsg = '';
            const items = this.sale.items
                .map((item, idx) => ({ sale_item_id: item.id, qty: this.returnQty[idx] }))
                .filter(i => i.qty > 0);
            const addItemsPayload = this.addItems.map(i => ({ product_id: i.product_id, qty: i.qty, unit_type: i.unit_type }));

            if (items.length === 0 && addItemsPayload.length === 0) {
                this.errorMsg = 'Tidak ada perubahan untuk disimpan.';
                return;
            }

            this.processing = true;
            try {
                const res = await fetch('{{ route('retur.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        sale_id: this.sale.id,
                        reason: this.reason,
                        items,
                        add_items: addItemsPayload,
                        additional_payment: this.additionalPayment || 0,
                        additional_payment_method: this.additionalPaymentMethod,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.errorMsg = data.message || 'Terjadi kesalahan.';
                    this.processing = false;
                    return;
                }
                window.location.reload();
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
