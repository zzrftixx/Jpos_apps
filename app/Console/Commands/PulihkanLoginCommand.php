<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\SqliteBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Jalan pulang ke aplikasi untuk pemilik toko yang lupa password.
 *
 * KENAPA TIDAK ADA TOMBOL "LUPA PASSWORD" DI HALAMAN LOGIN.
 *
 * Tombol seperti itu berdiri di depan pintu: siapa pun yang membuka aplikasi bisa
 * menekannya, termasuk kasir yang ingin melihat laporan laba, atau siapa saja yang
 * sempat duduk di depan komputer toko. Tanpa email dan tanpa internet, tidak ada cara
 * memverifikasi bahwa yang menekan memang pemiliknya. Menambahkannya sama saja dengan
 * menghapus password itu sendiri.
 *
 * Pemulihan karena itu dipindahkan ke tempat yang mensyaratkan sesuatu yang tidak dimiliki
 * peramban: akses ke berkas di komputer toko. Server hanya mendengarkan 127.0.0.1, jadi
 * lewat jaringan alat ini tidak terjangkau sama sekali.
 *
 * TIGA PENJAGAAN, karena akses fisik saja tidak cukup - kasir juga duduk di komputer itu:
 *
 *   1. Konfirmasi diketik. Klik ganda tidak sengaja tidak akan mengubah apa pun.
 *   2. Snapshot database dibuat lebih dulu, jadi tindakan ini pun punya titik balik.
 *   3. JEJAK YANG TERLIHAT. Setiap pemulihan dicatat dan dipasang sebagai peringatan di
 *      setiap halaman sampai pemilik menutupnya sendiri. Alat ini tidak bisa dipakai
 *      diam-diam - dan justru itu yang membuatnya aman untuk selalu ada.
 */
class PulihkanLoginCommand extends Command
{
    protected $signature = 'jpos:pulihkan-login
                            {--user= : Username yang ingin dipulihkan}
                            {--yakin : Lewati konfirmasi ketik (untuk pengujian otomatis)}';

    protected $description = 'Mengembalikan password admin ke bawaan supaya pemilik toko bisa masuk lagi';

    /** Kata yang harus diketik supaya tindakan ini tidak pernah terjadi karena klik tak sengaja. */
    private const KATA_KONFIRMASI = 'PULIHKAN';

    public const PASSWORD_BAWAAN = 'password';

    /** Kunci Setting tempat pemulihan terakhir dicatat, dibaca layout untuk memasang peringatan. */
    public const KUNCI_CATATAN = 'pemulihan_login_terakhir';

