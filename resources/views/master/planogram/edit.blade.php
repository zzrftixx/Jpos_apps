@extends('layouts.app')
@section('title', 'Atur Rak ' . $rack->name)

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-xl font-semibold">{{ $rack->name }}</h2>
        <p class="text-sm text-slate-500">{{ $rack->description ?: 'Klik kotak untuk mengisi atau mengosongkannya.' }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('planogram.cetak', $rack) }}" target="_blank" class="btn btn-sm btn-secondary">Cetak Peta</a>
        <a href="{{ route('planogram.index') }}" class="btn btn-sm btn-secondary">Kembali</a>
    </div>
</div>

@if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
        {{ $errors->first() }}
    </div>
@endif

{{-- Alpine hanya memegang kotak mana yang sedang dipilih. Daftar produknya HTML biasa,
     tidak ditanam ke dalam x-data - atribut x-data dibatasi tanda kutip ganda, jadi JSON
     apa pun di dalamnya akan memotong atributnya di tengah jalan dan mematikan seluruh
     komponen. Itu pernah terjadi dan sekarang dijaga tests/js/alpine-xdata.test.cjs. --}}
<div class="card p-6" x-data="{ baris: null, kolom: null, terbuka: false, bukaKotak(r, k, produk) { this.baris = r; this.kolom = k; this.terbuka = true; this.$refs.produk.value = produk || ''; } }">

    <div class="overflow-x-auto pb-2">
        <div class="inline-block min-w-full">
            @for($r = 0; $r < $rack->rows; $r++)
                <div class="flex gap-2 mb-2">
                    <div class="w-10 shrink-0 flex items-center justify-center text-xs text-slate-400 font-medium">
                        B{{ $r + 1 }}
                    </div>
                    @for($k = 0; $k < $rack->cols; $k++)
                        {{-- SENGAJA BUKAN $produk: nama itu sudah dipakai daftar produk untuk
                             dropdown di bawah, dan menimpanya di dalam kisi membuat dropdownnya
                             mengulang nilai terakhir kisi - atau null kalau kotak terakhirnya
                             kosong, yang membuat SELURUH HALAMAN gagal dimuat. --}}
                        @php
                            $slot = $peta[$r . '-' . $k] ?? null;
                            $isiKotak = $slot?->product;

                            if (! $isiKotak) {
                                $warna = 'bg-slate-50 border-dashed border-slate-300 text-slate-400 hover:border-brand-400';
                            } elseif ((float) $isiKotak->stock <= 0) {
                                $warna = 'bg-red-50 border-red-300 text-red-800';
                            } elseif ($isiKotak->isLowStock()) {
                                $warna = 'bg-amber-50 border-amber-300 text-amber-800';
                            } else {
                                $warna = 'bg-green-50 border-green-300 text-green-800';
                            }
                        @endphp
                        <button type="button"
                                class="w-32 h-24 shrink-0 border rounded-lg p-2 text-left text-xs transition {{ $warna }}"
                                @click="bukaKotak({{ $r }}, {{ $k }}, {{ $isiKotak?->id ?: 'null' }})">
                            @if($isiKotak)
                                <span class="block font-semibold leading-tight line-clamp-2">{{ $isiKotak->name }}</span>
                                <span class="block mt-1 opacity-75">{{ \App\Support\Angka::qty($isiKotak->stock) }} {{ $isiKotak->unit }}</span>
                            @else
                                <span class="block text-center mt-6">K{{ $k + 1 }}</span>
                            @endif
                        </button>
                    @endfor
                </div>
            @endfor
        </div>
    </div>

    <div class="flex flex-wrap gap-4 mt-4 text-xs text-slate-500">
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-100 border border-green-300"></span> Stok aman</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-300"></span> Stok menipis</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-100 border border-red-300"></span> Stok habis</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-slate-100 border border-dashed border-slate-300"></span> Kotak kosong</span>
    </div>

    {{-- Form pengisi kotak. Satu form dipakai bergantian oleh semua kotak - kalau tiap kotak
         punya form sendiri, rak 12x12 berarti 144 daftar produk ditanam ke satu halaman. --}}
    <form method="POST" action="{{ route('planogram.slot', $rack) }}"
          x-show="terbuka" x-cloak class="mt-6 border-t border-slate-200 pt-5">
        @csrf
        <input type="hidden" name="row" :value="baris">
        <input type="hidden" name="col" :value="kolom">

        <p class="text-sm font-semibold mb-3">
            Isi kotak baris <span x-text="baris + 1">1</span>, kolom <span x-text="kolom + 1">1</span>
        </p>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="form-label">Produk</label>
                <select name="product_id" x-ref="produk" class="form-input">
                    <option value="">&mdash; Kosongkan kotak ini &mdash;</option>
                    @foreach($produk as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-secondary" @click="terbuka = false">Tutup</button>
            </div>
        </div>

        <p class="text-xs text-slate-500 mt-3">
            Satu produk hanya boleh menempati satu kotak. Memilih produk yang sudah ada di kotak
            lain akan memindahkannya ke sini, bukan menggandakannya &mdash; supaya pertanyaan
            &ldquo;ada di mana?&rdquo; selalu punya satu jawaban.
        </p>
    </form>
</div>

<div class="card p-6 mt-4">
    <h3 class="font-semibold mb-1">Ukuran Rak</h3>
    <p class="text-xs text-slate-400 mb-4">
        Mengecilkan rak ditolak selama masih ada kotak terisi di luar ukuran baru &mdash; kotaknya
        akan hilang dari layar tapi tetap tersimpan, dan produknya seolah lenyap tanpa jejak.
    </p>
    <form method="POST" action="{{ route('planogram.update', $rack) }}" class="grid gap-3 md:grid-cols-5">
        @csrf @method('PUT')
        <div class="md:col-span-2">
            <label class="form-label">Nama Rak</label>
            <input type="text" name="name" class="form-input" value="{{ $rack->name }}" required>
        </div>
        <div>
            <label class="form-label">Baris</label>
            <input type="number" name="rows" class="form-input" value="{{ $rack->rows }}" min="1" max="12" required>
        </div>
        <div>
            <label class="form-label">Kolom</label>
            <input type="number" name="cols" class="form-input" value="{{ $rack->cols }}" min="1" max="12" required>
        </div>
        <div class="flex items-end">
            <button class="btn btn-primary w-full">Simpan</button>
        </div>
        <div class="md:col-span-5">
            <label class="form-label">Keterangan</label>
            <input type="text" name="description" class="form-input" value="{{ $rack->description }}">
        </div>
    </form>
</div>
@endsection
