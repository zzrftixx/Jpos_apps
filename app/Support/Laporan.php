<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Bentuk baku satu laporan, terlepas dari cara menampilkannya.
 *
 * Sepuluh laporan JPos punya isi berbeda-beda tapi kerangkanya sama: kop toko, judul,
 * periode, ringkasan, tabel rinci, catatan asumsi. Ditulis satu kali di sini supaya versi
 * PDF dan versi Excel tidak bisa berbeda isinya - kalau tiap laporan menyusun kop dan
 * catatannya sendiri, cepat atau lambat salah satunya akan tertinggal saat ada perubahan.
 *
 * Sebuah laporan terdiri dari satu atau lebih BAGIAN. Kebanyakan laporan cuma punya satu
 * (satu tabel), tapi Neraca butuh dua yang berdampingan - aset di satu sisi, kewajiban dan
 * modal di sisi lain.
 */
class Laporan
{
    /** @var array<int,array{judul:string,kolom:array,baris:array,total:?array}> */
    private array $bagian = [];

    /** @var array<string,string> */
    private array $ringkasan = [];

    /** @var array<int,string> */
    private array $catatan = [];

    private ?string $periode = null;

    private ?string $pencetak = null;

    public function __construct(
        public readonly string $judul,
        public readonly string $namaBerkas,
    ) {}

    public static function buat(string $judul, string $namaBerkas): self
    {
        return new self($judul, $namaBerkas);
    }

    public function periode(?string $dari, ?string $sampai = null): self
    {
        if (! $dari) {
            return $this;
        }

        $this->periode = $sampai && $sampai !== $dari
            ? Carbon::parse($dari)->format('d/m/Y') . ' s/d ' . Carbon::parse($sampai)->format('d/m/Y')
            : 'Per ' . Carbon::parse($dari)->format('d/m/Y');

        return $this;
    }

    public function pencetak(?string $nama): self
    {
        $this->pencetak = $nama;

        return $this;
    }

    /**
     * Ringkasan di atas tabel: angka-angka penting yang paling sering dicari, supaya tidak
     * perlu menjumlah sendiri dari tabel di bawahnya.
     *
     * @param  array<string,string>  $pasangan  label => nilai yang sudah diformat
     */
    public function ringkasan(array $pasangan): self
    {
        $this->ringkasan = array_merge($this->ringkasan, $pasangan);

        return $this;
    }

    /**
     * @param  array<int,array{label:string,key:string,align?:string,format?:string}>  $kolom
     *         format: teks | rupiah | angka | tanggal
     * @param  iterable  $baris  larik asosiatif, kuncinya sesuai `key` di $kolom
     * @param  array<string,mixed>|null  $total  baris total di kaki tabel
     */
    public function bagian(string $judul, array $kolom, iterable $baris, ?array $total = null): self
    {
        $this->bagian[] = [
            'judul' => $judul,
            'kolom' => $kolom,
            'baris' => is_array($baris) ? $baris : iterator_to_array($baris),
            'total' => $total,
        ];

        return $this;
    }

    /**
     * Catatan asumsi di kaki laporan.
     *
     * Ini bukan hiasan. Angka laporan keuangan tidak bisa dipertanggungjawabkan tanpa tahu
     * cara menghitungnya - "HPP memakai harga modal saat transaksi" mengubah arti seluruh
     * kolom laba. Yang membaca laporan ini bisa saja bank atau kantor pajak, dan mereka akan
     * menanyakannya.
     */
    public function catatan(string ...$baris): self
    {
        foreach ($baris as $satu) {
            $this->catatan[] = $satu;
        }

        return $this;
    }

