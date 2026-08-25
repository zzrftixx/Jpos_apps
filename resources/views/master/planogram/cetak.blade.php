<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Peta {{ $rack->name }}</title>
    <style>
        /* Dicetak melintang: rak lebih lebar daripada tinggi, dan kotaknya harus cukup besar
           untuk ditulisi tangan saat stok opname. */
        @page { size: A4 landscape; margin: 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; margin: 0 0 14px; font-size: 11px; }

        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        td, th { border: 1px solid #999; padding: 5px; vertical-align: top; height: 74px; }
        th.baris { width: 30px; height: auto; background: #f0f0f0; text-align: center; vertical-align: middle; }
        thead th { height: auto; background: #f0f0f0; text-align: center; font-size: 10px; }

        .nama { font-weight: bold; display: block; line-height: 1.25; }
        .stok { display: block; margin-top: 3px; color: #333; }
        .muka { display: block; color: #666; font-size: 10px; }

        /* Kolom kosong untuk ditulisi tangan saat menghitung fisik. Inilah alasan utama peta
           ini dicetak: menghitung sambil menulis di kertas, bukan bolak-balik ke layar. */
        .hitung { display: block; margin-top: 6px; border-top: 1px dotted #999; padding-top: 3px; color: #999; font-size: 10px; }

        .kosong { color: #bbb; text-align: center; padding-top: 26px; }

        .kaki { margin-top: 14px; font-size: 10px; color: #555; display: flex; justify-content: space-between; }

        .no-print { margin-bottom: 14px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <h1>Peta Rak &mdash; {{ $rack->name }}</h1>
    <p class="sub">
        {{ $rack->description ? $rack->description . ' &middot; ' : '' }}
        {{ $rack->rows }} baris x {{ $rack->cols }} kolom
        &middot; dicetak {{ now()->format('d/m/Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th class="baris"></th>
                @for($k = 0; $k < $rack->cols; $k++)
                    <th>Kolom {{ $k + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @for($r = 0; $r < $rack->rows; $r++)
                <tr>
                    <th class="baris">B{{ $r + 1 }}</th>
                    @for($k = 0; $k < $rack->cols; $k++)
                        @php
                            $slot = $peta[$r . '-' . $k] ?? null;
                            $produk = $slot?->product;
                        @endphp
                        <td>
                            @if($produk)
                                <span class="nama">{{ $produk->name }}</span>
                                <span class="stok">Sistem: {{ \App\Support\Angka::qty($produk->stock) }} {{ $produk->unit }}</span>
                                @if($slot->facings > 1)
                                    <span class="muka">{{ $slot->facings }} muka</span>
                                @endif
                                <span class="hitung">Fisik: ______</span>
                            @else
                                <span class="kosong">&mdash;</span>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="kaki">
        <span>Dihitung oleh: ________________________</span>
        <span>Tanggal: ______________</span>
        <span>Diperiksa: ________________________</span>
    </div>
</body>
</html>
