@extends('layouts.app')
@section('title', 'Neraca')

@section('content')
@include('laporan._tabs')

<div class="flex justify-end mb-4">
    @include('laporan._ekspor', ['jenis' => 'neraca', 'filter' => ['tanggal' => $tanggal]])
</div>

@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $belumDiatur = empty($atur['tanggal_mulai']);
@endphp

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <div>
        <label class="form-label">Posisi Per Tanggal</label>
        <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input">
    </div>
    <button class="btn btn-primary">Terapkan</button>
</form>

{{-- Penjelasan dibuka lebih dulu supaya angka di bawahnya bisa dibaca dengan benar.
     Neraca sering disalahartikan sebagai versi lain dari laporan laba - padahal keduanya
     menjawab pertanyaan yang berbeda. --}}
<details class="card p-5 mb-4" open>
    <summary class="cursor-pointer font-semibold text-slate-700">Neraca itu apa? &mdash; baca dulu kalau baru mulai belajar</summary>

    <div class="mt-4 text-sm text-slate-600 space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="bg-slate-50 rounded-lg p-4">
                <p class="font-semibold text-slate-700 mb-1">Laporan Laba Rugi itu FILM</p>
                <p>Merekam satu rentang waktu: <em>&ldquo;selama Januari, saya untung berapa?&rdquo;</em>
                   Isinya arus &mdash; omset masuk, HPP dan beban keluar, sisanya laba.</p>
            </div>
            <div class="bg-brand-50 rounded-lg p-4">
                <p class="font-semibold text-slate-700 mb-1">Neraca itu FOTO</p>
                <p>Membekukan satu detik: <em>&ldquo;per hari ini, apa yang saya PUNYA, berapa yang
                   saya UTANG, dan berapa yang benar-benar bagian saya?&rdquo;</em>
                   Isinya posisi, bukan arus.</p>
            </div>
        </div>

        <div class="bg-slate-800 text-slate-100 rounded-lg p-4 text-center font-mono text-sm">
            <div class="text-base">ASET &nbsp;=&nbsp; KEWAJIBAN &nbsp;+&nbsp; MODAL</div>
            <div class="text-xs text-slate-400 mt-1">yang dipunya &nbsp;=&nbsp; punya orang lain &nbsp;+&nbsp; punya sendiri</div>
        </div>

        <p>Kedua sisi <strong>selalu</strong> sama, dan itu bukan kebetulan. Setiap rupiah barang di
           toko asalnya cuma dua: uang orang lain (hutang), atau uang sendiri (modal). Tidak ada
           kemungkinan ketiga. Kalau kiri dan kanan tidak sama, pasti ada yang belum tercatat.</p>

        <p class="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <strong>Yang paling sering mengejutkan pemilik toko:</strong> laporan laba bilang untung
            besar, tapi dompet kosong. Neraca yang menjelaskan ke mana perginya &mdash; biasanya
            menumpuk jadi persediaan yang belum laku, atau dipakai membayar hutang.
            Laporan laba rugi tidak akan pernah bisa menunjukkan itu.
        </p>

        <p><strong>Prive</strong> juga sering disalahpahami: uang yang diambil pemilik untuk keperluan
           pribadi <em>bukan</em> beban toko. Itu mengurangi modal, bukan mengurangi laba &mdash;
           karena toko tidak mendapat apa pun sebagai gantinya.</p>
    </div>
</details>

{{-- Tanpa titik nol, laba ditahan akan menjumlahkan seluruh riwayat sejak aplikasi dipasang,
     termasuk periode yang datanya belum lengkap. Karena itu ini yang pertama diminta. --}}
@if($belumDiatur)
    <div class="card p-5 mb-4 border-l-4 border-amber-400">
        <h3 class="font-semibold text-slate-700 mb-1">Tentukan titik awal pembukuan dulu</h3>
        <p class="text-sm text-slate-500 mb-4">
            Neraca butuh tahu dari kapan dihitung dan berapa yang sudah ada saat itu. Tanpa ini,
            angkanya akan menjumlahkan seluruh riwayat sejak aplikasi dipasang &mdash; termasuk
            masa yang datanya belum lengkap.
        </p>
        @include('laporan._neraca-pembukuan', ['atur' => $atur])
    </div>
@endif