    public function handle(SqliteBackup $backup): int
    {
        $this->newLine();
        $this->line('  PEMULIHAN LOGIN JPOS');
        $this->line('  ' . str_repeat('=', 60));
        $this->newLine();

        $pengguna = $this->pilihPengguna();

        if ($pengguna === null) {
            return self::FAILURE;
        }

        $this->line('  Akun yang akan dipulihkan : ' . $pengguna->username . ' (' . $pengguna->name . ')');
        $this->line('  Password akan dikembalikan ke : ' . self::PASSWORD_BAWAAN);
        $this->newLine();
        $this->warn('  Setelah masuk, SEGERA ganti passwordnya di Manajemen > User.');
        $this->warn('  Selama masih memakai password bawaan, siapa pun yang tahu password itu bisa membuka data toko Anda.');
        $this->newLine();

        if (! $this->dikonfirmasi()) {
            $this->line('  Dibatalkan. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        // Mengubah password adalah perubahan data juga. Diperlakukan sama dengan tindakan
        // berisiko lainnya: ada titik balik sebelum dijalankan.
        try {
            $snapshot = $backup->create('pre-pulihkan-login');
            $this->line('  Salinan pengaman dibuat: ' . basename($snapshot));
        } catch (\Throwable $e) {
            $this->error('  Salinan pengaman gagal dibuat: ' . $e->getMessage());
            $this->error('  Pemulihan dibatalkan supaya tidak ada perubahan tanpa titik balik.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($pengguna) {
            $pengguna->password = self::PASSWORD_BAWAAN; // cast 'hashed' yang meng-hash-nya
            $pengguna->is_active = true;
            $pengguna->save();

            Setting::set(self::KUNCI_CATATAN, [
                'username' => $pengguna->username,
                'waktu' => now()->toDateTimeString(),
            ]);
        });

        $this->catatDiBerkasLog($pengguna->username);

        $this->newLine();
        $this->info('  BERHASIL. Sekarang bisa masuk dengan:');
        $this->line('     Username : ' . $pengguna->username);
        $this->line('     Password : ' . self::PASSWORD_BAWAAN);
        $this->newLine();
        $this->line('  Pemulihan ini akan tampil sebagai peringatan di dalam aplikasi sampai Anda menutupnya.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Tentukan akun mana yang dipulihkan.
     *
     * Kalau tidak ada satu pun akun admin - misalnya admin terakhir terhapus tidak sengaja -
     * akunnya dibuatkan ulang. Tanpa ini, toko terkunci selamanya dari datanya sendiri dan
     * satu-satunya jalan tersisa adalah membongkar berkas database dengan alat luar.
     */
    private function pilihPengguna(): ?User
    {
        $diminta = (string) $this->option('user');

        if ($diminta !== '') {
            $pengguna = User::where('username', $diminta)->first();

            if ($pengguna === null) {
                $this->error('  Tidak ada akun dengan username: ' . $diminta);
                $this->line('  Akun yang ada: ' . User::pluck('username')->implode(', '));

                return null;
            }

            return $pengguna;
        }

        $roleAdmin = Role::where('slug', 'admin')->first();

        $pengguna = $roleAdmin
            ? User::where('role_id', $roleAdmin->id)->orderBy('id')->first()
            : null;

        if ($pengguna !== null) {
            return $pengguna;
        }

        $this->warn('  Tidak ada akun admin di database ini. Akun admin dibuatkan ulang.');

        if ($roleAdmin === null) {
            $roleAdmin = Role::create([
                'name' => 'Administrator',
                'slug' => 'admin',
                'permissions' => array_keys(Role::allMenuKeys()),
                'is_system' => true,
            ]);
        }

        return new User([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@jpos.local',
            'role_id' => $roleAdmin->id,
            'is_active' => true,
        ]);
    }

    /**
     * Riwayat permanen, DI LUAR jangkauan aplikasi.
     *
     * Peringatan di layar bisa ditutup, dan yang bisa menutupnya adalah siapa pun yang
     * berhasil masuk - termasuk orang yang baru saja memakai alat ini tanpa hak. Kalau itu
     * satu-satunya jejak, jejaknya bisa dihapus oleh pelakunya sendiri.
     *
     * Berkas ini hanya ditambahi, tidak pernah ditulis ulang, dan tidak ada satu pun
     * halaman di aplikasi yang bisa menyentuhnya. Pemilik toko selalu bisa melihat
     * seluruh riwayat pemulihan, kapan pun ia curiga.
     */
    private function catatDiBerkasLog(string $username): void
    {
        try {
            $path = storage_path('logs/pemulihan-login.log');

            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0755, true);
            }

            @file_put_contents(
                $path,
                sprintf("[%s] password akun '%s' dikembalikan ke bawaan lewat alat pemulihan%s",
                    now()->toDateTimeString(), $username, PHP_EOL),
                FILE_APPEND
            );
        } catch (\Throwable) {
            // Log gagal ditulis bukan alasan menggagalkan pemulihan - pemilik toko yang
            // terkunci di luar datanya adalah masalah yang jauh lebih besar.
        }
    }

    private function dikonfirmasi(): bool
    {
        if ($this->option('yakin')) {
            return true;
        }

        $jawab = (string) $this->ask('  Ketik ' . self::KATA_KONFIRMASI . ' untuk melanjutkan, atau tekan Enter untuk membatalkan');

        return strtoupper(trim($jawab)) === self::KATA_KONFIRMASI;
    }
}
