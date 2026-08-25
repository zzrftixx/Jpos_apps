<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menormalkan angka berformat Indonesia ("1.500.000") menjadi angka yang dimengerti
 * validasi Laravel ("1500000").
 *
 * Kotak input angka di JPOS menampilkan pemisah ribuan sambil diketik supaya kasir bisa
 * membaca nominalnya. Nilai yang terkirim ke server karena itu berformat, dan aturan seperti
 * 'numeric' atau 'integer' akan MENOLAK "1.500.000".
 *
 * Pilihan desainnya sengaja begini, bukan memakai input tersembunyi berpasangan: kalau
 * JavaScript gagal dimuat di komputer kasir, kotak angka jadi kotak teks biasa dan apa yang
 * diketik kasir tetap tersimpan benar. Dengan input tersembunyi, nilai yang diketik akan
 * hilang tanpa jejak - jauh lebih berbahaya untuk aplikasi kasir.
 *
 * Hanya field di dalam FIELD_ANGKA yang disentuh. Daftar putih ini penting: kolom seperti
 * `barcode` juga berisi digit, dan `name` produk bisa mengandung titik ("Aqua 1.5L").
 */
class NormalizeNumericInput
{
    /**
     * Field yang boleh dinormalkan. Tanda * cocok dengan satu segmen indeks array.
     */
    private const FIELD_ANGKA = [
        // Master produk
        'cost_price',
        'sell_price',
        'wholesale_price',
        'wholesale_min_qty',
        'stock',
        'min_stock',
        'units.*.ratio_to_previous',
        'units.*.price',
        'units.*.cost_price',
        'units.*.modal_total',
        'units.*.biaya_lain',
        'units.*.wholesale_price',
        'units.*.wholesale_min_qty',

        // Transaksi
        'discount',
        'paid_amount',
        'additional_payment',
        'amount',
        'items.*.qty',
        'add_items.*.qty',
        'qty',

        // Pengaturan
        'percent',
        'label_width',
        'label_height',
        'custom_width',
        'margin',
        'font_size',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        $diubah = false;

        foreach (self::FIELD_ANGKA as $pola) {
            $this->terapkan($input, explode('.', $pola), $diubah);
        }

        if ($diubah) {
            $request->merge($input);
        }

        return $next($request);
    }

    /**
     * Turuni pola bersegmen (mis. units.*.price) lalu normalkan nilai di ujungnya.
     */
    private function terapkan(array &$data, array $segmen, bool &$diubah): void
    {
        $kunci = array_shift($segmen);

        if ($kunci === '*') {
            foreach ($data as $k => &$nilai) {
                if (is_array($nilai)) {
                    $this->terapkan($nilai, $segmen, $diubah);
                }
            }

            return;
        }

        if (! array_key_exists($kunci, $data)) {
            return;
        }

        if ($segmen !== []) {
            if (is_array($data[$kunci])) {
                $this->terapkan($data[$kunci], $segmen, $diubah);
            }

            return;
        }

        $normal = $this->normalkan($data[$kunci]);

        if ($normal !== null && $normal !== $data[$kunci]) {
            $data[$kunci] = $normal;
            $diubah = true;
        }
    }

    /**
     * Kembalikan angka dalam bentuk string biasa, atau null kalau nilai ini tidak boleh
     * disentuh (bukan string, kosong, atau tidak terbaca sebagai angka).
     */
    private function normalkan(mixed $nilai): ?string
    {
        // Checkout kasir mengirim JSON dengan angka sungguhan - tidak perlu diapa-apakan.
        if (! is_string($nilai)) {
            return null;
        }

        $teks = trim($nilai);

        if ($teks === '') {
            return null;
        }

        // Buang label mata uang & spasi, mis. "Rp 1.500.000,-"
        $bersih = preg_replace('/[^0-9.,\-]/', '', $teks);

        if ($bersih === '' || ! preg_match('/[0-9]/', $bersih)) {
            return null;
        }

        $negatif = str_starts_with($bersih, '-');
        $bersih = str_replace('-', '', $bersih);

        $adaKoma = str_contains($bersih, ',');
        $adaTitik = str_contains($bersih, '.');

        if ($adaKoma) {
            // Ada koma: koma = desimal, titik = ribuan. Tidak ambigu.
            $bulat = str_replace('.', '', substr($bersih, 0, strpos($bersih, ',')));
            $pecahan = preg_replace('/[^0-9]/', '', substr($bersih, strpos($bersih, ',') + 1));
        } elseif ($adaTitik) {
            // Hanya titik: bisa ribuan ("1.500") atau desimal gaya Inggris ("11.5") kalau
            // JavaScript mati dan kasir mengetik dengan kebiasaan lama.
            //
            // Aturannya: satu titik yang diikuti 1-2 digit sampai habis dianggap DESIMAL.
            // Selain itu (diikuti tepat 3 digit, atau titiknya lebih dari satu) dianggap
            // pemisah ribuan. Tanpa aturan ini, "11.5" pada persen pajak akan menjadi 115 -
            // salah sepuluh kali lipat.
            if (preg_match('/^(\d+)\.(\d{1,2})$/', $bersih, $cocok)) {
                $bulat = $cocok[1];
                $pecahan = $cocok[2];
            } else {
                $bulat = str_replace('.', '', $bersih);
                $pecahan = '';
            }
        } else {
            $bulat = $bersih;
            $pecahan = '';
        }

        $bulat = preg_replace('/[^0-9]/', '', $bulat);

        if ($bulat === '' && $pecahan === '') {
            return null;
        }

        $hasil = ($bulat === '' ? '0' : $bulat) . ($pecahan !== '' ? '.' . $pecahan : '');

        if (! is_numeric($hasil)) {
            return null;
        }

        return ($negatif ? '-' : '') . $hasil;
    }
}
