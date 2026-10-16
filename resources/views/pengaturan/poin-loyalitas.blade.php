@extends('layouts.app')
@section('title', 'Poin & Loyalitas Pelanggan')

@section('content')
<div class="max-w-2xl space-y-4">
    <div class="card p-6">
        <div class="flex items-start justify-between gap-4 mb-5 border-b border-slate-100 pb-4">
            <div>
                <h1 class="text-base font-bold text-slate-800">Program Poin &amp; Loyalitas Pelanggan</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Atur perolehan poin belanja per pelanggan dan target poin untuk penukaran voucher potongan harga.
                </p>
            </div>
            <div class="p-2.5 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 shrink-0">
                <x-icon.star />
            </div>
        </div>

        <form method="POST" action="{{ route('pengaturan.poin-loyalitas.update') }}" class="space-y-5">
            @csrf

            <div class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/60">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="enabled" value="1" {{ ($settings['enabled'] ?? false) ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 w-4 h-4">
                    <div>
                        <span class="text-sm font-semibold text-slate-800">Aktifkan Program Poin &amp; Loyalitas</span>
                        <p class="text-xs text-slate-500 mt-0.5">Jika aktif, pelanggan otomatis mendapatkan poin saat belanja lunas di kasir.</p>
                    </div>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label font-medium text-slate-700">Nominal Belanja untuk 1 Poin (Rp)</label>
                    <div class="relative mt-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400 font-semibold pointer-events-none">Rp</span>
                        <input type="text" data-jpos-number data-number-min="1" name="belanja_per_poin" value="{{ number_format((float) ($settings['belanja_per_poin'] ?? 10000), 0, ',', '.') }}" class="form-input font-mono" style="padding-left: 2.25rem;" required>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Contoh: Rp 10.000 (belanja Rp 50.000 otomatis dapat 5 poin).</p>
                </div>

                <div>
                    <label class="form-label font-medium text-slate-700">Target Poin Minimal</label>
                    <div class="relative mt-1">
                        <input type="text" data-jpos-number data-number-min="1" name="target_poin" value="{{ number_format((int) ($settings['target_poin'] ?? 50), 0, ',', '.') }}" class="form-input font-mono" required>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Poin minimal yang harus dikumpulkan pelanggan untuk tukar voucher (contoh: 50 poin).</p>
                </div>

                <div>
                    <label class="form-label font-medium text-slate-700">Nilai Voucher Potongan (Rp)</label>
                    <div class="relative mt-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400 font-semibold pointer-events-none">Rp</span>
                        <input type="text" data-jpos-number data-number-min="1" name="nilai_voucher" value="{{ number_format((float) ($settings['nilai_voucher'] ?? 25000), 0, ',', '.') }}" class="form-input font-mono" style="padding-left: 2.25rem;" required>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Besar potongan harga belanja saat voucher diklaim (contoh: Rp 25.000).</p>
                </div>
            </div>

            <div class="p-3.5 rounded-xl border border-blue-200 bg-blue-50/70 text-xs text-blue-800 space-y-1">
                <div class="font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Cara Kerja di Layar Kasir:
                </div>
                <ul class="list-disc list-inside pl-1 text-[11px] space-y-0.5 text-blue-700">
                    <li>Saat kasir memilih nama Pelanggan, sistem otomatis mengecek jumlah poin yang dimiliki.</li>
                    <li>Jika poin pelanggan mencapai target (misal &ge; 50 poin), tombol <strong>Gunakan Voucher Poin</strong> menyala hijau.</li>
                    <li>Sekali klik, keranjang otomatis terpotong senilai voucher dan poin pelanggan langsung terpotong saat transaksi selesai.</li>
                </ul>
            </div>

            <div class="flex items-center justify-end pt-2">
                <button type="submit" class="btn btn-primary px-5 py-2.5">
                    Simpan Pengaturan Poin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
