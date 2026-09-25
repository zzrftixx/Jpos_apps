@extends('layouts.app')
@section('title', 'Pengaturan Shift Kasir')

@section('content')
<div class="card p-6 max-w-2xl space-y-6"
     x-data="{
         isEnabled: {{ ($settings['enabled'] ?? true) ? 'true' : 'false' }},
         startingCashMode: '{{ $settings['starting_cash_mode'] ?? 'fixed' }}',
         timeMode: '{{ $settings['time_mode'] ?? 'auto' }}'
     }">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700">
                <svg width="20" height="20" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <h2 class="text-base font-bold text-slate-800">Pengaturan Shift Kasir</h2>
        </div>
        <p class="text-sm text-slate-500 mt-1">
            Tentukan apakah kasir di toko Anda perlu menggunakan sistem pergantian shift (modal awal, hitung kas laci, dan slip Z-Report) atau dapat langsung bertransaksi tanpa shift.
        </p>
    </div>

    <form method="POST" action="{{ route('pengaturan.shift-kasir.update') }}" class="space-y-6">
        @csrf

        {{-- 1. STATUS FITUR: AKTIF / NONAKTIF --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">
                Status Fitur Shift Kasir
            </label>
            <div class="space-y-3">
                {{-- Opsi 1: AKTIF --}}
                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="isEnabled ? 'border-emerald-500 bg-emerald-50/40 ring-1 ring-emerald-400/40' : 'border-slate-200'">
                    <input type="radio" name="enabled" value="1" x-model="isEnabled" :value="true" class="mt-1 text-emerald-600 focus:ring-emerald-500" {{ ($settings['enabled'] ?? true) ? 'checked' : '' }}>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">✓</span>
                            <span>Aktifkan Fitur Shift Kasir (Rekomendasi Toko / Shift Bergantian)</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Kasir membuka shift dengan <strong>modal awal kas laci</strong>, seluruh penjualan &amp; metode pembayaran otomatis tercatat per shift, dan saat tutup kasir menghitung uang fisik laci untuk mencetak <strong>Slip Z-Report (Rekonsiliasi Kas)</strong>.
                        </p>
                    </div>
                </label>

                {{-- Opsi 2: NONAKTIF --}}
                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition"
                       :class="!isEnabled ? 'border-amber-500 bg-amber-50/40 ring-1 ring-amber-400/40' : 'border-slate-200'">
                    <input type="radio" name="enabled" value="0" x-model="isEnabled" :value="false" class="mt-1 text-amber-600 focus:ring-amber-500" {{ !($settings['enabled'] ?? true) ? 'checked' : '' }}>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 font-semibold text-sm text-slate-800">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-600 text-xs font-bold">✕</span>
                            <span>Nonaktifkan Fitur Shift Kasir (Langsung Transaksi Tanpa Shift)</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Menu dan tombol shift disembunyikan. Kasir dapat langsung melayani transaksi tanpa perlu memasukkan modal awal dan tanpa rekonsiliasi penutupan shift laci.
                        </p>
                    </div>
                </label>
            </div>
        </div>

        {{-- FITUR LANJUTAN (Hanya saat Aktif) --}}
        <div class="border-t border-slate-200 pt-5 space-y-6" x-show="isEnabled" x-transition>

            {{-- 2. PENGATURAN KAS LACI (MODAL AWAL / CASH FLOAT) --}}
            <div class="space-y-3">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Pengaturan Kas Laci (Modal Awal / Float)</span>
                    </div>
                    <p class="text-xs text-slate-500">Atur cara pengisian modal kas di laci kasir saat awal shift dimulai.</p>
                </div>

                <div class="space-y-2.5">
                    {{-- Mode 1: Nominal Tetap Bawaan Toko --}}
                    <label class="flex items-start gap-3 border rounded-xl p-3.5 cursor-pointer hover:bg-slate-50 transition"
                           :class="startingCashMode === 'fixed' ? 'border-brand-500 bg-brand-50/20 ring-1 ring-brand-400/30' : 'border-slate-200'">
                        <input type="radio" name="starting_cash_mode" value="fixed" x-model="startingCashMode" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['starting_cash_mode'] ?? 'fixed') === 'fixed' ? 'checked' : '' }}>
                        <div class="flex-1">
                            <span class="block font-semibold text-sm text-slate-800">Nominal Tetap Bawaan Toko</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Kolom modal awal pada popup buka shift otomatis terisi dengan nominal standar ini (kasir tetap dapat mengubahnya jika uang fisik berbeda).
                            </span>
                            <div class="mt-2.5 max-w-xs" x-show="startingCashMode === 'fixed'">
                                <div class="flex items-stretch rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                                    <span class="inline-flex items-center px-3 bg-slate-100 text-slate-600 font-bold text-xs border-r border-slate-200 select-none">Rp</span>
                                    <input type="number" name="default_starting_cash" min="0" step="500"
                                           value="{{ (int) ($settings['default_starting_cash'] ?? 0) }}"
                                           placeholder="0"
                                           class="w-full py-1.5 px-2.5 text-xs font-bold text-slate-900 border-0 focus:ring-0">
                                </div>
                            </div>
                        </div>
                    </label>

                    {{-- Mode 2: Otomatis Ikuti Sisa Kas Fisik Shift Terakhir --}}
                    <label class="flex items-start gap-3 border rounded-xl p-3.5 cursor-pointer hover:bg-slate-50 transition"
                           :class="startingCashMode === 'last_closing' ? 'border-brand-500 bg-brand-50/20 ring-1 ring-brand-400/30' : 'border-slate-200'">
                        <input type="radio" name="starting_cash_mode" value="last_closing" x-model="startingCashMode" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['starting_cash_mode'] ?? '') === 'last_closing' ? 'checked' : '' }}>
                        <div class="flex-1">
                            <span class="block font-semibold text-sm text-slate-800">Otomatis Ikuti Sisa Kas Laci Shift Terakhir</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Modal awal shift berikutnya otomatis menggunakan hasil hitung fisik penutupan shift sebelumnya. Sangat cocok jika uang kas tidak disetor keluar saat pergantian kasir.
                            </span>
                        </div>
                    </label>

                    {{-- Mode 3: Nonaktifkan Mode Kas Laci --}}
                    <label class="flex items-start gap-3 border rounded-xl p-3.5 cursor-pointer hover:bg-slate-50 transition"
                           :class="startingCashMode === 'disabled' ? 'border-amber-500 bg-amber-50/30 ring-1 ring-amber-400/30' : 'border-slate-200'">
                        <input type="radio" name="starting_cash_mode" value="disabled" x-model="startingCashMode" class="mt-1 text-amber-600 focus:ring-amber-500" {{ ($settings['starting_cash_mode'] ?? '') === 'disabled' ? 'checked' : '' }}>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="block font-semibold text-sm text-slate-800">Nonaktifkan Mode Kas Laci (Tanpa Modal &amp; Uang Fisik Laci)</span>
                                <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Praktis</span>
                            </div>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Kasir tidak perlu memasukkan uang modal awal saat buka shift, dan tidak perlu menghitung uang fisik saat tutup shift. Shift kasir hanya mencatat jam kerja kasir serta akumulasi riwayat penjualan tanpa mengelola kas laci.
                            </span>
                        </div>
                    </label>
                </div>

                {{-- Wajibkan modal kas laci > 0 (Hanya relevan bila kas laci aktif) --}}
                <div x-show="startingCashMode !== 'disabled'" x-transition>
                    <label class="flex items-start gap-3 border border-slate-200 rounded-xl px-4 py-2.5 cursor-pointer hover:bg-slate-50 transition">
                        <input type="checkbox" name="require_positive_starting_cash" value="1" class="mt-1 text-brand-600 rounded" {{ ($settings['require_positive_starting_cash'] ?? false) ? 'checked' : '' }}>
                        <div>
                            <span class="block font-medium text-sm text-slate-800">Wajibkan Modal Kas Laci Lebih Dari Nol (&gt; Rp 0)</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Bila dicentang, kasir tidak diizinkan membuka shift dengan modal Rp 0. Bila tidak dicentang, kasir diperbolehkan membuka shift tanpa modal uang kembalian.
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- 3. SISTEM WAKTU SHIFT (JAM & TANGGAL OTOMATIS VS MANUAL) --}}
            <div class="space-y-3 pt-2">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Sistem Waktu Shift (Jam &amp; Tanggal)</span>
                    </div>
                    <p class="text-xs text-slate-500">Pilih bagaimana jam dan tanggal shift dicatat saat kasir membuka dan menutup shift.</p>
                </div>

                <div class="space-y-2.5">
                    {{-- Waktu Mode 1: Otomatis (Real-Time) --}}
                    <label class="flex items-start gap-3 border rounded-xl p-3.5 cursor-pointer hover:bg-slate-50 transition"
                           :class="timeMode === 'auto' ? 'border-brand-500 bg-brand-50/20 ring-1 ring-brand-400/30' : 'border-slate-200'">
                        <input type="radio" name="time_mode" value="auto" x-model="timeMode" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['time_mode'] ?? 'auto') === 'auto' ? 'checked' : '' }}>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-slate-800">Otomatis Sesuai Tanggal &amp; Jam Sistem (Rekomendasi)</span>
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">Akurat &amp; Aman</span>
                            </div>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Jam buka dan jam tutup shift terkunci otomatis menggunakan waktu *real-time* komputer/server saat tombol ditekan. Kasir tidak bisa merekayasa atau memanipulasi waktu transaksi shift.
                            </span>
                        </div>
                    </label>

                    {{-- Waktu Mode 2: Manual --}}
                    <label class="flex items-start gap-3 border rounded-xl p-3.5 cursor-pointer hover:bg-slate-50 transition"
                           :class="timeMode === 'manual' ? 'border-brand-500 bg-brand-50/20 ring-1 ring-brand-400/30' : 'border-slate-200'">
                        <input type="radio" name="time_mode" value="manual" x-model="timeMode" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['time_mode'] ?? '') === 'manual' ? 'checked' : '' }}>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-slate-800">Input Manual Jam &amp; Tanggal Shift</span>
                                <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">Fleksibel</span>
                            </div>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Menampilkan kotak input Tanggal &amp; Jam pada modal Buka Shift dan Tutup Shift. Kasir/Admin dapat menentukan atau menyesuaikan waktu shift sendiri bila pencatatan dilakukan susulan/mundur.
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- 4. OPSI ATURAN OPERASIONAL SHIFT --}}
            <div class="space-y-3 pt-2">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Opsi Aturan Operasional Shift</div>
                    <p class="text-xs text-slate-500">Sesuaikan tingkat kedisiplinan dan keamanan pencatatan kasir di toko Anda.</p>
                </div>

                {{-- Wajibkan buka shift sebelum transaksi --}}
                <label class="flex items-start gap-3 border border-slate-200 rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition">
                    <input type="checkbox" name="require_shift_for_sales" value="1" class="mt-1 text-brand-600 rounded" {{ ($settings['require_shift_for_sales'] ?? false) ? 'checked' : '' }}>
                    <div>
                        <span class="block font-medium text-sm text-slate-800">Wajibkan Kasir Membuka Shift Sebelum Bertransaksi</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            Bila dicentang, kasir tidak dapat memproses pembayaran jika belum menginput modal awal shift. Bila tidak dicentang (fleksibel), kasir tetap bisa melayani transaksi walau shift belum dibuka.
                        </span>
                    </div>
                </label>

                {{-- Tampilkan estimasi kas saat tutup shift --}}
                <label class="flex items-start gap-3 border border-slate-200 rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition">
                    <input type="checkbox" name="show_expected_cash_on_close" value="1" class="mt-1 text-brand-600 rounded" {{ ($settings['show_expected_cash_on_close'] ?? true) ? 'checked' : '' }}>
                    <div>
                        <span class="block font-medium text-sm text-slate-800">Tampilkan Estimasi Uang Kas Saat Tutup Shift (Open Close)</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            Bila dicentang, kasir dapat melihat perkiraan uang sistem sebelum menginput uang fisik. Bila dimatikan (<strong>Blind Close</strong>), kasir harus murni menghitung uang fisik tanpa melihat estimasi sistem terlebih dahulu untuk mencegah manipulasi.
                        </span>
                    </div>
                </label>
            </div>

        </div>

        <div class="pt-2 flex items-center justify-between border-t border-slate-200">
            <button type="submit" class="btn btn-primary px-6 py-2.5 rounded-xl font-semibold text-sm shadow-xs inline-flex items-center gap-2">
                <svg width="16" height="16" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Simpan Pengaturan
            </button>
            <span class="text-xs text-slate-400">
                Perubahan langsung berlaku ke seluruh akun kasir.
            </span>
        </div>
    </form>
</div>
@endsection