@php
    $seimbang = abs($posisi->selisih) < 0.01;
@endphp

{{-- Baris status neraca: Hijau jika seimbang, Merah dengan Tombol Aksi Langsung jika belum seimbang --}}
<div class="card p-4 sm:p-5 mb-4 {{ $seimbang ? 'border-l-4 border-green-500 bg-green-50/20' : 'border-l-4 border-red-500 bg-red-50/20' }}">
    @if($seimbang)
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-green-100 text-green-700 flex items-center justify-center shrink-0">
                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; flex-shrink: 0;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-green-800">Neraca seimbang.</p>
                <p class="text-xs text-slate-500 mt-0.5">
                    Total aset sama persis dengan kewajiban ditambah modal. Semua yang ada di toko bisa dijelaskan asal uangnya.
                </p>
            </div>
        </div>
    @else
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-600 font-bold text-xs">!</span>
                    <p class="text-sm sm:text-base font-bold text-red-700">
                        Neraca Belum Seimbang &mdash; Selisih {{ $rp(abs($posisi->selisih)) }}
                    </p>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed max-w-2xl">
                    Penyebab paling umum: produk dan stok diinput langsung di Master Produk sehingga nilai persediaan belum memiliki pos modal awal penyeimbang.
                </p>
            </div>

            @if($saranModalAwal > 0)
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 shrink-0">
                    <form method="POST" action="{{ route('laporan.neraca.pembukuan') }}" class="inline">
                        @csrf
                        <input type="hidden" name="tanggal_mulai" value="{{ $atur['tanggal_mulai'] ?? now()->startOfYear()->toDateString() }}">
                        <input type="hidden" name="saldo_awal_kas" value="{{ (int) ($atur['saldo_awal_kas'] ?? 0) }}">
                        <input type="hidden" name="modal_awal" value="{{ (int) $saranModalAwal }}">
                        <button type="submit"
                                class="h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs sm:text-sm inline-flex items-center justify-center gap-2 shadow-md transition select-none cursor-pointer whitespace-nowrap"
                                title="Klik untuk otomatis menyesuaikan modal awal toko agar neraca langsung seimbang">
                            <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; flex-shrink: 0;" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Seimbangkan Neraca Otomatis (Rp {{ number_format($saranModalAwal, 0, ',', '.') }})</span>
                        </button>
                    </form>
                    <a href="#titik-awal-pembukuan" class="text-xs text-slate-500 hover:text-slate-800 underline text-center sm:text-left whitespace-nowrap">
                        Atur Manual &darr;
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>

