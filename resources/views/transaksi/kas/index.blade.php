@extends('layouts.app')
@section('title', 'Kas Masuk/Keluar')

@section('content')
@if(auth()->user()->can_access('laporan'))
    <div class="flex justify-end mb-4">
        @include('laporan._ekspor', ['jenis' => 'kas', 'filter' => array_filter(['from' => request('from'), 'to' => request('to'), 'type' => request('type')])])
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-1">
        {{-- Keterangan kategori ditanam ke Alpine supaya penjelasannya muncul tepat saat
             kategorinya dipilih - bukan di catatan kaki yang tidak akan pernah dibaca. --}}
        <div class="card p-4" x-data="{
            type: 'out',
            kategori: '{{ array_key_first($categories) }}',
            keterangan: {{ Illuminate\Support\Js::from($keteranganKategori) }},
            get belanjaAset() { return this.kategori === 'aset_tetap' },
            pilihKategori(k) { this.kategori = k; if (k === 'aset_tetap') this.type = 'out' },
        }">
            <h3 class="font-semibold mb-3">Catat Transaksi Kas</h3>
            <div class="mb-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 leading-relaxed flex items-start gap-2">
                <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <span class="font-semibold">Penjualan Kasir Otomatis Masuk:</span> Uang penjualan dari kasir sudah langsung dicatat sebagai Kas Masuk. Form ini hanya untuk mutasi kas di luar kasir (biaya operasional, modal, prive, gaji, pembelian barang, dll).
                </div>
            </div>
            <form method="POST" action="{{ route('kas.store') }}" class="space-y-3">
                @csrf
                <div class="flex rounded-lg border overflow-hidden text-sm">
                    <label class="flex-1 text-center py-2" :class="belanjaAset ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : (type === 'in' ? 'bg-green-500 text-white cursor-pointer' : 'bg-white text-slate-600 cursor-pointer')">
                        <input type="radio" name="type" value="in" x-model="type" class="hidden" :disabled="belanjaAset"> Kas Masuk
                    </label>
                    <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="type === 'out' ? 'bg-red-500 text-white' : 'bg-white text-slate-600'">
                        <input type="radio" name="type" value="out" x-model="type" class="hidden"> Kas Keluar
                    </label>
                </div>
                <div>
                    <label class="form-label">Kategori</label>
                    <select name="category" x-model="kategori" @change="pilihKategori($event.target.value)" class="form-select" required>
                        @foreach($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1 leading-snug" x-text="keterangan[kategori] || ''"></p>
                </div>
                {{-- Isian aset tetap. Muncul hanya untuk kategori Beli Peralatan, karena hanya
                     kategori itu yang menggerakkan DUA sisi neraca sekaligus: uang keluar dan
                     aset bertambah. Keduanya ditulis dalam satu transaksi database. --}}
                <div x-show="belanjaAset" x-cloak class="space-y-3 rounded-lg bg-slate-50 border border-slate-200 p-3">
                    <p class="text-[11px] text-slate-600 leading-snug">
                        Peralatan bukan beban bulan ini &mdash; uangnya berubah wujud jadi barang yang
                        dipakai bertahun-tahun. Bebannya dicicil lewat penyusutan.
                    </p>
                    <div>
                        <label class="form-label">Nama Peralatan</label>
                        <input type="text" name="nama_aset" class="form-input" placeholder="Etalase kaca"
                               value="{{ old('nama_aset') }}" :required="belanjaAset">
                    </div>
                    <div>
                        <label class="form-label">Tanggal Perolehan</label>
                        <input type="date" name="acquired_at" class="form-input"
                               value="{{ old('acquired_at', now()->toDateString()) }}" :required="belanjaAset">
                    </div>
                    <div>
                        <label class="form-label">Umur Manfaat (bulan)</label>
                        <input type="number" name="useful_life_months" class="form-input" min="1" max="600"
                               value="{{ old('useful_life_months') }}" placeholder="kosongkan bila tidak menyusut">
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">
                            Diisi berarti nilainya disusutkan merata tiap bulan. Komputer 6 juta dengan umur
                            36 bulan menyusut 166.667 per bulan. Dikosongkan berarti tidak disusutkan &mdash;
                            wajar untuk etalase kaca.
                        </p>
                    </div>
                </div>

                <div>
                    <label class="form-label" x-text="belanjaAset ? 'Harga Perolehan' : 'Jumlah'">Jumlah</label>
                    <input type="text" data-jpos-number data-number-decimals="2" data-number-empty="kosong" name="amount" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Keterangan (opsional)</label>
                    <textarea name="note" class="form-textarea" rows="2" placeholder="Contoh: bayar listrik bulan Juli"></textarea>
                </div>
                <button class="w-full btn btn-primary justify-center">Simpan</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
            <div>
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="from" value="{{ $from }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="to" value="{{ $to }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Tipe Mutasi</label>
                <select name="type" class="form-select">
                    <option value="">Semua Mutasi</option>
                    <option value="in" {{ request('type') == 'in' ? 'selected' : '' }}>Semua Kas Masuk</option>
                    <option value="sale" {{ request('type') == 'sale' ? 'selected' : '' }}>Kas Masuk - Penjualan Kasir</option>
                    <option value="manual_in" {{ request('type') == 'manual_in' ? 'selected' : '' }}>Kas Masuk - Non-Penjualan (Manual)</option>
                    <option value="out" {{ request('type') == 'out' ? 'selected' : '' }}>Semua Kas Keluar</option>
                    <option value="manual_out" {{ request('type') == 'manual_out' ? 'selected' : '' }}>Kas Keluar - Non-Retur (Manual/Beban)</option>
                    <option value="return" {{ request('type') == 'return' ? 'selected' : '' }}>Kas Keluar - Refund Retur</option>
                </select>
            </div>
            <button class="btn btn-primary">Terapkan</button>
        </form>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-slate-500 font-medium">Total Kas Masuk</div>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-semibold">Periode</span>
                </div>
                <div class="text-xl font-bold text-green-600 mt-1">Rp {{ number_format($summary->total_in ?? 0, 0, ',', '.') }}</div>
                <div class="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 space-y-0.5">
                    <div class="flex justify-between">
                        <span>Penjualan Kasir:</span>
                        <strong class="text-slate-700">Rp {{ number_format($summary->penjualan ?? 0, 0, ',', '.') }}</strong>
                    </div>
                    @if(!empty($summary->penjualan_per_metode))
                    <div class="text-[10px] text-slate-400 pl-1">
                        @foreach($summary->penjualan_per_metode as $m => $val)
                            {{ \App\Support\MetodeBayar::label($m) }}: {{ number_format($val, 0, ',', '.') }}{{ !$loop->last ? ' • ' : '' }}
                        @endforeach
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span>Kas Masuk Manual:</span>
                        <strong class="text-slate-700">Rp {{ number_format($summary->manual_in ?? 0, 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-slate-500 font-medium">Total Kas Keluar</div>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-semibold">Periode</span>
                </div>
                <div class="text-xl font-bold text-red-600 mt-1">Rp {{ number_format($summary->total_out ?? 0, 0, ',', '.') }}</div>
                <div class="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 space-y-0.5">
                    <div class="flex justify-between">
                        <span>Beban & Pembelian:</span>
                        <strong class="text-slate-700">Rp {{ number_format($summary->manual_out ?? 0, 0, ',', '.') }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Refund Retur:</span>
                        <strong class="text-slate-700">Rp {{ number_format($summary->refund_retur ?? 0, 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-slate-500 font-medium">Saldo Periode</div>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 font-semibold">Masuk - Keluar</span>
                </div>
                <div class="text-xl font-bold mt-1 {{ ($summary->saldo_periode ?? 0) >= 0 ? 'text-slate-800' : 'text-red-600' }}">
                    Rp {{ number_format($summary->saldo_periode ?? 0, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400 mt-2 pt-2 border-t border-slate-100">
                    Pergerakan kas bersih toko selama periode yang dipilih.
                </div>
            </div>

            <div class="card p-4 bg-slate-50 border-slate-200">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-slate-600 font-medium">Saldo Kas Toko (Riil)</div>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-semibold">Neraca</span>
                </div>
                <div class="text-xl font-bold text-primary-700 mt-1">
                    Rp {{ number_format($summary->saldo_kas_toko ?? 0, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-200">
                    Posisi uang kas toko saat ini (termasuk saldo awal & mutasi).
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Kategori / Sumber</th>
                        <th class="text-right">Jumlah</th>
                        <th>Keterangan</th>
                        <th>Petugas</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr>
                        <td class="whitespace-nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $t->type === 'in' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $t->type === 'in' ? 'Masuk' : 'Keluar' }}
                            </span>
                        </td>
                        <td>
                            <div class="font-medium text-slate-800">{{ $t->category_label }}</div>
                            @if($t->source_type === 'sale')
                                <span class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider">Kasir POS</span>
                            @elseif($t->source_type === 'sale_return')
                                <span class="text-[10px] text-rose-600 font-semibold uppercase tracking-wider">Retur Refund</span>
                            @else
                                <span class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">Buku Kas</span>
                            @endif
                        </td>
                        <td class="text-right font-semibold whitespace-nowrap {{ $t->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $t->type === 'in' ? '+' : '-' }} Rp {{ number_format($t->amount, 0, ',', '.') }}
                        </td>
                        <td class="text-slate-600 text-sm max-w-xs truncate" title="{{ $t->display_note }}">
                            {{ $t->display_note }}
                        </td>
                        <td class="text-slate-600 text-sm whitespace-nowrap">{{ $t->user_name ?? '-' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if($t->source_type === 'sale')
                                <a href="{{ route('kasir.receipt', $t->reference_id) }}" target="_blank" class="text-primary-600 hover:underline text-xs inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Struk
                                </a>
                            @elseif($t->source_type === 'cash_transaction')
                                <form method="POST" action="{{ route('kas.destroy', $t->reference_id) }}" onsubmit="return confirm('Hapus catatan kas ini?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 text-sm hover:underline">Hapus</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada catatan mutasi kas pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
