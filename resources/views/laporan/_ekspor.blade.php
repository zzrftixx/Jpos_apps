{{-- Tombol unduh PDF & Excel. Ditulis sekali, dipakai sepuluh laporan.

     $jenis  = potongan jenis laporan pada rute ekspor (mis. 'laba-rugi')
     $filter = parameter yang menentukan isi laporan (periode / tanggal), diteruskan apa adanya
               supaya berkas yang terunduh persis sama dengan yang sedang dilihat di layar -
               kalau tidak, pemilik toko akan mengunduh laporan bulan berjalan padahal yang
               ditampilkan bulan lalu. --}}
@php $filter = $filter ?? []; @endphp

<div class="flex flex-wrap gap-2">
    <a href="{{ route('laporan.ekspor', array_merge(['jenis' => $jenis, 'format' => 'pdf'], $filter)) }}"
       class="btn btn-sm btn-secondary">
        Unduh PDF
    </a>
    <a href="{{ route('laporan.ekspor', array_merge(['jenis' => $jenis, 'format' => 'xlsx'], $filter)) }}"
       class="btn btn-sm btn-secondary">
        Unduh Excel
    </a>
</div>
