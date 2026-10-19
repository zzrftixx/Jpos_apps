<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pengaman API sinkronisasi tablet kasir (LAN Multi-Device).
 *
 * Mencegah akses liar dari perangkat asing di jaringan Wi-Fi/LAN toko
 * dengan memvalidasi Secret Sync Token melalui header X-JPOS-TABLET-TOKEN
 * atau Authorization Bearer token.
 */
class VerifyTabletSyncToken
{
    public const SETTING_KEY = 'tablet_sync_token';
    public const HEADER_NAME = 'X-JPOS-TABLET-TOKEN';

    /**
     * Dapatkan token sinkronisasi tablet yang aktif, atau buatkan token kriptografis baru jika belum ada.
     */
    public static function getOrGenerateToken(): string
    {
        $token = Setting::get(self::SETTING_KEY);
        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }

        // Generate token kriptografis 32 karakter secara deterministik
        $newToken = Str::random(32);
        Setting::set(self::SETTING_KEY, $newToken);

        return $newToken;
    }

    /**
     * Tangani request yang masuk ke endpoint tablet API.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = self::getOrGenerateToken();

        // Ambil token dari header khusus X-JPOS-TABLET-TOKEN atau Authorization: Bearer
        $providedToken = $request->header(self::HEADER_NAME) ?? $request->bearerToken();

        if (! is_string($providedToken) || trim($providedToken) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Akses API Ditolak: Header ' . self::HEADER_NAME . ' wajib disertakan untuk sinkronisasi tablet.',
            ], 401);
        }

        // Cegah timing attack dengan hash_equals
        if (! hash_equals($expectedToken, trim($providedToken))) {
            return response()->json([
                'success' => false,
                'message' => 'Akses API Ditolak: Token sinkronisasi tablet tidak valid.',
            ], 403);
        }

        return $next($request);
    }
}
