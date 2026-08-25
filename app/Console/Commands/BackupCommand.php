<?php

namespace App\Console\Commands;

use App\Support\SqliteBackup;
use Illuminate\Console\Command;

/**
 * Membuat snapshot database dari baris perintah.
 *
 * Dipakai UPDATE.bat sebelum menimpa berkas apa pun: kalau perintah ini gagal,
 * pembaruan dibatalkan dan tidak ada satu berkas pun yang diubah.
 */
class BackupCommand extends Command
{
    protected $signature = 'jpos:backup {--prefix=backup : Awalan nama berkas backup}';

    protected $description = 'Membuat salinan database SQLite yang konsisten';

    public function handle(SqliteBackup $backup): int
    {
        try {
            $path = $backup->create((string) $this->option('prefix'));
        } catch (\Throwable $e) {
            $this->error('Backup gagal: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup dibuat: ' . $path);
        $this->line('Ukuran: ' . number_format(filesize($path) / 1024, 1) . ' KB');

        return self::SUCCESS;
    }
}
