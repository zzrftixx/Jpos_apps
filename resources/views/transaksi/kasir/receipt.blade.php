<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk {{ $sale->invoice_no }}</title>
    {{-- $layout, $lebarCetak, dan $fontNota datang dari KasirController::receipt(): blok
         <style> di bawah dibangun dari nilai-nilai itu, jadi keputusannya harus sudah bulat
         sebelum satu baris CSS pun ditulis. --}}
    @php
        $layout = $layout ?? ($templateStruk['layout'] ?? 'simple');
        $lebarCetak = $lebarCetak ?? \App\Support\Struk::lebarCetak($printerStruk);
        $lebarIsi = $lebarIsi ?? $lebarCetak;
        $fontNota = $fontNota ?? 12;
        $karakterAngka = $karakterAngka ?? 5;
        $notaTerlaluSempit = $notaTerlaluSempit ?? false;
    @endphp
    <style>
    @if($layout === 'invoice')
        {{-- Kertas continuous form 22 x 16 cm untuk printer dot matrix. Ukurannya TETAP dan
             sengaja tidak mengikuti pengaturan Printer Struk: yang diatur di sana adalah
             lebar kertas roll termal, dan memakainya di sini akan memotong tabel invoice. --}}
        @page { size: 220mm 160mm; margin: 8mm; }
        body {
            width: 204mm;
            margin: 0 auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #000;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .inv-logo { max-width: 64px; max-height: 64px; margin-right: 10px; filter: grayscale(1) contrast(1.5); }
        .inv-title { font-size: 20px; font-weight: bold; letter-spacing: 3px; }
        .inv-parties { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .inv-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .inv-table th, .inv-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        .inv-table th { background: rgba(0,0,0,0.06); text-align: center; }
        .inv-totals { width: 60%; margin-left: auto; }
        .inv-totals td { padding: 2px 4px; }
        .inv-signature { display: flex; justify-content: space-between; margin-top: 28px; }
        .inv-signature > div { width: 40%; text-align: center; }
        .inv-signature .sign-line { margin-top: 42px; border-top: 1px solid #000; padding-top: 2px; }
        .no-print { margin-top: 12px; }
        @media print { .no-print { display: none; } }
    @else
        {{-- Ditata selebar yang BENAR-BENAR BISA DICETAK, bukan selebar kertas. Alasannya
             panjang dan penting; seluruhnya ada di App\Support\Struk. Ringkasnya: kepala
             cetak printer termal lebih sempit dari rolnya (58mm -> 48mm, 80mm -> 72mm), dan
             menata struk selebar kertas membuat pengandar printer mengecilkan seluruh
             halaman supaya muat - itulah yang terlihat sebagai tulisan miring dan tidak di
             tengah, padahal pratinjaunya rata tengah.

             Margin cetak sengaja dipasang sebagai padding BADAN, bukan margin @page:
             margin @page mempersempit kotak halaman sementara lebar badan tetap, sehingga
             isinya justru meluap ke luar area cetak - persis kebalikan dari yang diinginkan
             orang yang menaikkan angka margin untuk merapikan hasilnya. --}}
        @page { size: {{ $lebarCetak }}mm auto; margin: 0; }
        body {
            width: {{ $lebarCetak }}mm;
            margin: 0 auto;
            font-family: 'Courier New', monospace;
            font-size: {{ $layout === 'tabel' ? 12 : ($printerStruk['font_size'] ?? 12) }}px;
            font-weight: 600;
            color: #000;
            padding: {{ $layout === 'tabel' ? '4px' : '6px' }} {{ max(0, (float) ($printerStruk['margin'] ?? 0)) }}mm;
            box-sizing: border-box;
            max-width: {{ $lebarCetak }}mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        hr { border: none; border-top: 1px dashed #000; margin: {{ $layout === 'tabel' ? '4px 0' : '6px 0' }}; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .item-name { width: 100%; }
        {{-- Font tabel menyesuaikan lebar cetak: pada kertas sempit, nominal yang membungkus
             ke baris berikutnya membuat struk salah dibaca - dan itu jauh lebih mahal
             daripada huruf yang sedikit lebih kecil. Batas bawahnya 9px; lihat Struk. --}}
        .item-table { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #000; margin: 3px 0; box-sizing: border-box; font-size: {{ $fontNota }}px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 1px 2px; vertical-align: top; overflow-wrap: break-word; word-break: normal; box-sizing: border-box; }
        .item-table th { text-align: center; background: rgba(0,0,0,0.06); white-space: nowrap; }
        .item-table td.center { text-align: center; }
        .item-table td.right { text-align: right; }
        .logo {
            max-width: 60px;
            margin: 0 auto 4px;
            display: block;
            filter: grayscale(1) contrast(1.5);
            image-rendering: -webkit-optimize-contrast;
        }
        .no-print { margin-top: 12px; }
        @media print { .no-print { display: none; } }
    @endif
    </style>
</head>
<body>
@if($layout === 'invoice')
    <div class="inv-header">
        <div style="display:flex; align-items:center;">
            @if(($templateStruk['show_logo'] ?? false) && !empty($storeProfile['logo']))
                <img src="{{ url('media/' . $storeProfile['logo']) }}" class="inv-logo">
            @endif
            <div>
                <div style="font-size:16px; font-weight:bold;">{{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</div>
                @if($templateStruk['show_address'] ?? true)
                    <div>{{ $storeProfile['address'] ?? '' }}</div>
                @endif
                @if($templateStruk['show_phone'] ?? true)
                    <div>Telp: {{ $storeProfile['phone'] ?? '' }}</div>
                @endif
            </div>
        </div>
        <div class="right">
            <div class="inv-title">INVOICE</div>
            <div>No: {{ $sale->invoice_no }}</div>
            <div>Tanggal: {{ $sale->created_at->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    @if($sale->order_status === 'waiting')
        <div class="center" style="font-weight:bold; border:1px solid #000; padding:3px 0; margin-bottom:8px;">** BUKTI PEMESANAN (DP) **</div>
    @elseif($sale->order_status === 'cancelled')
        <div class="center" style="font-weight:bold; border:1px solid #000; padding:3px 0; margin-bottom:8px;">** PESANAN DIBATALKAN **</div>
    @endif

    <div class="inv-parties">
        <div>
            <div style="font-weight:bold;">Kepada Yth:</div>
            <div>{{ $sale->customer->name ?? 'Pelanggan Umum' }}</div>
        </div>
        @if($templateStruk['show_cashier'] ?? true)
        <div class="right">
            <div>Kasir: {{ $sale->cashier->name ?? '-' }}</div>
            @if($sale->order_status === 'waiting' && $sale->due_date)
            <div>Jatuh Tempo: {{ $sale->due_date->format('d/m/Y') }}</div>
            @endif
        </div>
        @endif
    </div>

    <table class="inv-table">
        <thead>
            <tr>
                <th style="width:6%;">No</th>
                <th>Nama Barang</th>
                <th style="width:12%;">Qty</th>
                <th style="width:20%;">Harga Satuan</th>
                <th style="width:20%;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $i => $item)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $item->product_name }}{{ $item->unit_label ? ' ('.$item->unit_label.')' : '' }}</td>
                <td class="center">@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }}</td>
                <td class="right">{{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            {{-- Baris kosong pengganjal: kertas continuous form punya tinggi tetap, jadi
                 invoice dengan dua barang tetap harus mengisi kotak tabelnya. --}}
            @for($i = count($sale->items); $i < 5; $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <table class="inv-totals">
        @if($sale->discount > 0)
        <tr><td>Diskon</td><td class="right">-{{ number_format($sale->discount, 0, ',', '.') }}</td></tr>
        @endif
        @if($sale->tax_amount > 0)
        <tr><td>Pajak</td><td class="right">{{ number_format($sale->tax_amount, 0, ',', '.') }}</td></tr>
        @endif
        <tr style="font-weight:bold; border-top:1px solid #000;"><td>TOTAL</td><td class="right">Rp {{ number_format($sale->total, 0, ',', '.') }}</td></tr>
        @if($sale->order_status === 'waiting')
        <tr><td>DP Dibayar</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
        <tr style="font-weight:bold;"><td>SISA BAYAR</td><td class="right">{{ number_format($sale->remaining, 0, ',', '.') }}</td></tr>
        @else
        <tr><td>Bayar ({{ strtoupper($sale->payment_method) }})</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Kembali</td><td class="right">{{ number_format($sale->change_amount, 0, ',', '.') }}</td></tr>
        @endif
    </table>

    @if($sale->order_status === 'waiting')
        <div class="center" style="margin-top:8px;">Sisa pembayaran dilunasi saat pengambilan barang.</div>
    @endif

    <div class="inv-signature">
        <div>
            <div>Penerima,</div>
            <div class="sign-line">( ..................... )</div>
        </div>
        <div>
            <div>Hormat Kami,</div>
            <div class="sign-line">( {{ $sale->cashier->name ?? '.....................' }} )</div>
        </div>
    </div>

    @if(!empty($templateStruk['footer_note']))
        <div class="center" style="margin-top:14px; font-size:0.9em;">{{ $templateStruk['footer_note'] }}</div>
    @endif

    <div class="no-print center">
        <button onclick="window.print()" style="padding:8px 16px;">🖨️ Cetak Invoice</button>
    </div>
@else
    @if(($templateStruk['show_logo'] ?? false) && !empty($storeProfile['logo']))
        <img src="{{ url('media/' . $storeProfile['logo']) }}" class="logo">
    @endif
    <div class="center" style="font-weight:bold; font-size: 1.1em;">{{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</div>
    @if($templateStruk['show_address'] ?? true)
        <div class="center">{{ $storeProfile['address'] ?? '' }}</div>
    @endif
    @if($templateStruk['show_phone'] ?? true)
        <div class="center">{{ $storeProfile['phone'] ?? '' }}</div>
    @endif

    @if(!empty($templateStruk['header_note']))
        <hr><div class="center">{{ $templateStruk['header_note'] }}</div>
    @endif

    @if($sale->order_status === 'waiting')
        <div class="center" style="font-weight:bold; border:1px solid #000; padding:2px 0; margin:4px 0;">** BUKTI PEMESANAN (DP) **</div>
    @elseif($sale->order_status === 'cancelled')
        <div class="center" style="font-weight:bold; border:1px solid #000; padding:2px 0; margin:4px 0;">** PESANAN DIBATALKAN **</div>
    @endif

    @if($layout === 'tabel')
        {{-- Header ala nota tradisional: Kepada / NOTA NO. --}}
        <div>Kepada: {{ $sale->customer->name ?? 'Umum' }}</div>
        @if($templateStruk['show_cashier'] ?? true)
        <div>Kasir: {{ $sale->cashier->name ?? '-' }}</div>
        @endif
        <div style="font-weight:bold; margin-top:2px;">NOTA NO. {{ $sale->invoice_no }}</div>
        <div>{{ $sale->created_at->format('d/m/Y H:i') }}</div>
        @if($sale->order_status === 'waiting' && $sale->due_date)
        <div>Jatuh Tempo: {{ $sale->due_date->format('d/m/Y') }}</div>
        @endif
    @else
        <hr>
        <table>
            <tr><td>No</td><td class="right">{{ $sale->invoice_no }}</td></tr>
            <tr><td>Tanggal</td><td class="right">{{ $sale->created_at->format('d/m/Y H:i') }}</td></tr>
            @if($templateStruk['show_cashier'] ?? true)
            <tr><td>Kasir</td><td class="right">{{ $sale->cashier->name ?? '-' }}</td></tr>
            @endif
            @if(($templateStruk['show_customer'] ?? true) && $sale->customer)
            <tr><td>Pelanggan</td><td class="right">{{ $sale->customer->name }}</td></tr>
            @endif
            @if($sale->order_status === 'waiting' && $sale->due_date)
            <tr><td>Jatuh Tempo</td><td class="right">{{ $sale->due_date->format('d/m/Y') }}</td></tr>
            @endif
        </table>
        <hr>
    @endif

    @php
        // Lebar kolom dihitung dari KEBUTUHAN NYATA tiap kolom dalam karakter, lalu diubah
        // ke persen mengikuti lebar cetak yang berlaku - bukan persen tetap.
        //
        // Cara lama memberi HARGA/JUMLAH sekian persen, padahal yang dibutuhkan kolom itu
        // sekian karakter ("1.500.000" butuh 9, berapa pun lebar kertasnya). Persen yang pas
        // di 80mm jadi kelebihan di 58mm, dan sisanya diambil dari kolom BARANG: terukur
        // 13,1mm di 58mm - tujuh karakter - sehingga satu nama produk pecah jadi enam baris.
        //
        // Seluruh perhitungannya beserta alasannya ada di App\Support\Struk.
        $karakterAngka = $sale->items->flatMap(fn($item) => [
            strlen(number_format($item->price, 0, ',', '.')),
            strlen(number_format($item->subtotal, 0, ',', '.')),
        ])->push(strlen(number_format($sale->total, 0, ',', '.')))->max() ?: 5;

        $kolom = \App\Support\Struk::kolomNota($lebarIsi, $fontNota, $karakterAngka);

        $qtyW = $kolom['qty']; $nameW = $kolom['nama'];
        $priceW = $kolom['harga']; $totalW = $kolom['jumlah'];

        // Berapa karakter nama produk yang muat dalam 1 baris di kolom BARANG. Kalau nama
        // produk lebih panjang dari itu - berarti bakal wrap - font baris itu diturunkan
        // 1px supaya wrap-nya lebih rapi.
        $nameCharsPerLine = \App\Support\Struk::karakterKolomNama($lebarIsi, $fontNota, $nameW);
    @endphp
    @if($layout === 'tabel')
        <table class="item-table">
            <colgroup>
                <col style="width:{{ $qtyW }}%;">
                <col style="width:{{ $nameW }}%;">
                <col style="width:{{ $priceW }}%;">
                <col style="width:{{ $totalW }}%;">
            </colgroup>
            <thead>
                <tr><th>QTY</th><th>BARANG</th><th>HARGA</th><th>JUMLAH</th></tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                {{-- Nama yang bakal membungkus diturunkan 1px, tapi tidak pernah di bawah
                     batas keterbacaan printer termal. --}}
                @php $nameFontSize = strlen($item->product_name) > $nameCharsPerLine
                    ? max(\App\Support\Struk::FONT_NOTA_MIN, $fontNota - 1)
                    : $fontNota; @endphp
                <tr>
                    <td class="center">@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }}</td>
                    <td style="font-size:{{ $nameFontSize }}px;">{{ $item->product_name }}</td>
                    <td class="right">{{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($layout === 'normal')
        @foreach($sale->items as $item)
            <table>
                <tr>
                    <td>{{ $item->product_name }}{{ $item->unit_label ? ' ('.$item->unit_label.')' : '' }}</td>
                    <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            </table>
            <div style="font-size:0.85em; opacity:0.75; margin-bottom:3px;">@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }} x {{ number_format($item->price, 0, ',', '.') }}</div>
        @endforeach
        <hr>
    @else
        @foreach($sale->items as $item)
            <div class="item-name">{{ $item->product_name }}{{ $item->unit_label ? ' ('.$item->unit_label.')' : '' }}</div>
            <table style="border-bottom: 1px solid #000; margin-bottom: 3px; padding-bottom: 2px;">
                <tr>
                    <td>@qty($item->qty){{ $item->unit_label ? ' '.$item->unit_label : '' }} x {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            </table>
        @endforeach
        <hr>
    @endif

    @php $isNota = $layout === 'tabel'; @endphp
    <table>
        @unless($isNota)
        <tr><td>Subtotal</td><td class="right">{{ number_format($sale->subtotal, 0, ',', '.') }}</td></tr>
        @endunless
        @if($sale->discount > 0)
        <tr><td>Diskon</td><td class="right">-{{ number_format($sale->discount, 0, ',', '.') }}</td></tr>
        @endif
        @if($sale->tax_amount > 0)
        <tr><td>Pajak</td><td class="right">{{ number_format($sale->tax_amount, 0, ',', '.') }}</td></tr>
        @endif
        <tr style="font-weight:bold;"><td>{{ $isNota ? 'Jumlah Rp.' : 'TOTAL' }}</td><td class="right">{{ number_format($sale->total, 0, ',', '.') }}</td></tr>
        @if($sale->order_status === 'waiting')
        <tr><td>DP Dibayar</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
        <tr style="font-weight:bold;"><td>SISA BAYAR</td><td class="right">{{ number_format($sale->remaining, 0, ',', '.') }}</td></tr>
        @else
        <tr><td>Bayar ({{ strtoupper($sale->payment_method) }})</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Kembali</td><td class="right">{{ number_format($sale->change_amount, 0, ',', '.') }}</td></tr>
        @endif
    </table>
    <hr>

    @if($sale->order_status === 'waiting')
        <div class="center">Sisa pembayaran dilunasi saat pengambilan barang.</div>
        <hr>
    @endif

    <div class="center">{{ $templateStruk['footer_note'] ?? 'Terima kasih telah berbelanja!' }}</div>

    <div class="no-print center">
        <button onclick="window.print()" style="padding:8px 16px;">🖨️ Cetak Struk</button>
    </div>
@endif

    <script>
        window.onload = function () {
            @if($printerStruk['auto_print'] ?? false)
                window.print();
            @endif
        };
    </script>
</body>
</html>
