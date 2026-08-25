<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menambahkan header keamanan dasar ke setiap respons HTML.
 *
 * JPOS berjalan di localhost, jadi permukaan serangnya kecil, tapi header ini murah dan
 * menutup beberapa kelas gangguan:
 *   - X-Frame-Options    : mencegah halaman kasir disematkan di iframe situs lain (clickjacking)
 *   - X-Content-Type-Options : mencegah browser menebak-nebak tipe konten
 *   - Referrer-Policy    : tidak membocorkan URL internal saat menekan tautan keluar
 *
 * Halaman cetak (struk, label barcode) sengaja TIDAK diberi X-Frame-Options DENY karena
 * beberapa alur cetak memuatnya lewat iframe/jendela; header lain tetap aman untuknya.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Struk & label barcode kadang dibuka di jendela/iframe cetak; jangan halangi itu.
        if (! $request->is('kasir/receipt/*') && ! $request->is('barcode/print')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
