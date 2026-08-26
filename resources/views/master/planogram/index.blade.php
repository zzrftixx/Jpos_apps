@extends('layouts.app')
@section('title', 'Planogram')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h2 class="text-xl font-semibold">Planogram &mdash; Peta Rak Toko</h2>
</div>

{{-- Istilahnya asing untuk kebanyakan pemilik toko kecil, jadi dijelaskan lebih dulu -
     sekaligus alasan kenapa repot-repot mengisinya. --}}
<details class="card p-5 mb-4" open>
    <summary class="cursor-pointer font-semibold text-slate-700">Planogram itu apa? &mdash; dan buat apa</summary>

    <div class="mt-4 text-sm text-slate-600 space-y-4">
        <p>
            <strong>Planogram adalah peta rak toko</strong> &mdash; gambar yang menunjukkan barang
            mana diletakkan di rak mana, baris ke berapa, kolom ke berapa. Di toko besar ini
            dokumen wajib. Di toko kecil biasanya cuma ada di kepala pemiliknya, dan itu jalan
            terus sampai ada karyawan baru, atau sampai pemiliknya sedang tidak di tempat.
        </p>

        <div class="grid gap-3 md:grid-cols-2">
            <div class="bg-slate-50 rounded-lg p-4">
                <p class="font-semibold text-slate-700 mb-1">Kasir bisa menjawab &ldquo;ada di mana?&rdquo;</p>
                <p class="text-xs">Lokasi rak ikut muncul di kartu produk pada layar Kasir, jadi tidak perlu mencari ke belakang.</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-4">
                <p class="font-semibold text-slate-700 mb-1">Stok opname jadi per rak</p>
                <p class="text-xs">Cetak peta satu rak, hitung sampai selesai, lanjut rak berikutnya &mdash; bukan bolak-balik mencari barang dari daftar.</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-4">
                <p class="font-semibold text-slate-700 mb-1">Restock terarah</p>
                <p class="text-xs">Kotak diberi warna sesuai keadaan stok, jadi rak yang perlu diisi terlihat sekali pandang.</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-4">
                <p class="font-semibold text-slate-700 mb-1">Penempatan bisa dipikirkan</p>
                <p class="text-xs">Barang margin tinggi di rak setinggi mata, barang cepat laku dekat kasir. Sebelumnya keputusan ini tidak punya alat bantu apa pun.</p>
            </div>
        </div>

        <p class="text-xs text-slate-500">
            Yang dicatat di sini hanya <strong>letaknya</strong> &mdash; barang ini ada di rak mana,
            baris dan kolom berapa. Jumlahnya tidak perlu diisi: kalau rak terlihat kosong, stoknya
            diambil dari gudang, dan jumlah yang sebenarnya sudah dijaga Master Produk.
        </p>
    </div>
</details>

<div class="card p-6 mb-4" x-data="{ buat: false }">
    <div class="flex items-center justify-between">
        <h3 class="font-semibold">Daftar Rak</h3>
        <button type="button" class="btn btn-sm btn-primary" @click="buat = !buat">
            <span x-text="buat ? 'Batal' : 'Tambah Rak'">Tambah Rak</span>
        </button>
    </div>

    <form method="POST" action="{{ route('planogram.store') }}" x-show="buat" x-cloak class="grid gap-3 md:grid-cols-5 mt-4 bg-slate-50 p-4 rounded-lg">
        @csrf
        <div class="md:col-span-2">
            <label class="form-label">Nama Rak</label>
            <input type="text" name="name" class="form-input" placeholder="Rak A" required>
        </div>
        <div>
            <label class="form-label">Jumlah Baris</label>
            <input type="number" name="rows" class="form-input" value="4" min="1" max="12" required>
        </div>
        <div>
            <label class="form-label">Jumlah Kolom</label>
            <input type="number" name="cols" class="form-input" value="6" min="1" max="12" required>
        </div>
        <div class="flex items-end">
            <button class="btn btn-primary w-full">Buat</button>
        </div>
        <div class="md:col-span-5">
            <label class="form-label">Keterangan (opsional)</label>
            <input type="text" name="description" class="form-input" placeholder="Rak dinding kiri, dekat pintu masuk">
        </div>
    </form>

    @error('name') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror

    @if($racks->isEmpty())
        <p class="text-sm text-slate-400 py-8 text-center">
            Belum ada rak. Mulai dengan membuat satu rak, misalnya &ldquo;Rak A&rdquo; berukuran 4 baris x 6 kolom.
        </p>
    @else
        <div class="overflow-x-auto mt-4">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr>
                        <th class="py-2 text-left">Nama</th>
                        <th class="py-2 text-left">Keterangan</th>
                        <th class="py-2 text-right">Ukuran</th>
                        <th class="py-2 text-right">Terisi</th>
                        <th class="py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($racks as $rack)
                        @php $kapasitas = $rack->rows * $rack->cols; @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-2 font-medium">{{ $rack->name }}</td>
                            <td class="py-2 text-slate-500">{{ $rack->description ?: '—' }}</td>
                            <td class="py-2 text-right">{{ $rack->rows }} x {{ $rack->cols }}</td>
                            <td class="py-2 text-right">{{ $rack->slots_count }} / {{ $kapasitas }}</td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a href="{{ route('planogram.edit', $rack) }}" class="text-brand-600 hover:underline text-xs">Atur Isi</a>
                                <a href="{{ route('planogram.cetak', $rack) }}" target="_blank" class="text-slate-500 hover:underline text-xs ml-3">Cetak Peta</a>
                                <form method="POST" action="{{ route('planogram.destroy', $rack) }}" class="inline ml-3"
                                      onsubmit="return confirm('Hapus rak ini beserta seluruh isi petanya?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline text-xs">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
