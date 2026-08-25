@extends('layouts.app')
@section('title', 'Modul Kasir')

@section('content')
{{-- Pindaian didengarkan dari DOKUMEN, bukan dari kolom cari. Alat pindai barcode adalah
     papan ketik: ia menembakkan karakter ke elemen mana pun yang sedang terfokus, dan kasir
     terus-menerus menyentuh hal lain (jumlah, nominal bayar, tombol). Penangkapnya ada di
     public/vendor/jpos-pemindai.js beserta seluruh alasannya. --}}
<div x-data="kasirApp()" x-init="init()"
     @jpos:barcode-dipindai.document="pindai($event.detail.kode)"
     class="flex flex-col lg:flex-row gap-4 h-full">

    {{-- LEFT: Product grid --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between mb-3 gap-2">
            <h2 class="font-semibold text-lg hidden sm:block">Modul Kasir</h2>
            <div class="flex items-center gap-2 ml-auto">
                @if($allowToggle)
                <div class="flex rounded-lg border overflow-hidden text-sm">
                    <button type="button" @click="setViewMode('gambar')" :class="viewMode === 'gambar' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5">🖼️ Gambar</button>
                    <button type="button" @click="setViewMode('list')" :class="viewMode === 'list' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 border-l">📋 List</button>
                </div>
                @endif
                <a href="{{ route('kasir.tahan') }}" class="btn btn-outline text-sky-700 border-sky-300">
                    ⏸ Tertahan @if($jumlahTertahan > 0)<span class="ml-1 px-1.5 rounded-full bg-sky-600 text-white text-xs">{{ $jumlahTertahan }}</span>@endif
                </a>
                <a href="{{ route('kasir.waiting-list') }}" class="btn btn-outline text-amber-700 border-amber-300">
                    📋 Pesanan / DP
                </a>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 mb-4">
            <div class="relative flex-1 min-w-[200px]">
                <input type="text" x-model="search" x-ref="searchInput" @keydown.enter.prevent="cariAtauPindai()"
                    placeholder="Cari produk / scan barcode..." class="form-input pl-9">
                <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/></svg>
            </div>
            {{-- Hasil pindaian SELALU dikabarkan, berhasil maupun gagal. Kegagalan yang diam
                 membuat kasir mengira alat pindainya rusak, dan tidak ada satu pun cara
                 baginya untuk tahu bahwa yang sebenarnya terjadi adalah kodenya belum
                 terdaftar. --}}
            <div x-show="scanPesan" x-cloak x-transition.opacity
                 class="w-full text-sm rounded-lg px-3 py-2 order-last"
                 :class="scanGagal ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700'"
                 x-text="scanPesan"></div>
            <select x-model="categoryId" class="form-select w-40">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="viewMode === 'gambar'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-4">
            <template x-for="p in filteredProducts" :key="p.id">
                <button @click="onProductClick(p)" :disabled="p.type !== 'jasa' && p.stock <= 0"
                    class="card p-2 text-left hover:shadow-md hover:-translate-y-0.5 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <div class="aspect-square rounded-lg overflow-hidden bg-slate-100 mb-2">
                        <img :src="p.image_url" class="w-full h-full object-cover">
                    </div>
                    <div class="text-sm font-medium truncate" x-text="p.name"></div>
                    <div class="text-xs" :class="p.type === 'jasa' ? 'text-blue-600' : 'text-slate-400'" x-text="p.type === 'jasa' ? '🛠️ Jasa' : ('Stok: ' + formatQty(p.stock) + ' ' + (p.unit || 'pcs'))"></div>
                    <template x-if="p.rack_location">
                        <div class="text-[10px] text-slate-500 truncate" x-text="'📍 ' + p.rack_location"></div>
                    </template>
                    <div class="text-sm font-semibold text-blue-600 mt-1" x-text="'Rp ' + formatNumber(p.sell_price)"></div>
                    <template x-if="p.additional_units && p.additional_units.length > 0">
                        <div class="text-[10px] text-amber-600" x-text="'atau per ' + p.additional_units.map(u => u.unit_name).join('/')"></div>
                    </template>
                    <template x-if="p.wholesale_price">
                        <div class="text-[10px] text-emerald-600" x-text="'Grosir min ' + formatNumber(p.wholesale_min_qty)"></div>
                    </template>
                </button>
            </template>
            <template x-if="filteredProducts.length === 0">
                <div class="col-span-full text-center text-slate-400 py-16">Produk tidak ditemukan.</div>
            </template>
        </div>

        <div x-show="viewMode === 'list'" class="card overflow-hidden mb-4">
            <table class="data-table w-full">
                <thead><tr><th>Kode</th><th>Nama Produk</th><th class="text-right">Harga</th><th class="text-right">Stok</th><th></th></tr></thead>
                <tbody>
                    <template x-for="p in filteredProducts" :key="p.id">
                        <tr @click="(p.type === 'jasa' || p.stock > 0) && onProductClick(p)" :class="(p.type !== 'jasa' && p.stock <= 0) ? 'opacity-40' : 'cursor-pointer hover:bg-slate-50'">
                            <td class="text-slate-500 text-xs" x-text="p.sku || '-'"></td>
                            <td>
                                <div class="font-medium" x-text="p.name"></div>
                                <template x-if="p.rack_location">
                                    <div class="text-[10px] text-slate-500" x-text="'📍 ' + p.rack_location"></div>
                                </template>
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
                                <span x-show="p.type !== 'jasa'" x-text="formatQty(p.stock)"></span>
                            </td>
                            <td class="text-right">
                                <button type="button" @click.stop="onProductClick(p)" :disabled="p.type !== 'jasa' && p.stock <= 0"
                                    class="w-7 h-7 rounded bg-brand-500 text-white text-sm font-bold hover:bg-brand-600 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="filteredProducts.length === 0">
                        <tr><td colspan="5" class="text-center text-slate-400 py-8">Produk tidak ditemukan.</td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- RIGHT: Cart --}}
    <div class="w-full lg:w-96 shrink-0 flex flex-col card p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold">Keranjang</h3>
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

        <select x-model="customerId" class="form-select mb-3">
            <option value="">Pelanggan Umum</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>

        <div class="flex-1 overflow-y-auto space-y-2 min-h-[120px] max-h-[40vh]">
            {{-- Jenis satuan ikut jadi kunci: kotak qty membaca opsi desimalnya SEKALI saat
                 dipasang, jadi baris yang berubah dari satuan hitung ke satuan timbangan
                 harus benar-benar menghasilkan elemen baru, bukan elemen lama yang dipakai
                 ulang dengan aturan lama. --}}
            <template x-for="(item, idx) in cart" :key="item.product_id + '-' + item.unit_type + '-' + (item.is_weighable ? 'p' : 'b')">
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
                <p class="text-sm text-slate-400 text-center py-6">Keranjang masih kosong.<br>Klik produk untuk menambahkan.</p>
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
            <div class="flex justify-between font-semibold text-base pt-1"><span>Total</span><span x-text="'Rp ' + formatNumber(total())"></span></div>
        </div>

        <div class="border-t mt-3 pt-3 space-y-2">
            <label class="flex items-center gap-2 text-sm bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 cursor-pointer">
                <input type="checkbox" x-model="isWaitingList">
                <span>Jadikan <strong>Pesanan / Waiting List</strong></span>
            </label>

            {{-- Niat kasir DIPILIH, bukan ditebak dari nominal.
                 Keduanya menghasilkan pesanan `waiting` yang sama; yang berbeda cuma
                 nominal DP-nya (0 atau sekian). Radio ini ada supaya kasir tidak perlu
                 tahu bahwa "tanpa DP" berarti mengetik angka 0 - itu aturan tersembunyi. --}}
            <template x-if="isWaitingList">
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 text-sm border rounded-lg px-3 py-2 cursor-pointer"
                           :class="modePesanan === 'tanpa_dp' ? 'border-amber-400 bg-amber-50 font-medium' : 'border-slate-200'">
                        <input type="radio" value="tanpa_dp" x-model="modePesanan" @change="paidAmount = 0">
                        <span>Tanpa DP</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm border rounded-lg px-3 py-2 cursor-pointer"
                           :class="modePesanan === 'dp' ? 'border-amber-400 bg-amber-50 font-medium' : 'border-slate-200'">
                        <input type="radio" value="dp" x-model="modePesanan">
                        <span>Bayar DP dulu</span>
                    </label>
                </div>
            </template>

            <template x-if="isWaitingList">
                <div>
                    <label class="form-label">Tanggal Pengambilan / Jatuh Tempo (opsional)</label>
                    <input type="date" x-model="dueDate" class="form-input">
                </div>
            </template>

            <select x-model="paymentMethod" class="form-select">
                <option value="cash">Tunai</option>
                <option value="debit">Kartu Debit</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer</option>
            </select>
            {{-- Disembunyikan di mode Tanpa DP: tidak ada nominal yang perlu diisi, dan kolom
                 kosong yang tetap tampil hanya membuat kasir ragu apakah ada yang terlewat. --}}
            <template x-if="!isWaitingList || modePesanan === 'dp'">
                <input type="text" data-jpos-number x-number="paidAmount" :placeholder="isWaitingList ? 'Jumlah DP' : 'Jumlah bayar'" class="form-input">
            </template>

            <template x-if="isWaitingList && modePesanan === 'dp'">
                <div class="grid grid-cols-4 gap-1">
                    <template x-for="pct in [25, 50, 75, 90]">
                        <button @click="paidAmount = Math.round(total() * pct / 100)" class="text-xs py-1.5 rounded bg-amber-100 hover:bg-amber-200" x-text="pct + '%'"></button>
                    </template>
                </div>
            </template>
            <template x-if="!isWaitingList">
                <div class="grid grid-cols-4 gap-1">
                    <template x-for="amt in quickAmounts()">
                        <button @click="paidAmount = amt" class="text-xs py-1.5 rounded bg-slate-100 hover:bg-slate-200" x-text="formatShort(amt)"></button>
                    </template>
                </div>
            </template>

            <template x-if="!isWaitingList">
                <div class="flex justify-between text-sm font-medium" :class="change() < 0 ? 'text-red-600' : 'text-green-600'">
                    <span>Kembalian</span><span x-text="'Rp ' + formatNumber(Math.max(change(),0))"></span>
                </div>
            </template>
            <template x-if="isWaitingList">
                <div class="flex justify-between text-sm font-medium text-amber-700">
                    <span>Sisa yang harus dilunasi</span><span x-text="'Rp ' + formatNumber(Math.max(total() - (paidAmount||0), 0))"></span>
                </div>
            </template>

            {{-- Jalur TERTAHAN, terpisah dari Pesanan/DP di atas. Bedanya bukan mesinnya -
                 keduanya sama-sama menahan keranjang beserta stoknya - melainkan niatnya:
                 ini untuk pelanggan yang masih berdiri di depan kasir dan mau ambil barang
                 lain, supaya antrean di belakangnya bisa dilayani lebih dulu. --}}
            <template x-if="!isWaitingList">
                <button @click="tahanTransaksi()" :disabled="cart.length === 0 || processing"
                    class="w-full btn justify-center py-2 border border-sky-300 text-sky-700 bg-sky-50 hover:bg-sky-100 disabled:opacity-50">
                    ⏸ Tahan Transaksi &mdash; layani pelanggan berikutnya
                </button>
            </template>

            <button @click="checkout()" :disabled="cart.length === 0 || processing"
                class="w-full btn justify-center py-3 text-base disabled:opacity-50"
                :class="isWaitingList ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'btn-primary'">
                <span x-show="!processing && !isWaitingList">Bayar &amp; Cetak Struk</span>
                <span x-show="!processing && isWaitingList" x-text="modePesanan === 'dp' ? 'Simpan Sebagai Pesanan (DP)' : 'Simpan Sebagai Pesanan (Tanpa DP)'"></span>
                <span x-show="processing">Memproses...</span>
            </button>
            <p class="text-xs text-red-500" x-text="errorMsg"></p>
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
</div>

