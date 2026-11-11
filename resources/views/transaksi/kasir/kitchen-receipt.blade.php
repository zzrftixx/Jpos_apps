<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kitchen Ticket #{{ $sale->invoice_no }}</title>
    @php
        $lebarCetak = $lebarCetak ?? \App\Support\Struk::lebarCetak($printerStruk ?? []);
        $lebarIsi = $lebarIsi ?? $lebarCetak;
        $fontSize = $printerStruk['font_size'] ?? 13;
    @endphp
    <style>
        @page { size: {{ $lebarCetak }}mm auto; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            width: {{ $lebarCetak }}mm;
            max-width: {{ $lebarCetak }}mm;
            margin: 0 auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $fontSize }}px;
            font-weight: 700;
            color: #000;
            padding: 4px {{ max(0, (float) ($printerStruk['margin'] ?? 0)) }}mm;
            box-sizing: border-box;
            overflow-x: hidden;
            word-wrap: break-word;
            line-height: 1.3;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        hr { border: none; border-top: 2px dashed #000; margin: 6px 0; }
        .double-hr { border: none; border-top: 3px double #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 0; vertical-align: top; }
        .table-badge {
            display: block;
            border: 2px solid #000;
            padding: 6px 4px;
            margin: 6px 0;
            text-align: center;
            font-size: 1.35em;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .item-row {
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #000;
        }
        .item-main {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            font-size: 1.1em;
            font-weight: 800;
        }
        .item-qty {
            width: 25%;
            font-size: 1.2em;
            font-weight: 900;
        }
        .item-title {
            width: 75%;
            text-align: left;
        }
        .item-note {
            margin-left: 25%;
            font-size: 0.9em;
            font-style: italic;
            font-weight: bold;
            color: #111;
            margin-top: 2px;
        }
        .no-print { margin-top: 14px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    {{-- HEADER KITCHEN TICKET --}}
    <div class="center" style="font-size: 1.15em; font-weight: 900; letter-spacing: 0.5px;">
        *** PESANAN DAPUR / BAR ***
    </div>
    <div class="center" style="font-size: 0.9em;">
        {{ $storeProfile['name'] ?? 'JPOS F&B' }}
    </div>

    {{-- NOMOR MEJA SANGAT BESAR & JELAS --}}
    <div class="table-badge">
        {{ !empty($sale->table_no) ? $sale->table_no : 'MEJA: - (TAKE AWAY)' }}
    </div>

    {{-- META PESANAN --}}
    <table>
        <tr>
            <td style="width: 32%;">No. Order</td>
            <td style="width: 4%;">:</td>
            <td><strong>{{ $sale->invoice_no }}</strong></td>
        </tr>
        <tr>
            <td>Waktu</td>
            <td>:</td>
            <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>Kasir</td>
            <td>:</td>
            <td>{{ $sale->cashier->name ?? 'Kasir' }}</td>
        </tr>
        @if($sale->customer)
        <tr>
            <td>Pelanggan</td>
            <td>:</td>
            <td>{{ $sale->customer->name }}</td>
        </tr>
        @endif
    </table>

    <hr>
    <div style="font-size: 0.85em; font-weight: 900; margin-bottom: 4px; display: flex; justify-content: space-between;">
        <span>QTY</span>
        <span>MENU / ITEM</span>
    </div>
    <hr>

    {{-- DAFTAR ITEM PESANAN --}}
    @foreach($sale->items as $item)
    <div class="item-row">
        <div class="item-main">
            <span class="item-qty">@qty($item->qty)x</span>
            <span class="item-title">{{ $item->product_name }}{{ $item->unit_label ? ' ('.$item->unit_label.')' : '' }}</span>
        </div>
        @if(!empty($item->note))
        <div class="item-note">
            &bull; {{ $item->note }}
        </div>
        @endif
    </div>
    @endforeach

    {{-- CATATAN KESELURUHAN PESANAN --}}
    @if(!empty($sale->note))
    <div style="margin-top: 6px; padding: 4px; border: 1px solid #000; font-size: 0.9em;">
        <strong>CATATAN UMUM:</strong><br>
        {{ $sale->note }}
    </div>
    @endif

    <div class="double-hr"></div>
    <div class="center" style="font-size: 0.8em;">
        Dicetak: {{ now()->format('d/m/Y H:i:s') }}
    </div>
    <div class="center" style="font-size: 0.85em; font-weight: 900; margin-top: 3px;">
        -- HARAP SEGERA DISIAPKAN --
    </div>

    {{-- TOMBOL CETAK MANUAL UNTUK PREVIEW --}}
    <div class="no-print">
        <button onclick="window.print()" style="padding: 7px 14px; font-weight: bold; cursor: pointer; border-radius: 4px; border: 1px solid #333; background: #fff;">
            Cetak Struk Kitchen
        </button>
    </div>

    {{-- AUTO-PRINT JIKA DIPANGGIL DENGAN ?autoprint=1 --}}
    @if(request()->query('autoprint') == 1)
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 250);
        });
    </script>
    @endif
</body>
</html>
