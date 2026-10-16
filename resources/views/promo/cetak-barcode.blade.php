<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Barcode Promo - {{ $promotion->name }}</title>
    <style>
    @if($modeRoll)
        {{-- MODE ROL (printer struk 58/80 mm) --}}
        @page { size: {{ $lebarCetak }}mm auto; margin: 0; }
        body {
            width: {{ $lebarCetak }}mm;
            max-width: {{ $lebarCetak }}mm;
            margin: 0 auto;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label {
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            padding: 2mm 1mm;
            border-bottom: 1px dashed #000;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }
    @else
        {{-- MODE LABEL (printer label sticker) --}}
        @page { size: {{ $lebarLabel }}mm {{ $tinggiLabel }}mm; margin: 0; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label {
            width: {{ $lebarLabel }}mm;
            height: {{ $tinggiLabel }}mm;
            display: block;
            text-align: center;
            box-sizing: border-box;
            padding: 1.5mm;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .label + .label { page-break-before: always; break-before: page; }
    @endif

        .badge-promo {
            display: inline-block;
            background: #000;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1mm;
        }
        .name {
            font-size: 9.5px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.1;
        }
        .price-row {
            margin-top: 1mm;
            margin-bottom: 0.5mm;
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 4px;
        }
        .normal-price {
            font-size: 9px;
            text-decoration: line-through;
            color: #555;
        }
        .promo-price {
            font-size: 11px;
            font-weight: 900;
            color: #000;
        }
        .hemat-badge {
            font-size: 8px;
            font-weight: bold;
            background: #e2e8f0;
            color: #0f172a;
            padding: 0.5px 3px;
            border-radius: 2px;
        }
        .label svg {
            max-width: 100%;
            height: auto;
            margin-top: 0.5mm;
        }
        .no-print {
            text-align: center;
            margin: 16px;
            font-family: Arial, sans-serif;
        }
        .no-print .panel {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            max-width: 600px;
            margin: 12px auto;
            text-align: left;
            font-size: 13px;
        }
        .no-print button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }
        .no-print button:hover {
            background: #1d4ed8;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
    <script src="@aset('vendor/jsbarcode-3.11.6.all.min.js')"></script>
</head>
<body>
    <div class="no-print">
        <h2>Cetak Barcode Label Rak Promo</h2>
        <div class="panel">
            <div><strong>Promo:</strong> {{ $promotion->name }}</div>
            <div><strong>Total Produk:</strong> {{ count($labels) }} jenis</div>
            <div><strong>Mode Kertas:</strong> {{ $modeRoll ? "Kertas Struk / Rol ({$lebarCetak}mm)" : "Printer Label ({$lebarLabel}x{$tinggiLabel}mm)" }}</div>
            <div style="margin-top: 8px; font-size: 12px; color: #64748b;">
                * Scan barcode promo ini langsung di kasir untuk otomatis menerapkan harga promo pada transaksi!
            </div>
        </div>

        <form method="GET" style="margin-bottom: 12px;">
            <label style="font-size: 13px; margin-right: 6px;">Jumlah lembar per produk:</label>
            <input type="number" name="qty" value="{{ $qty }}" min="1" max="50" style="width: 60px; padding: 4px; text-align: center;">
            <button type="submit" style="padding: 4px 10px; font-size: 12px; background: #64748b;">Terapkan</button>
        </form>

        <button onclick="window.print()">🖨️ Cetak {{ count($labels) * $qty }} Label Barcode</button>
    </div>

    @foreach($labels as $label)
        @for($i = 0; $i < $qty; $i++)
        <div class="label">
            <div class="badge-promo">PROMO SPESIAL</div>
            <div class="name" title="{{ $label['name'] }}">{{ $label['name'] }}</div>
            
            <div class="price-row">
                <span class="normal-price">Rp {{ number_format($label['sell_price'], 0, ',', '.') }}</span>
                <span class="promo-price">Rp {{ number_format($label['promo_price'], 0, ',', '.') }}</span>
                @if($label['hemat'] > 0)
                    <span class="hemat-badge">Hemat Rp {{ number_format($label['hemat'], 0, ',', '.') }}</span>
                @endif
            </div>

            <svg class="barcode-svg" data-code="{{ $label['barcode'] }}"></svg>
        </div>
        @endfor
    @endforeach

    <script>
        document.querySelectorAll('.barcode-svg').forEach(function (el) {
            JsBarcode(el, el.dataset.code, {
                format: 'CODE128',
                displayValue: true,
                fontSize: 9,
                height: {{ $modeRoll ? 22 : 24 }},
                margin: 1,
            });
        });
    </script>
</body>
</html>
