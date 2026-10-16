@extends('layouts.app')
@section('title', 'Edit Promo: ' . $promotion->name)

@section('content')
<div class="max-w-4xl mx-auto" x-data="promoEditApp()">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Promo</h1>
            <p class="text-xs text-slate-500 mt-0.5">{{ $promotion->name }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('promo.cetak-barcode', $promotion) }}" target="_blank" class="btn btn-outline text-blue-700 border-blue-200 hover:bg-blue-50">
                <x-icon.barcode class="w-4 h-4 inline mr-1" /> Cetak Barcode Rak
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
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">1. Informasi Promo</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Promo <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $promotion->name) }}" class="form-input w-full" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai (Opsional)</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $promotion->start_date?->format('Y-m-d')) }}" class="form-input w-full">
                    <p class="text-[11px] text-slate-400 mt-0.5">Kosongkan jika promo langsung berlaku hari ini</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai (Opsional)</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $promotion->end_date?->format('Y-m-d')) }}" class="form-input w-full">
                    <p class="text-[11px] text-slate-400 mt-0.5">Kosongkan jika promo berlaku terus tanpa batas akhir</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan / Catatan Promo (Opsional)</label>
                    <textarea name="description" rows="2" class="form-input w-full">{{ old('description', $promotion->description) }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $promotion->is_active ? '1' : '0') === '1' ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-xs font-semibold text-slate-700">Promo Aktif</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Kartu Produk Promo --}}
        <div class="card p-5 mb-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">2. Produk dalam Promo</h2>
                    <p class="text-[11px] text-slate-500">Pilih produk dan tentukan harga jual promo khusus</p>
                </div>
                <button type="button" @click="addItem()" class="btn btn-sm btn-outline text-brand-600 border-brand-200 hover:bg-brand-50">
                    + Tambah Produk
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            {{-- Pilih Produk --}}
                            <div class="sm:col-span-5">
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                    Produk #<span x-text="index + 1"></span> <span class="text-red-500">*</span>
                                </label>
                                <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" @change="onProductChange(index)" class="form-select w-full text-xs" required>
                                    <option value="">-- Pilih Produk --</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" :selected="String(item.product_id) === '{{ $p->id }}'">{{ $p->name }} (Normal: Rp {{ number_format((float) $p->sell_price, 0, ',', '.') }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Harga Normal (Readonly info) --}}
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Harga Normal</label>
                                <div class="text-xs font-medium text-slate-600 py-1.5 px-2 bg-slate-100 rounded border border-slate-200 truncate">
                                    Rp <span x-text="formatNumber(item.sell_price || 0)"></span>
                                </div>
                            </div>

                            {{-- Harga Promo --}}
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                    Harga Promo <span class="text-red-500">*</span>
                                </label>
                                <input type="text" data-jpos-number data-number-min="0" :name="'items[' + index + '][promo_price]'" x-number="item.promo_price" placeholder="0" class="form-input w-full text-xs font-semibold text-green-700" required>
                            </div>

                            {{-- Barcode Khusus (Opsional) --}}
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">Barcode Promo</label>
                                <input type="text" :name="'items[' + index + '][promo_barcode]'" x-model="item.promo_barcode" placeholder="Otomatis" class="form-input w-full text-xs font-mono">
                            </div>

                            {{-- Tombol Hapus --}}
                            <div class="sm:col-span-1 text-right">
                                <button type="button" @click="removeItem(index)" class="btn btn-sm btn-outline text-red-600 border-red-200 hover:bg-red-50 w-full" :disabled="items.length <= 1" title="Hapus baris">
                                    &times;
                                </button>
                            </div>
                        </div>

                        {{-- Hemat Info & Barcode Notice --}}
                        <div class="flex items-center justify-between text-[11px] mt-2 pt-2 border-t border-slate-200/60 text-slate-500">
                            <div>
                                <span class="font-medium text-slate-600">Hemat:</span>
                                <span class="font-bold text-green-600">
                                    Rp <span x-text="formatNumber(Math.max(0, (item.sell_price || 0) - (item.promo_price || 0)))"></span>
                                </span>
                                <span class="text-slate-400" x-show="item.sell_price > 0 && item.promo_price > 0">
                                    (<span x-text="Math.round(((item.sell_price - item.promo_price) / item.sell_price) * 100)"></span>% off)
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                Barcode: <span x-text="item.promo_barcode || '(dibuat otomatis)'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mb-8">
            <a href="{{ route('promo.index') }}" class="btn btn-outline">Batal</a>
            <button type="submit" class="btn btn-primary px-6" :disabled="items.length === 0">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script type="application/json" id="products-catalog-json">
    {!! json_encode($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sell_price' => (float)$p->sell_price, 'barcode' => $p->barcode])) !!}
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
    var initialItems = [];
    try {
        var elProd = document.getElementById('products-catalog-json');
        if (elProd) rawProducts = JSON.parse(elProd.textContent);
        var elItems = document.getElementById('promo-initial-items-json');
        if (elItems) initialItems = JSON.parse(elItems.textContent);
    } catch (e) {
        rawProducts = [];
        initialItems = [];
    }

    if (!initialItems || initialItems.length === 0) {
        initialItems = [{ product_id: '', sell_price: 0, promo_price: '', promo_barcode: '' }];
    }

    return {
        products: rawProducts,
        items: initialItems,
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
            var prod = this.products.find(function(p) { return p.id == item.product_id; });
            if (prod) {
                item.sell_price = prod.sell_price || 0;
                if (!item.promo_price) {
                    item.promo_price = Math.round(prod.sell_price * 0.9);
                }
            } else {
                item.sell_price = 0;
            }
        },
        formatNumber: function(val) {
            var num = Number(val) || 0;
            return num.toLocaleString('id-ID');
        }
    };
}
</script>
@endsection
