<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Uji Cetak Struk</title>
    <style>
        {{-- Sama persis dengan halaman struk sungguhan: lebar CETAK, margin sebagai padding
             badan, bukan margin @page. Kalau lembar ini tercetak rapi, struk juga rapi. --}}
        @page { size: {{ $lebarCetak }}mm auto; margin: 0; }
        body {
            width: {{ $lebarCetak }}mm;
            max-width: {{ $lebarCetak }}mm;
            margin: 0 auto;
            padding: 4px {{ $margin }}mm;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .center { text-align: center; }
        hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
        .batas { border: 1px solid #000; padding: 3px 0; }
        .penggaris { display: flex; width: 100%; }
        .penggaris div { flex: 1 1 0; border-left: 1px solid #000; height: 7mm; font-size: 7px; padding-left: 1px; }
        .penggaris div:last-child { border-right: 1px solid #000; }
        .blok { background: #000; height: 4mm; width: 100%; }
        .no-print { margin-top: 14px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="center" style="font-weight:bold;">UJI CETAK STRUK</div>
    <div class="center">Lebar cetak: {{ $lebarCetak }} mm</div>
    <div class="center">Margin: {{ $margin }} mm</div>
    <hr>

    <div class="batas center">GARIS TEPI</div>
    <div style="font-size:9px; margin-top:3px;">
        Kotak di atas harus utuh keempat sisinya. Kalau sisi kiri/kanannya terpotong,
        turunkan angka Lebar Cetak.
    </div>
    <hr>

    <div class="blok"></div>
    <div style="font-size:9px; margin-top:3px;">
        Batang hitam di atas harus penuh dari tepi kiri sampai tepi kanan area cetak,
        tanpa terpotong.
    </div>
    <hr>

    <div class="penggaris">
        @for($i = 1; $i <= 10; $i++)<div>{{ $i }}</div>@endfor
    </div>
    <div style="font-size:9px;">
        Penggaris 10 bagian sama besar. Ukur dengan penggaris sungguhan: seluruhnya harus
        selebar {{ $lebarCetak }} mm. Kalau ternyata lebih sempit, berarti printer masih
        mengecilkan halaman &mdash; turunkan Lebar Cetak sampai angkanya cocok.
    </div>
    <hr>

    <div class="center">1234567890 ABCDEFGHIJ</div>
    <div class="center">Rp 1.500.000</div>
    <hr>
    <div class="center" style="font-size:9px;">
        Tulisan di atas harus benar-benar di tengah.
    </div>

    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 16px;">Cetak Lembar Uji</button>
        <p style="font-size:12px; color:#555; max-width:520px; margin:10px auto;">
            Saat dialog cetak muncul, pastikan <strong>Skala = 100% / Default</strong>
            (jangan "Fit to page") dan matikan "Headers and footers". Kalau skalanya bukan
            100%, hasil cetaknya pasti mengecil berapa pun angka yang diisi di pengaturan.
        </p>
    </div>
</body>
</html>
