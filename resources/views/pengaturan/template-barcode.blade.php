@extends('layouts.app')
@section('title', 'Template Barcode')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-data="{
    showName: {{ ($settings['show_name'] ?? true) ? 'true' : 'false' }},
    showPrice: {{ ($settings['show_price'] ?? true) ? 'true' : 'false' }},
    showText: {{ ($settings['show_barcode_text'] ?? true) ? 'true' : 'false' }},
    barcodeType: '{{ $settings['barcode_type'] ?? 'CODE128' }}',
    renderPreview() {
        const el = document.getElementById('preview-barcode');
        if (!el || typeof JsBarcode === 'undefined') return;
        try {
            JsBarcode(el, '{{ $sample->barcode ?? '1234567890123' }}', {
                format: this.barcodeType || 'CODE128',
                displayValue: this.showText,
                fontSize: 10, height: 30, margin: 4, width: 1.4,
            });
        } catch (e) {}
    }
}" x-init="$watch('showName', () => renderPreview()); $watch('showPrice', () => renderPreview()); $watch('showText', () => renderPreview()); $watch('barcodeType', () => renderPreview()); $nextTick(() => renderPreview());">

    <div class="card p-6">
        <h3 class="font-semibold mb-4">Desain Label Barcode</h3>
        <form method="POST" action="{{ route('pengaturan.template-barcode.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="form-label">Tipe Barcode</label>
                <select name="barcode_type" x-model="barcodeType" class="form-select">
                    <option value="CODE128">CODE128</option>
                    <option value="EAN13">EAN13</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_name" value="1" x-model="showName"> Tampilkan Nama Produk
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_price" value="1" x-model="showPrice"> Tampilkan Harga
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_barcode_text" value="1" x-model="showText"> Tampilkan Angka di Bawah Barcode
            </label>
            <p class="text-xs text-slate-400">Ukuran label diatur di menu Printer Barcode.</p>
            <button class="btn btn-primary">Simpan Template</button>
        </form>
    </div>

    <div class="card p-6">
        <h3 class="font-semibold mb-4">Preview Live</h3>
        <div class="flex items-center justify-center bg-slate-50 rounded-lg p-8">
            <div class="border border-dashed border-slate-300 bg-white p-3 text-center" style="width:160px;">
                <div class="text-xs font-bold truncate" x-show="showName">{{ $sample->name ?? 'Contoh Produk' }}</div>
                <svg id="preview-barcode" class="mx-auto"></svg>
                <div class="text-xs font-bold mt-1" x-show="showPrice">Rp {{ number_format($sample->sell_price ?? 15000, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="@aset('vendor/jsbarcode-3.11.6.all.min.js')"></script>
@endpush
@endsection
