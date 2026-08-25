@extends('layouts.app')
@section('title', 'Laporan Laba Rugi')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', ['jenis' => 'laba-rugi', 'filter' => ['from' => $from, 'to' => $to]])
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <div>
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="from" value="{{ $from }}" class="form-input">
    </div>
    <div>
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="to" value="{{ $to }}" class="form-input">
    </div>
    <button class="btn btn-primary">Terapkan</button>
</form>

{{-- Susunan laba rugi yang lazim, dibaca dari atas ke bawah. Tiap baris menjelaskan dari
     mana angkanya, supaya bisa ditelusuri sendiri tanpa bertanya. --}}
<div class="card p-6 mb-4">
    <h3 class="font-semibold mb-1">Laba Rugi</h3>
    <p class="text-xs text-slate-400 mb-4">
        Periode {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}
        s/d {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}
    </p>

    <table class="w-full text-sm">
        <tbody>
            <tr>
                <td class="py-2">Omset bersih <span class="text-xs text-slate-400">(penjualan lunas &minus; retur, tanpa pajak)</span></td>
                <td class="py-2 text-right font-medium">Rp {{ number_format($summary->omset, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="py-2">Harga Pokok Penjualan <span class="text-xs text-slate-400">(harga modal barang yang terjual)</span></td>
                <td class="py-2 text-right text-red-600">&minus; Rp {{ number_format($summary->hpp, 0, ',', '.') }}</td>
            </tr>
            <tr class="border-t border-slate-200">
                <td class="py-2 font-semibold">Laba Kotor</td>
                <td class="py-2 text-right font-semibold">Rp {{ number_format($summary->laba_kotor, 0, ',', '.') }}</td>
            </tr>
            @if($summary->pendapatan_lain > 0)
            <tr>
                <td class="py-2">Pendapatan lain <span class="text-xs text-slate-400">(di luar penjualan)</span></td>
                <td class="py-2 text-right text-green-600">+ Rp {{ number_format($summary->pendapatan_lain, 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr>
                <td class="py-2">Beban operasional <span class="text-xs text-slate-400">(listrik, gaji, sewa, lain-lain)</span></td>
                <td class="py-2 text-right text-red-600">&minus; Rp {{ number_format($summary->beban, 0, ',', '.') }}</td>
            </tr>
            <tr class="border-t-2 border-slate-800">
                <td class="py-3 font-bold text-base">Laba Bersih</td>
                <td class="py-3 text-right font-bold text-base {{ $summary->laba_bersih >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    Rp {{ number_format($summary->laba_bersih, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    @if($pajak > 0)
    <p class="text-xs text-slate-400 mt-4 pt-3 border-t border-slate-100">
        Pajak dipungut dari pembeli selama periode ini: <span class="font-medium text-slate-600">Rp {{ number_format($pajak, 0, ',', '.') }}</span>.
        Ini titipan yang harus disetorkan, bukan pendapatan toko &mdash; karena itu tidak dihitung sebagai omset.
    </p>
    @endif
</div>

{{-- Penjelasan yang paling sering ditanyakan, ditaruh di tempat pertanyaannya muncul. --}}
<div class="card p-4 mb-4 bg-slate-50">
    <p class="text-xs text-slate-500 leading-relaxed">
        <span class="font-medium text-slate-700">Yang TIDAK dihitung di sini, dan alasannya:</span><br>
        &bull; <span class="font-medium">Modal yang Anda setor</span> dan <span class="font-medium">uang yang Anda ambil untuk keperluan pribadi</span> tidak muncul di laporan ini.
        Keduanya memindahkan uang antara Anda dan toko &mdash; tidak ada yang dihasilkan maupun dihabiskan. Keduanya ada di Neraca.<br>
        &bull; <span class="font-medium">Pembelian barang dagangan</span> juga bukan beban di sini. Uangnya berubah wujud jadi stok di rak;
        ia baru jadi beban saat barangnya terjual, dan pada saat itu dihitung sebagai HPP.<br>
        &bull; Pesanan yang <span class="font-medium">belum lunas</span> belum dihitung sebagai omset, karena barangnya belum benar-benar terjual.<br>
        &bull; Harga modal dibekukan saat barang terjual, jadi laporan periode ini akan menghasilkan angka yang sama berapa kali pun dibuka &mdash;
        walaupun harga modal produknya diperbarui besok.
    </p>
</div>

<div class="card overflow-hidden">
    <table class="data-table w-full">
        <thead>
            <tr>
                <th>Tanggal</th><th>Omset</th><th>HPP</th><th>Laba Kotor</th>
                <th>Beban</th><th>Laba Bersih</th>
            </tr>
        </thead>
        <tbody>
            @forelse($daily as $row)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                <td>Rp {{ number_format($row->omset, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row->hpp, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($row->laba_kotor, 0, ',', '.') }}</td>
                <td class="text-red-600">Rp {{ number_format($row->beban, 0, ',', '.') }}</td>
                <td class="font-medium {{ $row->laba_bersih >= 0 ? 'text-green-600' : 'text-red-600' }}">Rp {{ number_format($row->laba_bersih, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
