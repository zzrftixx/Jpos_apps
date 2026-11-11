<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pesan Menu - {{ $table->name }} | {{ $storeProfile['name'] ?? 'Restoran' }}</title>

    {{-- Tailwind & Alpine.js dari aset lokal aplikasi --}}
    <link rel="stylesheet" href="@aset('vendor/jpos.css')">
    <script src="@aset('vendor/tailwind-play-3.4.17.js')"></script>
    <script defer src="@aset('vendor/alpine-3.15.12.min.js')"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { -webkit-tap-highlight-color: transparent; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen pb-24" x-data="selfOrderApp()">

    {{-- HEADER ATAS --}}
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-2xs">
        <div class="max-w-xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-xl shadow-xs">
                    🍽️
                </div>
                <div>
                    <h1 class="font-black text-slate-900 text-sm leading-tight truncate max-w-[200px] sm:max-w-xs">
                        {{ $storeProfile['name'] ?? 'Restoran & Cafe' }}
                    </h1>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 text-[11px] font-black px-2 py-0.5 rounded-full border border-amber-200">
                            🪑 {{ $table->name }}
                        </span>
                        @if($table->description)
                            <span class="text-[10px] text-slate-500 line-clamp-1 truncate max-w-[120px]">{{ $table->description }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tombol Keranjang Cepat di Header --}}
            <button type="button" @click="bukaReviewModal()"
                    class="relative p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                <span class="text-lg">🛒</span>
                <span x-show="cartTotalItems > 0" x-text="cartTotalItems"
                      class="absolute -top-1 -right-1 bg-rose-600 text-white text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center border-2 border-white shadow-xs">
                </span>
            </button>
        </div>
    </header>

    {{-- KONTEN UTAMA --}}
    <main class="max-w-xl mx-auto px-4 pt-3.5 space-y-3.5">
        {{-- Banner Pesanan Aktif Meja Ini (Jika Sebelumnya Sudah Pernah Order) --}}
        @if($activeSale)
            <div class="p-3 bg-purple-50 border border-purple-200 rounded-xl text-xs text-purple-900 flex items-start gap-2.5 shadow-2xs">
                <span class="text-base shrink-0">🔔</span>
                <div class="flex-1">
                    <div class="font-bold flex items-center justify-between">
                        <span>Ada Pesanan Aktif di Meja Ini</span>
                        <a href="{{ route('meja.order.status', ['code' => $table->code, 'sale' => $activeSale->id]) }}" class="underline font-bold text-purple-700">Lihat Status &rarr;</a>
                    </div>
                    <p class="text-[11px] text-purple-700 mt-0.5">
                        Nomor Nota: <strong>{{ $activeSale->invoice_no }}</strong> (Total: Rp {{ number_format($activeSale->total, 0, ',', '.') }}). Menu yang Anda pesan di bawah akan ditambahkan sebagai pesanan berikutnya.
                    </p>
                </div>
            </div>
        @endif

        {{-- Input Nama Pemesan --}}
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-2xs">
            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Pemesan (Untuk panggilan pesanan):</label>
            <div class="flex gap-2">
                <input type="text" x-model="customerName" placeholder="Contoh: Budi / Meja Depan"
                       class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500 font-medium">
            </div>
        </div>

        {{-- Bar Pencarian Menu --}}
        <div class="relative">
            <input type="text" x-model="searchQuery" placeholder="Cari makanan, minuman, cemilan..."
                   class="w-full text-xs pl-9 pr-8 py-2.5 bg-white border border-slate-300 rounded-xl shadow-2xs focus:outline-hidden focus:ring-2 focus:ring-amber-500">
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            <button x-show="searchQuery" @click="searchQuery = ''" type="button"
                    class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 font-bold text-sm px-1">&times;</button>
        </div>

        {{-- Tab Kategori Menu Horizontal Scrollable --}}
        <div class="flex items-center gap-2 overflow-x-auto hide-scrollbar pb-1 pt-0.5">
            <button type="button" @click="selectedCategory = ''"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition"
                    :class="selectedCategory === '' ? 'bg-amber-500 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'">
                Semua Menu
            </button>
            @foreach($categories as $cat)
                <button type="button" @click="selectedCategory = '{{ $cat->id }}'"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition"
                        :class="selectedCategory === '{{ $cat->id }}' ? 'bg-amber-500 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'">
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>

        {{-- Daftar Kartu Menu --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template x-for="item in filteredItems" :key="item.id">
                <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-2xs flex flex-col justify-between transition hover:border-slate-300">
                    <div class="flex gap-3">
                        {{-- Gambar / Placeholder Menu --}}
                        <div class="w-20 h-20 rounded-lg bg-amber-50 border border-amber-100 flex items-center justify-center shrink-0 overflow-hidden">
                            <template x-if="item.image_url">
                                <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!item.image_url">
                                <span class="text-2xl">🍲</span>
                            </template>
                        </div>

                        {{-- Info Menu --}}
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider" x-text="item.category_name"></div>
                            <h3 class="font-bold text-slate-900 text-sm leading-snug line-clamp-2 mt-0.5" x-text="item.name"></h3>
                            <div class="text-xs font-black text-slate-900 mt-1" x-text="formatRupiah(item.price)"></div>

                            {{-- Keterangan Stok / Ketersediaan --}}
                            <div class="mt-1">
                                <template x-if="!item.is_available">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">
                                        Habis
                                    </span>
                                </template>
                                <template x-if="item.is_available && item.tracks_stock">
                                    <span class="text-[10px] text-slate-500 font-medium">
                                        Tersisa <span class="font-bold text-slate-700" x-text="item.stock"></span> <span x-text="item.unit"></span>
                                    </span>
                                </template>
                                <template x-if="item.is_available && !item.tracks_stock">
                                    <span class="text-[10px] text-emerald-600 font-semibold">
                                        Siap Saji
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Tombol Tambah ke Keranjang & Kontrol Kuantitas --}}
                    <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2">
                        {{-- Tombol Catatan Item --}}
                        <button type="button" @click="bukaModalCatatan(item)"
                                class="text-[11px] text-slate-500 hover:text-amber-600 font-medium flex items-center gap-1">
                            <span>✏️</span>
                            <span x-text="getCartNote(item.id) ? 'Catatan: ' + getCartNote(item.id) : '+ Catatan'"></span>
                        </button>

                        {{-- Kontrol Qty --}}
                        <div>
                            <template x-if="!item.is_available">
                                <button type="button" disabled class="px-3 py-1 bg-slate-100 text-slate-400 font-bold text-xs rounded-lg cursor-not-allowed">
                                    Habis
                                </button>
                            </template>

                            <template x-if="item.is_available && getCartQty(item.id) === 0">
                                <button type="button" @click="tambahKeKeranjang(item)"
                                        class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-lg shadow-2xs active:scale-95 transition">
                                    + Tambah
                                </button>
                            </template>

                            <template x-if="item.is_available && getCartQty(item.id) > 0">
                                <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 rounded-lg p-0.5">
                                    <button type="button" @click="kurangiItem(item.id)"
                                            class="w-6 h-6 rounded bg-white text-slate-700 font-bold text-xs flex items-center justify-center shadow-2xs hover:bg-slate-100 active:scale-95">
                                        &minus;
                                    </button>
                                    <span class="w-6 text-center font-black text-xs text-slate-900" x-text="getCartQty(item.id)"></span>
                                    <button type="button" @click="tambahKeKeranjang(item)"
                                            class="w-6 h-6 rounded bg-amber-500 text-white font-bold text-xs flex items-center justify-center shadow-2xs hover:bg-amber-600 active:scale-95">
                                        +
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Kosong Jika Tidak Ada Menu --}}
        <div x-show="filteredItems.length === 0" class="py-12 text-center text-slate-500">
            <div class="text-4xl mb-2">🔍</div>
            <div class="font-bold text-slate-700 text-sm">Menu Tidak Ditemukan</div>
            <p class="text-xs text-slate-400 mt-1">Coba cari kata kunci lain atau pilih kategori Semua Menu.</p>
        </div>
    </main>

    {{-- FLOATING BOTTOM BAR (MUNCUL JIKA ADA ITEM DI KERANJANG) --}}
    <div x-show="cartTotalItems > 0" x-cloak
         class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3 shadow-lg">
        <div class="max-w-xl mx-auto flex items-center justify-between gap-3">
            <div>
                <div class="text-[11px] text-slate-500 font-medium">
                    <span class="font-bold text-slate-800" x-text="cartTotalItems + ' Menu'"></span> di keranjang
                </div>
                <div class="text-base font-black text-amber-600" x-text="formatRupiah(cartTotalHarga)"></div>
            </div>

            <button type="button" @click="bukaReviewModal()"
                    class="btn bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-2.5 px-5 rounded-xl shadow-md active:scale-95 transition flex items-center gap-2">
                <span>Lihat Pesanan &rarr;</span>
            </button>
        </div>
    </div>

    {{-- MODAL REVIEW PESANAN & CHECKOUT --}}
    <div x-show="showReviewModal" x-cloak
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-end sm:items-center justify-center p-0 sm:p-4"
         @keydown.escape.window="showReviewModal = false">
        <div class="bg-white rounded-t-2xl sm:rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden"
             @click.away="showReviewModal = false">
            {{-- Header Modal --}}
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🛒</span>
                    <h3 class="font-black text-slate-800 text-sm">Konfirmasi Pesanan - {{ $table->name }}</h3>
                </div>
                <button type="button" @click="showReviewModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold px-1">&times;</button>
            </div>

            {{-- Isi Review --}}
            <div class="p-4 overflow-y-auto space-y-4 flex-1">
                {{-- Info Pemesan --}}
                <div class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl text-xs space-y-2">
                    <div>
                        <label class="block font-bold text-slate-700 mb-0.5">Nama Pemesan:</label>
                        <input type="text" x-model="customerName" placeholder="Tamu {{ $table->name }}"
                               class="w-full text-xs px-2.5 py-1.5 border border-slate-300 rounded bg-white font-semibold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-0.5">Catatan Umum Pesanan (Opsional):</label>
                        <input type="text" x-model="orderNote" placeholder="Contoh: Sambal dipisah, tolong sediakan sendok tambahan"
                               class="w-full text-xs px-2.5 py-1.5 border border-slate-300 rounded bg-white">
                    </div>
                </div>

                {{-- Daftar Menu yang Dipesan --}}
                <div>
                    <div class="text-xs font-bold text-slate-700 mb-2">Rincian Menu:</div>
                    <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
                        <template x-for="item in cart" :key="item.id">
                            <div class="p-3 bg-white flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-slate-900 text-xs truncate" x-text="item.name"></div>
                                    <div class="text-[11px] text-slate-500 font-semibold" x-text="formatRupiah(item.price)"></div>
                                    <template x-if="item.note">
                                        <div class="text-[10px] text-amber-700 font-medium italic mt-0.5" x-text="'Catatan: ' + item.note"></div>
                                    </template>
                                </div>

                                {{-- Kontrol Qty di Modal --}}
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button" @click="kurangiItem(item.product_id)"
                                            class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 font-bold text-xs text-slate-700 flex items-center justify-center">
                                        &minus;
                                    </button>
                                    <span class="w-6 text-center font-black text-xs text-slate-900" x-text="item.qty"></span>
                                    <button type="button" @click="tambahKeKeranjang(item)"
                                            class="w-6 h-6 rounded bg-amber-500 hover:bg-amber-600 font-bold text-xs text-white flex items-center justify-center">
                                        +
                                    </button>
                                </div>

                                <div class="text-right font-black text-xs text-slate-900 shrink-0 w-20"
                                     x-text="formatRupiah(item.price * item.qty)"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Total Ringkasan --}}
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Jumlah Menu:</span>
                        <span class="font-bold" x-text="cartTotalItems + ' Item'"></span>
                    </div>
                    <div class="flex justify-between text-slate-900 text-sm font-black pt-1.5 border-t border-slate-200">
                        <span>Total Pembayaran:</span>
                        <span class="text-amber-600 text-base" x-text="formatRupiah(cartTotalHarga)"></span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">
                        * Pembayaran dapat diselesaikan di kasir saat selesai bersantap.
                    </p>
                </div>
            </div>

            {{-- Footer Tombol Kirim --}}
            <div class="p-4 border-t border-slate-100 bg-white shrink-0 flex items-center justify-between gap-3">
                <button type="button" @click="showReviewModal = false" class="btn btn-outline text-xs py-2 px-3.5 text-slate-600">
                    Tambah Menu Lagi
                </button>
                <button type="button" @click="kirimPesanan()" :disabled="submitting || cart.length === 0"
                        class="btn bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-2.5 px-5 rounded-xl shadow-md active:scale-95 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!submitting">🚀 Kirim Pesanan ke Kasir</span>
                    <span x-show="submitting">Mengirim Pesanan...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- MODAL CATATAN ITEM --}}
    <div x-show="showNoteModal" x-cloak
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-4"
         @keydown.escape.window="showNoteModal = false">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-sm w-full p-4.5"
             @click.away="showNoteModal = false">
            <h4 class="font-bold text-slate-900 text-sm mb-1" x-text="'Catatan Menu: ' + (activeNoteItem ? activeNoteItem.name : '')"></h4>
            <p class="text-[11px] text-slate-500 mb-3">Tulis permintaan khusus (contoh: pedas manis, es sedikit, tanpa seledri).</p>

            <textarea x-model="activeItemNote" rows="2" placeholder="Tulis catatan di sini..."
                      class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-amber-500"></textarea>

            <div class="mt-3 flex items-center justify-end gap-2">
                <button type="button" @click="showNoteModal = false" class="btn btn-outline text-xs py-1.5 px-3">Batal</button>
                <button type="button" @click="simpanCatatanItem()" class="btn bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs py-1.5 px-3.5 rounded-lg">
                    Simpan Catatan
                </button>
            </div>
        </div>
    </div>

    <script>
    function selfOrderApp() {
        return {
            tableCode: '{{ $table->code }}',
            tableName: '{{ $table->name }}',
            customerName: '',
            orderNote: '',
            searchQuery: '',
            selectedCategory: '',
            allItems: @json($menuItems),
            cart: [],
            submitting: false,
            showReviewModal: false,
            showNoteModal: false,
            activeNoteItem: null,
            activeItemNote: '',

            get filteredItems() {
                let items = this.allItems;
                if (this.selectedCategory) {
                    items = items.filter(it => String(it.category_id) === String(this.selectedCategory));
                }
                if (this.searchQuery.trim()) {
                    const q = this.searchQuery.toLowerCase();
                    items = items.filter(it => it.name.toLowerCase().includes(q) || (it.category_name && it.category_name.toLowerCase().includes(q)));
                }
                return items;
            },

            get cartTotalItems() {
                return this.cart.reduce((sum, it) => sum + it.qty, 0);
            },

            get cartTotalHarga() {
                return this.cart.reduce((sum, it) => sum + (it.price * it.qty), 0);
            },

            getCartQty(productId) {
                const found = this.cart.find(it => it.product_id === productId);
                return found ? found.qty : 0;
            },

            getCartNote(productId) {
                const found = this.cart.find(it => it.product_id === productId);
                return found ? found.note : '';
            },

            tambahKeKeranjang(item) {
                const found = this.cart.find(it => it.product_id === item.id || it.product_id === item.product_id);
                if (found) {
                    if (item.tracks_stock && found.qty >= item.stock) {
                        alert('Stok menu ini sudah mencapai batas ketersediaan.');
                        return;
                    }
                    found.qty += 1;
                } else {
                    this.cart.push({
                        id: 'cart_' + Date.now(),
                        product_id: item.id || item.product_id,
                        name: item.name,
                        price: item.price,
                        qty: 1,
                        note: '',
                        tracks_stock: item.tracks_stock,
                        stock: item.stock
                    });
                }
            },

            kurangiItem(productId) {
                const idx = this.cart.findIndex(it => it.product_id === productId);
                if (idx !== -1) {
                    if (this.cart[idx].qty > 1) {
                        this.cart[idx].qty -= 1;
                    } else {
                        this.cart.splice(idx, 1);
                    }
                }
            },

            bukaModalCatatan(item) {
                this.activeNoteItem = item;
                this.activeItemNote = this.getCartNote(item.id) || '';
                this.showNoteModal = true;
            },

            simpanCatatanItem() {
                if (!this.activeNoteItem) return;
                let found = this.cart.find(it => it.product_id === this.activeNoteItem.id);
                if (!found) {
                    this.tambahKeKeranjang(this.activeNoteItem);
                    found = this.cart.find(it => it.product_id === this.activeNoteItem.id);
                }
                if (found) {
                    found.note = this.activeItemNote;
                }
                this.showNoteModal = false;
            },

            bukaReviewModal() {
                if (this.cart.length === 0) {
                    alert('Silakan pilih minimal satu menu terlebih dahulu.');
                    return;
                }
                this.showReviewModal = true;
            },

            formatRupiah(num) {
                return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
            },

            async kirimPesanan() {
                if (this.cart.length === 0 || this.submitting) return;

                this.submitting = true;

                const payload = {
                    customer_name: this.customerName || ('Tamu ' + this.tableName),
                    customer_note: this.orderNote,
                    items: this.cart.map(it => ({
                        product_id: it.product_id,
                        qty: it.qty,
                        note: it.note || null
                    }))
                };

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const res = await fetch('{{ route("meja.order.submit", $table->code) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json();

                    if (data.success) {
                        // Siarkan notifikasi instan lintas-tab / peramban kasir (0ms)
                        try {
                            const eventData = {
                                sale_id: data.sale_id,
                                table_name: '{{ $table->name }}',
                                invoice_no: data.invoice_no,
                                timestamp: Date.now()
                            };
                            localStorage.setItem('jpos_table_order_event', JSON.stringify(eventData));
                            if (window.BroadcastChannel) {
                                const bc = new BroadcastChannel('jpos_table_orders');
                                bc.postMessage(eventData);
                                bc.close();
                            }
                        } catch(err) {}

                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        }
                    } else {
                        alert(data.message || 'Gagal mengirim pesanan. Silakan coba kembali.');
                        this.submitting = false;
                    }
                } catch (e) {
                    console.error('Error submitting order:', e);
                    alert('Terjadi kesalahan jaringan saat mengirim pesanan. Pastikan HP terhubung ke jaringan restoran.');
                    this.submitting = false;
                }
            }
        };
    }
    </script>
</body>
</html>
