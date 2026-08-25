<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Label Barcode</title>
    <style>
    @if($modeRoll)
        {{-- MODE ROL (printer struk 58/80 mm).

             Label dicetak sebagai untaian menerus, BUKAN satu label per halaman. Alasannya
             ada di BarcodePrintController: printer struk tidak mengenal ukuran halaman
             40x25 mm, jadi setiap `page-break` di sini berarti satu umpan kertas penuh -
             dan itulah yang menghabiskan segulung kertas untuk beberapa label saja. --}}
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
            padding: 1.5mm 0;
            border-bottom: 1px dashed #000;
            overflow: hidden;
            /* Tidak ada page-break: justru itu inti mode ini. */
            break-inside: avoid;
            page-break-inside: avoid;
        }
    @else
        {{-- MODE LABEL (printer label khusus): tepat satu label per halaman.

             Margin @page sengaja 0 dan jaraknya dipasang sebagai padding label. Sebelumnya
             @page bermargin 2 mm sementara labelnya tetap dipaksa selebar-penuh, sehingga
             SETIAP label meluap ke halaman kedua - enam label tercetak jadi dua belas
             halaman. Itu sebab kedua kertas habis. --}}
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
            padding: 2mm;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        /* Pemisah halaman dipasang SEBELUM label berikutnya, bukan sesudah setiap label -
           supaya tidak ada halaman kosong menggantung di akhir cetakan. */
        .label + .label { page-break-before: always; break-before: page; }
    @endif

        .label .name { font-size: 10px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label .price { font-size: 11px; font-weight: bold; margin-top: 1mm; }
        .label svg { max-width: 100%; }
        .no-print { text-align: center; margin: 16px; font-family: Arial, sans-serif; }
        .no-print .peringatan { color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; max-width: 620px; margin: 10px auto; text-align: left; font-size: 13px; }
        @media print { .no-print { display: none; } }
    </style>
    <script src="@aset('vendor/jsbarcode-3.11.6.all.min.js')"></script>
</head>
<body>
    <div class="no-print">
        <p style="font-size:13px; color:#334155;">
            <strong>{{ $totalLabel }}</strong> label akan dicetak
            ({{ $jumlahBaris }} baris terpilih &times; {{ $qty }} lembar)
            &mdash; mode <strong>{{ $modeRoll ? 'kertas rol / printer struk' : 'printer label' }}</strong>.
        </p>

        @if($dipangkas)
        <div class="peringatan">
            <strong>Jumlah label dibatasi ke {{ $totalLabel }}.</strong>
            Permintaan aslinya {{ number_format($totalDiminta, 0, ',', '.') }} label &mdash; itu jauh lebih banyak
            dari yang wajar sekali cetak, dan biasanya berarti angka "jumlah cetak per label" salah ketik.
            Kalau memang disengaja, cetak beberapa kali dalam kelompok yang lebih kecil.
        </div>
        @endif

        @if($modeRoll)
        <div class="peringatan">
            Printer yang dipakai adalah <strong>printer struk {{ $lebarCetak }}mm</strong>, bukan printer label.
            Label karena itu dicetak menyambung ke bawah dengan garis potong, bukan satu label per halaman &mdash;
            kalau dipaksa satu per halaman, tiap label memakan satu umpan kertas penuh dan segulung kertas habis
            untuk beberapa label saja. Ganti di <strong>Pengaturan &rsaquo; Printer Barcode</strong>.
        </div>
        @endif

        <button onclick="window.print()" style="padding:8px 16px;">🖨️ Cetak {{ $totalLabel }} Label</button>
    </div>

    @foreach($labels as $label)
        @php $p = $label['product']; @endphp
        @for($i = 0; $i < $qty; $i++)
        <div class="label">
            @if($template['show_name'] ?? true)
                {{-- Nama satuan ditulis lebih tipis: pada label 40x25mm, menebalkan
                     keduanya membuat nama produknya sendiri sulit dibaca dari rak. --}}
                <div class="name">{{ $p->name }}@if($label['unit_label']) <span style="font-weight:normal;">({{ $label['unit_label'] }})</span>@endif</div>
            @endif
            {{-- Jatuh ke SKU kalau produk tidak punya barcode manual. Tanpa ini label
                 tercetak tanpa garis sama sekali dan tidak bisa dipindai di kasir. --}}
            <svg class="barcode-svg" data-code="{{ $label['kode'] }}"></svg>
            @if($template['show_price'] ?? true)
                <div class="price">Rp {{ number_format($label['price'], 0, ',', '.') }}@if($label['unit_label'])<span style="font-weight:normal;">/{{ $label['unit_label'] }}</span>@endif</div>
            @endif
        </div>
        @endfor
    @endforeach

    <script>
        document.querySelectorAll('.barcode-svg').forEach(function (el) {
            JsBarcode(el, el.dataset.code, {
                format: '{{ $template['barcode_type'] ?? 'CODE128' }}',
                displayValue: {{ ($template['show_barcode_text'] ?? true) ? 'true' : 'false' }},
                fontSize: 10,
                height: {{ $modeRoll ? 26 : 30 }},
                margin: 2,
            });
        });
    </script>
</body>
</html>