@push('scripts')
<script>
function kasirApp() {
    return {
        allProducts: @json($productsForCart),
        search: '',
        // Umpan balik hasil pindaian. Ada karena versi sebelumnya sama sekali tidak
        // menampilkan apa pun saat kode tidak ketemu - kasir memindai berulang kali tanpa
        // petunjuk, lalu menyimpulkan alat pindainya rusak.
        scanPesan: '',
        scanGagal: false,
        scanTimer: null,
        categoryId: '',
        cart: [],
        customerId: '',
        discount: 0,
        paymentMethod: 'cash',
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

            this.pulihkanDraf();
            this.pulihkanKeranjangTertahan();

            // Disimpan setiap kali isi transaksi berubah. $watch di Alpine 3 memantau
            // sampai ke dalam isi array, jadi perubahan qty per baris ikut tersimpan.
            ['cart', 'customerId', 'discount', 'paymentMethod', 'paidAmount', 'isWaitingList', 'dueDate']
                .forEach(k => this.$watch(k, () => this.simpanDraf()));

            this.focusSearch();
        },

        simpanDraf() {
            // Ditunda sesaat supaya mengetik qty tidak menulis ke penyimpanan tiap ketukan.
            clearTimeout(this._timerDraf);
            this._timerDraf = setTimeout(() => {
                try {
                    if (this.cart.length === 0) { this.hapusDraf(); return; }

                    localStorage.setItem(this.kunciDraf, JSON.stringify({
                        versi: 1,
                        disimpanPada: Date.now(),
                        cart: this.cart,
                        customerId: this.customerId,
                        discount: this.discount,
                        paymentMethod: this.paymentMethod,
                        paidAmount: this.paidAmount,
                        isWaitingList: this.isWaitingList,
                        dueDate: this.dueDate,
                    }));
                } catch (e) {
                    // Penyimpanan penuh atau diblokir - transaksi tetap bisa dilanjutkan,
                    // hanya tidak tersimpan sebagai draf.
                }
            }, 250);
        },

        hapusDraf() {
            clearTimeout(this._timerDraf);
            try { localStorage.removeItem(this.kunciDraf); } catch (e) { /* diabaikan */ }
        },

        kosongkanKeranjang() {
            this.cart = [];
            this.discount = 0;
            this.paidAmount = 0;
            this.customerId = '';
            this.isWaitingList = false;
            this.dueDate = '';
            this.catatanDraf = [];
            this.drafDipulihkan = false;
            this.errorMsg = '';
            this.hapusDraf();
            this.focusSearch();
        },

        /**
         * Memulihkan draf DAN mencocokkannya ulang dengan katalog terbaru.
         *
         * Ini bagian yang penting: kasir pindah halaman justru untuk mengubah produk, jadi
         * saat kembali harga, stok, atau satuannya bisa sudah berbeda - bahkan produknya
         * bisa sudah dihapus. Memulihkan mentah-mentah berarti menjual dengan harga basi.
         */
        pulihkanDraf() {
            let draf = null;
            try {
                const mentah = localStorage.getItem(this.kunciDraf);
                if (!mentah) return;
                draf = JSON.parse(mentah);
            } catch (e) { this.hapusDraf(); return; }

            if (!draf || !Array.isArray(draf.cart) || draf.cart.length === 0) { this.hapusDraf(); return; }

            const umurJam = (Date.now() - (draf.disimpanPada || 0)) / 3600000;
            if (!isFinite(umurJam) || umurJam > this.UMUR_DRAF_JAM) {
                // Draf sisa shift kemarin lebih berbahaya daripada berguna.
                this.hapusDraf();
                return;
            }

            const catatan = [];
            const pulih = [];

            draf.cart.forEach(baris => {
                const produk = this.allProducts.find(p => p.id === baris.product_id);

                if (!produk) {
                    catatan.push(`"${baris.name}" dikeluarkan - produk sudah tidak ada atau dinonaktifkan.`);
                    return;
                }

                // Satuan besar bisa sudah dihapus dari produk saat kasir mengubahnya.
                let harga = produk.sell_price, konversi = 1, labelSatuan = null;
                let hargaGrosir = produk.wholesale_price, minGrosir = produk.wholesale_min_qty;
                let bolehPecahan = !!produk.is_weighable;

                if (baris.unit_type && baris.unit_type !== 'base') {
                    const idSatuan = parseInt(String(baris.unit_type).substring(5));
                    const pu = (produk.additional_units || []).find(u => u.id === idSatuan);
                    if (!pu) {
                        catatan.push(`"${produk.name}" dikeluarkan - satuan ${baris.unit_label || ''} sudah dihapus.`);
                        return;
                    }
                    harga = pu.price; konversi = pu.conversion; labelSatuan = pu.unit_name;
                    hargaGrosir = pu.wholesale_price; minGrosir = pu.wholesale_min_qty;
                    bolehPecahan = !!pu.is_weighable;
                }

                if (Number(harga) !== Number(baris.price)) {
                    catatan.push(`Harga "${produk.name}" berubah dari Rp ${this.formatNumber(baris.price)} menjadi Rp ${this.formatNumber(harga)}.`);
                }

                // parseFloat, bukan parseInt: draf berisi 0,4 Kg akan dipulihkan sebagai 1 Kg
                // kalau dipotong ke bilangan bulat - kasir menekan Bayar dan menagih lebih
                // dari yang ditimbang.
                const minQty = bolehPecahan ? 0.001 : 1;
                const qtyDraf = parseFloat(baris.qty);

                pulih.push({
                    product_id: produk.id, name: produk.name, image_url: produk.image_url, type: produk.type,
                    unit_type: baris.unit_type || 'base', unit_label: labelSatuan, conversion: konversi,
                    is_weighable: bolehPecahan,
                    qty: Math.max(minQty, isFinite(qtyDraf) ? qtyDraf : minQty),
                    price: harga, wholesale_price: hargaGrosir, wholesale_min_qty: minGrosir,
                });
            });

            this.cart = pulih;

            // Stok bisa sudah berkurang; potong qty yang tidak lagi muat.
            this.cart.forEach((baris, idx) => {
                if (baris.type === 'jasa') return;
                const maks = this.maxQtyForLine(idx);
                if (maks < this.minQty(baris)) {
                    catatan.push(`"${baris.name}" dikeluarkan - stoknya sudah habis.`);
                    baris._buang = true;
                } else if (baris.qty > maks) {
                    catatan.push(`Qty "${baris.name}" disesuaikan dari ${this.formatQty(baris.qty)} menjadi ${this.formatQty(maks)} karena stok berkurang.`);
                    baris.qty = maks;
                }
            });
            this.cart = this.cart.filter(b => !b._buang);

            if (this.cart.length === 0) { this.hapusDraf(); return; }

            this.customerId = draf.customerId || '';
            this.discount = Number(draf.discount) || 0;
            this.paymentMethod = draf.paymentMethod || 'cash';
            this.paidAmount = Number(draf.paidAmount) || 0;
            this.isWaitingList = !!draf.isWaitingList;
            this.dueDate = draf.dueDate || '';

            this.catatanDraf = catatan;

            // Kalau tidak ada yang berubah dan kasir baru saja pindah halaman, pulihkan
            // diam-diam - memberi notifikasi setiap kali justru mengganggu. Notifikasi
            // hanya muncul saat ada yang perlu diketahui, atau saat drafnya sudah lama.
            const menitBerlalu = (Date.now() - (draf.disimpanPada || 0)) / 60000;
            this.drafDipulihkan = catatan.length > 0 || menitBerlalu > this.JEDA_PEMULIHAN_SENYAP_MENIT;
        },

        setViewMode(mode) {
            this.viewMode = mode;
            if (this.allowToggle) localStorage.setItem('kasir_view_mode', mode);
        },

        focusSearch() {
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        get filteredProducts() {
            return this.allProducts.filter(p => {
                const matchSearch = !this.search || p.name.toLowerCase().includes(this.search.toLowerCase());
                const matchCat = !this.categoryId || p.category_id == this.categoryId;
                return matchSearch && matchCat;
            });
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

        productStock(productId) {
            const p = this.allProducts.find(x => x.id === productId);
            return p ? p.stock : 0;
        },

        // Total satuan dasar yang sudah dipakai baris lain di keranjang untuk produk yang sama
        // (mis. sudah ada baris "3 dus", ini dihitung 3 * konversi). excludeIdx dipakai supaya
        // baris yang sedang diubah tidak menghitung dirinya sendiri.
        usedBaseUnits(productId, excludeIdx = -1) {
            return this.cart.reduce((sum, item, idx) => {
                if (idx === excludeIdx || item.product_id !== productId) return sum;
                return sum + item.qty * item.conversion;
            }, 0);
        },

        maxQtyForLine(idx) {
            const item = this.cart[idx];
            if (item.type === 'jasa') return Infinity;
            const remainingBaseUnits = Math.max(this.productStock(item.product_id) - this.usedBaseUnits(item.product_id, idx), 0);
            const maks = remainingBaseUnits / item.conversion;
            // Satuan timbangan boleh menyisakan pecahan (0,4 Kg terakhir tetap bisa dijual);
            // satuan hitung tidak - setengah Dus tidak ada wujudnya.
            return item.is_weighable ? Math.floor(maks * 1000) / 1000 : Math.floor(maks);
        },

        addToCartWithUnit(p, unitType) {
            let conversion = 1, unitLabel = null, price = p.sell_price;
            let wholesalePrice = p.wholesale_price, wholesaleMinQty = p.wholesale_min_qty;
            /* Satuan dasar mengambil izin pecahan dari tabel satuan ("Kg" memang timbangan
               di mana pun dipakai); satuan tambahan menentukannya per produk. */
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
                    const remaining = Math.max(p.stock - this.usedBaseUnits(p.id), 0);
                    if (remaining / conversion < 1) {
                        this.errorMsg = `Stok ${p.name} tidak cukup.`;
                        this.focusSearch();
                        return;
                    }
                }
                this.errorMsg = '';
                /* Baris baru selalu dimulai dari 1 satuan, termasuk untuk timbangan - kasir
                   menimbang dulu, baru mengetik angkanya. */
                this.cart.push({
                    product_id: p.id, name: p.name, image_url: p.image_url, type: p.type,
                    unit_type: unitType, unit_label: unitLabel, conversion: conversion,
                    is_weighable: bolehPecahan,
                    qty: 1,
                    price: price, wholesale_price: wholesalePrice, wholesale_min_qty: wholesaleMinQty,
                });
            }
            this.focusSearch();
        },

        linePrice(item) {
            if (this.isWholesaleActive(item)) return item.wholesale_price;
            return item.price;
        },
        isWholesaleActive(item) {
            return item.wholesale_price != null && item.wholesale_min_qty != null && item.qty >= item.wholesale_min_qty;
        },

        /* Langkah tombol +/- mengikuti jenis satuannya: menaikkan berat per 1 Kg terlalu
           kasar untuk timbangan, sedangkan menaikkan Pcs per 0,1 tidak ada artinya. */
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
            // Satuan hitung dibulatkan di sini juga: server menolak pecahan untuk satuan
            // ini, dan lebih baik kasir tahu sekarang daripada saat menekan tombol Bayar.
            if (!item.is_weighable) qty = Math.round(qty);
            qty = this.bulatQty(qty);
            if (qty < this.minQty(item)) qty = this.minQty(item);
            if (qty > max) qty = max;
            item.qty = qty;
        },
        removeItem(idx) { this.cart.splice(idx, 1); },

        subtotal() { return this.cart.reduce((s, i) => s + this.linePrice(i) * i.qty, 0); },
        taxAmount() { return Math.round((this.subtotal() - this.discount) * this.taxPercent / 100); },
        total() { return Math.max(this.subtotal() - this.discount + this.taxAmount(), 0); },
        change() { return (this.paidAmount || 0) - this.total(); },

        quickAmounts() {
            const t = this.total();
            if (t <= 0) return [10000, 20000, 50000, 100000];
            const rounded = Math.ceil(t / 10000) * 10000;
            return [t, rounded, rounded + 10000, rounded + 50000];
        },

        // Delegasi ke helper bersama di public/vendor/jpos-number.js supaya format angka
        // di seluruh aplikasi berasal dari satu sumber.
        formatNumber(n) { return window.JposNumber ? window.JposNumber.format(n) : Math.round(n || 0).toLocaleString('id-ID'); },
        // Kuantitas & stok boleh pecahan sejak barang timbangan bisa dijual per Kg atau
        // per Gram. formatNumber() membulatkan ke bilangan bulat, jadi memakainya untuk
        // qty akan menampilkan sisa stok 9,6 Kg sebagai "10".
        formatQty(n) { return window.JposNumber ? window.JposNumber.formatQty(n) : String(n ?? 0); },
        formatShort(n) {
            // Pemisah desimal di sini WAJIB koma. Versi lama menghasilkan "1.5jt", dan di
            // locale Indonesia titik berarti ribuan - jadi terbaca "satu setengah juta"
            // sebagai "1500 juta".
            if (n >= 1000000) return (n/1000000).toFixed(1).replace('.0','').replace('.', ',') + 'jt';
            if (n >= 1000) return this.formatNumber(Math.round(n/1000)) + 'rb';
            return this.formatNumber(n);
        },

        // ---------------------------------------------------------------
        // Pindai barcode
        // ---------------------------------------------------------------

        /**
         * Dipanggil oleh penangkap alat pindai (public/vendor/jpos-pemindai.js), yang
         * mendengarkan seluruh halaman - bukan cuma kolom cari. Alat pindai menembakkan
         * karakter ke elemen mana pun yang sedang terfokus, dan sebelum ini hasil pindaian
         * bisa mendarat di kolom nominal bayar.
         */
        async pindai(kode) {
            if (!kode) return;

            this.scanPesan = '';
            this.scanGagal = false;

            try {
                const res = await fetch(`{{ route('kasir.scan') }}?barcode=${encodeURIComponent(kode)}`);

                if (!res.ok) {
                    this.beriTahuPindai('Gagal menghubungi aplikasi (kode ' + res.status + '). Coba lagi.', true);
                    return;
                }

                const data = await res.json();

                if (!data.found) {
                    // JANGAN diam. Versi sebelumnya tidak menampilkan apa pun saat kode tidak
                    // ketemu, jadi kasir memindai berulang kali tanpa satu pun petunjuk lalu
                    // menyimpulkan "pindai tidak bisa". Kodenya sengaja ditinggal di kolom
                    // cari supaya bisa langsung dicari lewat nama.
                    this.search = kode;
                    this.beriTahuPindai(data.message || ('Kode ' + kode + ' tidak terdaftar.'), true);
                    return;
                }

                this.search = '';
                this.tambahHasilPindai(data);
            } catch (e) {
                this.beriTahuPindai('Pindaian gagal diproses. Periksa sambungan lalu ulangi.', true);
            }
        },

        /**
         * Masukkan hasil pindaian ke keranjang.
         *
         * Kalau yang dipindai adalah label SATUAN, satuannya langsung dipakai tanpa bertanya -
         * itulah gunanya barcode per satuan. Memunculkan pemilih satuan di sini justru
         * mengembalikan langkah yang ingin dihilangkan, dan pada jam ramai langkah itulah
         * yang paling sering terlewat.
         */
        tambahHasilPindai(data) {
            const p = data.product;

            if (data.unit_id) {
                const satuan = (p.additional_units || []).find(u => u.id === data.unit_id);

                if (satuan) {
                    this.addToCartWithUnit(p, String(data.unit_id));
                    this.beriTahuPindai(p.name + ' (' + satuan.unit_name + ') ditambahkan.', false);
                    return;
                }
            }

            // Produk bersatuan banyak yang dipindai lewat kode PRODUK tetap perlu ditanyakan:
            // kodenya tidak memberi tahu satuan mana yang dimaksud, dan menebaknya berarti
            // menebak harga.
            this.onProductClick(p);
            this.beriTahuPindai(p.name + ' ditemukan.', false);
        },

        beriTahuPindai(pesan, gagal) {
            this.scanPesan = pesan;
            this.scanGagal = gagal;

            if (this.scanTimer) clearTimeout(this.scanTimer);

            // Pesan berhasil hilang sendiri; pesan gagal bertahan lebih lama karena kasir
            // perlu sempat membacanya di tengah antrean.
            this.scanTimer = setTimeout(() => { this.scanPesan = ''; }, gagal ? 6000 : 2500);
        },

        /**
         * Enter di kolom cari.
         *
         * Kalau isinya cocok persis dengan sebuah kode, diperlakukan sebagai pindaian manual -
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

            // Tidak ada yang cocok dengan namanya - kemungkinan besar ini memang kode.
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
            const diambil = @json($keranjangDiambil ?? null);

            if (!Array.isArray(diambil) || diambil.length === 0) return;

            this.cart = [];

            diambil.forEach(baris => {
                const p = this.allProducts.find(x => x.id === baris.product_id);
                if (!p) return;   // produknya sudah dihapus sejak ditahan

                // Satuan dicocokkan lewat konversinya, bukan lewat namanya: nama satuan bisa
                // diubah di Master Data, angka konversinya yang menentukan harga.
                const satuan = (p.additional_units || []).find(u => u.conversion === baris.unit_conversion);

                this.addToCartWithUnit(p, satuan ? String(satuan.id) : 'base');
                const idx = this.cart.length - 1;
                if (idx >= 0) this.cart[idx].qty = baris.qty;
            });
        },

        async tahanTransaksi() {
            if (this.cart.length === 0 || this.processing) return;

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
                        payment_method: this.paymentMethod,
                        is_parked: true,
                    }),
                });

                if (!res.ok) {
                    const galat = await res.json().catch(() => ({}));
                    this.errorMsg = galat.message || 'Gagal menahan transaksi.';
                    return;
                }

                window.location.href = '{{ route('kasir.tahan') }}';
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
                window.open(data.receipt_url, '_blank');
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
    }
}
</script>
@endpush
@endsection
