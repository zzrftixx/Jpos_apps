<?php

namespace App\Support;

/**
 * Utilitas informasi jaringan lokal (LAN) untuk arsitektur Multi-Device Client-Server.
 *
 * Mendeteksi alamat IP lokal komputer server toko, port aktif, dan tautan akses
 * yang dapat dibuka langsung oleh komputer kasir lain atau tablet Android/iPad
 * tanpa perlu menginstal aplikasi apa pun (Zero-Install Web LAN).
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
     * Dapatkan port aktif server JPOS.
     */
    public static function getActivePort(): int
    {
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] > 0) {
            return (int) $_SERVER['SERVER_PORT'];
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

        foreach ($ips as $index => $ip) {
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
     */
    public static function getFirewallCommand(): string
    {
        $port = self::getActivePort();
        return "netsh advfirewall firewall add rule name=\"JPOS Server Port {$port}\" dir=in action=allow protocol=TCP localport={$port}";
    }
}
