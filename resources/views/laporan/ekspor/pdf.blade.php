@php
    $toko = $laporan->toko();
    $ringkasan = $laporan->daftarRingkasan();
    $catatan = $laporan->daftarCatatan();
    // Dilewatkan dari EksporLaporan, bukan diambil sendiri: pemotongan baris rincian
    // ditentukan di sana bersama alasan dan hasil pengukurannya.
    $bagianTercetak = $bagianTercetak ?? $laporan->daftarBagian();
    $dibuang = $dibuang ?? 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $laporan->judul }}</title>
    <style>
        /* DejaVu Sans ikut di dalam dompdf, jadi tidak ada satu pun berkas yang perlu diambil
           dari jaringan. Komputer kasir tidak punya internet. */
        @page { margin: 18mm 14mm 20mm 14mm; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 0; }

        .kop { border-bottom: 2px solid #334155; padding-bottom: 8px; margin-bottom: 12px; }
        .kop .toko { font-size: 15px; font-weight: bold; }
        .kop .kontak { font-size: 8px; color: #555; margin-top: 2px; }

        .judul { font-size: 13px; font-weight: bold; margin-top: 10px; }
        .periode { font-size: 9px; color: #555; margin-top: 2px; }
        .jejak { font-size: 8px; color: #777; margin-top: 2px; }

        .ringkasan { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .ringkasan td { padding: 4px 8px; border: 1px solid #cbd5e1; }
        .ringkasan td.label { background: #f1f5f9; font-weight: bold; width: 38%; }
        .ringkasan td.nilai { text-align: right; }

        .judul-bagian { font-size: 10px; font-weight: bold; margin: 12px 0 5px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #334155; color: #fff; font-size: 8px; font-weight: bold;
            padding: 5px 6px; text-align: left; border: 1px solid #334155;
        }
        table.data td { padding: 4px 6px; border: 1px solid #cbd5e1; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data tr.total td { background: #f1f5f9; font-weight: bold; border-top: 2px solid #334155; }

        .kanan { text-align: right; }
        .merah { color: #b91c1c; }

        .catatan { margin-top: 16px; border-top: 1px solid #cbd5e1; padding-top: 8px; }
        .catatan .judul-catatan { font-size: 8px; font-weight: bold; margin-bottom: 3px; }
        .catatan p { font-size: 8px; color: #555; margin: 0 0 2px; line-height: 1.4; }

        .ttd { margin-top: 26px; width: 100%; }
        .ttd td { width: 33%; font-size: 8px; text-align: center; padding-top: 34px; }
        .ttd .garis { border-top: 1px solid #555; padding-top: 3px; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="toko">{{ $toko['nama'] }}</div>
        @php $kontak = array_filter([$toko['alamat'], $toko['telepon'], $toko['email']]); @endphp
        @if($kontak)
            <div class="kontak">{{ implode(' · ', $kontak) }}</div>
        @endif

        <div class="judul">{{ $laporan->judul }}</div>
        @if($laporan->labelPeriode())
            <div class="periode">{{ $laporan->labelPeriode() }}</div>
        @endif
        <div class="jejak">
            Dicetak {{ now()->format('d/m/Y H:i') }}@if($laporan->namaPencetak()) oleh {{ $laporan->namaPencetak() }}@endif
        </div>
    </div>

    @if($ringkasan)
        <table class="ringkasan">
            @foreach($ringkasan as $label => $nilai)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td class="nilai">{{ $nilai }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Peringatan pemotongan ditaruh DI ATAS tabel, bukan cuma di catatan kaki. Laporan
         yang diam-diam terpotong akan dikira lengkap, dan angkanya dipakai mengambil
         keputusan. --}}
    @if($dibuang > 0)
        <div style="border:1px solid #b91c1c;background:#fef2f2;color:#b91c1c;padding:6px 8px;margin-bottom:10px;font-size:8px;">
            <strong>Rincian ini SEBAGIAN.</strong>
            Hanya {{ number_format(\App\Support\Laporan::BATAS_BARIS_PDF, 0, ',', '.') }} baris pertama yang tercetak;
            {{ number_format($dibuang, 0, ',', '.') }} baris lainnya tidak ikut.
            Angka pada baris TOTAL tetap dihitung dari SELURUH data.
            Untuk daftar lengkapnya, unduh versi Excel.
        </div>
    @endif

    @foreach($bagianTercetak as $bagian)
        @if($bagian['judul'])
            <div class="judul-bagian">{{ $bagian['judul'] }}</div>
        @endif

        {{-- Tabel panjang DIPECAH jadi beberapa tabel kecil.

             dompdf menyimpan seluruh peta sel satu tabel di memori sekaligus, dan biayanya
             tumbuh jauh lebih cepat daripada jumlah barisnya. Terukur pada katalog 2.000
             produk berkolom delapan: satu tabel utuh menghabiskan lebih dari 512 MB dan
             prosesnya mati - laporan stok toko besar tidak akan pernah bisa dicetak.

             Dipecah per 200 baris, peta selnya dilepas tiap kali satu tabel selesai. Hasil
             cetaknya tidak berbeda: judul kolom diulang di tiap potongan, yang justru
             membantu saat laporannya berpindah halaman. --}}
        @php
            $potonganIsi = $bagian['baris'] ? array_chunk($bagian["baris"], 200) : [[]];
            $indeksTerakhir = count($potonganIsi) - 1;
        @endphp
        @foreach($potonganIsi as $nomorPotongan => $isiPotongan)
        @php $potonganTerakhir = $nomorPotongan === $indeksTerakhir; @endphp
        <table class="data">
            <thead>
                <tr>
                    @foreach($bagian['kolom'] as $kolom)
                        @php $rata = ($kolom['format'] ?? 'teks') === 'teks' ? '' : ' class="kanan"'; @endphp
                        <th{!! $rata !!}>{{ $kolom['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($isiPotongan as $data)
                    <tr>
                        @foreach($bagian['kolom'] as $kolom)
                            @php
                                $jenis = $kolom['format'] ?? 'teks';
                                $nilai = $data[$kolom['key']] ?? null;
                                $kelas = $jenis === 'teks' ? '' : 'kanan';
                                if ($jenis === 'rupiah' && (float) $nilai < 0) {
                                    $kelas .= ' merah';
                                }
                            @endphp
                            <td class="{{ trim($kelas) }}">{{ \App\Support\Laporan::format($nilai, $jenis) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($bagian['kolom']) }}" style="text-align:center;color:#999;padding:14px;">
                            Tidak ada data pada periode ini.
                        </td>
                    </tr>
                @endforelse

                @if($bagian['total'] && $potonganTerakhir)
                    <tr class="total">
                        @foreach($bagian['kolom'] as $kolom)
                            @php
                                $jenis = $kolom['format'] ?? 'teks';
                                $nilai = $bagian['total'][$kolom['key']] ?? null;
                            @endphp
                            <td class="{{ $jenis === 'teks' ? '' : 'kanan' }}">
                                {{ $nilai === null ? '' : \App\Support\Laporan::format($nilai, $jenis) }}
                            </td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
        @endforeach
    @endforeach

    {{-- Angka laporan keuangan tidak bisa dipertanggungjawabkan tanpa tahu cara
         menghitungnya. Yang membaca ini bisa saja bank atau kantor pajak. --}}
    @if($catatan)
        <div class="catatan">
            <div class="judul-catatan">Catatan &amp; Dasar Perhitungan</div>
            @foreach($catatan as $satu)
                <p>· {{ $satu }}</p>
            @endforeach
        </div>
    @endif

    <table class="ttd">
        <tr>
            <td><div class="garis">Disusun oleh</div></td>
            <td></td>
            <td><div class="garis">Disetujui oleh</div></td>
        </tr>
    </table>
</body>
</html>
