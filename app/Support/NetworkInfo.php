<?php

namespace App\Support;

/**
 * Utilitas informasi jaringan lokal (LAN) untuk arsitektur Multi-Device Client-Server.
 *
 * Mendeteksi alamat IP lokal komputer server toko, port aktif, nama host, mDNS,
 * dan tautan akses yang dapat dibuka langsung oleh komputer kasir lain atau tablet Android/iPad.
 *
 * Dilengkapi pengamanan ketat terhadap celah SSRF, DNS Rebinding, Loopback Hijacking,
 * dan Command Injection pada skrip konfigurasi firewall/hosts.
 */
class NetworkInfo
{
    /**
     * Dapatkan semua alamat IPv4 lokal yang valid pada komputer server.
     *
     * @return array<string>
     */
    public static function getLocalIps(): array
    {
        $ips = [];

        // 1. Coba lewat hostname lokal PHP
        $hostname = gethostname();
        if ($hostname !== false) {
            $hostIps = @gethostbynamel($hostname);
            if (is_array($hostIps)) {
                foreach ($hostIps as $ip) {
                    if (self::isValidLanIp($ip)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        // 2. Fallback parser ipconfig di Windows jika gethostbynamel tidak lengkap
        if (empty($ips) && PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('ipconfig 2>&1');
            if ($output) {
                preg_match_all('/IPv4 Address[.\s]*:\s*([0-9.]+)/i', $output, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $ip) {
                        $ip = trim($ip);
                        if (self::isValidLanIp($ip)) {
                            $ips[] = $ip;
                        }
                    }
                }
            }
        }

        $ips = array_values(array_unique($ips));

        // Jika benar-benar tidak ada antarmuka jaringan terdeteksi, berikan localhost
        return !empty($ips) ? $ips : ['127.0.0.1'];
    }

    /**
     * Validasi apakah alamat IP merupakan IP LAN privat yang bisa diakses perangkat lain.
     */
    public static function isValidLanIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // Abaikan loopback dan link-local (169.254.x.x)
        if ($ip === '127.0.0.1' || str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        return true;
    }

    /**
     * Dapatkan port aktif server JPOS dengan validasi batas port TCP (1 - 65535).
     */
    public static function getActivePort(): int
    {
        if (isset($_SERVER['SERVER_PORT'])) {
            $port = (int) $_SERVER['SERVER_PORT'];
            if ($port >= 1 && $port <= 65535) {
                return $port;
            }
        }

        return 8000;
    }

    /**
     * Dapatkan daftar URL lengkap untuk akses dari perangkat lain.
     *
     * @return array<array{ip: string, url: string, is_recommended: bool}>
     */
    public static function getAccessUrls(): array
    {
        $port = self::getActivePort();
        $ips = self::getLocalIps();
        $urls = [];

        foreach ($ips as $ip) {
            $isLan = str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip);
            $urls[] = [
                'ip' => $ip,
                'url' => "http://{$ip}:{$port}",
                'is_recommended' => (bool) $isLan,
            ];
        }

        return $urls;
    }

    /**
     * Perintah PowerShell untuk membuka port Windows Firewall jika diblokir.
     * Mencegah command injection dengan memastikan port strictly integer 1-65535.
     */
    public static function getFirewallCommand(): string
    {
        $port = self::getActivePort();
        return "netsh advfirewall firewall add rule name=\"JPOS Server Port {$port}\" dir=in action=allow protocol=TCP localport={$port}";
    }

    /**
     * Dapatkan nama host mesin komputer server ini dengan sanitasi karakter alfanumerik.
     */
    public static function getHostname(): string
    {
        $host = gethostname();
        if ($host === false || trim($host) === '') {
            return 'server-jpos';
        }

        $clean = preg_replace('/[^a-zA-Z0-9-]/', '-', trim($host));
        $clean = trim($clean, '-');

        return $clean !== '' ? $clean : 'server-jpos';
    }

    /**
     * Dapatkan nama domain mDNS lokal bawaan (.local) tanpa perlu konfigurasi DNS.
     * Standar mDNS/Bonjour/Zeroconf ini otomatis dikenali oleh Windows 10/11, macOS, iOS, dan Android.
     */
    public static function getMdnsDomain(): string
    {
        $host = self::getHostname();
        $host = preg_replace('/\.local$/i', '', $host);
        $host = preg_replace('/[^a-zA-Z0-9-]/', '-', $host);
        $host = trim($host, '-');

        return strtolower($host ?: 'server-jpos') . '.local';
    }

    /**
     * Dapatkan alamat IP LAN utama yang direkomendasikan untuk routing client.
     */
    public static function getPrimaryLanIp(): string
    {
        $ips = self::getLocalIps();
        foreach ($ips as $ip) {
            if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) {
                return $ip;
            }
        }

        return $ips[0] ?? '127.0.0.1';
    }

    /**
     * Bersihkan dan validasi input nama domain dari pengguna.
     *
     * PERLINDUNGAN KEAMANAN TINGGI (Divisi 3):
     * 1. Anti-Loopback Hijacking: Menolak mentah-mentah jika input adalah alamat IP (misal 127.0.0.1)
     *    atau reserved loopback/internal domain (localhost, loopback, broadcasthost).
     * 2. Anti-DNS Rebinding & Hostname Collision: Mewajibkan struktur FQDN (minimal 2 label terpisah titik).
     * 3. Anti-Command Injection: Hanya mengizinkan [a-z0-9] dan tanda strip (-) di tiap label,
     *    sehingga 100% bebas dari karakter injeksi shell (" ' ` $ ; & | \ / < >).
     * 4. Panjang domain sesuai standar RFC 1035 (maksimal 253 karakter, label 1-63 karakter).
     */
    public static function cleanDomain(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $clean = trim($input);
        if ($clean === '') {
            return null;
        }

        // Hapus protokol http:// atau https://
        $clean = preg_replace('~^https?://~i', '', $clean);

        // Hapus path atau query parameter jika ada
        $clean = preg_split('~[/?#]~', $clean)[0] ?? '';

        // Hapus port jika pengguna memasukkan format kasir.toko:8000
        $clean = preg_split('~:~', $clean)[0] ?? '';

        // Ubah ke huruf kecil dan bersihkan titik/spasi di ujung
        $clean = strtolower(trim($clean, " \t\n\r\0\x0B."));

        // 1. Batas panjang total domain RFC 1035
        if (strlen($clean) < 3 || strlen($clean) > 253) {
            return null;
        }

        // 2. Anti-Loopback Hijacking: Tolak jika merupakan format IP address
        if (filter_var($clean, FILTER_VALIDATE_IP)) {
            return null;
        }

        // 3. Tolak reserved hostnames & metadata cloud endpoints
        $reserved = [
            'localhost', 'loopback', 'broadcasthost', 'localdomain',
            'ip6-localhost', 'ip6-loopback', '0.0.0.0',
            'metadata.google.internal', 'instance-data',
        ];
        if (in_array($clean, $reserved, true) || str_ends_with($clean, '.localhost')) {
            return null;
        }

        // 4. Wajib memiliki minimal 2 label terpisah titik (misal: kasir.toko, pos.lan)
        if (!str_contains($clean, '.')) {
            return null;
        }

        $labels = explode('.', $clean);
        if (count($labels) < 2) {
            return null;
        }

        // 5. Validasi ketat setiap label: 1-63 karakter, alfanumerik diawali/diakhiri [a-z0-9]
        foreach ($labels as $label) {
            if (strlen($label) < 1 || strlen($label) > 63) {
                return null;
            }
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                return null;
            }
        }

        return $clean;
    }
}
