<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Alat terminal untuk developer / pemilik toko mengaktifkan (unlock) atau
 * mengunci (lock / promo PRO) modul Shift Kasir.
 *
 * Dijalankan langsung atau via skrip KELOLA-SHIFT-KASIR.bat.
 */
class KelolaShiftCommand extends Command
{
    protected $signature = 'jpos:kelola-shift
                            {aksi? : unlock untuk aktifkan, lock untuk kunci/promo}';

    protected $description = 'Kelola status aktivasi modul Shift Kasir (Unlock / Lock Mode PRO)';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  ============================================================');
        $this->line('    JPOS - PENGELOLA AKTIVASI MODUL SHIFT KASIR');
        $this->line('  ============================================================');
        $this->newLine();

        $statusSekarang = Setting::shiftKasirEnabled();
        $labelStatus = $statusSekarang ? 'AKTIF (UNLOCKED)' : 'TERKUNCI (MODE PRO MARKETING)';

        $this->line('  Status saat ini : ' . $labelStatus);
        $this->newLine();

        $aksi = strtolower((string) $this->argument('aksi'));

        if (! in_array($aksi, ['unlock', 'lock'], true)) {
            $this->line('  Silakan pilih tindakan:');
            $this->line('    [1] Aktifkan / Unlock Fitur Shift Kasir');
            $this->line('    [2] Kunci / Nonaktifkan Fitur Shift Kasir (Mode PRO Marketing)');
            $this->line('    [0] Batal / Keluar');
            $this->newLine();

            $pilihan = trim((string) $this->ask('  Pilihan Anda (1/2/0)', '0'));

            if ($pilihan === '1') {
                $aksi = 'unlock';
            } elseif ($pilihan === '2') {
                $aksi = 'lock';
            } else {
                $this->info('  Operasi dibatalkan. Tidak ada pengaturan yang diubah.');
                return self::SUCCESS;
            }
        }

        $setting = Setting::shiftKasir();

        if ($aksi === 'unlock') {
            $setting['enabled'] = true;
            Setting::set('shift_kasir', $setting);

            $this->newLine();
            $this->info('  [BERHASIL] Fitur Shift Kasir telah di-UNLOCK dan AKTIF!');
            $this->line('  - Kasir kini dapat membuka shift, memasukkan modal awal, dan cetak Z-Report.');
            $this->line('  - Menu Shift Kasir dan Pengaturannya terbuka penuh di aplikasi.');
        } else {
            $setting['enabled'] = false;
            Setting::set('shift_kasir', $setting);

            $this->newLine();
            $this->warn('  [BERHASIL] Fitur Shift Kasir telah DIKUNCI (Mode PRO Marketing).');
            $this->line('  - Kasir dapat langsung berjualan tanpa perlu buka shift kas laci.');
            $this->line('  - Menu di sidebar berlabel PRO dan mengarahkan klien untuk hubungi developer.');
        }

        $this->newLine();
        $this->line('  ============================================================');
        return self::SUCCESS;
    }
}