    /**
     * Batas jumlah baris rincian yang dicetak ke PDF.
     *
     * BUKAN angka yang dikarang. Diukur pada tabel berkolom delapan memakai runtime yang
     * dibundel, batas memori 512 MB:
     *
     *      500 baris  ->  11 detik, 150 MB
     *      800 baris  ->  17 detik, 208 MB
     *    2.000 baris  ->  46 detik, 380 MB
     *    2.000 baris tanpa pemecahan tabel  ->  MATI, memori habis
     *
     * dompdf membangun pohon bingkai seluruh dokumen sebelum menggambar, jadi biayanya
     * tumbuh terus dan tidak bisa dihindari dengan mengalirkan data. Pada 2.000 baris,
     * satu ekspor akan MENAHAN SELURUH APLIKASI selama hampir satu menit - server bawaan
     * hanya melayani satu permintaan pada satu waktu, jadi kasir tidak bisa berjualan
     * selama itu.
     *
     * Excel TIDAK dibatasi (2.000 baris cuma 9 detik, 62 MB) dan memang alat yang tepat
     * untuk daftar sepanjang itu. PDF untuk diserahkan dan dicetak; 2.000 baris berarti
     * lebih dari lima puluh halaman yang tidak akan dibaca siapa pun.
     */
    public const BATAS_BARIS_PDF = 500;

    public function daftarBagian(?int $batasBaris = null): array
    {
        if ($batasBaris === null) {
            return $this->bagian;
        }

        return array_map(function (array $bagian) use ($batasBaris) {
            $bagian['baris'] = array_slice($bagian['baris'], 0, $batasBaris);

            return $bagian;
        }, $this->bagian);
    }

    /**
     * Berapa baris yang tidak ikut tercetak kalau dibatasi.
     *
     * Dipakai untuk MENGUMUMKAN pemotongannya, bukan menyembunyikannya. Laporan yang
     * diam-diam terpotong jauh lebih berbahaya daripada laporan yang jelas-jelas bilang
     * "ini sebagian" - yang pertama akan dikira lengkap, dan angkanya dipakai mengambil
     * keputusan.
     */
    public function jumlahBarisDibuang(int $batasBaris): int
    {
        $dibuang = 0;

        foreach ($this->bagian as $bagian) {
            $dibuang += max(count($bagian['baris']) - $batasBaris, 0);
        }

        return $dibuang;
    }

    public function daftarRingkasan(): array
    {
        return $this->ringkasan;
    }

    public function daftarCatatan(): array
    {
        return $this->catatan;
    }

    public function labelPeriode(): ?string
    {
        return $this->periode;
    }

    public function namaPencetak(): ?string
    {
        return $this->pencetak;
    }

    public function toko(): array
    {
        $profil = Setting::get('store_profile', []);

        return [
            'nama' => $profil['name'] ?? 'JPOS',
            'alamat' => $profil['address'] ?? null,
            'telepon' => $profil['phone'] ?? null,
            'email' => $profil['email'] ?? null,
        ];
    }

    /** Nama berkas unduhan, mis. "laba-rugi-2026-08-12". */
    public function namaUnduhan(string $ekstensi): string
    {
        return $this->namaBerkas . '-' . now()->format('Y-m-d') . '.' . $ekstensi;
    }

    /**
     * Memformat satu sel sesuai jenis kolomnya.
     *
     * Dipakai versi PDF (yang menerima teks jadi) maupun versi Excel untuk kolom teks.
     * Kolom angka di Excel sengaja TIDAK lewat sini - di sana nilainya harus tetap angka
     * supaya bisa dijumlah, dan formatnya diserahkan ke format sel.
     */
    public static function format(mixed $nilai, string $jenis): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        // Baris total menaruh label seperti "TOTAL" di kolom pertama, yang jenisnya bisa saja
        // tanggal atau angka. Dipaksakan ke pemformatnya, Carbon::parse('TOTAL') melempar dan
        // seluruh laporan gagal dirender - bukan salah satu selnya saja. Teks yang bukan angka
        // dan bukan tanggal dikembalikan apa adanya.
        if ($jenis !== 'teks' && is_string($nilai) && ! is_numeric($nilai)) {
            return $nilai;
        }

        return match ($jenis) {
            'rupiah' => ($nilai < 0 ? '-' : '') . 'Rp ' . number_format(abs((float) $nilai), 0, ',', '.'),
            'angka' => Angka::qty($nilai),
            'tanggal' => Carbon::parse($nilai)->format('d/m/Y'),
            default => (string) $nilai,
        };
    }
}
