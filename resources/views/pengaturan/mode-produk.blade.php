@extends('layouts.app')
@section('title', 'Mode Produk')

@section('content')
<div class="card p-6 max-w-lg">
    <p class="text-sm text-slate-500 mb-4">
        Atur seberapa lengkap form <strong>Tambah/Edit Produk</strong>. <strong>Harga Grosir</strong>
        &amp; <strong>Min. Qty Grosir</strong> selalu tersedia di kedua mode — pengaturan ini hanya
        menentukan apakah section <strong>Satuan Tambahan</strong> (produk dengan lebih dari satu satuan
        jual, mis. per DUS/LUSIN/KG) ditampilkan atau tidak.
    </p>
    <form method="POST" action="{{ route('pengaturan.mode-produk.update') }}" class="space-y-3">
        @csrf
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="mode" value="sederhana" class="mt-1" {{ ($settings['mode'] ?? 'sederhana') == 'sederhana' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">✂️ Sederhana (Rekomendasi)</span>
                <span class="block text-xs text-slate-400">Form Tambah/Edit Produk cuma satu harga per produk (plus Harga Grosir kalau perlu). Section Satuan Tambahan disembunyikan. Cocok untuk kebanyakan toko.</span>
            </span>
        </label>
        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
            <input type="radio" name="mode" value="lengkap" class="mt-1" {{ ($settings['mode'] ?? 'sederhana') == 'lengkap' ? 'checked' : '' }}>
            <span>
                <span class="block font-medium text-sm">📋 Lengkap</span>
                <span class="block text-xs text-slate-400">Form Tambah/Edit Produk juga menampilkan Satuan Tambahan, untuk produk yang dijual per DUS/LUSIN/KG dengan harga & konversi masing-masing.</span>
            </span>
        </label>
        {{-- FITUR LANJUTAN.

             Ditaruh di halaman ini, bukan di halaman baru, karena pertanyaannya sama persis
             dengan yang sudah dijawab di atas: seberapa lengkap form produk. Halaman baru
             berarti satu kunci izin baru dan satu tempat lagi yang harus dicari orang -
             untuk satu centang. Kalau nanti centang seperti ini bertambah, halaman ini yang
             berganti nama, bukan bertambah saudara. --}}
        <div class="border-t border-slate-200 pt-4 mt-4">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Fitur Lanjutan</div>
            <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer hover:bg-slate-50">
                <input type="checkbox" name="grosir_jasa" value="1" class="mt-1" {{ ($settings['grosir_jasa'] ?? false) ? 'checked' : '' }}>
                <span>
                    <span class="block font-medium text-sm">Harga grosir untuk produk Jasa</span>
                    <span class="block text-xs text-slate-400 mt-0.5">
                        Biasanya jasa cuma punya satu harga. Nyalakan ini kalau harganya turun saat
                        jumlahnya banyak &mdash; mis. fotokopi Rp 500/lembar, jadi Rp 300/lembar mulai
                        100 lembar. Aturannya sama dengan grosir barang: begitu jumlah di Kasir
                        mencapai minimumnya, harga otomatis berganti.
                    </span>
                    <span class="block text-xs text-slate-400 mt-1">Dibiarkan mati, form Jasa tetap sesederhana sekarang.</span>
                </span>
            </label>
        </div>

        <button class="btn btn-primary">Simpan</button>
    </form>
    <p class="text-xs text-slate-400 mt-3">Catatan: produk yang sudah punya Satuan Tambahan tetap bisa diedit satuannya walau mode diset Sederhana — pengaturan ini cuma menyembunyikan section-nya untuk produk baru/yang belum punya satuan tambahan.</p>
</div>
@endsection
