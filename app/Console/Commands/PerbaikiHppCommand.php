<?php

namespace App\Console\Commands;

use App\Support\Akuntansi;
use Illuminate\Console\Command;

class PerbaikiHppCommand extends Command
{
    protected $signature = 'jpos:perbaiki-hpp';

    protected $description = 'Memperbaiki dan menyeimbangkan HPP laporan yang abnormal akibat kesalahan input harga modal satuan bertingkat';

    public function handle(): int
    {
        $this->info('Memulai perbaikan HPP dan harga modal produk...');

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $before = Akuntansi::ringkasanLabaRugi($from, $to);
        $this->line(sprintf('Sebelum perbaikan: Omset Rp %s | HPP - Rp %s | Laba Kotor Rp %s',
            number_format($before->omset, 0, ',', '.'),
            number_format($before->hpp, 0, ',', '.'),
            number_format($before->laba_kotor, 0, ',', '.')
        ));

        $result = Akuntansi::perbaikiHppAbnormal();

        $after = Akuntansi::ringkasanLabaRugi($from, $to);
        $this->newLine();
        $this->info(sprintf('Berhasil memeriksa produk: %d produk disesuaikan, %d item transaksi disinkronkan.', $result['produk_count'], $result['items_count']));
        $this->newLine();
        $this->line(sprintf('Setelah perbaikan: Omset Rp %s | HPP - Rp %s | Laba Kotor Rp %s',
            number_format($after->omset, 0, ',', '.'),
            number_format($after->hpp, 0, ',', '.'),
            number_format($after->laba_kotor, 0, ',', '.')
        ));

        return self::SUCCESS;
    }
}
