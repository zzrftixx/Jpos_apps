@extends('layouts.app')
@section('title', 'Printer Struk')

@section('content')
<div class="card p-6 max-w-lg" x-data="{ profile: '{{ $settings['profile'] ?? 'pos80' }}' }">
    <p class="text-sm text-slate-500 mb-4">
        Pengaturan ini menentukan lebar kertas dan ukuran huruf saat mencetak struk melalui browser ke printer thermal Anda.
        Pastikan printer thermal sudah terpasang sebagai printer default / dipilih saat dialog cetak muncul.
    </p>
    <form method="POST" action="{{ route('pengaturan.printer-struk.update') }}" class="space-y-4">
        @csrf
        <div>
            <label class="form-label">Profil Printer</label>
            <select name="profile" x-model="profile" class="form-select">
                <option value="pos58" {{ ($settings['profile'] ?? '') == 'pos58' ? 'selected' : '' }}>POS58 (58mm)</option>
                <option value="pos80" {{ ($settings['profile'] ?? 'pos80') == 'pos80' ? 'selected' : '' }}>POS80 (80mm)</option>
                <option value="custom" {{ ($settings['profile'] ?? '') == 'custom' ? 'selected' : '' }}>Custom Printer</option>
            </select>
        </div>
        <div x-show="profile === 'custom'" x-cloak>
            <label class="form-label">Lebar Kertas Custom (mm)</label>
            <input type="text" data-jpos-number data-number-decimals="1" data-number-group="0" data-number-min="20" name="custom_width" value="{{ $settings['paper_size'] ?? 80 }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Lebar Cetak (mm)</label>
            <input type="text" data-jpos-number data-number-decimals="1" data-number-group="0" data-number-min="20" name="print_width" value="{{ $settings['print_width'] }}" class="form-input">
            <p class="text-xs text-slate-500 mt-1">
                <strong>Ini angka yang menentukan hasil cetak</strong>, dan wajar kalau lebih kecil dari lebar kertas.
                Printer termal tidak mencetak sampai tepi kertas: rol 58&nbsp;mm hanya mencetak selebar
                <strong>48&nbsp;mm</strong>, rol 80&nbsp;mm selebar <strong>72&nbsp;mm</strong>. Kalau struk ditata
                selebar kertas, printer mengecilkan seluruh halaman supaya muat &mdash; dan itulah yang terlihat
                sebagai tulisan miring dan tidak di tengah, walau pratinjaunya rata tengah.
                Kosongkan untuk memakai angka bawaan profil di atas.
            </p>
        </div>
        <div>
            <label class="form-label">Margin Cetak (mm)</label>
            <input type="text" data-jpos-number data-number-decimals="1" data-number-group="0" name="margin" value="{{ $settings['margin'] ?? 0 }}" class="form-input">
            <p class="text-xs text-slate-500 mt-1">
                Disarankan <strong>0 mm</strong> untuk printer 58mm (POS58) agar lebar cetak tidak menyempit. Margin 5mm akan memangkas ruang isi menjadi 38mm sehingga teks/angka mudah meluap.
            </p>
        </div>
        <div>
            <label class="form-label">Ukuran Font (px)</label>
            <input type="text" data-jpos-number data-number-group="0" data-number-min="8" name="font_size" value="{{ $settings['font_size'] ?? 12 }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Nama Printer (opsional, catatan saja)</label>
            <input type="text" name="printer_name" value="{{ $settings['printer_name'] ?? '' }}" class="form-input" placeholder="Contoh: EPSON TM-T82">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="auto_print" value="1" {{ ($settings['auto_print'] ?? false) ? 'checked' : '' }}>
            Cetak otomatis saat struk dibuka
        </label>
        <div class="flex items-center gap-3">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('pengaturan.printer-struk.uji') }}" target="_blank" class="btn btn-outline">🖨️ Uji Cetak</a>
        </div>
    </form>
    <p class="text-xs text-slate-500 mt-3">
        <strong>Uji Cetak</strong> mencetak selembar contoh berisi garis tepi, batang hitam penuh, dan
        penggaris. Simpan dulu pengaturannya, lalu cetak lembar itu &mdash; kalau garis tepinya utuh
        dan penggarisnya benar-benar selebar angka Lebar Cetak, struk sungguhan pasti rapi juga.
    </p>
    @if($notaTerlaluSempit)
    <div class="mt-4 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
        <strong>Template struk "Nota (tabel)" tidak dipakai pada lebar cetak ini.</strong>
        Tabel 4 kolom (QTY / BARANG / HARGA / JUMLAH) butuh sekitar {{ $lebarNotaMinimal }} mm untuk
        tetap terbaca. Di bawah itu setiap barisnya membungkus berkali-kali, jadi struk otomatis
        memakai tata letak bertumpuk yang memang dirancang untuk kertas sempit. Naikkan Lebar Cetak
        atau ganti templatenya di menu <a href="{{ route('pengaturan.template-struk') }}" class="underline">Template Struk</a>.
    </div>
    @endif
    <div class="mt-4 text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-1">
        <p class="font-bold text-slate-700">Panduan Dialog Cetak Browser (Chrome / Edge):</p>
        <p>&bull; <strong>Margins (Margin)</strong>: Pilih <strong>"None" (Tanpa Margin)</strong> agar tepi kertas tidak terpotong oleh margin browser bawaan.</p>
        <p>&bull; <strong>Scale (Skala)</strong>: Pilih <strong>100% / Default</strong> (jangan "Fit to page" agar cetakan tidak mengecil atau blur).</p>
        <p>&bull; <strong>Headers and footers</strong>: <strong>Matikan (Uncheck)</strong> agar tanggal dan URL browser tidak ikut tercetak.</p>
        <p class="pt-1 text-slate-500">&bull; Logo toko sebaiknya gambar hitam-putih kontras tinggi (bukan foto berwarna) karena printer thermal tidak bisa mencetak gradasi abu-abu.</p>
    </div>
</div>
@endsection
