@extends('layouts.app')
@section('title', 'Printer Barcode')

@section('content')
<div class="card p-6 max-w-lg">
    <p class="text-sm text-slate-500 mb-4">Atur ukuran label barcode (mm) sesuai printer label / thermal Anda.</p>
    <form method="POST" action="{{ route('pengaturan.printer-barcode.update') }}" class="space-y-4"
          x-data="{ mode: '{{ $settings['mode'] ?? 'label' }}' }">
        @csrf
        <div>
            <label class="form-label">Label Dicetak Ke</label>
            <select name="mode" x-model="mode" class="form-select">
                <option value="label">Printer label khusus (satu label per lembar)</option>
                <option value="roll">Printer struk 58/80mm (menyambung ke bawah)</option>
            </select>
            <p class="text-xs text-slate-500 mt-1">
                Pilih sesuai printer yang benar-benar dipakai. Printer struk <strong>tidak mengenal</strong>
                ukuran lembar {{ $settings['label_width'] ?? 40 }}&times;{{ $settings['label_height'] ?? 25 }}&nbsp;mm:
                setiap pindah lembar diterjemahkan jadi satu umpan kertas penuh, sehingga beberapa label saja
                bisa menghabiskan segulung kertas. Di mode kertas rol, label dicetak menyambung dengan garis
                potong dan mengikuti Lebar Cetak di menu <a href="{{ route('pengaturan.printer-struk') }}" class="underline">Printer Struk</a>.
            </p>
        </div>
        <div class="grid grid-cols-2 gap-4" x-show="mode === 'label'" x-cloak>
            <div>
                <label class="form-label">Lebar Label (mm)</label>
                <input type="text" data-jpos-number data-number-decimals="2" data-number-group="0" name="label_width" value="{{ $settings['label_width'] ?? 40 }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Tinggi Label (mm)</label>
                <input type="text" data-jpos-number data-number-decimals="2" data-number-group="0" name="label_height" value="{{ $settings['label_height'] ?? 25 }}" class="form-input">
            </div>
        </div>
        <div>
            <label class="form-label">Nama Printer (opsional, catatan saja)</label>
            <input type="text" name="printer_name" value="{{ $settings['printer_name'] ?? '' }}" class="form-input">
        </div>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
@endsection
