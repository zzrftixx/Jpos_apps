@extends('layouts.app')
@section('title', 'Tampilan Kasir')

@section('content')
<div class="card p-6 max-w-2xl space-y-6">
    <div>
        <h2 class="text-base font-bold text-slate-800">Pengaturan Tampilan Modul Kasir</h2>
        <p class="text-sm text-slate-500 mt-1">
            Sesuaikan tata letak antarmuka kasir dan mode tampilan produk sesuai kenyamanan alur kerja toko Anda.
        </p>
    </div>

    <form method="POST" action="{{ route('pengaturan.tampilan-kasir.update') }}" class="space-y-6">
        @csrf

        {{-- MODE PRODUK KATALOG --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">
                Mode Tampilan Produk Kasir
            </label>
            <div class="space-y-2.5">
                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition {{ ($settings['default_view'] ?? 'gambar') === 'gambar' ? 'border-brand-400 bg-brand-50/40' : 'border-slate-200' }}">
                    <input type="radio" name="default_view" value="gambar" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['default_view'] ?? 'gambar') == 'gambar' ? 'checked' : '' }}>
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            <span>Gambar saja</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Modul Kasir selalu menampilkan kartu produk bergambar.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition {{ ($settings['default_view'] ?? '') === 'list' ? 'border-brand-400 bg-brand-50/40' : 'border-slate-200' }}">
                    <input type="radio" name="default_view" value="list" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['default_view'] ?? '') == 'list' ? 'checked' : '' }}>
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span>List saja</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Modul Kasir selalu menampilkan tabel ringkas (kode, nama, harga, stok).</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 border rounded-xl px-4 py-3 cursor-pointer hover:bg-slate-50 transition {{ ($settings['default_view'] ?? '') === 'both' ? 'border-brand-400 bg-brand-50/40' : 'border-slate-200' }}">
                    <input type="radio" name="default_view" value="both" class="mt-1 text-brand-600 focus:ring-brand-500" {{ ($settings['default_view'] ?? '') == 'both' ? 'checked' : '' }}>
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 font-semibold text-sm text-slate-800">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Dua-duanya (Bisa Dialihkan)</span>
                        </div>
                        <span class="block text-xs text-slate-400 mt-0.5">Kasir bisa beralih bebas antara Gambar dan List lewat tombol toggle di Modul Kasir.</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="btn btn-primary px-6 py-2.5 rounded-xl font-semibold text-sm shadow-xs">
                Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection
