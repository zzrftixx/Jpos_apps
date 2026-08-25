<?php

namespace App\Http\Controllers;

use App\Console\Commands\PulihkanLoginCommand;
use App\Http\Controllers\Concerns\OptimizesUploadedImages;
use App\Models\Setting;
use App\Support\SqliteBackup;
use App\Support\Struk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    use OptimizesUploadedImages;

    public function profilToko()
    {
        $profile = Setting::get('store_profile', []);
        return view('pengaturan.profil-toko', compact('profile'));
    }

    public function updateProfilToko(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'footer_note' => ['nullable', 'string'],
            'logo' => $this->imageRules(),
        ]);

        $profile = Setting::get('store_profile', []);

        if ($request->hasFile('logo')) {
            if (!empty($profile['logo'])) {
                Storage::disk('public')->delete($profile['logo']);
            }
            $data['logo'] = $this->saveOptimizedImage($request->file('logo'), 'logo');
        } else {
            $data['logo'] = $profile['logo'] ?? null;
        }

        Setting::set('store_profile', $data);

        return back()->with('success', 'Profil toko berhasil disimpan.');
    }

    public function printerStruk()
    {
        $settings = Setting::get('printer_struk', []);
        // Kompatibilitas data lama: sebelum ada profil, paper_size cuma '58'/'80' tanpa key 'profile'.
        $settings += [
            'profile' => in_array((string) ($settings['paper_size'] ?? '80'), ['58', '80']) ? 'pos' . ($settings['paper_size'] ?? '80') : 'custom',
            'paper_size' => 80,
            'margin' => 0,
            'font_size' => 12,
            'auto_print' => false,
            'printer_name' => '',
        ];

        // Lebar cetak ditampilkan supaya pemilik toko bisa melihat - dan menyetel - angka
        // yang sebenarnya menentukan hasil cetak. Tanpa ini, satu-satunya cara menemukan
        // angka yang benar adalah mencoba-coba lebar kertas sampai kebetulan pas.
        $settings['print_width'] = Struk::lebarCetak($settings);

        // Peringatan kalau template "Nota (tabel)" yang dipilih ternyata tidak akan terpakai
        // pada lebar cetak ini. Tanpa ini, pemilik toko memilih template di satu menu lalu
        // mendapat tata letak lain di kertas, tanpa satu pun petunjuk kenapa.
        $templateStruk = Setting::get('template_struk', []);
        $lebarIsi = max(1.0, $settings['print_width'] - (2 * (float) ($settings['margin'] ?? 0)));

        $notaTerlaluSempit = ($templateStruk['layout'] ?? 'simple') === 'tabel'
            && ! Struk::muatNota($lebarIsi, Struk::FONT_NOTA_MIN, 9);

        $lebarNotaMinimal = Struk::lebarMinimalNota(9);

        return view('pengaturan.printer-struk', compact('settings', 'notaTerlaluSempit', 'lebarNotaMinimal'));
    }

    /**
     * Lembar uji cetak struk.
     *
     * Tanpa ini, satu-satunya cara mengetahui apakah Lebar Cetak sudah benar adalah
     * mencetak struk sungguhan berkali-kali dan menebak dari hasilnya. Lembar ini
     * menunjukkan langsung apa yang perlu dilihat: garis tepi yang harus utuh, batang
     * hitam yang harus penuh, dan penggaris yang bisa diukur dengan penggaris sungguhan.
     */
    public function ujiCetakStruk()
    {
        $printerStruk = Setting::get('printer_struk', []);

        return response()
            ->view('pengaturan.uji-cetak-struk', [
                'lebarCetak' => Struk::lebarCetak($printerStruk),
                'margin' => (float) ($printerStruk['margin'] ?? 0),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function updatePrinterStruk(Request $request)
    {
        $data = $request->validate([
            'profile' => ['required', 'in:pos58,pos80,custom'],
            'custom_width' => ['required_if:profile,custom', 'nullable', 'numeric', 'min:20', 'max:300'],
            // Lebar cetak boleh disetel sendiri: sebagian printer termal menyimpang dari
            // angka lazimnya, dan tanpa kolom ini satu-satunya cara menemukan angka yang
            // benar adalah mencoba-coba lebar kertas sampai kebetulan pas.
            'print_width' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'margin' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'font_size' => ['required', 'integer', 'min:8', 'max:20'],
            'printer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data['paper_size'] = match ($data['profile']) {
            'pos58' => 58,
            'pos80' => 80,
            default => (float) $data['custom_width'],
        };

        // Kalau lebar cetak dikosongkan, dipakai angka bawaan profilnya - 48mm untuk rol
        // 58mm dan 72mm untuk rol 80mm, sesuai lebar kepala cetak yang sebenarnya.
        $lebarCetak = (float) ($data['print_width'] ?? 0);
        $data['print_width'] = $lebarCetak > 0
            ? $lebarCetak
            : (Struk::LEBAR_CETAK[$data['profile']] ?? $data['paper_size']);

        // Margin lebih lebar dari kertas akan membuat isi struk menyempit sampai tidak
        // terbaca; ditahan di sini supaya tidak perlu ditemukan lewat kertas terbuang.
        $data['margin'] = min((float) ($data['margin'] ?? 0), max(0, ($data['print_width'] - 20) / 2));
        $data['auto_print'] = $request->boolean('auto_print');
        unset($data['custom_width']);

        Setting::set('printer_struk', $data);

        return back()->with('success', 'Pengaturan printer struk disimpan.');
    }

    public function printerBarcode()
    {
        $settings = Setting::get('printer_barcode', []);
        $settings += ['label_width' => 40, 'label_height' => 25, 'printer_name' => '', 'mode' => 'label'];

        return view('pengaturan.printer-barcode', compact('settings'));
    }

    public function updatePrinterBarcode(Request $request)
    {
        $data = $request->validate([
            // Ukuran lembar hanya berlaku di mode printer label; di mode kertas rol label
            // dicetak menyambung mengikuti Lebar Cetak printer struk.
            'label_width' => ['required_if:mode,label', 'nullable', 'numeric', 'min:10', 'max:200'],
            'label_height' => ['required_if:mode,label', 'nullable', 'numeric', 'min:10', 'max:200'],
            'mode' => ['required', 'in:label,roll'],
            'printer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data['label_width'] = (float) ($data['label_width'] ?? 40);
        $data['label_height'] = (float) ($data['label_height'] ?? 25);

        Setting::set('printer_barcode', $data);

        return back()->with('success', 'Pengaturan printer barcode disimpan.');
    }

    public function templateBarcode()
    {
        $settings = Setting::get('template_barcode', [
            'show_name' => true, 'show_price' => true, 'show_barcode_text' => true, 'barcode_type' => 'CODE128',
        ]);
        $sample = \App\Models\Product::first();
        return view('pengaturan.template-barcode', compact('settings', 'sample'));
    }

    public function updateTemplateBarcode(Request $request)
    {
        $data = $request->validate([
            'barcode_type' => ['required', 'in:CODE128,EAN13'],
        ]);
        $data['show_name'] = $request->boolean('show_name');
        $data['show_price'] = $request->boolean('show_price');
        $data['show_barcode_text'] = $request->boolean('show_barcode_text');

        Setting::set('template_barcode', $data);

        return back()->with('success', 'Template barcode disimpan.');
    }

    public function templateStruk()
    {
        $settings = Setting::get('template_struk', []);
        $storeProfile = Setting::get('store_profile', []);
        $printerStruk = Setting::get('printer_struk', ['paper_size' => 80]);

        // Pratinjau HARUS memakai lebar cetak, bukan lebar kertas. Sebelumnya pratinjau
        // digambar selebar kertas sementara hasil cetaknya mengikuti lebar kepala cetak
        // yang lebih sempit - jadi pratinjaunya rata tengah dan meyakinkan, tapi yang
        // keluar dari printer tidak. Pratinjau yang berbohong lebih buruk daripada tidak
        // ada pratinjau: ia membuat orang mencari penyebabnya di tempat yang salah.
        $printerStruk['print_width'] = Struk::lebarCetak($printerStruk);

        $sample = (object) [
            'invoice_no' => 'INV260101001',
            'created_at' => now(),
            'items' => collect([
                (object) ['product_name' => 'Contoh Produk A', 'qty' => 2, 'price' => 15000, 'subtotal' => 30000],
                (object) ['product_name' => 'Contoh Produk B', 'qty' => 1, 'price' => 8000, 'subtotal' => 8000],
            ]),
            'subtotal' => 38000,
            'discount' => 0,
            'tax_amount' => 4180,
            'total' => 42180,
            'paid_amount' => 50000,
            'change_amount' => 7820,
            'payment_method' => 'cash',
            'customer' => (object) ['name' => 'Pelanggan Umum'],
            'cashier' => (object) ['name' => 'Admin'],
        ];

        return view('pengaturan.template-struk', compact('settings', 'storeProfile', 'printerStruk', 'sample'));
    }

    public function updateTemplateStruk(Request $request)
    {
        $data = $request->validate([
            'layout' => ['required', 'in:simple,normal,tabel,invoice'],
            'header_note' => ['nullable', 'string'],
            'footer_note' => ['nullable', 'string'],
        ]);
        $data['show_logo'] = $request->boolean('show_logo');
        $data['show_address'] = $request->boolean('show_address');
        $data['show_phone'] = $request->boolean('show_phone');
        $data['show_cashier'] = $request->boolean('show_cashier');
        $data['show_customer'] = $request->boolean('show_customer');

        Setting::set('template_struk', $data);

        return back()->with('success', 'Template struk disimpan.');
    }

    public function pajak()
    {
        $settings = Setting::get('tax', ['enabled' => false, 'name' => 'PPN', 'percent' => 11, 'include_in_price' => false]);
        return view('pengaturan.pajak', compact('settings'));
    }

    public function updatePajak(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $data['include_in_price'] = $request->boolean('include_in_price');

        Setting::set('tax', $data);

        return back()->with('success', 'Pengaturan pajak disimpan.');
    }

    public function backupRestore(SqliteBackup $backup)
    {
        // Pengurutan dan pelabelannya ada di SqliteBackup::daftar() beserta alasannya:
        // singkatnya, mengurutkan berdasarkan NAMA berkas membuat snapshot lama naik ke atas.
        $backups = $backup->daftar();

        $totalKb = array_sum(array_column($backups, 'ukuran_kb'));

        return view('pengaturan.backup-restore', [
            'backups' => $backups,
            'totalKb' => $totalKb,
            'batasSimpan' => SqliteBackup::KEEP,
        ]);
    }

    /**
     * Tutup peringatan "login dipulihkan".
     *
     * Peringatannya sengaja bertahan sampai ditutup manual: kalau ia hilang sendiri setelah
     * beberapa saat, pemulihan yang dilakukan orang lain bisa lewat tanpa pernah dilihat
     * pemiliknya - dan seluruh gunanya sebagai jejak ikut hilang.
     */
    public function tutupCatatanPemulihan(Request $request)
    {
        if (! $request->user()?->can_access('user')) {
            abort(403);
        }

        Setting::where('key', PulihkanLoginCommand::KUNCI_CATATAN)->delete();
        $request->session()->forget('pemulihan_login');

        return back()->with('success', 'Peringatan pemulihan login ditutup. Riwayat lengkapnya tetap tersimpan di storage/logs/pemulihan-login.log.');
    }

    /**
     * Hapus satu berkas backup dari riwayat.
     *
     * Seluruh penjagaannya ada di SqliteBackup::hapus() - termasuk larangan menghapus
     * backup terakhir yang tersisa.
     */
    public function deleteBackup(Request $request, SqliteBackup $backup)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        if ($masalah = $backup->hapus($data['nama'])) {
            return back()->with('error', 'Backup tidak dihapus. ' . $masalah);
        }

        return back()->with('success', 'Backup ' . basename($data['nama']) . ' dihapus dari riwayat.');
    }

    public function createBackup(Request $request, SqliteBackup $backup)
    {
        try {
            $path = $backup->create();
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup gagal dibuat: ' . $e->getMessage());
        }

        return back()->with('success', 'Backup database berhasil dibuat: ' . basename($path));
    }

    public function downloadBackup(string $filename)
    {
        $path = 'backups/' . basename($filename);

        if (!str_ends_with(strtolower($path), '.sqlite') || !Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path);
    }

    public function restoreBackup(Request $request, SqliteBackup $backup)
    {
        $request->validate([
            'backup_file' => ['required', 'file'],
        ]);

        $uploaded = $request->file('backup_file')->getRealPath();

        // Divalidasi lebih dulu: database aktif tidak boleh tersentuh sedikit pun
        // sampai file penggantinya terbukti database JPOS yang sehat.
        if ($problem = $backup->validateArchive($uploaded)) {
            return back()->with('error', 'Restore dibatalkan. ' . $problem);
        }

        try {
            $safety = $backup->create('pre-restore');
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore dibatalkan karena backup pengaman gagal dibuat: ' . $e->getMessage());
        }

        try {
            $backup->replaceWith($uploaded);
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore gagal: ' . $e->getMessage() . ' Data lama Anda masih utuh (salinan: ' . basename($safety) . ').');
        }

        // Backup bisa berasal dari versi aplikasi yang lebih lama.
        Artisan::call('migrate', ['--force' => true]);

        // Sesi login lama tidak ada di database yang baru, jadi user dikeluarkan
        // secara tertib alih-alih dibiarkan menemui error di halaman berikutnya.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Database berhasil di-restore. Silakan login kembali. Salinan data sebelum restore disimpan sebagai ' . basename($safety) . '.');
    }

    public function database(SqliteBackup $backup)
    {
        $dbPath = $backup->databasePath();
        $size = file_exists($dbPath) ? round(filesize($dbPath) / 1024, 1) : 0;

        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tableCounts = [];
        foreach ($tables as $t) {
            try {
                $tableCounts[$t->name] = DB::table($t->name)->count();
            } catch (\Throwable $e) {
                $tableCounts[$t->name] = '-';
            }
        }

        return view('pengaturan.database', compact('dbPath', 'size', 'tableCounts'));
    }

    public function runMigrate(SqliteBackup $backup)
    {
        // Migrasi mengubah struktur database secara permanen, jadi selalu ada
        // titik balik sebelum dijalankan.
        try {
            $backup->create('pre-migrate');
        } catch (\Throwable $e) {
            return back()->with('error', 'Migrasi dibatalkan karena backup pengaman gagal dibuat: ' . $e->getMessage());
        }

        Artisan::call('migrate', ['--force' => true]);

        return back()->with('success', 'Migrasi database berhasil dijalankan.');
    }

    public function wipeData(Request $request, SqliteBackup $backup)
    {
        abort_unless($request->user()->role?->slug === 'admin', 403, 'Hanya admin yang bisa melakukan aksi ini.');

        $data = $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if (trim($data['confirmation']) !== 'HAPUS SEMUA DATA') {
            return back()->with('error', 'Teks konfirmasi tidak sesuai. Tidak ada data yang dihapus.');
        }

        // Backup otomatis dulu sebelum menghapus. Kalau ini gagal, penghapusan
        // dibatalkan - lebih baik batal daripada menghapus tanpa jaring pengaman.
        try {
            $safety = $backup->create('pre-wipe');
        } catch (\Throwable $e) {
            return back()->with('error', 'Penghapusan dibatalkan karena backup pengaman gagal dibuat: ' . $e->getMessage());
        }

        DB::transaction(function () {
            DB::statement('PRAGMA foreign_keys = OFF');

            // Urutan menghormati foreign key: tabel anak dihapus dulu sebelum tabel induknya.
            // User & Role sengaja tidak disentuh supaya masih bisa login setelah ini.
            DB::table('sale_return_items')->delete();
            DB::table('sale_returns')->delete();
            DB::table('sale_items')->delete();
            DB::table('sales')->delete();
            DB::table('stock_movements')->delete();
            DB::table('product_units')->delete();
            DB::table('products')->delete();
            DB::table('categories')->delete();
            DB::table('suppliers')->delete();
            DB::table('customers')->delete();
            DB::table('units')->delete();
            DB::table('cash_transactions')->delete();

            DB::statement('PRAGMA foreign_keys = ON');
        });

        return back()->with('success', 'Semua data transaksi & master data berhasil dihapus. Backup otomatis dibuat sebelum penghapusan: ' . basename($safety) . '.');
    }

    public function tampilanKasir()
    {
        $settings = Setting::get('kasir_display', ['default_view' => 'gambar']);
        return view('pengaturan.tampilan-kasir', compact('settings'));
    }

    public function updateTampilanKasir(Request $request)
    {
        $data = $request->validate([
            'default_view' => ['required', 'in:gambar,list,both'],
        ]);

        Setting::set('kasir_display', $data);

        return back()->with('success', 'Pengaturan tampilan kasir disimpan.');
    }

    public function modeProduk()
    {
        $settings = Setting::get('produk_mode', ['mode' => 'sederhana']);
        return view('pengaturan.mode-produk', compact('settings'));
    }

    public function updateModeProduk(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:sederhana,lengkap'],
        ]);

        Setting::set('produk_mode', $data);

        return back()->with('success', 'Pengaturan mode produk disimpan.');
    }

    public function tentang()
    {
        $app = [
            'nama' => 'JPOS',
            'versi' => config('jpos.version'),
            'produk_oleh' => 'JaylaTech ID',
            'kontak' => [
                'instagram' => 'jaylatech',
                'tiktok' => 'jaylatech',
                'whatsapp' => '085156757402',
            ],
        ];

        return view('pengaturan.tentang', compact('app'));
    }

}
