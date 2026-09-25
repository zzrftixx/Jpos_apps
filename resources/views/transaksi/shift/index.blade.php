@extends('layouts.app')
@section('title', 'Shift Kasir')

@section('content')
<div x-data="shiftApp()" class="space-y-5">
    {{-- Header & Breadcrumb --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Manajemen Shift Kasir</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Pengawasan modal awal kasir, penghitungan fisik uang laci (*cash count*), dan pencatatan selisih kas.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($activeShift)
                <button type="button" @click="bukaModalTutup({{ $activeShift->id }})"
                        class="btn btn-primary bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm px-3.5 py-2 rounded-xl shadow-xs flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                    <span>Tutup Shift Saya (#{{ $activeShift->id }})</span>
                </button>
            @elseif($shiftKasirEnabled)
                <button type="button" @click="showBukaModal = true"
                        class="btn btn-primary text-white font-semibold text-xs sm:text-sm px-3.5 py-2 rounded-xl shadow-xs flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Mulai / Buka Shift Baru</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Banner jika fitur dinonaktifkan di Pengaturan --}}
    @if(!$shiftKasirEnabled)
        <div class="card p-4 border-l-4 border-amber-500 bg-amber-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="p-1.5 rounded-lg bg-amber-100 text-amber-800 shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <div>
                    <div class="font-bold text-sm text-amber-900">Fitur Shift Kasir Sedang Dinonaktifkan</div>
                    <div class="text-xs text-amber-800 mt-0.5">
                        Fitur shift saat ini dimatikan di menu Pengaturan. Kasir dapat langsung bertransaksi tanpa shift. Riwayat shift sebelumnya tetap tersimpan untuk keperluan audit &amp; pembukuan.
                    </div>
                </div>
            </div>
            @if(auth()->user()?->can_access('pengaturan'))
                <a href="{{ route('pengaturan.shift-kasir') }}" class="btn btn-outline border-amber-300 text-amber-900 hover:bg-amber-100 text-xs shrink-0 font-semibold">
                    Atur di Pengaturan &rarr;
                </a>
            @endif
        </div>
    @endif

    {{-- Banner Status Shift Aktif --}}
    @if($activeShift)
        @php $activeSummary = $activeShift->calculateSummary(); @endphp
        <div class="card p-4 sm:p-5 border-l-4 border-emerald-500 bg-emerald-50/25">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs uppercase tracking-wider">Shift Anda Sedang Berjalan</span>
                        <span class="text-xs text-slate-500">Mulai: {{ $activeShift->opened_at->format('d/m/Y H:i') }} ({{ $activeShift->opened_at->diffForHumans() }})</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-xs pt-1">
                        <div>Modal Awal: <strong class="text-slate-800 font-mono font-bold">Rp {{ number_format($activeShift->starting_cash, 0, ',', '.') }}</strong></div>
                        <div>&bull;</div>
                        <div>Penjualan Tunai: <strong class="text-emerald-700 font-mono font-bold">Rp {{ number_format($activeSummary['cash_sales'], 0, ',', '.') }}</strong></div>
                        <div>&bull;</div>
                        <div>Non-Tunai: <strong class="text-blue-700 font-mono font-bold">Rp {{ number_format($activeSummary['non_cash_sales'], 0, ',', '.') }}</strong></div>
                        <div>&bull;</div>
                        <div>Uang Seharusnya di Laci: <strong class="text-slate-900 font-mono font-bold">Rp {{ number_format($activeSummary['expected_cash'], 0, ',', '.') }}</strong></div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('kasir.index') }}" class="btn btn-outline text-xs px-3 py-2 rounded-lg font-semibold bg-white shadow-2xs">
                        Buka Layar Kasir &rarr;
                    </a>
                    <button type="button" @click="bukaModalTutup({{ $activeShift->id }})"
                            class="btn btn-primary bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2 rounded-lg shadow-2xs">
                        Tutup Shift Sekarang
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Filter Bar & Riwayat Shift --}}
    <div class="card p-4 sm:p-5">
        <form method="GET" action="{{ route('shift.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
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
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-xs font-semibold text-slate-600">Status</label>
                <select name="status" class="form-select text-xs">
                    <option value="">Semua Status</option>
                    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open (Berjalan)</option>
                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed (Selesai)</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary w-full text-xs font-semibold h-[38px] justify-center">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Tabel Riwayat Shift --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 text-slate-700 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200 select-none">
                    <tr>
                        <th class="py-3 px-3 w-12 text-center">#</th>
                        <th class="py-3 px-3">Kasir</th>
                        <th class="py-3 px-3">Waktu Buka / Tutup</th>
                        <th class="py-3 px-3 text-right">Modal Awal</th>
                        <th class="py-3 px-3 text-right">Penjualan Tunai</th>
                        <th class="py-3 px-3 text-right">Non-Tunai</th>
                        <th class="py-3 px-3 text-right">Uang Fisik Laci</th>
                        <th class="py-3 px-3 text-center">Selisih</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shifts as $s)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-3 text-center font-mono font-bold text-slate-400">
                                #{{ $s->id }}
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-800">{{ $s->user->name ?? 'Kasir #' . $s->user_id }}</div>
                                @if($s->notes)
                                    <div class="text-[11px] text-slate-500 truncate max-w-xs" title="{{ $s->notes }}">{{ $s->notes }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-700">{{ $s->opened_at->format('d/m/Y H:i') }}</div>
                                <div class="text-[11px] text-slate-400">
                                    {{ $s->closed_at ? $s->closed_at->format('d/m/Y H:i') . ' (' . $s->opened_at->diffInHours($s->closed_at) . ' jam ' . ($s->opened_at->diffInMinutes($s->closed_at) % 60) . ' mnt)' : 'Masih aktif' }}
                                </div>
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-semibold text-slate-700">
                                Rp {{ number_format($s->starting_cash, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-semibold text-emerald-700">
                                Rp {{ number_format($s->cash_sales, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-semibold text-blue-700">
                                Rp {{ number_format($s->non_cash_sales, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-800">
                                {{ $s->actual_cash !== null ? 'Rp ' . number_format($s->actual_cash, 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-3 text-center font-mono">
                                @if($s->status === 'closed')
                                    @php $diff = (float) $s->difference; @endphp
                                    @if(abs($diff) < 0.01)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">PAS</span>
                                    @elseif($diff > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">+Rp {{ number_format($diff, 0, ',', '.') }}</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">-Rp {{ number_format(abs($diff), 0, ',', '.') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($s->status === 'open')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Aktif</span>
                                    </span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600">
                                        Selesai
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                    @if($s->status === 'open')
                                        <button type="button" @click="bukaModalTutup({{ $s->id }})"
                                                class="btn btn-xs bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold border border-emerald-300 px-2 py-1 rounded-lg">
                                            Tutup
                                        </button>
                                    @endif
                                    <a href="{{ route('shift.print', $s->id) }}" target="_blank"
                                       class="btn btn-xs bg-white hover:bg-slate-100 text-slate-700 font-semibold border border-slate-300 px-2 py-1 rounded-lg flex items-center gap-1"
                                       title="Cetak Slip Rekap Shift">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        <span>Cetak</span>
                                    </a>
                                    @if(auth()->user()?->can_access('laporan'))
                                        <a href="{{ route('laporan.shift', ['shift_id' => $s->id, 'from' => $s->opened_at->toDateString(), 'to' => ($s->closed_at ? $s->closed_at->toDateString() : now()->toDateString())]) }}"
                                           class="btn btn-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold border border-blue-200 px-2 py-1 rounded-lg flex items-center gap-1"
                                           title="Lihat Laporan Transaksi Shift">
                                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <span>Transaksi</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-slate-400">
                                Belum ada riwayat shift kasir pada filter periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($shifts->hasPages())
            <div class="p-3 border-t border-slate-100">
                {{ $shifts->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL BUKA SHIFT --}}
    <div x-show="showBukaModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200"
             @click.outside="showBukaModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Mulai Shift Kasir Baru</span>
                </h3>
                <button type="button" @click="showBukaModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('shift.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kasir</label>
                    <input type="text" value="{{ auth()->user()->name }}" readonly class="form-input text-xs bg-slate-50 font-bold text-slate-700">
                </div>

                {{-- Waktu Buka Shift: Otomatis vs Manual --}}
                @if(($shiftKasirSettings['time_mode'] ?? 'auto') === 'manual')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                        <span>Tanggal &amp; Jam Buka Shift <span class="text-red-500">*</span></span>
                        <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Input Manual</span>
                    </label>
                    <input type="datetime-local" name="opened_at" value="{{ now()->format('Y-m-d\TH:i') }}" required
                           class="form-input text-xs w-full bg-amber-50/30 border-amber-300 font-mono">
                    <p class="text-[11px] text-slate-500 mt-1">Ubah tanggal &amp; jam jika shift dibuka susulan / di waktu berbeda.</p>
                </div>
                @else
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5 flex items-center justify-between text-xs text-slate-600">
                    <span class="flex items-center gap-1.5 font-medium">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Waktu Buka Shift:
                    </span>
                    <span class="font-semibold text-slate-800 bg-white border px-2 py-0.5 rounded shadow-2xs">
                        Otomatis ({{ now()->format('d/m/Y H:i') }})
                    </span>
                </div>
                @endif

                @if(!$cashDrawerEnabled)
                <input type="hidden" name="starting_cash" value="0">
                <div class="bg-amber-50/80 border border-amber-200/80 rounded-xl p-3.5 flex items-start gap-2.5 text-xs text-amber-900">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <span class="font-bold block text-slate-800">Mode Kas Laci Dinonaktifkan</span>
                        <span class="text-slate-600 leading-relaxed">Anda tidak perlu memasukkan uang modal awal kas laci. Shift akan langsung dibuka untuk mencatat riwayat transaksi dan jam kerja Anda.</span>
                    </div>
                </div>
                @else
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                        <span>Modal Awal Uang Laci (Opening Cash Float) <span class="text-red-500">*</span></span>
                        @if(($shiftKasirSettings['starting_cash_mode'] ?? 'fixed') === 'last_closing')
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">Ikuti Kas Terakhir</span>
                        @endif
                    </label>
                    <div class="flex items-stretch rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                        <span class="inline-flex items-center px-3 bg-slate-100 text-slate-600 font-bold text-xs border-r border-slate-200 select-none">Rp</span>
                        <input type="number" name="starting_cash" required
                               min="{{ !empty($shiftKasirSettings['require_positive_starting_cash']) ? 1 : 0 }}"
                               value="{{ (int) ($defaultStartingCash ?? 0) }}"
                               step="100" placeholder="0" autofocus
                               class="w-full py-2 px-3 text-sm font-bold text-slate-900 border-0 focus:ring-0 font-mono">
                    </div>
                    @if(($shiftKasirSettings['starting_cash_mode'] ?? 'fixed') === 'last_closing')
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">
                            💡 Otomatis mengambil sisa kas fisik dari shift terakhir: Rp {{ number_format($defaultStartingCash ?? 0, 0, ',', '.') }}
                        </p>
                    @else
                        <p class="text-[11px] text-slate-500 mt-1">Uang kembalian yang dimasukkan ke laci kasir saat awal shift dimulai.</p>
                    @endif
                </div>
                @endif
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan (Opsional)</label>
                    <textarea name="notes" rows="2" placeholder="Catatan shift pagi / siang..." class="form-textarea text-xs"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showBukaModal = false" class="btn btn-outline text-xs">Batal</button>
                    <button type="submit" class="btn btn-primary text-xs font-bold px-4 py-2">Buka Shift Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL TUTUP SHIFT --}}
    <div x-show="showTutupModal" x-cloak
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 shadow-2xl border border-slate-200"
             @click.outside="showTutupModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <span>Tutup Shift Kasir #<span x-text="tutupData.id"></span></span>
                </h3>
                <button type="button" @click="showTutupModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <template x-if="tutupLoading">
                <div class="py-12 text-center text-slate-400 text-xs">
                    Memuat ringkasan shift terkini...
                </div>
            </template>

            <template x-if="!tutupLoading && tutupData.summary">
                <form :action="'/shift/' + tutupData.id + '/close'" method="POST" class="space-y-4">
                    @csrf

                    {{-- Waktu Tutup Shift (Manual vs Otomatis) --}}
                    @if(($shiftKasirSettings['time_mode'] ?? 'auto') === 'manual')
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Tanggal &amp; Jam Tutup Shift <span class="text-red-500">*</span></span>
                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Input Manual</span>
                        </label>
                        <input type="datetime-local" name="closed_at" value="{{ now()->format('Y-m-d\TH:i') }}" required
                               class="form-input text-xs w-full bg-amber-50/30 border-amber-300 font-mono">
                        <p class="text-[11px] text-slate-500 mt-1">Ubah tanggal &amp; jam bila penutupan shift dicatat susulan.</p>
                    </div>
                    @endif

                    {{-- Kotak Rincian Penjualan --}}
                    <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200 text-xs space-y-2">
                        @if($cashDrawerEnabled)
                        <div class="flex justify-between text-slate-600">
                            <span>Modal Awal Kasir:</span>
                            <span class="font-mono font-bold text-slate-800">Rp <span x-text="formatRupiah(tutupData.summary.starting_cash)"></span></span>
                        </div>
                        @endif
                        <div class="flex justify-between text-slate-600">
                            <span>(+) Penjualan Tunai:</span>
                            <span class="font-mono font-bold text-emerald-700">+ Rp <span x-text="formatRupiah(tutupData.summary.cash_sales)"></span></span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>(+) Penjualan Non-Tunai (QRIS/Transfer):</span>
                            <span class="font-mono font-semibold text-blue-700">Rp <span x-text="formatRupiah(tutupData.summary.non_cash_sales)"></span></span>
                        </div>
                        <template x-if="tutupData.summary.cash_refunds > 0">
                            <div class="flex justify-between text-red-600">
                                <span>(-) Refund Retur Tunai:</span>
                                <span class="font-mono font-bold">- Rp <span x-text="formatRupiah(tutupData.summary.cash_refunds)"></span></span>
                            </div>
                        </template>
                        @if($cashDrawerEnabled)
                        <div class="pt-2 border-t border-slate-200 flex justify-between text-slate-900 font-bold text-sm">
                            <span>Uang Seharusnya di Laci:</span>
                            <span class="font-mono text-emerald-800">Rp <span x-text="formatRupiah(tutupData.summary.expected_cash)"></span></span>
                        </div>
                        @else
                        <div class="pt-2 border-t border-slate-200 flex justify-between text-slate-900 font-bold text-sm">
                            <span>Total Penjualan Shift:</span>
                            <span class="font-mono text-emerald-800">Rp <span x-text="formatRupiah(tutupData.summary.total_sales)"></span></span>
                        </div>
                        @endif
                    </div>

                    @if($cashDrawerEnabled)
                    {{-- Input Penghitungan Uang Fisik Kasir --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">
                            Uang Fisik Laci Aktual (Hasil Hitung Fisik) <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-stretch rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                            <span class="inline-flex items-center px-3 bg-slate-100 text-slate-600 font-bold text-xs border-r border-slate-200 select-none">Rp</span>
                            <input type="number" name="actual_cash" x-model.number="tutupUangFisik" required min="0" step="100" placeholder="0"
                                   class="w-full py-2.5 px-3 text-base font-bold font-mono text-slate-900 border-0 focus:ring-0">
                        </div>
                    </div>

                    {{-- Indikator Selisih Kas --}}
                    <div class="p-3 rounded-xl border text-xs flex items-center justify-between"
                         :class="selisihKelas()">
                        <span class="font-semibold" x-text="selisihLabel()"></span>
                        <span class="font-mono font-bold text-sm" x-text="selisihNominal()"></span>
                    </div>
                    @else
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-600 flex items-center gap-2">
                        <span class="p-1 rounded bg-slate-200 text-slate-700">ℹ️</span>
                        <span>Mode Kas Laci Nonaktif — Rekapitulasi shift akan ditutup tanpa menghitung fisik uang kas laci.</span>
                    </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Penutup Shift (Opsional)</label>
                        <textarea name="notes" rows="2" placeholder="Keterangan bila ada selisih uang atau pergantian tugas..." class="form-textarea text-xs"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="showTutupModal = false" class="btn btn-outline text-xs">Batal</button>
                        <button type="submit" class="btn btn-primary bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2">
                            Simpan &amp; Tutup Shift
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function shiftApp() {
    return {
        showBukaModal: false,
        showTutupModal: false,
        tutupLoading: false,
        tutupData: {},
        tutupUangFisik: 0,

        bukaModalTutup(shiftId) {
            this.showTutupModal = true;
            this.tutupLoading = true;
            fetch('/shift/' + shiftId + '/summary', { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    this.tutupData = {
                        id: shiftId,
                        shift: data.shift,
                        summary: data.summary,
                    };
                    this.tutupUangFisik = data.summary.expected_cash || 0;
                    this.tutupLoading = false;
                })
                .catch(err => {
                    this.tutupLoading = false;
                    alert('Gagal memuat ringkasan shift: ' + err);
                });
        },

        formatRupiah(num) {
            return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
        },

        hitungSelisih() {
            if (!this.tutupData.summary) return 0;
            return (this.tutupUangFisik || 0) - (this.tutupData.summary.expected_cash || 0);
        },

        selisihNominal() {
            const diff = this.hitungSelisih();
            if (Math.abs(diff) < 0.01) return 'Rp 0 (Pas)';
            if (diff > 0) return '+ Rp ' + this.formatRupiah(diff);
            return '- Rp ' + this.formatRupiah(Math.abs(diff));
        },

        selisihLabel() {
            const diff = this.hitungSelisih();
            if (Math.abs(diff) < 0.01) return 'Uang Laci Sesuai (Tepat Nol):';
            if (diff > 0) return 'Selisih Kas Lebih (Over):';
            return 'Selisih Kas Kurang (Short):';
        },

        selisihKelas() {
            const diff = this.hitungSelisih();
            if (Math.abs(diff) < 0.01) return 'bg-green-50 border-green-200 text-green-800';
            if (diff > 0) return 'bg-blue-50 border-blue-200 text-blue-800';
            return 'bg-red-50 border-red-200 text-red-800';
        }
    };
}
</script>
@endpush
@endsection
