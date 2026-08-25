@extends('layouts.app')
@section('title', 'Pembelian & Hutang')

@section('content')
<div x-data="{
    showForm: false,
    showBayar: false,
    notaBayar: null,
    supplierId: '',
    products: {{ Illuminate\Support\Js::from($products) }},
    baris: [],
    kunciBaris: 1,
    biayaLain: 0,
    caraBayar: 'tunai',
    dibayar: 0,

    angka(n) { return window.JposNumber ? window.JposNumber.format(n) : Math.round(n || 0).toLocaleString('id-ID'); },
    qty(n) { return window.JposNumber ? window.JposNumber.formatQty(n) : String(n ?? 0); },

    produk(id) { return this.products.find(p => p.id === Number(id)) || null; },

    /* Satuan yang bisa dipakai membeli: satuan dasar produk, plus seluruh satuan
       tambahannya. Toko biasanya kulakan per Dus walau menjualnya per Pcs. */
    satuanProduk(row) {
        const p = this.produk(row.product_id);
        if (! p) return [];
        return [{ value: 'base', label: p.unit, conversion: 1 }].concat(
            (p.units || []).map(u => ({ value: 'unit_' + u.id, label: u.name, conversion: u.conversion }))
        );
    },
    konversi(row) {
        const s = this.satuanProduk(row).find(x => x.value === row.unit_type);
        return s ? Number(s.conversion) : 1;
    },
    labelSatuan(row) {
        const s = this.satuanProduk(row).find(x => x.value === row.unit_type);
        return s ? s.label : '';
    },

    subtotalBaris(row) { return Math.round((Number(row.qty) || 0) * (Number(row.price) || 0)); },
    subtotal() { return this.baris.reduce((t, r) => t + this.subtotalBaris(r), 0); },
    total() { return this.subtotal() + (Number(this.biayaLain) || 0); },

    /* Harga modal per satuan dasar setelah biaya kirim dibagi sebanding nilai barangnya.
       Ditampilkan hidup supaya pemilik toko langsung melihat apakah harga jualnya masih
       masuk akal - sebelum notanya disimpan. */
    modalPerSatuanDasar(row) {
        const isi = (Number(row.qty) || 0) * this.konversi(row);
        if (isi <= 0) return 0;
        const sub = this.subtotalBaris(row);
        const total = this.subtotal();
        const porsi = total > 0 ? (Number(this.biayaLain) || 0) * (sub / total) : 0;
        return Math.round(((sub + porsi) / isi) * 100) / 100;
    },

    tambahBaris() {
        this.baris.push({ _key: this.kunciBaris++, product_id: '', unit_type: 'base', qty: 1, price: '' });
    },
    hapusBaris(key) { this.baris = this.baris.filter(r => r._key !== key); },

    /* Mengganti produk mengubah daftar satuannya, jadi pilihan satuan lama bisa jadi tidak
       ada lagi di produk yang baru. */
    gantiProduk(row) {
        row.unit_type = 'base';
        const p = this.produk(row.product_id);
        if (p && ! row.price) row.price = p.cost_price;
    },

    bukaForm() {
        this.showForm = true;
        this.baris = [];
        this.biayaLain = 0;
        this.caraBayar = 'tunai';
        this.dibayar = 0;
        this.supplierId = '';
        this.tambahBaris();
    },
    bukaBayar(nota) { this.notaBayar = nota; this.showBayar = true; },
}">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2 flex-wrap" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari no. nota / pemasok..." class="form-input w-64">
            <select name="status" class="form-select w-40">
                <option value="">Semua</option>
                <option value="hutang" {{ request('status') === 'hutang' ? 'selected' : '' }}>Masih Hutang</option>
                <option value="lunas" {{ request('status') === 'lunas' ? 'selected' : '' }}>Lunas</option>
            </select>
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="bukaForm()" class="btn btn-primary">+ Catat Pembelian</button>
    @if(auth()->user()->can_access('laporan'))
        @include('laporan._ekspor', ['jenis' => 'hutang', 'filter' => []])
    @endif

    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div class="card p-4">
            <div class="text-xs text-slate-500">Total Hutang ke Pemasok</div>
            <div class="text-xl font-bold text-orange-600">Rp {{ number_format($ringkasan->total_hutang, 0, ',', '.') }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-slate-500">Nota Belum Lunas</div>
            <div class="text-xl font-bold">{{ $ringkasan->jumlah_nota_hutang }}</div>
        </div>
        <div class="card p-4 {{ $ringkasan->jatuh_tempo > 0 ? 'border-2 border-red-200' : '' }}">
            <div class="text-xs text-slate-500">Lewat Jatuh Tempo</div>
            <div class="text-xl font-bold {{ $ringkasan->jatuh_tempo > 0 ? 'text-red-600' : '' }}">{{ $ringkasan->jatuh_tempo }}</div>
        </div>
    </div>

    <p class="text-xs text-slate-400 mb-4">
        * Mencatat pembelian di sini menambah stok, memperbarui harga modal produk, dan mencatat uangnya &mdash;
        keluar dari kas kalau tunai, atau menjadi hutang kalau belum dibayar. Pembelian TIDAK mengurangi laba:
        uangnya berubah wujud jadi stok, dan baru jadi beban saat barangnya terjual.
    </p>

    {{-- Blok ini yang ditukar saat pencarian langsung. --}}
    <div data-live-results>
    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead>
                <tr>
                    <th>No. Nota</th><th>Tanggal</th><th>Pemasok</th><th>Barang</th>
                    <th>Total</th><th>Sisa Hutang</th><th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $p)
                <tr>
                    <td class="font-medium">
                        {{ $p->purchase_no }}
                        @if($p->supplier_invoice_no)
                            <div class="text-xs text-slate-400">Nota: {{ $p->supplier_invoice_no }}</div>
                        @endif
                    </td>
                    <td>{{ $p->purchase_date->format('d/m/Y') }}</td>
                    <td>{{ $p->supplier->name ?? '-' }}</td>
                    <td class="text-xs text-slate-500">
                        @foreach($p->items->take(3) as $it)
                            {{ $it->product_name }} <span class="text-slate-400">@qty($it->qty) {{ $it->unit_label }}</span><br>
                        @endforeach
                        @if($p->items->count() > 3)
                            <span class="text-slate-400">+{{ $p->items->count() - 3 }} barang lain</span>
                        @endif
                    </td>
                    <td>Rp {{ number_format($p->total, 0, ',', '.') }}</td>
                    <td>
                        @if($p->sudahLunas())
                            <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Lunas</span>
                        @else
                            <div class="font-medium text-orange-600">Rp {{ number_format($p->sisa_hutang, 0, ',', '.') }}</div>
                            @if($p->due_date)
                                @php $telat = $p->umurTunggakan(); @endphp
                                <div class="text-xs {{ $telat > 0 ? 'text-red-600 font-medium' : 'text-slate-400' }}">
                                    @if($telat > 0)
                                        Telat {{ $telat }} hari
                                    @else
                                        Jatuh tempo {{ $p->due_date->format('d/m/Y') }}
                                    @endif
                                </div>
                            @endif
                        @endif
                    </td>
                    <td class="text-right space-x-2 whitespace-nowrap">
                        @unless($p->sudahLunas())
                        <button @click='bukaBayar(@json(["id" => $p->id, "no" => $p->purchase_no, "sisa" => (float) $p->sisa_hutang]))'
                                class="text-blue-600 text-sm hover:underline">Bayar</button>
                        @endunless
                        <form method="POST" action="{{ route('pembelian.destroy', $p) }}" class="inline"
                              onsubmit="return confirm('Batalkan pembelian {{ $p->purchase_no }}?\n\nStok yang masuk dari nota ini akan dikeluarkan kembali, dan uang yang sudah dibayar dikembalikan ke kas.')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Batal</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada pembelian tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links() }}</div>
    </div>

    {{-- Form pembelian --}}
    <div x-show="showForm" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-start justify-center p-4 overflow-y-auto">
        <div @click.outside="showForm=false" class="bg-white rounded-xl shadow-xl w-full max-w-3xl my-8">
            <h3 class="font-semibold text-lg p-6 pb-0">Catat Pembelian</h3>
            <form method="POST" action="{{ route('pembelian.store') }}" class="p-6 space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="form-label">Pemasok</label>
                        <select name="supplier_id" x-model="supplierId" class="form-select">
                            <option value="">- Tanpa pemasok -</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">No. Nota Pemasok</label>
                        <input type="text" name="supplier_invoice_no" class="form-input" placeholder="opsional">
                    </div>
                    <div>
                        <label class="form-label">Tanggal Beli</label>
                        <input type="date" name="purchase_date" value="{{ now()->toDateString() }}" required class="form-input">
                    </div>
                </div>

                <div class="border rounded-lg p-3 bg-slate-50 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-slate-600">Barang yang Dibeli</div>
                        <button type="button" @click="tambahBaris()" class="text-xs font-medium text-brand-600 border border-brand-200 rounded-full px-3 py-1 hover:bg-brand-50">+ Tambah Barang</button>
                    </div>

                    <template x-for="(row, index) in baris" :key="row._key">
                        <div class="border rounded-lg p-3 bg-white space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-[2fr_1fr_1fr_1fr] gap-2">
                                <div>
                                    <label class="form-label">Produk</label>
                                    <select :name="'items[' + row._key + '][product_id]'" x-model="row.product_id" @change="gantiProduk(row)" required class="form-select">
                                        <option value="">- Pilih produk -</option>
                                        <template x-for="p in products" :key="p.id">
                                            <option :value="p.id" x-text="p.name + ' (' + p.sku + ')'"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Satuan Beli</label>
                                    <select :name="'items[' + row._key + '][unit_type]'" x-model="row.unit_type" class="form-select">
                                        <template x-for="s in satuanProduk(row)" :key="s.value">
                                            <option :value="s.value" x-text="s.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Jumlah</label>
                                    <input type="text" data-jpos-number data-number-decimals="4" :name="'items[' + row._key + '][qty]'" x-number="row.qty" required class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">Harga Beli / satuan</label>
                                    <input type="text" data-jpos-number data-number-decimals="2" :name="'items[' + row._key + '][price]'" x-number="row.price" required class="form-input">
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500">
                                    Subtotal <span class="font-medium text-slate-700">Rp <span x-text="angka(subtotalBaris(row))"></span></span>
                                    <template x-if="modalPerSatuanDasar(row) > 0">
                                        <span> &middot; harga modal jadi <span class="font-medium text-slate-700">Rp <span x-text="angka(modalPerSatuanDasar(row))"></span></span>
                                        per <span x-text="produk(row.product_id) ? produk(row.product_id).unit : ''"></span></span>
                                    </template>
                                </span>
                                <button type="button" @click="hapusBaris(row._key)" class="text-red-500 hover:underline">Hapus</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Biaya Lain (ongkir, bongkar muat)</label>
                        <input type="text" data-jpos-number data-number-decimals="2" name="other_cost" x-number="biayaLain" class="form-input">
                        <p class="text-[11px] text-slate-400 mt-1">Dibagi ke tiap barang sebanding nilainya, supaya harga modal mencerminkan biaya sampai di toko.</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Subtotal barang</span><span>Rp <span x-text="angka(subtotal())"></span></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Biaya lain</span><span>Rp <span x-text="angka(biayaLain)"></span></span></div>
                        <div class="flex justify-between font-semibold border-t border-slate-200 mt-2 pt-2"><span>Total</span><span>Rp <span x-text="angka(total())"></span></span></div>
                    </div>
                </div>

                <div>
                    <label class="form-label">Cara Bayar</label>
                    <div class="flex rounded-lg border overflow-hidden text-sm">
                        <label class="flex-1 text-center py-2 cursor-pointer" :class="caraBayar === 'tunai' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                            <input type="radio" name="bayar" value="tunai" x-model="caraBayar" class="hidden"> Tunai / Lunas
                        </label>
                        <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="caraBayar === 'hutang' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                            <input type="radio" name="bayar" value="hutang" x-model="caraBayar" class="hidden"> Hutang / Bayar Nanti
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1" x-show="caraBayar === 'tunai'">Uang dicatat keluar dari kas sebesar total pembelian.</p>
                    <p class="text-[11px] text-slate-400 mt-1" x-show="caraBayar === 'hutang'" x-cloak>Sisanya tercatat sebagai hutang ke pemasok, dan bisa dicicil kapan saja.</p>
                </div>

                <template x-if="caraBayar === 'hutang'">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Bayar Sekarang (DP, opsional)</label>
                            <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" name="paid_amount" x-number="dibayar" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-input">
                        </div>
                    </div>
                </template>

                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-input" placeholder="opsional">
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" @click="showForm=false" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-primary" :disabled="baris.length === 0">Simpan Pembelian</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bayar hutang --}}
    <div x-show="showBayar" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showBayar=false" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
            <h3 class="font-semibold text-lg mb-1">Bayar Hutang</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="notaBayar ? notaBayar.no : ''"></p>
            <template x-if="notaBayar">
                <form :action="'{{ url('pembelian') }}/' + notaBayar.id + '/bayar'" method="POST" class="space-y-3">
                    @csrf
                    <div class="text-sm bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
                        Sisa hutang: <span class="font-semibold">Rp <span x-text="angka(notaBayar.sisa)"></span></span>
                    </div>
                    <div>
                        <label class="form-label">Jumlah Bayar</label>
                        <input type="text" data-jpos-number data-number-decimals="2" name="amount" x-number.oneway="notaBayar.sisa" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Tanggal Bayar</label>
                        <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Catatan</label>
                        <input type="text" name="note" class="form-input" placeholder="opsional">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showBayar=false" class="btn btn-outline">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
@endsection
