<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengompresi respons HTML/JSON dinamis dengan gzip agar transfer via LAN lebih ringan
 * meskipun server built-in PHP (php artisan serve / jpos:lan) tidak punya mod_deflate.
 */
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if ($response->getStatusCode() !== 200 || $response->headers->has('Content-Encoding')) {
            return $response;
        }

        if (ini_get('zlib.output_compression') || ! function_exists('gzencode')) {
            return $response;
        }

        $acceptEncoding = (string) $request->headers->get('Accept-Encoding', '');
        if (stripos($acceptEncoding, 'gzip') === false) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        $isCompressible = $contentType === ''
            || str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/plain')
            || str_contains($contentType, 'text/css')
            || str_contains($contentType, 'javascript')
            || str_contains($contentType, 'image/svg+xml');

        if (! $isCompressible) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || strlen($content) < 1024) {
            return $response;
        }

        $compressed = gzencode($content, 5);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        $vary = (string) $response->headers->get('Vary', '');
        if (stripos($vary, 'Accept-Encoding') === false) {
            $response->headers->set('Vary', $vary !== '' ? $vary . ', Accept-Encoding' : 'Accept-Encoding');
        }

        return $response;
    }
}
