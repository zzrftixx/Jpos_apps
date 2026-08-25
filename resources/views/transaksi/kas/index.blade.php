@extends('layouts.app')
@section('title', 'Kas Masuk/Keluar')

@section('content')
@if(auth()->user()->can_access('laporan'))
    <div class="flex justify-end mb-4">
        @include('laporan._ekspor', ['jenis' => 'kas', 'filter' => ['from' => request('from'), 'to' => request('to')]])
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
                <label class="form-label">Tipe</label>
                <select name="type" class="form-select">
                    <option value="">Semua</option>
                    <option value="in" {{ request('type') == 'in' ? 'selected' : '' }}>Kas Masuk</option>
                    <option value="out" {{ request('type') == 'out' ? 'selected' : '' }}>Kas Keluar</option>
                </select>
            </div>
            <button class="btn btn-primary">Terapkan</button>
        </form>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="card p-4"><div class="text-xs text-slate-500">Total Kas Masuk</div><div class="text-xl font-bold text-green-600">Rp {{ number_format($summary->total_in ?? 0, 0, ',', '.') }}</div></div>
            <div class="card p-4"><div class="text-xs text-slate-500">Total Kas Keluar</div><div class="text-xl font-bold text-red-600">Rp {{ number_format($summary->total_out ?? 0, 0, ',', '.') }}</div></div>
            <div class="card p-4"><div class="text-xs text-slate-500">Saldo (Masuk - Keluar)</div><div class="text-xl font-bold">Rp {{ number_format(($summary->total_in ?? 0) - ($summary->total_out ?? 0), 0, ',', '.') }}</div></div>
        </div>

        <div class="card overflow-hidden">
            <table class="data-table w-full">
                <thead><tr><th>Tanggal</th><th>Tipe</th><th>Kategori</th><th class="text-right">Jumlah</th><th>Keterangan</th><th>User</th><th></th></tr></thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr>
                        <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $t->type === 'in' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $t->type === 'in' ? 'Masuk' : 'Keluar' }}
                            </span>
                        </td>
                        <td>{{ $categories[$t->category] ?? $t->category }}</td>
                        <td class="text-right {{ $t->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $t->type === 'in' ? '+' : '-' }} Rp {{ number_format($t->amount, 0, ',', '.') }}
                        </td>
                        <td class="text-slate-500">{{ $t->note ?: '-' }}</td>
                        <td>{{ $t->user->name ?? '-' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('kas.destroy', $t) }}" onsubmit="return confirm('Hapus catatan kas ini?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 text-sm hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada catatan kas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
