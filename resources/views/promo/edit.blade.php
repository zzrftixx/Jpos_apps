@extends('layouts.app')
@section('title', 'Edit Promo - ' . $promotion->name)

@section('content')
<div class="max-w-5xl mx-auto" x-data="promoEditApp()">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Promo: {{ $promotion->name }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">Perbarui informasi promo, daftar produk, dan harga promo khusus</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('promo.cetak-barcode', $promotion) }}" target="_blank" class="btn btn-sm btn-outline text-blue-700 border-blue-200 hover:bg-blue-50 inline-flex items-center gap-1.5 shrink-0 py-1.5 px-3">
                <svg style="width: 14px; height: 14px; min-width: 14px; max-width: 14px; flex-shrink: 0;" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="2" y="4" width="2" height="16" rx="0.75"/>
                    <rect x="6" y="4" width="3" height="16" rx="0.75"/>
                    <rect x="11" y="4" width="1.5" height="16" rx="0.75"/>
                    <rect x="14.5" y="4" width="3" height="16" rx="0.75"/>
                    <rect x="19.5" y="4" width="2" height="16" rx="0.75"/>
                </svg>
                <span class="whitespace-nowrap">Label Rak</span>
            </a>
            <a href="{{ route('promo.index') }}" class="btn btn-outline">&larr; Kembali</a>
        </div>
    </div>

    @if($errors->any())
        <div class="card bg-red-50 border border-red-200 p-4 mb-4 text-red-700 text-sm">
            <div class="font-bold mb-1">Periksa kembali formulir:</div>
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('promo.update', $promotion) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Kartu Info Dasar Promo --}}
        <div class="card p-4 mb-4 shadow-sm">
            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 mb-3 flex items-center gap-1.5">
                <span class="w-5 h-5 rounded-full bg-brand-50 text-brand-600 inline-flex items-center justify-center text-[10px] font-bold">1</span>
                <span>Informasi Promo</span>
            </h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-1">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Promo <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $promotion->name) }}" class="form-input w-full text-xs" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai (Opsional)</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($promotion->start_date)->format('Y-m-d')) }}" class="form-input w-full text-xs">
                    <p class="text-[10px] text-slate-400 mt-0.5">Kosongkan jika promo langsung berlaku</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai (Opsional)</label>
                    <input type="date" name="end_date" value="{{ old('end_date', optional($promotion->end_date)->format('Y-m-d')) }}" class="form-input w-full text-xs">
                    <p class="text-[10px] text-slate-400 mt-0.5">Kosongkan jika promo berlaku terus</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan / Catatan Promo (Opsional)</label>
                    <textarea name="description" rows="2" class="form-input w-full text-xs" placeholder="Catatan tambahan untuk kasir atau pembeli...">{{ old('description', $promotion->description) }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $promotion->is_active ? '1' : '0') === '1' ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-xs font-semibold text-slate-700">Promo Aktif</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Kartu Produk Promo Ringkas & Ramping --}}
        <div class="card p-4 mb-4 shadow-sm">
            <div class="flex items-center justify-between pb-2 mb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-brand-50 text-brand-600 inline-flex items-center justify-center text-[10px] font-bold">2</span>
                    <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Produk dalam Promo</h2>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 border border-brand-200" x-text="items.length + ' item'"></span>
                </div>
                <button type="button" @click="addItem()" class="btn btn-sm btn-primary py-1 px-2.5 text-xs inline-flex items-center gap-1 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah Produk</span>
                </button>
            </div>

            {{-- Header Kolom Ringkas --}}
            <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-3 py-1.5 bg-slate-50 rounded-lg text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 border border-slate-200">
                <div class="sm:col-span-5">Nama Produk</div>
                <div class="sm:col-span-2 text-right">Harga Normal</div>
                <div class="sm:col-span-2 text-right">Harga Promo</div>
                <div class="sm:col-span-2">Barcode Promo</div>
                <div class="sm:col-span-1 text-center">Hapus</div>
            </div>

            {{-- Daftar Baris Produk Ringkas --}}
            <div class="space-y-2">
                <template x-for="(item, index) in items" :key="index">
                    <div class="p-2.5 bg-white rounded-lg border border-slate-200 hover:border-brand-300 transition-colors shadow-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                            {{-- 1. Nama Produk (5 Kolom) --}}
                            <div class="sm:col-span-5">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-600 inline-flex items-center justify-center text-[10px] font-bold shrink-0" x-text="index + 1"></span>
                                    <div class="flex-1 min-w-0">
                                        <select :name="'items[' + index + '][product_id]'" 
                                                x-model="item.product_id" 
                                                @change="onProductChange(index)" 
                                                class="form-select w-full text-xs py-1.5 rounded-lg border-slate-300 focus:border-brand-500 focus:ring-brand-500 font-medium" 
                                                required>
                                            <option value="">-- Pilih Produk --</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}" :selected="String(item.product_id) === '{{ $p->id }}'">
                                                    {{ $p->name }} — Rp {{ number_format((float) $p->sell_price, 0, ',', '.') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- 2. Harga Normal (2 Kolom) --}}
                            <div class="sm:col-span-2 text-right">
                                <div class="text-xs font-semibold text-slate-600 whitespace-nowrap">
                                    <span class="text-slate-400 font-normal text-[11px]">Rp</span>
                                    <span x-text="formatNumber(item.sell_price || 0)"></span>
                                </div>
                            </div>

                            {{-- 3. Harga Promo (2 Kolom) --}}
                            <div class="sm:col-span-2">
                                <div class="relative">
                                    <input type="text" 
                                           data-jpos-number 
                                           data-number-min="0" 
                                           :name="'items[' + index + '][promo_price]'" 
                                           x-number="item.promo_price" 
                                           placeholder="0" 
                                           class="form-input w-full py-1 px-2.5 text-xs font-bold text-green-700 text-right bg-white border-green-300 focus:border-brand-500 focus:ring-brand-500 rounded-lg" 
                                           required>
                                </div>
                                <div x-show="item.sell_price > 0 && item.promo_price > 0 && Number(item.promo_price) < Number(item.sell_price)" class="text-[10px] text-green-600 font-bold text-right mt-0.5">
                                    Hemat <span x-text="Math.round(((item.sell_price - item.promo_price) / item.sell_price) * 100) + '%'"></span>
                                </div>
                            </div>

                            {{-- 4. Barcode Promo (2 Kolom) --}}
                            <div class="sm:col-span-2">
                                <input type="text" 
                                       :name="'items[' + index + '][promo_barcode]'" 
                                       x-model="item.promo_barcode" 
                                       placeholder="Auto generate" 
                                       class="form-input w-full py-1 px-2 text-xs font-mono border-slate-300 focus:border-brand-500 focus:ring-brand-500 rounded-lg placeholder-slate-400">
                            </div>

                            {{-- 5. Tombol Hapus (1 Kolom) --}}
                            <div class="sm:col-span-1 text-center">
                                <button type="button" 
                                        @click="removeItem(index)" 
                                        class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors disabled:opacity-50 inline-flex items-center justify-center" 
                                        :disabled="items.length <= 1" 
                                        title="Hapus baris">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Tombol Tambah Bawah yang Ringkas --}}
            <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
                <button type="button" @click="addItem()" class="btn btn-sm btn-outline text-brand-600 border-dashed border-brand-300 hover:bg-brand-50 inline-flex items-center gap-1 font-semibold text-xs py-1 px-3 rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah Baris</span>
                </button>
                <span class="text-[11px] text-slate-400">Barcode promo otomatis digenerate jika dikosongkan.</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mb-8">
            <a href="{{ route('promo.index') }}" class="btn btn-outline">Batal</a>
            <button type="submit" class="btn btn-primary px-6 shadow-sm" :disabled="items.length === 0">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script type="application/json" id="products-catalog-json">
    {!! json_encode($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sell_price' => (float)$p->sell_price, 'barcode' => $p->barcode])) !!}
</script>

<script type="application/json" id="promo-old-items-json">
    {!! json_encode(old('items', [])) !!}
</script>

<script type="application/json" id="promo-initial-items-json">
    {!! json_encode($promotion->items->map(fn($item) => [
        'product_id' => $item->product_id,
        'sell_price' => (float)($item->product->sell_price ?? 0),
        'promo_price' => (float)$item->promo_price,
        'promo_barcode' => $item->promo_barcode ?? '',
    ])) !!}
</script>

<script>
function promoEditApp() {
    var rawProducts = [];
    var oldItems = [];
    var initialItems = [];
    try {
        var elProd = document.getElementById('products-catalog-json');
        if (elProd) rawProducts = JSON.parse(elProd.textContent);
        var elOld = document.getElementById('promo-old-items-json');
        if (elOld) oldItems = JSON.parse(elOld.textContent);
        var elItems = document.getElementById('promo-initial-items-json');
        if (elItems) initialItems = JSON.parse(elItems.textContent);
    } catch (e) {
        rawProducts = [];
        oldItems = [];
        initialItems = [];
    }

    var effectiveItems = [];
    if (Array.isArray(oldItems) && oldItems.length > 0) {
        effectiveItems = oldItems.map(function(item) {
            var prod = rawProducts.find(function(p) { return String(p.id) === String(item.product_id); });
            return {
                product_id: item.product_id || '',
                sell_price: prod ? prod.sell_price : 0,
                promo_price: item.promo_price || '',
                promo_barcode: item.promo_barcode || ''
            };
        });
    } else if (Array.isArray(initialItems) && initialItems.length > 0) {
        effectiveItems = initialItems;
    } else {
        effectiveItems = [{ product_id: '', sell_price: 0, promo_price: '', promo_barcode: '' }];
    }

    return {
        products: rawProducts,
        items: effectiveItems,
        addItem: function() {
            this.items.push({ product_id: '', sell_price: 0, promo_price: '', promo_barcode: '' });
        },
        removeItem: function(idx) {
            if (this.items.length > 1) {
                this.items.splice(idx, 1);
            }
        },
        onProductChange: function(idx) {
            var item = this.items[idx];
            var prod = this.getProduct(item.product_id);
            if (prod) {
                item.sell_price = prod.sell_price || 0;
                if (!item.promo_price || item.promo_price === 0) {
                    item.promo_price = Math.round(prod.sell_price * 0.9);
                }
            } else {
                item.sell_price = 0;
            }
        },
        getProduct: function(productId) {
            var id = String(productId);
            return this.products.find(function(p) { return String(p.id) === id; });
        },
        getProductBarcode: function(productId) {
            var p = this.getProduct(productId);
            return p ? (p.barcode || '') : '';
        },
        formatNumber: function(val) {
            var num = Number(val) || 0;
            return num.toLocaleString('id-ID');
        }
    };
}
</script>
@endsection