<div class="grid gap-4 lg:grid-cols-2 mb-4">
    {{-- ================================================================== ASET --}}
    <div class="card p-6">
        <h3 class="font-semibold mb-1">Aset &mdash; yang toko punya</h3>
        <p class="text-xs text-slate-400 mb-4">Posisi per {{ \Illuminate\Support\Carbon::parse($tanggal)->format('d/m/Y') }}</p>

        <table class="w-full text-sm">
            <tbody>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Kas &amp; setara kas
                        <span class="block text-xs text-slate-400">Uang tunai di laci dan rekening</span>
                    </td>
                    <td class="py-2 text-right font-medium">{{ $rp($posisi->kas) }}</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Persediaan barang
                        <span class="block text-xs text-slate-400">Stok di rak dikali harga modal, termasuk barang pesanan yang belum diserahkan</span>
                    </td>
                    <td class="py-2 text-right font-medium">{{ $rp($posisi->persediaan) }}</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Aset tetap
                        <span class="block text-xs text-slate-400">Etalase, komputer, timbangan &mdash; setelah dikurangi penyusutan</span>
                    </td>
                    <td class="py-2 text-right font-medium">{{ $rp($posisi->aset_tetap) }}</td>
                </tr>
                <tr class="bg-slate-50">
                    <td class="py-3 font-semibold">Total Aset</td>
                    <td class="py-3 text-right font-bold text-base">{{ $rp($posisi->total_aset) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ==================================================== KEWAJIBAN + MODAL --}}
    <div class="card p-6">
        <h3 class="font-semibold mb-1">Kewajiban &amp; Modal &mdash; asal uangnya</h3>
        <p class="text-xs text-slate-400 mb-4">Harus sama persis dengan Total Aset</p>

        <table class="w-full text-sm">
            <tbody>
                <tr>
                    <td colspan="2" class="pt-1 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Kewajiban &mdash; punya orang lain</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Hutang usaha
                        <span class="block text-xs text-slate-400">Barang yang sudah diterima tapi belum dibayar ke pemasok</span>
                    </td>
                    <td class="py-2 text-right font-medium">{{ $rp($posisi->hutang_usaha) }}</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Uang muka pelanggan
                        <span class="block text-xs text-slate-400">DP yang sudah diterima untuk barang yang belum diserahkan</span>
                    </td>
                    <td class="py-2 text-right font-medium">{{ $rp($posisi->uang_muka) }}</td>
                </tr>
                <tr class="border-b border-slate-200">
                    <td class="py-2 font-semibold">Total Kewajiban</td>
                    <td class="py-2 text-right font-semibold">{{ $rp($posisi->total_kewajiban) }}</td>
                </tr>

                <tr>
                    <td colspan="2" class="pt-4 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Modal &mdash; punya sendiri</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">Modal awal</td>
                    <td class="py-2 text-right">{{ $rp($posisi->modal_awal) }}</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">Tambahan modal</td>
                    <td class="py-2 text-right">{{ $rp($posisi->tambahan_modal) }}</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Prive
                        <span class="block text-xs text-slate-400">Uang yang diambil pemilik &mdash; mengurangi modal, bukan laba</span>
                    </td>
                    <td class="py-2 text-right text-red-600">&minus; {{ $rp($posisi->prive) }}</td>
                </tr>
                <tr class="border-b border-slate-100">
                    <td class="py-2">
                        Laba ditahan
                        <span class="block text-xs text-slate-400">Untung yang tidak diambil, sejak {{ $atur['tanggal_mulai'] ? \Illuminate\Support\Carbon::parse($atur['tanggal_mulai'])->format('d/m/Y') : 'awal data' }}</span>
                    </td>
                    <td class="py-2 text-right {{ $posisi->laba_ditahan < 0 ? 'text-red-600' : '' }}">{{ $rp($posisi->laba_ditahan) }}</td>
                </tr>
                <tr class="border-b border-slate-200">
                    <td class="py-2 font-semibold">Total Modal</td>
                    <td class="py-2 text-right font-semibold">{{ $rp($posisi->total_modal) }}</td>
                </tr>

                <tr class="bg-slate-50">
                    <td class="py-3 font-semibold">Total Kewajiban dan Modal</td>
                    <td class="py-3 text-right font-bold text-base">{{ $rp($posisi->total_kewajiban + $posisi->total_modal) }}</td>
                </tr>
                @unless($seimbang)
                    <tr>
                        <td class="py-2 text-red-600">Selisih belum tercatat</td>
                        <td class="py-2 text-right font-semibold text-red-600">{{ $rp($posisi->selisih) }}</td>
                    </tr>
                @endunless
            </tbody>
        </table>
    </div>
</div>

{{-- Sisa tagihan pesanan sengaja TIDAK dijadikan pos neraca: barangnya belum diserahkan,
     jadi penjualannya belum boleh diakui. Tapi pemilik toko tetap perlu tahu angkanya. --}}
@if($posisi->sisa_tagihan_pesanan > 0)
    <div class="card p-4 mb-4 text-sm">
        <span class="text-slate-500">Sisa tagihan pesanan DP:</span>
        <strong>{{ $rp($posisi->sisa_tagihan_pesanan) }}</strong>
        <span class="block text-xs text-slate-400 mt-1">
            Belum masuk neraca karena barangnya belum diserahkan &mdash; jadi penjualannya belum
            boleh diakui. Angka ini menjadi omset saat pesanannya dilunasi.
        </span>
    </div>
@endif

