<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengubah sebuah Laporan menjadi berkas PDF atau Excel.
 *
 * Dua keputusan yang menentukan hasilnya bisa dipakai atau tidak:
 *
 * PDF DIRENDER SEPENUHNYA OFFLINE. isRemoteEnabled sengaja dimatikan, dan fontnya memakai
 * DejaVu Sans yang sudah ikut di dalam dompdf. Komputer kasir tidak punya internet; kalau
 * pustaka ini sampai mencoba mengambil font atau gambar dari jaringan, permintaan itu akan
 * menggantung sampai timeout dan ekspornya gagal - bukan gagal cepat, tapi menggantung.
 *
 * EXCEL MENYIMPAN ANGKA SEBAGAI ANGKA, bukan teks. Menulis "Rp 1.500.000" ke dalam sel
 * membuatnya tidak bisa dijumlah, tidak bisa diurutkan, dan tidak bisa dipakai rumus - dan
 * satu-satunya alasan orang minta Excel dan bukan PDF justru itu. Rupiahnya diurus format
 * sel, bukan isi selnya.
 */
class EksporLaporan
{
    public function pdf(Laporan $laporan): StreamedResponse
    {
        $opsi = new Options();
        $opsi->set('isRemoteEnabled', false);
        $opsi->set('isHtml5ParserEnabled', true);
        $opsi->set('defaultFont', 'DejaVu Sans');
        // Direktori sementara milik aplikasi. Bawaannya sys_get_temp_dir(), yang pada Windows
        // portable bisa saja tidak bisa ditulisi tergantung akun yang menjalankannya.
        $opsi->set('tempDir', storage_path('app/tmp'));
        $opsi->set('fontDir', storage_path('app/tmp/fonts'));
        $opsi->set('fontCache', storage_path('app/tmp/fonts'));

        foreach ([storage_path('app/tmp'), storage_path('app/tmp/fonts')] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        // Rincian yang kepanjangan dipotong DAN diumumkan. Alasan angkanya ada di
        // Laporan::BATAS_BARIS_PDF, beserta hasil pengukurannya.
        $dibuang = $laporan->jumlahBarisDibuang(Laporan::BATAS_BARIS_PDF);

        if ($dibuang > 0) {
            $laporan->catatan(sprintf(
                'HANYA %s baris pertama yang dicetak di sini; %s baris lainnya TIDAK ikut. '
                . 'Mencetak seluruhnya ke PDF akan menahan aplikasi sampai kasir tidak bisa '
                . 'berjualan. Untuk daftar lengkapnya, unduh versi Excel - versi itu tidak dibatasi.',
                number_format(Laporan::BATAS_BARIS_PDF, 0, ',', '.'),
                number_format($dibuang, 0, ',', '.')
            ));
        }

        $dompdf = new Dompdf($opsi);
        $dompdf->setPaper('A4', $this->perluMelintang($laporan) ? 'landscape' : 'portrait');
        $dompdf->loadHtml(view('laporan.ekspor.pdf', [
            'laporan' => $laporan,
            'bagianTercetak' => $laporan->daftarBagian(Laporan::BATAS_BARIS_PDF),
            'dibuang' => $dibuang,
        ])->render());
        $dompdf->render();

        // Nomor halaman ditulis setelah render, karena jumlah halamannya baru diketahui di
        // sini. Laporan tanpa nomor halaman tidak bisa dipastikan lengkap saat dicetak.
        $kanvas = $dompdf->getCanvas();
        $kanvas->page_text(
            $kanvas->get_width() - 110,
            $kanvas->get_height() - 28,
            'Hal. {PAGE_NUM} dari {PAGE_COUNT}',
            null,
            8,
            [0.4, 0.4, 0.4]
        );

        $isi = $dompdf->output();

        return response()->streamDownload(
            fn () => print($isi),
            $laporan->namaUnduhan('pdf'),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function excel(Laporan $laporan): StreamedResponse
    {
        $buku = new Spreadsheet();
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle($this->judulLembar($laporan->judul));

        $lebarTerlebar = $this->kolomTerbanyak($laporan);
        $baris = 1;

        $baris = $this->tulisKop($lembar, $laporan, $lebarTerlebar, $baris);
        $baris = $this->tulisRingkasan($lembar, $laporan, $lebarTerlebar, $baris);

        foreach ($laporan->daftarBagian() as $bagian) {
            $baris = $this->tulisBagian($lembar, $bagian, $baris);
        }

        $this->tulisCatatan($lembar, $laporan, $lebarTerlebar, $baris);

        $buku->setActiveSheetIndex(0);

        $penulis = new Xlsx($buku);

        return response()->streamDownload(function () use ($penulis, $buku) {
            $penulis->save('php://output');
            // Dilepas eksplisit: PhpSpreadsheet menyimpan seluruh isi lembar di memori, dan
            // pada katalog besar itu bukan jumlah yang kecil.
            $buku->disconnectWorksheets();
        }, $laporan->namaUnduhan('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* ------------------------------------------------------------------ bagian Excel */

    private function tulisKop($lembar, Laporan $laporan, int $lebar, int $baris): int
    {
        $toko = $laporan->toko();
        $kolomAkhir = $this->huruf($lebar);

        $lembar->setCellValue('A' . $baris, $toko['nama']);
        $lembar->mergeCells("A{$baris}:{$kolomAkhir}{$baris}");
        $lembar->getStyle('A' . $baris)->getFont()->setBold(true)->setSize(14);
        $baris++;

        $kontak = array_filter([$toko['alamat'], $toko['telepon'], $toko['email']]);
        if ($kontak) {
            $lembar->setCellValue('A' . $baris, implode(' · ', $kontak));
            $lembar->mergeCells("A{$baris}:{$kolomAkhir}{$baris}");
            $lembar->getStyle('A' . $baris)->getFont()->setSize(9)->getColor()->setRGB('666666');
            $baris++;
        }

        $baris++;

        $lembar->setCellValue('A' . $baris, $laporan->judul);
        $lembar->mergeCells("A{$baris}:{$kolomAkhir}{$baris}");
        $lembar->getStyle('A' . $baris)->getFont()->setBold(true)->setSize(12);
        $baris++;

        if ($laporan->labelPeriode()) {
            $lembar->setCellValue('A' . $baris, $laporan->labelPeriode());
            $lembar->mergeCells("A{$baris}:{$kolomAkhir}{$baris}");
            $lembar->getStyle('A' . $baris)->getFont()->setSize(9)->getColor()->setRGB('666666');
            $baris++;
        }

        $jejak = 'Dicetak ' . now()->format('d/m/Y H:i');
        if ($laporan->namaPencetak()) {
            $jejak .= ' oleh ' . $laporan->namaPencetak();
        }
        $lembar->setCellValue('A' . $baris, $jejak);
        $lembar->mergeCells("A{$baris}:{$kolomAkhir}{$baris}");
        $lembar->getStyle('A' . $baris)->getFont()->setSize(9)->getColor()->setRGB('666666');

        return $baris + 2;
    }

    private function tulisRingkasan($lembar, Laporan $laporan, int $lebar, int $baris): int
    {
        $ringkasan = $laporan->daftarRingkasan();

        if (! $ringkasan) {
            return $baris;
        }

        foreach ($ringkasan as $label => $nilai) {
            $lembar->setCellValue('A' . $baris, $label);
            $lembar->setCellValue('B' . $baris, $nilai);
            $lembar->getStyle('A' . $baris)->getFont()->setBold(true);
            $baris++;
        }

        return $baris + 1;
    }

    private function tulisBagian($lembar, array $bagian, int $baris): int
    {
        $kolom = $bagian['kolom'];
        $jumlahKolom = count($kolom);
        $kolomAkhir = $this->huruf($jumlahKolom);

        if ($bagian['judul']) {
            $lembar->setCellValue('A' . $baris, $bagian['judul']);
            $lembar->getStyle('A' . $baris)->getFont()->setBold(true)->setSize(11);
            $baris++;
        }

        $barisJudul = $baris;

        foreach ($kolom as $i => $def) {
            $lembar->setCellValue($this->huruf($i + 1) . $baris, $def['label']);
        }

        $lembar->getStyle("A{$baris}:{$kolomAkhir}{$baris}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $lembar->getRowDimension($baris)->setRowHeight(20);
        $baris++;

        $barisPertamaData = $baris;

        foreach ($bagian['baris'] as $data) {
            foreach ($kolom as $i => $def) {
                $sel = $this->huruf($i + 1) . $baris;
                $nilai = $data[$def['key']] ?? null;
                $this->isiSel($lembar, $sel, $nilai, $def['format'] ?? 'teks');
            }
            $baris++;
        }

        if ($bagian['total']) {
            foreach ($kolom as $i => $def) {
                $sel = $this->huruf($i + 1) . $baris;
                $nilai = $bagian['total'][$def['key']] ?? null;
                $this->isiSel($lembar, $sel, $nilai, $def['format'] ?? 'teks');
            }

            $lembar->getStyle("A{$baris}:{$kolomAkhir}{$baris}")->applyFromArray([
                'font' => ['bold' => true],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
            $baris++;
        }

        if ($baris > $barisPertamaData) {
            $lembar->getStyle("A{$barisPertamaData}:{$kolomAkhir}" . ($baris - 1))
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
        }

        // Baris judul dibekukan supaya nama kolom tetap terlihat saat menggulir laporan
        // panjang. Hanya bagian pertama - satu lembar cuma bisa punya satu titik beku.
        if ($lembar->getFreezePane() === null) {
            $lembar->freezePane('A' . ($barisJudul + 1));
        }

        foreach ($kolom as $i => $def) {
            $huruf = $this->huruf($i + 1);
            $lembar->getColumnDimension($huruf)->setWidth($def['lebar'] ?? (($def['format'] ?? 'teks') === 'teks' ? 32 : 16));
        }

        return $baris + 1;
    }

    private function isiSel($lembar, string $sel, mixed $nilai, string $format): void
    {
        // Baris total menaruh label seperti "TOTAL" di kolom pertama, dan kolom itu bisa saja
        // bertipe angka. Dipaksa (float), labelnya berubah jadi 0 - dan baris total tampak
        // seperti data sungguhan bernilai nol.
        $angkaSungguhan = is_numeric($nilai);

        if (($format === 'rupiah' || $format === 'angka') && $angkaSungguhan) {
            $lembar->setCellValue($sel, (float) $nilai);
            $lembar->getStyle($sel)->getNumberFormat()->setFormatCode(
                $format === 'rupiah'
                    // Negatif ditampilkan merah dalam kurung - kaidah laporan keuangan yang
                    // lazim, dan jauh lebih sulit terlewat daripada tanda minus.
                    ? '_("Rp"* #,##0_);[Red]_("Rp"* \(#,##0\);_("Rp"* "-"_);_(@_)'
                    : NumberFormat::FORMAT_NUMBER_00
            );
            $lembar->getStyle($sel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            return;
        }

        $lembar->setCellValueExplicit(
            $sel,
            Laporan::format($nilai, $format),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
    }

    private function tulisCatatan($lembar, Laporan $laporan, int $lebar, int $baris): void
    {
        $catatan = $laporan->daftarCatatan();

        if (! $catatan) {
            return;
        }

        $kolomAkhir = $this->huruf($lebar);

        $lembar->setCellValue('A' . $baris, 'Catatan');
        $lembar->getStyle('A' . $baris)->getFont()->setBold(true)->setSize(9);
        $baris++;

        foreach ($catatan as $satu) {
            $lembar->setCellValue('A' . $baris, '· ' . $satu);
            $lembar->mergeCells("A{$baris}:{$kolomAkhir}{$baris}");
            $lembar->getStyle('A' . $baris)->getFont()->setSize(9)->getColor()->setRGB('666666');
            $lembar->getStyle('A' . $baris)->getAlignment()->setWrapText(true);
            $baris++;
        }
    }

    /* ---------------------------------------------------------------------- pembantu */

    private function huruf(int $nomor): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nomor);
    }

    private function kolomTerbanyak(Laporan $laporan): int
    {
        $terbanyak = 2;

        foreach ($laporan->daftarBagian() as $bagian) {
            $terbanyak = max($terbanyak, count($bagian['kolom']));
        }

        return $terbanyak;
    }

    /** Tabel lebar dicetak melintang; kalau dipaksa tegak, kolomnya saling menindih. */
    private function perluMelintang(Laporan $laporan): bool
    {
        return $this->kolomTerbanyak($laporan) >= 6;
    }

    /**
     * Nama lembar Excel dibatasi 31 karakter dan tidak boleh memuat : \ / ? * [ ].
     * Melanggarnya menghasilkan berkas yang ditolak Excel saat dibuka - bukan peringatan,
     * tapi benar-benar gagal dibuka.
     */
    private function judulLembar(string $judul): string
    {
        $bersih = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', '-', $judul);

        return mb_substr($bersih, 0, 31);
    }
}
