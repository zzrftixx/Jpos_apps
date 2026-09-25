<?php

namespace App\Console\Commands;

use App\Support\NetworkInfo;
use Illuminate\Console\Command;

class NetworkServeCommand extends Command
{
    /**
     * Nama dan signature perintah command.
     *
     * @var string
     */
    protected $signature = 'jpos:lan {--serve : Jalankan server langsung dengan bind 0.0.0.0} {--port=8000 : Port yang digunakan}';

    /**
     * Deskripsi perintah command.
     *
     * @var string
     */
    protected $description = 'Tampilkan informasi alamat IP jaringan LAN toko atau jalankan server multi-device JPOS';

    /**
     * Eksekusi perintah command.
     */
    public function handle(): int
    {
        $port = (int) $this->option('port');
        $ips = NetworkInfo::getLocalIps();

        $this->info('===========================================================');
        $this->info('  JPOS MULTI-DEVICE (CLIENT - SERVER LAN TOKO)');
        $this->info('===========================================================');
        $this->line('');
        $this->line('Alamat URL yang dapat dibuka dari Komputer Kasir lain / Tablet / HP:');

        foreach ($ips as $ip) {
            $isLan = str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip);
            $tag = $isLan ? ' <fg=green;options=bold>[DIREKOMENDASIKAN]</>' : '';
            $this->line("  -> <fg=cyan;options=bold>http://{$ip}:{$port}</>{$tag}");
        }

        $this->line('');
        $this->comment('Petunjuk Penggunaan Kasir Tambahan (Client):');
        $this->line('1. Hubungkan komputer/HP kasir ke Wi-Fi atau kabel LAN yang sama dengan komputer ini.');
        $this->line('2. Buka Google Chrome atau Microsoft Edge di komputer/HP tersebut.');
        $this->line("3. Ketik salah satu alamat di atas pada address bar browser (contoh: http://{$ips[0]}:{$port}).");
        $this->line('4. Tanpa perlu menginstal aplikasi apa pun (Zero-Install).');
        $this->line('');

        if ($this->option('serve')) {
            $this->warn("Memulai server JPOS pada 0.0.0.0:{$port} (Multi-Worker = 4)...");
            $this->line('Tekan Ctrl+C untuk menghentikan server.');
            putenv('PHP_CLI_SERVER_WORKERS=4');
            $this->call('serve', [
                '--host' => '0.0.0.0',
                '--port' => $port,
            ]);
        }

        return self::SUCCESS;
    }
}
