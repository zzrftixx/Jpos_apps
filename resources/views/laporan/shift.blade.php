@extends('layouts.app')
@section('title', 'Laporan Transaksi per Shift')

@section('content')
@include('laporan._tabs')

<div x-data="{
    viewMode: 'semua',
    expandedSales: {},
    toggleSale(id) {
        this.expandedSales[id] = !this.expandedSales[id];
    },
    bukaSemua(ids) {
        ids.forEach(id => this.expandedSales[id] = true);
    },
    tutupSemua(ids) {
        ids.forEach(id => this.expandedSales[id] = false);
    },
    semuaTerbuka(ids) {
        return ids.length > 0 && ids.every(id => !!this.expandedSales[id]);
    }
}" class="space-y-5">

    {{-- HEADER & EKSPOR --}}
    <div class="flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Laporan Transaksi per Shift</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Rekapitulasi lengkap sesi kasir, penerimaan metode bayar, mutasi kas laci, dan rincian item produk terjual.
            </p>
        </div>
        @include('laporan._ekspor', ['jenis' => 'shift', 'filter' => array_filter([
            'from' => $from, 'to' => $to, 'user_id' => $userId, 'shift_id' => $shiftId, 'status' => $status,
        ])])
    </div>

    {{-- FORM FILTER --}}
    <form method="GET" action="{{ route('laporan.shift') }}" class="card p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label class="form-label text-xs font-semibold text-slate-600">Dari Tanggal</label>
                <input type="date" name="from" value="{{ $from }}" class="form-input text-xs">
            </div>
            <div>
                <label class="form-label text-xs font-semibold text-slate-600">Sampai Tanggal</label>
                <input type="date" name="to" value="{{ $to }}" class="form-input text-xs">
            </div>
            <div>
                <label class="form-label text-xs font-semibold text-slate-600">Pilih Kasir</label>
                <select name="user_id" class="form-select text-xs">
                    <option value="">Semua Kasir</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ $userId == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-xs font-semibold text-slate-600">Pilih Shift</label>
                <select name="shift_id" class="form-select text-xs">
                    <option value="">Semua Shift ({{ $daftarShift->count() }} shift)</option>
                    @foreach($daftarShift as $ds)
                        <option value="{{ $ds->id }}" {{ $shiftId == $ds->id ? 'selected' : '' }}>
                            #{{ $ds->id }} &middot; {{ $ds->user->name ?? 'Kasir' }} ({{ $ds->opened_at->format('d/m H:i') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-xs font-semibold text-slate-600">Status Shift</label>
                <select name="status" class="form-select text-xs">
                    <option value="">Semua Status</option>
                    <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Open (Berjalan)</option>
                    <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Closed (Selesai)</option>
                </select>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mt-3 pt-3 border-t border-slate-100">
            <p class="text-[11px] text-slate-400">
                * Ringkasan &amp; rincian transaksi hanya memuat transaksi aktif (transaksi dibatalkan dikeluarkan dari laporan).
            </p>
            <div class="flex items-center gap-2 self-end sm:self-auto">
                @if($userId || $shiftId || $status || request('from') || request('to'))
                    <a href="{{ route('laporan.shift') }}" class="btn btn-outline text-xs py-1.5 px-3">Reset Filter</a>
                @endif
                <button type="submit" class="btn btn-primary text-xs py-1.5 px-4 font-semibold">Terapkan Filter</button>
            </div>
        </div>
    </form>

    {{-- KARTU METRIK RINGKASAN AKUMULATIF --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="card p-3.5">
            <div class="text-xs text-slate-500 font-medium">Total Shift</div>
            <div class="text-xl font-bold text-slate-800 mt-1">{{ $summary->shift_count }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $summary->closed_shift_count }} Selesai &middot; {{ $summary->open_shift_count }} Aktif</div>
        </div>
        <div class="card p-3.5">
            <div class="text-xs text-slate-500 font-medium">Total Penjualan Shift</div>
            <div class="text-xl font-bold text-green-700 mt-1">Rp {{ number_format($summary->total_sales, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $summary->total_trx }} transaksi aktif</div>
        </div>
        <div class="card p-3.5">
            <div class="text-xs text-slate-500 font-medium">Penerimaan Tunai</div>
            <div class="text-xl font-bold text-slate-800 mt-1">Rp {{ number_format($summary->total_cash, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">pembayaran kas masuk</div>
        </div>
        <div class="card p-3.5">
            <div class="text-xs text-slate-500 font-medium">Penerimaan Non-Tunai</div>
            <div class="text-xl font-bold text-blue-700 mt-1">Rp {{ number_format($summary->total_non_cash, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">transfer, qris, kartu debit/kredit</div>
        </div>
        <div class="card p-3.5">
            <div class="text-xs text-slate-500 font-medium">Total Selisih Kas Laci</div>
            <div class="text-xl font-bold mt-1 font-mono">
                @if(abs($summary->total_difference) < 0.01)
                    <span class="text-green-700">Rp 0 (Pas)</span>
                @elseif($summary->total_difference > 0)
                    <span class="text-blue-700">+Rp {{ number_format($summary->total_difference, 0, ',', '.') }}</span>
                @else
                    <span class="text-red-700">-Rp {{ number_format(abs($summary->total_difference), 0, ',', '.') }}</span>
                @endif
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">rekonsiliasi fisik shift selesai</div>
        </div>
    </div>

    {{-- BILAH PENGALIH MODE TAMPILAN (REKAP VS RINCIAN LENGKAP) --}}
    <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200 pb-2">
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs">
            <button type="button" @click="viewMode = 'semua'"
                    :class="viewMode === 'semua' ? 'bg-white text-slate-800 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <span>Semua (Rekap &amp; Rincian)</span>
            </button>
            <button type="button" @click="viewMode = 'rekap'"
                    :class="viewMode === 'rekap' ? 'bg-white text-slate-800 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Tabel Rekap Shift</span>
            </button>
            <button type="button" @click="viewMode = 'rincian'"
                    :class="viewMode === 'rincian' ? 'bg-white text-slate-800 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>Rincian Transaksi Lengkap</span>
            </button>
        </div>

        <div class="text-xs text-slate-500">
            Menampilkan <strong class="text-slate-700">{{ $shifts->total() }}</strong> shift pada periode filter
        </div>
    </div>

    {{-- =========================================================================
         BAGIAN 1: TABEL REKAPITULASI SHIFT (REKAP)
         ========================================================================= --}}
    <div x-show="viewMode === 'semua' || viewMode === 'rekap'" x-transition class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span>Tabel Rekapitulasi Shift Kasir</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Matriks perbandingan performa, omset, dan rekonsiliasi kas laci per sesi shift.</p>
            </div>
            <span class="text-xs text-slate-500 font-mono">{{ $rekapShifts->count() }} sesi shift</span>
        </div>

        <div class="card overflow-hidden border border-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-100 text-slate-700 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200 select-none">
                        <tr>
                            <th class="py-3 px-3 w-12 text-center">Shift</th>
                            <th class="py-3 px-3">Kasir</th>
                            <th class="py-3 px-3">Waktu Buka / Tutup</th>
                            <th class="py-3 px-3 text-center">Durasi</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-right">Modal Awal</th>
                            <th class="py-3 px-3 text-right">Penjualan Tunai</th>
                            <th class="py-3 px-3 text-right">Non-Tunai</th>
                            <th class="py-3 px-3 text-right">Total Omset</th>
                            <th class="py-3 px-3 text-right">Fisik Laci</th>
                            <th class="py-3 px-3 text-center">Selisih</th>
                            <th class="py-3 px-3 text-center">Trx</th>
                            <th class="py-3 px-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $sumRekapModal = 0;
                            $sumRekapTunai = 0;
                            $sumRekapNonTunai = 0;
                            $sumRekapTotal = 0;
                            $sumRekapFisik = 0;
                            $sumRekapTrx = 0;
                        @endphp
                        @forelse($rekapShifts as $rs)
                            @php
                                $calc = $rs->calculated ?? $rs->calculateSummary();
                                $isClosed = $rs->status === 'closed';
                                $diff = $isClosed ? (float) ($rs->difference ?? 0) : null;
                                $durasi = $rs->closed_at
                                    ? ($rs->opened_at->diffInHours($rs->closed_at) . 'j ' . ($rs->opened_at->diffInMinutes($rs->closed_at) % 60) . 'm')
                                    : 'Berjalan';
                                
                                $sumRekapModal += (float) $rs->starting_cash;
                                $sumRekapTunai += (float) ($calc['cash_sales'] ?? $rs->cash_sales);
                                $sumRekapNonTunai += (float) ($calc['non_cash_sales'] ?? $rs->non_cash_sales);
                                $sumRekapTotal += (float) ($calc['total_sales'] ?? ($rs->cash_sales + $rs->non_cash_sales));
                                $sumRekapFisik += (float) ($rs->actual_cash ?? 0);
                                $sumRekapTrx += (int) ($calc['transaction_count'] ?? 0);
                            @endphp
                            <tr class="hover:bg-blue-50/40 transition">
                                <td class="py-2.5 px-3 text-center font-mono font-bold text-slate-500">
                                    #{{ $rs->id }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="font-bold text-slate-800">{{ $rs->user->name ?? 'Kasir #' . $rs->user_id }}</span>
                                    @if($rs->notes)
                                        <div class="text-[10px] text-slate-400 truncate max-w-[140px]" title="{{ $rs->notes }}">{{ $rs->notes }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <div class="font-semibold text-slate-700">{{ $rs->opened_at->format('d/m/Y H:i') }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $rs->closed_at ? $rs->closed_at->format('d/m/Y H:i') : 'Masih berjalan' }}
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                                    {{ $durasi }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    @if($rs->status === 'open')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                            <span>Aktif</span>
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-700">
                                            Selesai
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-700">
                                    Rp {{ number_format($rs->starting_cash, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-green-700">
                                    Rp {{ number_format($calc['cash_sales'] ?? $rs->cash_sales, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-blue-700">
                                    Rp {{ number_format($calc['non_cash_sales'] ?? $rs->non_cash_sales, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                    Rp {{ number_format($calc['total_sales'] ?? ($rs->cash_sales + $rs->non_cash_sales), 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-800">
                                    {{ $rs->actual_cash !== null ? 'Rp ' . number_format($rs->actual_cash, 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono">
                                    @if($isClosed)
                                        @if(abs($diff) < 0.01)
                                            <span class="inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">PAS</span>
                                        @elseif($diff > 0)
                                            <span class="inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">+Rp {{ number_format($diff, 0, ',', '.') }}</span>
                                        @else
                                            <span class="inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">-Rp {{ number_format(abs($diff), 0, ',', '.') }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono font-bold text-slate-700">
                                    {{ $calc['transaction_count'] ?? 0 }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="#shift-card-{{ $rs->id }}" @click="viewMode = 'semua'"
                                           class="btn btn-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded"
                                           title="Lompat ke rincian shift ini">
                                            Rincian &darr;
                                        </a>
                                        <a href="{{ route('shift.print', $rs->id) }}" target="_blank"
                                           class="btn btn-xs bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-1 rounded"
                                           title="Cetak Slip Rekap Shift">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="py-8 text-center text-slate-400 italic">
                                    Tidak ada data shift pada rentang waktu atau filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($rekapShifts->count() > 0)
                        <tfoot class="bg-slate-100 font-bold border-t-2 border-slate-300">
                            <tr>
                                <td colspan="5" class="py-3 px-3 text-slate-800 uppercase text-[10px] tracking-wider">
                                    TOTAL SELURUH SHIFT ({{ $rekapShifts->count() }} shift)
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-slate-800">
                                    Rp {{ number_format($sumRekapModal, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-green-800">
                                    Rp {{ number_format($sumRekapTunai, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-blue-800">
                                    Rp {{ number_format($sumRekapNonTunai, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-green-900 text-sm">
                                    Rp {{ number_format($sumRekapTotal, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-slate-800">
                                    Rp {{ number_format($sumRekapFisik, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3 text-center font-mono">
                                    @if(abs($summary->total_difference) < 0.01)
                                        <span class="text-green-700">PAS</span>
                                    @elseif($summary->total_difference > 0)
                                        <span class="text-blue-700">+Rp {{ number_format($summary->total_difference, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-red-700">-Rp {{ number_format(abs($summary->total_difference), 0, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center font-mono text-slate-800">
                                    {{ $sumRekapTrx }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         BAGIAN 2: RINCIAN LENGKAP TRANSAKSI PER SHIFT (LENGKAP)
         ========================================================================= --}}
    <div x-show="viewMode === 'semua' || viewMode === 'rincian'" x-transition class="space-y-6 pt-2">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <span>Rincian Transaksi Lengkap per Shift</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Rincian item barang terjual, breakdown metode pembayaran, dan mutasi kas laci per shift.</p>
            </div>
        </div>

        @forelse($shifts as $s)
            @php
                $calc = $s->calculated ?? $s->calculateSummary();
                $isClosed = $s->status === 'closed';
                $diff = (float) ($s->difference ?? 0);
                $shiftSales = $s->sales;
                $trxCount = $shiftSales->count();
                $trxTotal = (float) $shiftSales->sum('total');
                $trxDiskon = (float) $shiftSales->sum('discount');
                $trxPajak = (float) $shiftSales->sum('tax_amount');
                $saleIds = $shiftSales->pluck('id')->all();
            @endphp

            <div id="shift-card-{{ $s->id }}" class="card overflow-hidden border border-slate-200 shadow-xs">
                {{-- Header Kartu Shift --}}
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-base text-slate-900 font-mono">Shift #{{ $s->id }}</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="font-semibold text-slate-800 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>{{ $s->user->name ?? 'Kasir #' . $s->user_id }}</span>
                            </span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="text-xs text-slate-600">
                                {{ $s->opened_at->format('d/m/Y H:i') }}
                                &rarr;
                                {{ $s->closed_at ? $s->closed_at->format('d/m/Y H:i') : 'Sekarang (Aktif)' }}
                                @if($s->closed_at)
                                    <span class="text-slate-400 font-normal">({{ $s->opened_at->diffInHours($s->closed_at) }} jam {{ $s->opened_at->diffInMinutes($s->closed_at) % 60 }} mnt)</span>
                                @endif
                            </span>
                            @if($s->status === 'open')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    <span>Aktif</span>
                                </span>
                            @else
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-700">
                                    Selesai
                                </span>
                            @endif
                        </div>
                        @if($s->notes)
                            <div class="text-xs text-slate-500 italic">
                                Catatan: {{ $s->notes }}
                            </div>
                        @endif
                    </div>

                    {{-- Metrik Kas Shift & Tombol Cetak --}}
                    <div class="flex items-center gap-2.5 flex-wrap text-xs">
                        <div class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
                            <span class="text-slate-400 text-[10px] uppercase font-bold block">Modal Awal</span>
                            <span class="font-mono font-bold text-slate-800">Rp {{ number_format($s->starting_cash, 0, ',', '.') }}</span>
                        </div>
                        <div class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
                            <span class="text-slate-400 text-[10px] uppercase font-bold block">Penjualan Tunai</span>
                            <span class="font-mono font-bold text-green-700">Rp {{ number_format($calc['cash_sales'] ?? $s->cash_sales, 0, ',', '.') }}</span>
                        </div>
                        <div class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
                            <span class="text-slate-400 text-[10px] uppercase font-bold block">Non-Tunai</span>
                            <span class="font-mono font-bold text-blue-700">Rp {{ number_format($calc['non_cash_sales'] ?? $s->non_cash_sales, 0, ',', '.') }}</span>
                        </div>
                        @if($isClosed)
                            <div class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
                                <span class="text-slate-400 text-[10px] uppercase font-bold block">Fisik Laci</span>
                                <span class="font-mono font-bold text-slate-800">{{ $s->actual_cash !== null ? 'Rp ' . number_format($s->actual_cash, 0, ',', '.') : '-' }}</span>
                            </div>
                            <div class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
                                <span class="text-slate-400 text-[10px] uppercase font-bold block">Selisih Kas</span>
                                <span class="font-mono font-bold">
                                    @if(abs($diff) < 0.01)
                                        <span class="text-green-700">PAS</span>
                                    @elseif($diff > 0)
                                        <span class="text-blue-700">+Rp {{ number_format($diff, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-red-700">-Rp {{ number_format(abs($diff), 0, ',', '.') }}</span>
                                    @endif
                                </span>
                            </div>
                        @endif
                        <a href="{{ route('shift.print', $s->id) }}" target="_blank"
                           class="btn btn-outline text-xs py-1.5 px-3 font-semibold flex items-center gap-1.5 bg-white shadow-2xs"
                           title="Cetak Slip Rekap Shift Kasir">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>Slip Z-Report</span>
                        </a>
                    </div>
                </div>

                {{-- PANEL DUAL: REKAP METODE PEMBAYARAN & REKAP KAS LACI SHIFT --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3.5 bg-slate-50/50 border-b border-slate-200 text-xs">
                    {{-- Box Kiri: Penerimaan per Metode Pembayaran --}}
                    <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                        <div class="font-bold text-slate-700 mb-2 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                <span>Rekap Penerimaan Metode Bayar</span>
                            </span>
                            <span class="text-green-700 font-mono font-bold">
                                Total Rp {{ number_format(($calc['total_sales'] ?? $trxTotal), 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @php $pBreakdown = $calc['payment_breakdown'] ?? []; @endphp
                            @forelse(\App\Support\MetodeBayar::pilihan() as $kunci => $labelMetode)
                                @php $nominalMetode = (float) ($pBreakdown[$kunci] ?? 0); @endphp
                                @continue($nominalMetode <= 0 && $kunci === 'lainnya')
                                <div class="border rounded-md px-2.5 py-1.5 {{ \App\Support\MetodeBayar::kelas($kunci) }}">
                                    <div class="text-[10px] font-semibold opacity-80">{{ $labelMetode }}</div>
                                    <div class="font-bold font-mono text-xs mt-0.5">Rp {{ number_format($nominalMetode, 0, ',', '.') }}</div>
                                </div>
                            @empty
                                <div class="text-slate-400 italic">Belum ada pembayaran.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Box Kanan: Mutasi Kas Laci & Rekonsiliasi --}}
                    <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs">
                        <div class="font-bold text-slate-700 mb-2 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span>Rekap Aliran Kas &amp; Rekonsiliasi Laci</span>
                            </span>
                            <span class="text-slate-500 font-mono">
                                {{ $isClosed ? 'Shift Ditutup' : 'Shift Masih Aktif' }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                            <div class="border rounded-md px-2 py-1 bg-slate-50 border-slate-200">
                                <span class="text-slate-400 block text-[9px] uppercase font-bold">Modal Awal</span>
                                <span class="font-mono font-bold text-slate-800">Rp {{ number_format($calc['starting_cash'] ?? $s->starting_cash, 0, ',', '.') }}</span>
                            </div>
                            <div class="border rounded-md px-2 py-1 bg-green-50 border-green-200">
                                <span class="text-green-600 block text-[9px] uppercase font-bold">Penjualan Kas</span>
                                <span class="font-mono font-bold text-green-800">+Rp {{ number_format($calc['cash_sales'] ?? $s->cash_sales, 0, ',', '.') }}</span>
                            </div>
                            <div class="border rounded-md px-2 py-1 bg-slate-50 border-slate-200">
                                <span class="text-slate-400 block text-[9px] uppercase font-bold">Kas Masuk/Keluar</span>
                                <span class="font-mono font-bold text-slate-700">
                                    +{{ number_format($calc['cash_in'] ?? 0, 0, ',', '.') }} / -{{ number_format($calc['cash_out'] ?? 0, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="border rounded-md px-2 py-1 bg-blue-50 border-blue-200">
                                <span class="text-blue-600 block text-[9px] uppercase font-bold">Seharusnya di Laci</span>
                                <span class="font-mono font-bold text-blue-900">Rp {{ number_format($calc['expected_cash'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Toolbar Tabel Transaksi: Tombol Buka/Tutup Semua Item --}}
                <div class="px-4 py-2 bg-slate-100/80 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="font-bold text-slate-700 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Daftar Transaksi Penjualan ({{ $trxCount }} transaksi)</span>
                    </div>
                    @if($trxCount > 0)
                        <div class="flex items-center gap-2">
                            <button type="button" @click="bukaSemua({{ json_encode($saleIds) }})"
                                    class="text-[11px] font-semibold text-brand-600 hover:text-brand-800 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                <span>Buka Semua Item Barang</span>
                            </button>
                            <span class="text-slate-300">|</span>
                            <button type="button" @click="tutupSemua({{ json_encode($saleIds) }})"
                                    class="text-[11px] font-semibold text-slate-500 hover:text-slate-700 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                <span>Tutup Semua Item</span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Tabel Transaksi di dalam Shift --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200 select-none">
                            <tr>
                                <th class="py-2.5 px-3 w-10 text-center">#</th>
                                <th class="py-2.5 px-3">Waktu</th>
                                <th class="py-2.5 px-3">No. Invoice</th>
                                <th class="py-2.5 px-3">Pelanggan</th>
                                <th class="py-2.5 px-3">Metode Bayar</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3 text-center">Item Produk</th>
                                <th class="py-2.5 px-3 text-right">Diskon</th>
                                <th class="py-2.5 px-3 text-right">Pajak</th>
                                <th class="py-2.5 px-3 text-right">Total</th>
                                <th class="py-2.5 px-3 text-center w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($shiftSales as $idx => $sale)
                                @php $itemCount = $sale->items->count(); @endphp
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-2.5 px-3 text-center text-slate-400 font-mono">{{ $idx + 1 }}</td>
                                    <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-800">{{ $sale->invoice_no }}</td>
                                    <td class="py-2.5 px-3 text-slate-700">{{ $sale->customer->name ?? 'Umum' }}</td>
                                    <td class="py-2.5 px-3">
                                        @php
                                            $metodes = $sale->payments->isEmpty()
                                                ? [$sale->payment_method]
                                                : $sale->payments->pluck('method')->unique()->all();
                                        @endphp
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($metodes as $m)
                                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold {{ \App\Support\MetodeBayar::kelas($m) }}">
                                                    {{ \App\Support\MetodeBayar::label($m) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @if($sale->order_status === 'completed')
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">Selesai</span>
                                        @elseif($sale->order_status === 'waiting')
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Menunggu DP</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button" @click="toggleSale({{ $sale->id }})"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-100 font-semibold text-[11px] shadow-2xs transition">
                                            <span>{{ $itemCount }} item</span>
                                            <svg class="w-3 h-3 transition-transform" :class="expandedSales[{{ $sale->id }}] ? 'rotate-180 text-brand-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-600">
                                        {{ $sale->discount > 0 ? 'Rp ' . number_format($sale->discount, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-600">
                                        {{ $sale->tax_amount > 0 ? 'Rp ' . number_format($sale->tax_amount, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                        Rp {{ number_format($sale->total, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <a href="{{ route('kasir.receipt', $sale->id) }}" target="_blank"
                                           class="btn btn-xs bg-white hover:bg-slate-100 text-slate-700 font-semibold border border-slate-300 px-2 py-0.5 rounded flex items-center justify-center gap-1"
                                           title="Cetak Struk Transaksi">
                                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            <span>Struk</span>
                                        </a>
                                    </td>
                                </tr>

                                {{-- SUB-ROW: RINCIAN ITEM PRODUK DALAM INVOICE INI --}}
                                <tr x-show="expandedSales[{{ $sale->id }}]" x-cloak class="bg-blue-50/40 border-b border-blue-100">
                                    <td colspan="11" class="py-3 px-4 pl-12">
                                        <div class="bg-white rounded-lg border border-blue-200 overflow-hidden shadow-2xs">
                                            <div class="bg-slate-100 px-3 py-1.5 font-bold text-[11px] text-slate-700 flex items-center justify-between border-b border-slate-200">
                                                <span>Rincian Produk &bull; {{ $sale->invoice_no }} ({{ $itemCount }} macam barang)</span>
                                                <span class="text-slate-500 font-normal">Waktu: {{ $sale->created_at->format('d/m/Y H:i:s') }}</span>
                                            </div>
                                            <table class="w-full text-xs">
                                                <thead class="bg-slate-50 text-slate-600 font-semibold text-[10px] uppercase tracking-wider border-b border-slate-200">
                                                    <tr>
                                                        <th class="py-1.5 px-3 w-8 text-center">#</th>
                                                        <th class="py-1.5 px-3">Nama Produk / Barang</th>
                                                        <th class="py-1.5 px-3 text-center">Kuantiti</th>
                                                        <th class="py-1.5 px-3 text-right">Harga Satuan</th>
                                                        <th class="py-1.5 px-3 text-right">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($sale->items as $itIdx => $item)
                                                        <tr class="hover:bg-slate-50/70">
                                                            <td class="py-1.5 px-3 text-center text-slate-400 font-mono">{{ $itIdx + 1 }}</td>
                                                            <td class="py-1.5 px-3 font-medium text-slate-800">
                                                                {{ $item->product_name }}
                                                                @if($item->returned_qty > 0)
                                                                    <span class="text-[10px] text-red-600 bg-red-50 px-1 rounded font-normal ml-1">
                                                                        (Retur: {{ $item->returned_qty }})
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="py-1.5 px-3 text-center font-mono">
                                                                {{ (float) $item->qty }} {{ $item->unit_label ?? 'Pcs' }}
                                                            </td>
                                                            <td class="py-1.5 px-3 text-right font-mono text-slate-600">
                                                                Rp {{ number_format($item->price, 0, ',', '.') }}
                                                            </td>
                                                            <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-800">
                                                                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="py-6 text-center text-slate-400 italic">
                                        Belum ada transaksi penjualan pada shift ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($trxCount > 0)
                            <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
                                <tr>
                                    <td colspan="7" class="py-2.5 px-3 text-slate-700 uppercase text-[10px] tracking-wider">
                                        Total Shift #{{ $s->id }} ({{ $trxCount }} transaksi)
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                                        {{ $trxDiskon > 0 ? 'Rp ' . number_format($trxDiskon, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                                        {{ $trxPajak > 0 ? 'Rp ' . number_format($trxPajak, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-green-700 text-sm">
                                        Rp {{ number_format($trxTotal, 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @empty
            <div class="card p-12 text-center text-slate-400">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="font-semibold text-slate-600 text-base">Tidak Ada Data Shift</div>
                <p class="text-xs text-slate-400 mt-1">Tidak ditemukan riwayat shift kasir pada rentang tanggal atau penyaring yang dipilih.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $shifts->links() }}
    </div>

</div>
@endsection