{{-- ============================================================== ASET TETAP --}}
<div class="card p-6 mb-4">
    <div class="flex items-center justify-between mb-1">
        <h3 class="font-semibold">Aset Tetap</h3>
        <a href="{{ route('kas.index') }}" class="btn btn-sm btn-secondary">Catat lewat menu Kas</a>
    </div>
    <p class="text-xs text-slate-400 mb-4">
        Barang yang dipakai untuk berjualan, bukan untuk dijual &mdash; etalase, komputer kasir,
        motor pengantar, timbangan.
        <strong>Halaman ini hanya menampilkan.</strong> Pembelian peralatan dicatat di menu
        <em>Kas Masuk/Keluar</em> dengan kategori &ldquo;Beli Peralatan / Aset Tetap&rdquo;,
        supaya uangnya dan asetnya tercatat bersamaan dalam satu langkah.
    </p>

    @if($asetTetap->isEmpty())
        <p class="text-sm text-slate-400 py-4 text-center">
            Belum ada aset tetap. Catat pembeliannya lewat menu Kas.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr>
                        <th class="py-2 text-left">Nama</th>
                        <th class="py-2 text-left">Diperoleh</th>
                        <th class="py-2 text-right">Harga Perolehan</th>
                        <th class="py-2 text-right">Umur</th>
                        <th class="py-2 text-right">Akum. Penyusutan</th>
                        <th class="py-2 text-right">Nilai Buku</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($asetTetap as $aset)
                        <tr class="border-b border-slate-100">
                            <td class="py-2">{{ $aset->name }}</td>
                            <td class="py-2">{{ $aset->acquired_at->format('d/m/Y') }}</td>
                            <td class="py-2 text-right">{{ $rp($aset->acquisition_cost) }}</td>
                            <td class="py-2 text-right">{{ $aset->useful_life_months ? $aset->useful_life_months . ' bln' : '—' }}</td>
                            <td class="py-2 text-right text-red-600">{{ $rp($aset->penyusutanSampai($tanggal)) }}</td>
                            <td class="py-2 text-right font-medium">{{ $rp($aset->nilaiBukuSampai($tanggal)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400 mt-3">
            Untuk menghapus, hapus baris kasnya di menu Kas &mdash; asetnya ikut terhapus, supaya
            neraca tidak timpang.
        </p>
    @endif
</div>

{{-- ============================================================== TUTUP BUKU --}}
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-6" id="titik-awal-pembukuan">
        <h3 class="font-semibold mb-1">Titik Awal Pembukuan</h3>
        <p class="text-xs text-slate-400 mb-4">
            Dari kapan neraca dihitung, dan berapa yang sudah ada saat itu.
            Mengubahnya akan mengubah seluruh angka di halaman ini.
        </p>
        @include('laporan._neraca-pembukuan', ['atur' => $atur])
    </div>

    <div class="card p-6">
        <h3 class="font-semibold mb-1">Tutup Buku</h3>
        <p class="text-xs text-slate-400 mb-4">
            Membekukan neraca tanggal ini supaya bisa dibandingkan nanti. Perlu dilakukan karena
            nilai persediaan <strong>tidak bisa dihitung mundur</strong> &mdash; catatan stok hanya
            menyimpan jumlahnya, dan harga modal berubah setiap kali kulakan. Tanpa dibekukan,
            pertanyaan &ldquo;bulan lalu modal saya berapa?&rdquo; tidak akan bisa dijawab.
        </p>

        <form method="POST" action="{{ route('laporan.neraca.tutup-buku') }}" class="grid gap-3 md:grid-cols-3 mb-5">
            @csrf
            <div>
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input" required>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Catatan (opsional)</label>
                <input type="text" name="note" class="form-input" placeholder="Tutup buku bulan berjalan">
            </div>
            <div class="md:col-span-3">
                <button class="btn btn-primary btn-sm">Bekukan Neraca Tanggal Ini</button>
            </div>
        </form>

        @if($snapshots->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-slate-400 border-b border-slate-200">
                        <tr>
                            <th class="py-2 text-left">Tanggal</th>
                            <th class="py-2 text-right">Total Aset</th>
                            <th class="py-2 text-right">Kewajiban</th>
                            <th class="py-2 text-right">Modal</th>
                            <th class="py-2 text-right">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($snapshots as $snap)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $snap->tanggal->format('d/m/Y') }}</td>
                                <td class="py-2 text-right">{{ $rp($snap->total_aset) }}</td>
                                <td class="py-2 text-right">{{ $rp($snap->total_kewajiban) }}</td>
                                <td class="py-2 text-right">{{ $rp($snap->total_modal) }}</td>
                                <td class="py-2 text-right {{ abs($snap->selisih) >= 0.01 ? 'text-red-600 font-semibold' : 'text-slate-400' }}">{{ $rp($snap->selisih) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
