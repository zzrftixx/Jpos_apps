@extends('layouts.app')
@section('title', 'Cetak Barcode')

{{-- $semuaToken datang dari BarcodePrintController::index() dan mencakup SELURUH hasil
     penyaringan, bukan cuma halaman yang tampil. Tiap token mewakili SATU satuan, bukan satu
     produk: label rak untuk harga per Kg tidak boleh menempel di kemasan yang dijual per
     Gram. Bentuknya "{id}" untuk satuan dasar dan "{id}:{unit}" untuk satuan tambahan. --}}

@section('content')
{{-- Daftar tokennya TIDAK ditaruh di dalam x-data.

     x-data adalah atribut berpembatas kutip ganda, dan JSON penuh dengan kutip ganda -
     atributnya terpotong di tanda kutip pertama dan seluruh komponen Alpine mati (HUKUM
     H11). Ini sudah terbukti terjadi saat pengembangan fitur ini, dan yang menakutkan:
     gerbang tests/js/alpine-xdata.test.cjs TIDAK menangkapnya, karena ia membaca berkas
     Blade di mana @json(...) belum diperluas menjadi tanda kutip.

     Karena itu datanya dibaca dari <script type="application/json"> di bawah - yang
     sekaligus menyelesaikan masalah kedua: skrip itu berada DI DALAM blok yang ditukar
     pencarian langsung, jadi daftarnya selalu ikut menjadi baru. --}}
<div x-data="{ selected: [], semua: [], bacaSemua() { const el = this.$el.querySelector('[data-semua-token]'); this.semua = el ? JSON.parse(el.textContent) : []; } }"
     x-init="bacaSemua()"
     {{-- Sebelum ada pencarian langsung, setiap pencarian memuat ulang halaman dan
          pilihan centang otomatis kosong. Perilaku itu dipertahankan: pilihan direset
          saat daftar berganti, supaya produk dari hasil pencarian sebelumnya tidak
          ikut tercetak tanpa disadari. --}}
     @jpos:hasil-diperbarui.window="selected = []; bacaSemua()">
    <form method="GET" class="flex gap-2 mb-4" data-live-search>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari produk / barcode / SKU..." class="form-input w-64">
        <button class="btn btn-outline">Cari</button>
    </form>

    <p class="text-xs text-slate-400 -mt-2 mb-4">Produk bersatuan tambahan tampil satu baris per satuan &mdash; centang satuan mana saja yang labelnya mau dicetak, dan tiap label otomatis memakai harga satuan itu sendiri.</p>

    {{-- Blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    {{-- Daftar token SELURUH hasil penyaringan ditaruh DI DALAM blok yang ikut ditukar oleh
         pencarian langsung. Kalau ditaruh di luar (mis. hanya di x-data), setelah kasir
         mencari, "pilih semua" akan memilih token dari daftar SEBELUM pencarian - dan
         mencetak label produk yang tidak ada di layar. --}}
    <script type="application/json" data-semua-token>@json($semuaToken)</script>
    <div class="card overflow-hidden mb-4">
        <table class="data-table w-full">
            <thead>
                <tr>
                    {{-- :checked terikat ke keadaan sesungguhnya, bukan berdiri sendiri:
                         tanpa itu kotaknya tetap tercentang setelah pilihan direset atau
                         setelah satu baris dilepas, dan memberi kesan semuanya masih terpilih. --}}
                    <th><input type="checkbox"
                               :checked="selected.length === semua.length && semua.length > 0"
                               @change="selected = $event.target.checked ? semua.slice() : []"></th>
                    <th>Nama</th><th>Satuan</th><th>Barcode</th><th>Harga</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                @php $barisSatuan = 1 + $p->units->count(); @endphp
                <tr>
                    <td><input type="checkbox" value="{{ $p->id }}" x-model="selected"></td>
                    <td rowspan="{{ $barisSatuan }}" class="font-medium">{{ $p->name }}</td>
                    <td>{{ $p->unit }}</td>
                    {{-- Label memakai SKU kalau produk tidak punya barcode manual, jadi
                         barcode-nya tidak tercetak kosong dan tetap bisa dipindai. --}}
                    <td rowspan="{{ $barisSatuan }}" class="text-slate-500">
                        @if($p->barcode)
                            {{ $p->barcode }}
                        @else
                            {{ $p->sku }} <span class="text-xs text-slate-400">(otomatis dari SKU)</span>
                        @endif
                    </td>
                    <td>Rp {{ number_format($p->sell_price, 0, ',', '.') }}</td>
                </tr>
                @foreach($p->units as $pu)
                <tr class="bg-slate-50/70">
                    <td><input type="checkbox" value="{{ $p->id }}:{{ $pu->id }}" x-model="selected"></td>
                    <td class="text-slate-500">&#8618; {{ $pu->unit->name }}</td>
                    <td>Rp {{ number_format($pu->price, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                @empty
                <tr><td colspan="5" class="text-center text-slate-400 py-8">Tidak ada produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 mb-4">{{ $products->links() }}</div>
    </div>

    {{-- Jumlah total ditampilkan SEBELUM dicetak. Kertas yang sudah keluar tidak bisa
         ditarik kembali, jadi angka yang bikin kaget harus terlihat selagi masih bisa
         dibatalkan - bukan sesudah printer berjalan. --}}
    <div class="card p-4 flex items-center gap-3 flex-wrap"
         x-data="{ perLabel: 1, get total() { return this.selected.length * Math.max(1, this.perLabel || 1); } }">
        <label class="text-sm">Jumlah cetak per label:</label>
        <input type="text" data-jpos-number data-number-group="0" data-number-min="1" data-number-max="{{ \App\Http\Controllers\BarcodePrintController::MAKS_PER_LABEL }}"
               value="1" x-ref="qty" x-model.number="perLabel" class="form-input w-24">

        <button class="btn btn-primary" :disabled="selected.length === 0"
            @click="window.open('{{ route('barcode.print') }}?ids=' + selected.join(',') + '&qty=' + Math.max(1, perLabel || 1), '_blank')">
            🖨️ Cetak Label Terpilih (<span x-text="selected.length"></span> baris)
        </button>

        <span class="text-sm" :class="total > {{ \App\Http\Controllers\BarcodePrintController::MAKS_LABEL_SEKALI_CETAK }} ? 'text-red-600 font-medium' : 'text-slate-500'"
              x-show="selected.length > 0" x-cloak>
            Total <span x-text="total"></span> label
            <template x-if="total > {{ \App\Http\Controllers\BarcodePrintController::MAKS_LABEL_SEKALI_CETAK }}">
                <span>&mdash; melebihi batas {{ \App\Http\Controllers\BarcodePrintController::MAKS_LABEL_SEKALI_CETAK }} sekali cetak, sisanya tidak akan ikut tercetak.</span>
            </template>
        </span>
    </div>
</div>
@endsection
