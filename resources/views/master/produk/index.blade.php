@extends('layouts.app')
@section('title', 'Produk')

@section('content')
<div x-data="{
    showModal: false, editItem: null, type: 'barang', unit: 'Pcs', produkMode: '{{ $produkMode }}', imagePreview: null,
    grosirJasa: {{ $grosirJasa ? 'true' : 'false' }},
    unitsList: {{ Illuminate\Support\Js::from($units->pluck('name')) }},
    unitsById: {{ Illuminate\Support\Js::from($units->pluck('name', 'id')) }},
    daftarRak: {{ Illuminate\Support\Js::from($rakTersedia) }},
    rakDipilih: '', kotakDipilih: '',
    multiUnitEnabled: false,
    hppCalcEnabled: false,
    baseSellPrice: 0,
    unitRows: [], nextUnitRowKey: 1,
    stockDisplay: 0, stockUnit: 'base',

    angka(n) { return window.JposNumber ? window.JposNumber.format(n) : Math.round(n || 0).toLocaleString('id-ID'); },
    qty(n) { return window.JposNumber ? window.JposNumber.formatQty(n) : String(n ?? 0); },
    bulat(n) { return Math.round((Number(n) || 0) * 10000) / 10000; },

    /* ------------------------------------------------------------- lokasi rak */

    /* Kotak yang boleh dipilih: yang kosong, DITAMBAH kotak yang sedang ditempati produk
       yang lagi diedit. Tanpa yang kedua, membuka form lalu menyimpan tanpa mengubah apa
       pun akan melepas produknya dari rak. */
    kotakRak() {
        const rak = this.daftarRak.find(r => String(r.id) === String(this.rakDipilih));
        if (!rak) return [];
        const idSaya = this.editItem ? this.editItem.id : null;
        return rak.kotak.filter(k => !k.product_id || k.product_id === idSaya);
    },

    /* Menyetel dropdown rak mengikuti penempatan produk yang sedang diedit. */
    setelLokasiRak(item) {
        this.rakDipilih = ''; this.kotakDipilih = '';
        if (!item) return;
        for (const rak of this.daftarRak) {
            const kotak = rak.kotak.find(k => k.product_id === item.id);
            if (kotak) {
                this.rakDipilih = String(rak.id);
                this.kotakDipilih = kotak.row + '-' + kotak.col;
                return;
            }
        }
    },

    previewImage(e) {
        const file = e.target.files[0];
        if (file) {
            this.imagePreview = URL.createObjectURL(file);
        }
    },

    /* ---------------------------------------------------------- rantai satuan */

    /* Satuan yang menjadi acuan baris ini: satuan dasar produk untuk baris pertama,
       atau satuan baris di atasnya. Inilah yang membuat labelnya berbunyi
       'Isi (berapa Botol)' alih-alih 'konversi' yang harus dihitung sendiri. */
    prevUnitLabel(index) {
        if (index === 0) return this.unit || 'satuan dasar';
        const sebelumnya = this.unitRows[index - 1];
        return (sebelumnya && this.unitsById[sebelumnya.unit_id]) || 'satuan sebelumnya';
    },
    /* Pratinjau hidup dari rasio berjenjang ke satuan dasar - angka yang sama persis
       dengan yang dihitung server saat disimpan. */
    cumulativeConversion(index) {
        let total = 1;
        for (let i = 0; i <= index; i++) {
            const r = Number(this.unitRows[i] ? this.unitRows[i].ratio_to_previous : NaN);
            if (!r || isNaN(r)) return null;
            total *= r;
        }
        return this.bulat(total);
    },

    /* ------------------------------------------- harga modal dari harga beli (HPP) */

    /* Yang diketahui pemilik toko cuma dua angka di nota: harga beli satu Dus, dan
       ongkirnya. Berapa harga modal per Pcs adalah pekerjaan aplikasi. */
    rowCostPrice(row) {
        if (this.hppCalcEnabled) {
            return (Number(row.modal_total) || 0) + (Number(row.biaya_lain) || 0);
        }
        return Number(row.cost_price) || 0;
    },
    /* Harga modal per satu satuan di bawahnya - angka yang biasanya dicari pemilik toko
       saat menawar ke pemasok. */
    rowHppIsi(row, index) {
        const modal = this.rowCostPrice(row);
        const isi = Number(row.ratio_to_previous) || 0;
        if (!modal || !isi) return null;
        return modal / isi;
    },
    /* Harga modal SATUAN DASAR yang diturunkan dari pembelian. Inilah angka yang benar-benar
       menggerakkan Laporan Laba dan Nilai Stok, jadi ditampilkan terang-terangan supaya
       pemilik toko tahu laporannya ikut terbawa benar. Dihitung dengan cara yang sama
       persis seperti di server. */
    hargaModalDasarTurunan() {
        let konversi = 1;
        for (let i = 0; i < this.unitRows.length; i++) {
            konversi *= Number(this.unitRows[i].ratio_to_previous) || 0;
            const modal = this.rowCostPrice(this.unitRows[i]);
            if (modal > 0 && konversi > 0) return Math.round((modal / konversi) * 100) / 100;
        }
        return null;
    },

    /* ------------------------------------------------- harga jual otomatis dari isi */

    /* Harga satuan di bawah baris ini; baris pertama mengacu ke harga jual satuan dasar. */
    prevEffectivePrice(index) {
        if (index <= 0) return Number(this.baseSellPrice) || 0;
        return this.rowEffectivePrice(this.unitRows[index - 1], index - 1);
    },
    /* Harga baris ini: diketik sendiri, atau dilipat dari harga satuan di bawahnya. Berantai,
       jadi mengubah harga dasar ikut menggerakkan seluruh satuan di atasnya. */
    rowEffectivePrice(row, index) {
        if (! row.auto_price) return Number(row.price) || 0;

        const isi = Number(row.ratio_to_previous) || 0;
        return Math.round(this.prevEffectivePrice(index) * isi);
    },
    unitRowProfitText(row, index) {
        const modal = this.rowCostPrice(row);
        if (! modal) return '-';
        const untung = this.rowEffectivePrice(row, index) - modal;
        return (untung < 0 ? '-Rp ' : 'Rp ') + this.angka(Math.abs(untung));
    },
    addUnitRow() {
        this.unitRows.push({ _key: this.nextUnitRowKey++, unit_id: '', barcode: '', ratio_to_previous: '', price: '', auto_price: false, cost_price: '', modal_total: '', biaya_lain: '', wholesale_price: '', wholesale_min_qty: '', allow_decimal: false });
    },
    removeUnitRow(key) {
        const dasar = this.stockBase;
        this.unitRows = this.unitRows.filter(r => r._key !== key);
        this.pulihkanStok(dasar);
    },
    /* Urutan baris MENENTUKAN rantainya, jadi tombol naik/turun ini bukan kosmetik. */
    moveUnitRow(index, arah) {
        const tujuan = index + arah;
        if (tujuan < 0 || tujuan >= this.unitRows.length) return;
        const dasar = this.stockBase;
        const rows = this.unitRows.slice();
        [rows[index], rows[tujuan]] = [rows[tujuan], rows[index]];
        this.unitRows = rows;
        this.pulihkanStok(dasar);
    },
    matikanMultiSatuan() {
        if (this.unitRows.length === 0) return;
        if (confirm('Menonaktifkan Multi Satuan akan menghapus semua satuan tambahan yang sudah diisi untuk produk ini. Lanjutkan?')) {
            const dasar = this.stockBase;
            this.unitRows = [];
            this.stockUnit = 'base';
            this.stockDisplay = dasar;
        } else {
            this.multiUnitEnabled = true;
        }
    },

    /* ------------------------------------------------------- stok multi satuan */

    /* Stok boleh diketik dalam satuan mana pun dari rantai; yang dikirim ke server
       SELALU dalam satuan dasar. stockBase sengaja dibuat turunan, bukan state
       terpisah, supaya tidak ada dua sumber kebenaran yang bisa berbeda. */
    konversiSatuanStok(kunci) {
        if (!kunci || kunci === 'base') return 1;
        const idx = this.unitRows.findIndex(r => String(r._key) === String(kunci));
        if (idx === -1) return 1;
        return this.cumulativeConversion(idx) || 1;
    },
    get stockBase() {
        return this.bulat((Number(this.stockDisplay) || 0) * this.konversiSatuanStok(this.stockUnit));
    },
    /* Mengganti satuan tidak boleh mengubah stok sebenarnya - yang berubah cuma
       cara menampilkannya. Nilai dasarnya dibaca dulu, baru satuannya diganti. */
    gantiSatuanStok(kunci) {
        const dasar = this.stockBase;
        this.stockUnit = kunci;
        this.stockDisplay = this.bulat(dasar / this.konversiSatuanStok(kunci));
    },
    /* Dipanggil setelah rantai berubah (baris dihapus/diurut ulang): satuan yang
       dipilih bisa saja sudah tidak ada, atau konversinya bergeser. */
    pulihkanStok(dasar) {
        if (this.stockUnit !== 'base' && !this.unitRows.some(r => String(r._key) === String(this.stockUnit))) {
            this.stockUnit = 'base';
        }
        this.stockDisplay = this.bulat(dasar / this.konversiSatuanStok(this.stockUnit));
    },

    /* ------------------------------------------------------------------ modal */

    openEdit(item) {
        this.imagePreview = null;
        this.editItem = item;
        this.type = item.type;
        this.unit = item.unit || 'Pcs';
        this.multiUnitEnabled = !!item.multi_unit_enabled;
        this.hppCalcEnabled = !!item.hpp_calc_enabled;
        this.baseSellPrice = Number(item.sell_price) || 0;
        this.unitRows = (item.units || []).map(u => ({
            _key: this.nextUnitRowKey++,
            unit_id: u.unit_id,
            barcode: u.barcode || '',
            ratio_to_previous: u.ratio_to_previous !== null ? Number(u.ratio_to_previous) : '',
            price: Number(u.price),
            /* Harga tersimpan dianggap diketik sendiri. Menyalakan harga otomatis adalah
               keputusan sadar, dan menebaknya dari kebetulan angka yang pas justru membuat
               harga berubah sendiri saat produk dibuka untuk hal lain.

               CATATAN: jangan pernah memakai tanda kutip ganda di dalam blok x-data ini,
               termasuk di dalam komentar. Atributnya dibatasi kutip ganda, jadi satu saja
               membuat browser menutup atribut di tengah jalan dan seluruh komponen mati -
               tanpa error apa pun di sisi server. */
            auto_price: false,
            cost_price: u.cost_price !== null ? Number(u.cost_price) : '',
            modal_total: u.modal_total !== null && u.modal_total !== undefined ? Number(u.modal_total) : '',
            biaya_lain: u.biaya_lain !== null && u.biaya_lain !== undefined ? Number(u.biaya_lain) : '',
            wholesale_price: u.wholesale_price !== null ? Number(u.wholesale_price) : '',
            wholesale_min_qty: u.wholesale_min_qty,
            allow_decimal: !!u.allow_decimal,
        }));
        this.stockUnit = 'base';
        this.stockDisplay = Number(item.stock) || 0;
        this.setelLokasiRak(item);
        this.showModal = true;
    },
    openAdd() {
        this.imagePreview = null; this.editItem = null; this.type = 'barang'; this.unit = 'Pcs';
        this.multiUnitEnabled = false; this.hppCalcEnabled = false; this.baseSellPrice = 0;
        this.unitRows = [];
        this.stockUnit = 'base'; this.stockDisplay = 0;
        this.setelLokasiRak(null);
        this.showModal = true;
    },
}">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2 flex-wrap" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama/SKU/barcode..." class="form-input w-64">
            <select name="category_id" class="form-select w-44">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select w-36">
                <option value="">Semua Tipe</option>
                <option value="barang" {{ request('type') == 'barang' ? 'selected' : '' }}>Barang</option>
                <option value="jasa" {{ request('type') == 'jasa' ? 'selected' : '' }}>Jasa</option>
            </select>
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah Produk</button>
    </div>

    {{-- Blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <div class="card p-4"><div class="text-xs text-slate-500">Total Produk</div><div class="text-xl font-bold">{{ $summary->total_produk ?? 0 }}</div></div>
        <div class="card p-4"><div class="text-xs text-slate-500">Total Stok</div><div class="text-xl font-bold">@qty($summary->total_stok ?? 0)</div></div>
        <div class="card p-4"><div class="text-xs text-slate-500">Nilai Stok (HPP)</div><div class="text-xl font-bold">Rp {{ number_format($summary->total_nilai_hpp ?? 0, 0, ',', '.') }}</div></div>
        <div class="card p-4"><div class="text-xs text-slate-500">Nilai Stok (Harga Jual)</div><div class="text-xl font-bold text-green-600">Rp {{ number_format($summary->total_nilai_jual ?? 0, 0, ',', '.') }}</div></div>
    </div>
    <p class="text-xs text-slate-400 -mt-2 mb-4">* Nilai dihitung dari stok saat ini &times; harga modal/harga jual masing-masing produk yang sesuai filter di atas.</p>

    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead><tr><th></th><th>Nama</th><th>SKU / Barcode</th><th>Kategori</th><th>Satuan</th><th>Harga Jual</th><th>Stok</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse($products as $p)
                @php $barisSatuan = 1 + $p->units->count(); @endphp
                <tr>
                    <td rowspan="{{ $barisSatuan }}"><img src="{{ $p->image_url }}" class="w-10 h-10 rounded object-cover bg-slate-100"></td>
                    <td rowspan="{{ $barisSatuan }}" class="font-medium">{{ $p->name }}</td>
                    <td rowspan="{{ $barisSatuan }}" class="text-slate-500 text-xs">{{ $p->sku }}<br>{{ $p->barcode }}</td>
                    <td rowspan="{{ $barisSatuan }}">{{ $p->category->name ?? '-' }}</td>
                    <td>{{ $p->unit }}</td>
                    <td>Rp {{ number_format($p->sell_price, 0, ',', '.') }}</td>
                    <td>
                        @if($p->isJasa())
                            <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">🛠️ Jasa</span>
                        @else
                            <span class="{{ $p->isLowStock() ? 'text-red-600 font-semibold' : '' }}">@qty($p->stock)</span> {{ $p->unit }}
                        @endif
                    </td>
                    <td rowspan="{{ $barisSatuan }}" class="text-right space-x-2">
                        <button @click='openEdit(@json($p))' class="text-blue-600 text-sm hover:underline">Edit</button>
                        <form method="POST" action="{{ route('produk.destroy', $p) }}" class="inline" onsubmit="return confirm('Hapus produk ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Hapus</button>
                        </form>
                    </td>
                </tr>
                @foreach($p->units as $pu)
                <tr class="bg-slate-50/70">
                    <td class="text-slate-500">&#8618; {{ $pu->unit->name }}</td>
                    <td>Rp {{ number_format($pu->price, 0, ',', '.') }}</td>
                    <td>
                        {{-- Pembagian biasa, bukan intdiv: satuan yang lebih KECIL dari satuan
                             dasar punya konversi di bawah 1 (Gram = 0,001), dan membulatkannya
                             ke integer lebih dulu membuat hasilnya meleset 1.000 kali. --}}
                        @php $setara = ((float) $pu->conversion) > 0 ? $p->stock / (float) $pu->conversion : 0; @endphp
                        <span class="{{ $p->isLowStock() ? 'text-red-600 font-semibold' : '' }}">@qty($setara)</span> {{ $pu->unit->name }}
                    </td>
                </tr>
                @endforeach
                @empty
                <tr><td colspan="8" class="text-center text-slate-400 py-8">Belum ada produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
    </div>

    {{-- Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-lg my-8 max-h-[85vh] flex flex-col">
            <h3 class="font-semibold text-lg p-6 pb-0 shrink-0" x-text="editItem ? 'Edit Produk' : 'Tambah Produk'"></h3>
            <form :action="editItem ? '{{ url('master/produk') }}/' + editItem.id : '{{ route('produk.store') }}'" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editItem"><input type="hidden" name="_method" value="PUT"></template>

                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">

                <div class="flex items-center gap-3">
                    <img :src="imagePreview ? imagePreview : (editItem && editItem.image_url ? editItem.image_url : '{{ asset('images/no-image.svg') }}')" class="w-16 h-16 rounded object-cover bg-slate-100 border">
                    <div class="flex-1">
                        <label class="form-label">Gambar Produk</label>
                        <input type="file" name="image" accept="image/*" @change="previewImage($event)" class="form-input">
                    </div>
                </div>

                <div>
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="name" :value="editItem ? editItem.name : ''" required class="form-input">
                </div>

                <div>
                    <label class="form-label">Tipe Produk</label>
                    <div class="flex rounded-lg border overflow-hidden text-sm">
                        <label class="flex-1 text-center py-2 cursor-pointer" :class="type === 'barang' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                            <input type="radio" name="type" value="barang" x-model="type" class="hidden"> 📦 Barang
                        </label>
                        <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="type === 'jasa' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                            <input type="radio" name="type" value="jasa" x-model="type" class="hidden"> 🛠️ Jasa
                        </label>
                    </div>
                    <p class="text-xs text-slate-400 mt-1" x-show="type === 'jasa'">Produk jasa tidak punya stok - setiap kali dijual tetap tercatat sebagai penjualan, tapi tidak mengurangi stok apa pun.</p>
                </div>

                <template x-if="editItem">
                    <div>
                        <label class="form-label">SKU (otomatis)</label>
                        <input type="text" :value="editItem ? editItem.sku : ''" disabled class="form-input bg-slate-50 text-slate-500">
                    </div>
                </template>

                <div>
                    <label class="form-label">Barcode (opsional, sesuai barcode produk)</label>
                    <input type="text" name="barcode" :value="editItem ? editItem.barcode : ''" class="form-input">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" x-bind:selected="editItem && editItem.category_id == {{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" x-bind:selected="editItem && editItem.supplier_id == {{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- LOKASI RAK (Planogram) - hanya untuk barang; jasa tidak menempati rak.

                     Ditaruh di sini, bukan cuma di halaman Planogram, karena saat memasukkan
                     barang baru pemilik toko sedang memegang barangnya dan tahu persis mau
                     ditaruh di mana. Memaksanya membuka halaman lain berarti langkah itu
                     ditunda - dan peta rak yang setengah terisi lebih menyesatkan daripada
                     tidak ada peta sama sekali. --}}
                <template x-if="type === 'barang' && daftarRak.length > 0">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-slate-700">Lokasi Rak</span>
                            <a href="{{ route('planogram.index') }}" target="_blank" class="text-xs text-brand-600 hover:underline">Atur rak</a>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Rak</label>
                                <select name="rack_id" x-model="rakDipilih" @change="kotakDipilih = ''" class="form-select">
                                    <option value="">&mdash; Tidak ditaruh di rak &mdash;</option>
                                    <template x-for="r in daftarRak" :key="r.id">
                                        <option :value="r.id" x-text="r.nama"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Kotak</label>
                                <select name="rack_slot" x-model="kotakDipilih" class="form-select" :disabled="!rakDipilih">
                                    <option value="">&mdash; Pilih kotak &mdash;</option>
                                    <template x-for="k in kotakRak()" :key="k.row + '-' + k.col">
                                        <option :value="k.row + '-' + k.col" x-text="k.label"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-2 leading-snug">
                            Satu produk hanya menempati satu kotak. Memilih kotak baru akan
                            <strong>memindahkannya</strong>, bukan menggandakan. Kotak yang sudah
                            ditempati produk lain tidak ditawarkan di sini.
                        </p>
                    </div>
                </template>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="form-label">Harga Modal</label>
                        {{-- Saat harga modal diturunkan dari harga beli, kotak ini jadi
                             tampilan hasil. Nilainya tetap dikirim lewat hidden supaya
                             server menerimanya walau kotaknya tidak bisa diketik. --}}
                        <template x-if="!(hppCalcEnabled && hargaModalDasarTurunan() !== null)">
                            <input type="text" data-jpos-number data-number-decimals="2" name="cost_price" x-number.oneway="editItem ? editItem.cost_price : 0" required class="form-input">
                        </template>
                        <template x-if="hppCalcEnabled && hargaModalDasarTurunan() !== null">
                            <div>
                                <input type="hidden" name="cost_price" :value="hargaModalDasarTurunan()">
                                <div class="form-input bg-emerald-50 text-emerald-800 font-medium" x-text="'Rp ' + angka(hargaModalDasarTurunan())"></div>
                            </div>
                        </template>
                    </div>
                    <div>
                        <label class="form-label">Harga Jual</label>
                        <input type="text" data-jpos-number data-number-decimals="2" name="sell_price" x-number="baseSellPrice" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Satuan</label>
                        <select name="unit" x-model="unit" class="form-select">
                            <template x-if="unit && !unitsList.includes(unit)">
                                <option :value="unit" x-text="unit"></option>
                            </template>
                            @foreach($units as $u)
                                <option value="{{ $u->name }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Grosir untuk JASA hanya muncul kalau dinyalakan di Pengaturan > Mode Produk.
                     Datang dari toko fotokopi: harga per lembar turun begitu jumlahnya banyak
                     (cetak skripsi, jilid borongan). Aturannya sama persis dengan grosir barang,
                     jadi tidak ada mesin harga baru - yang ditambah cuma jalan masuknya. --}}
                <div x-show="type === 'barang' || grosirJasa">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Harga Grosir (opsional)</label>
                            <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" name="wholesale_price" x-number.oneway="editItem ? editItem.wholesale_price : ''" placeholder="kosongkan jika tidak ada" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Min. Qty Grosir</label>
                            <input type="text" data-jpos-number data-number-empty="kosong" data-number-min="2" name="wholesale_min_qty" x-number.oneway="editItem ? editItem.wholesale_min_qty : ''" placeholder="mis. 12" class="form-input">
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 mb-3">
                        Kalau qty beli (satuan dasar) mencapai jumlah ini di Kasir, harga otomatis pakai harga grosir.
                        <span x-show="type === 'jasa'" x-cloak class="block mt-0.5">Contoh fotokopi: <strong>Rp 300</strong> mulai <strong>100</strong> lembar, dari harga satuan Rp 500.</span>
                    </p>

                    <template x-if="produkMode === 'lengkap' || unitRows.length > 0">
                    <div class="border rounded-lg p-3 bg-slate-50 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="text-sm font-medium text-slate-600">Multi Satuan</div>
                                <div class="text-xs text-slate-400">Opsional, konversi berjenjang mis. Pallet &rarr; Karton &rarr; Dus &rarr; Pcs</div>
                            </div>
                            <label class="flex items-center gap-2 text-sm shrink-0 cursor-pointer">
                                {{-- Hidden pendamping: checkbox yang tidak dicentang tidak dikirim
                                     browser sama sekali, jadi tanpa ini server tidak pernah tahu
                                     bedanya "dimatikan" dan "tidak disentuh". --}}
                                <input type="hidden" name="multi_unit_enabled" value="0">
                                <input type="checkbox" name="multi_unit_enabled" value="1" x-model="multiUnitEnabled"
                                    @change="if (!multiUnitEnabled) matikanMultiSatuan()">
                                Aktifkan Multi Satuan
                            </label>
                        </div>

                        <template x-if="multiUnitEnabled">
                        <div class="space-y-3">
                            {{-- Untuk pemilik toko yang tidak mau repot menghitung harga
                                 modal: cukup ketik yang tertulis di nota. --}}
                            <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer border-t border-slate-200 pt-3">
                                <input type="hidden" name="hpp_calc_enabled" value="0">
                                <input type="checkbox" name="hpp_calc_enabled" value="1" class="mt-0.5" x-model="hppCalcEnabled">
                                <span>
                                    <span class="block font-medium text-slate-700">🧮 Hitungkan harga modal dari harga beli</span>
                                    <span class="block text-slate-400">Isi harga beli dan biaya lain per satuan, harga modal dihitung sendiri &mdash; termasuk harga modal satuan dasar yang dipakai Laporan Laba dan Nilai Stok.</span>
                                </span>
                            </label>

                            <template x-if="hppCalcEnabled && hargaModalDasarTurunan() !== null">
                                <p class="text-xs bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-3 py-2">
                                    Harga modal <span class="font-medium" x-text="unit"></span> terhitung
                                    <span class="font-semibold">Rp <span x-text="angka(hargaModalDasarTurunan())"></span></span>
                                    &mdash; angka inilah yang dipakai Laporan Laba dan Nilai Stok.
                                </p>
                            </template>

                            <div class="flex justify-end">
                                <button type="button" @click="addUnitRow()" class="shrink-0 text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200 rounded-full px-3 py-1 hover:bg-brand-50">+ Tambah Satuan</button>
                            </div>

                            <template x-if="unitRows.length === 0">
                                <p class="text-xs text-slate-400">Belum ada satuan tambahan. Klik "+ Tambah Satuan", mulai dari satuan yang paling dekat dengan satuan dasar (mis. Botol), lalu tambah satuan yang lebih besar berikutnya (Pack, Dus, Karton, Pallet, dst).</p>
                            </template>

                            <template x-for="(row, index) in unitRows" :key="row._key">
                                <div class="border rounded-lg p-3 bg-white space-y-3 relative">
                                    <div class="absolute top-3 right-3 flex items-center gap-2">
                                        <button type="button" @click="moveUnitRow(index, -1)" :disabled="index === 0" class="text-slate-400 hover:text-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" title="Naikkan urutan">&#9650;</button>
                                        <button type="button" @click="moveUnitRow(index, 1)" :disabled="index === unitRows.length - 1" class="text-slate-400 hover:text-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" title="Turunkan urutan">&#9660;</button>
                                        <button type="button" @click="removeUnitRow(row._key)" class="text-red-500 text-xs hover:underline">Hapus</button>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3 pr-24">
                                        <div>
                                            <label class="form-label">Satuan</label>
                                            <select :name="'units[' + row._key + '][unit_id]'" x-model.number="row.unit_id" required class="form-select">
                                                <option value="">- Pilih -</option>
                                                @foreach($units as $unit)
                                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label">Isi (berapa <span x-text="prevUnitLabel(index)"></span>)</label>
                                            <input type="text" data-jpos-number data-number-decimals="4" :name="'units[' + row._key + '][ratio_to_previous]'" x-number="row.ratio_to_previous" required placeholder="12" class="form-input">
                                            <p class="text-[11px] text-slate-400 mt-0.5" x-show="cumulativeConversion(index)">= <span x-text="qty(cumulativeConversion(index))"></span> <span x-text="unit"></span></p>
                                        </div>
                                        <div>
                                            <label class="form-label">Harga</label>
                                            <template x-if="!row.auto_price">
                                                <input type="text" data-jpos-number data-number-decimals="2" :name="'units[' + row._key + '][price]'" x-number="row.price" required placeholder="25.000" class="form-input">
                                            </template>
                                            <template x-if="row.auto_price">
                                                <div>
                                                    <input type="hidden" :name="'units[' + row._key + '][price]'" :value="rowEffectivePrice(row, index)">
                                                    <div class="form-input bg-slate-50 text-slate-500" x-text="'Rp ' + angka(rowEffectivePrice(row, index))"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    {{-- Barcode milik satuan ini sendiri. Kalau diisi, memindainya di Kasir
                                         LANGSUNG memilih satuan ini - kasir tidak perlu memilih apa pun,
                                         dan langkah itulah yang paling sering terlewat pada jam ramai.
                                         Kalau dikosongkan, label satuan ini tetap memakai kode produknya
                                         seperti sebelumnya, jadi tidak ada toko yang perlu mengisi apa pun. --}}
                                    <div class="pr-24">
                                        <label class="form-label">Barcode satuan ini <span class="text-slate-400 font-normal">(opsional)</span></label>
                                        <input type="text" :name="'units[' + row._key + '][barcode]'" x-model="row.barcode"
                                               placeholder="Kosongkan untuk memakai barcode produk" class="form-input">
                                        <p class="text-[11px] text-slate-400 mt-0.5">
                                            Diisi kalau kemasan <span x-text="unitsById[row.unit_id] || 'satuan ini'"></span> punya barcode sendiri.
                                            Saat dipindai, satuannya langsung terpilih di Kasir.
                                        </p>
                                    </div>
                                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                        <input type="hidden" :name="'units[' + row._key + '][allow_decimal]'" value="0">
                                        <input type="checkbox" :name="'units[' + row._key + '][allow_decimal]'" value="1" x-model="row.allow_decimal">
                                        <span>⚖️ Boleh dijual qty desimal di Kasir (mis. 2,5 <span x-text="unitsById[row.unit_id] || 'satuan ini'"></span>)</span>
                                    </label>
                                    <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
                                        <input type="checkbox" class="mt-0.5" x-model="row.auto_price">
                                        <span>💰 Harga otomatis dari isi &mdash; harga <span x-text="unitsById[row.unit_id] || 'satuan ini'"></span> = harga <span x-text="prevUnitLabel(index)"></span> &times; isi, jadi untungnya ikut sebanding tanpa perlu dihitung ulang.</span>
                                    </label>

                                    <div class="pt-2 border-t border-slate-100 space-y-2">
                                        <template x-if="!hppCalcEnabled">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="form-label">Harga Modal (opsional)</label>
                                                    <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" :name="'units[' + row._key + '][cost_price]'" x-number="row.cost_price" placeholder="kosongkan jika tidak ada" class="form-input">
                                                </div>
                                                <div>
                                                    <label class="form-label">Untung / satuan ini</label>
                                                    <div class="form-input bg-slate-50 text-slate-500" x-text="unitRowProfitText(row, index)"></div>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="hppCalcEnabled">
                                            <div class="space-y-2">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="form-label">Harga Beli 1 <span x-text="unitsById[row.unit_id] || 'satuan'"></span></label>
                                                        <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" :name="'units[' + row._key + '][modal_total]'" x-number="row.modal_total" placeholder="mis. 100.000" class="form-input">
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Biaya Lain (ongkir, dll)</label>
                                                        <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" :name="'units[' + row._key + '][biaya_lain]'" x-number="row.biaya_lain" placeholder="mis. 10.000" class="form-input">
                                                    </div>
                                                </div>
                                                <p class="text-xs text-slate-500">
                                                    Harga modal 1 <span x-text="unitsById[row.unit_id] || 'satuan'"></span>:
                                                    <span class="font-medium text-slate-700">Rp <span x-text="angka(rowCostPrice(row))"></span></span>
                                                    <template x-if="rowHppIsi(row, index) !== null">
                                                        <span> &middot; per <span x-text="prevUnitLabel(index)"></span>:
                                                            <span class="font-medium text-slate-700">Rp <span x-text="angka(rowHppIsi(row, index))"></span></span>
                                                        </span>
                                                    </template>
                                                    &middot; untung: <span x-text="unitRowProfitText(row, index)"></span>
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                        <div>
                                            <label class="form-label">Harga Grosir (opsional)</label>
                                            <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" :name="'units[' + row._key + '][wholesale_price]'" x-number="row.wholesale_price" placeholder="kosongkan jika tidak ada" class="form-input">
                                        </div>
                                        <div>
                                            <label class="form-label">Min. Qty Grosir</label>
                                            <input type="text" data-jpos-number data-number-empty="kosong" data-number-min="2" :name="'units[' + row._key + '][wholesale_min_qty]'" x-number="row.wholesale_min_qty" placeholder="12" class="form-input">
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <p class="text-xs text-slate-400 pt-2 border-t border-slate-200">Isi berjenjang dari satuan terkecil ke terbesar: tiap baris cukup diisi "Isi" = berapa satuan sebelumnya (baris di atasnya, atau satuan dasar untuk baris pertama). Contoh: satuan dasar Pcs &rarr; Botol (isi 12, artinya 1 Botol = 12 Pcs) &rarr; Pack (isi 6, artinya 1 Pack = 6 Botol) &rarr; Dus (isi 4). Sistem otomatis menghitung total konversi tiap satuan ke satuan dasar.</p>
                        </div>
                        </template>
                    </div>
                    </template>
                </div>

                <div class="grid grid-cols-2 gap-3" x-show="type === 'barang'">
                    <div>
                        <label class="form-label">Stok</label>
                        <div class="grid grid-cols-[1fr_7rem] gap-2">
                            <input type="text" data-jpos-number data-number-decimals="4" x-number="stockDisplay" :required="type === 'barang'" class="form-input">
                            <select :value="stockUnit" @change="gantiSatuanStok($event.target.value)" class="form-select">
                                <option value="base" x-text="unit"></option>
                                <template x-for="row in unitRows" :key="row._key">
                                    <template x-if="row.unit_id">
                                        <option :value="row._key" x-text="unitsById[row.unit_id] || ''"></option>
                                    </template>
                                </template>
                            </select>
                        </div>
                        {{-- Yang dikirim ke server SELALU dalam satuan dasar. --}}
                        <input type="hidden" name="stock" :value="stockBase">
                        <p class="text-[11px] text-slate-400 mt-1" x-show="stockUnit !== 'base'">= <span x-text="qty(stockBase)"></span> <span x-text="unit"></span></p>
                    </div>
                    <div>
                        <label class="form-label">Stok Minimum (opsional, dalam <span x-text="unit"></span>)</label>
                        <input type="text" data-jpos-number data-number-decimals="4" data-number-empty="kosong" name="min_stock" x-number.oneway="editItem ? editItem.min_stock : ''" placeholder="0" class="form-input">
                    </div>
                </div>

                {{-- Hidden pendamping wajib ada: checkbox yang tidak dicentang sama sekali
                     tidak dikirim browser, jadi tanpa ini server tidak pernah tahu bedanya
                     "dilepas centangnya" dan "tidak disentuh". --}}
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-bind:checked="!editItem || editItem.is_active"> Aktif
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_taxable" value="0">
                        <input type="checkbox" name="is_taxable" value="1" x-bind:checked="!!(editItem && editItem.is_taxable)"> Kena pajak
                    </label>
                </div>
                <p class="text-xs text-slate-400 -mt-1">Produk baru sengaja <strong>tidak</strong> dicentang. Centang hanya untuk produk yang memang dikenai pajak. Berpengaruh hanya jika pajak diaktifkan di Pengaturan &rsaquo; Pajak.</p>

                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" x-text="editItem ? editItem.description : ''" class="form-textarea" rows="2"></textarea>
                </div>

                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t shrink-0">
                    <button type="button" @click="showModal=false" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
