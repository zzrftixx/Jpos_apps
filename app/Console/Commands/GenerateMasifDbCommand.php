<?php

namespace App\Console\Commands;

use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\NeracaSnapshot;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Rack;
use App\Models\RackSlot;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Support\Akuntansi;
use App\Support\Angka;
use App\Support\SqliteBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateMasifDbCommand extends Command
{
    protected $signature = 'jpos:generate-masif-db
                            {--skala=masif : kecil | sedang | masif}
                            {--tujuan= : Tulis hasilnya ke berkas ini, bukan ke database/toko_masif_simulasi.sqlite}
                            {--saya-paham-risikonya : Lewati penjaga lingkungan produksi}';

    /**
     * Seberapa besar toko simulasinya.
     *
     * Angka masif sengaja menyamai patokan performa di BLUEPRINT §9: 2.000 produk adalah
     * ukuran yang dipakai mengukur waktu buka halaman Kasir dan batas baris ekspor PDF.
     * Memakai ukuran yang sama membuat hasil pengamatan di sini bisa langsung dibandingkan
     * dengan angka acuan itu.
     *
     * `kecil` ada supaya test otomatis bisa menjalankan seluruh alur pembuatan dalam hitungan
     * detik - tanpa itu, penjaga generator ini tidak akan pernah dijalankan siapa pun.
     */
    private const SKALA = [
        'kecil'  => ['produk' => 30,   'hari' => 30,  'trx_per_hari' => 4],
        'sedang' => ['produk' => 300,  'hari' => 120, 'trx_per_hari' => 8],
        'masif'  => ['produk' => 2000, 'hari' => 365, 'trx_per_hari' => 16],
    ];

    protected $description = 'Menghasilkan database simulasi toko masif (JOS MART SUPERMARKET) yang konsisten dan seimbang 100%';

    public function handle(SqliteBackup $backupHelper): int
    {
        // PENJAGA: alat pengembangan, BUKAN untuk komputer toko.
        //
        // Perintah ini menulis salinan simulasi ke storage/app/private/backups - folder yang
        // sama dengan tempat backup sungguhan. Di komputer toko, berkas itu akan muncul di
        // menu Pengaturan > Backup & Restore sebagai pilihan yang bisa dipulihkan, dan
        // memulihkannya berarti MENGGANTI SELURUH DATA PENJUALAN TOKO dengan data karangan.
        //
        // Berkas ini juga tidak ikut dikemas ke paket (lihat build-exe.ps1), jadi penjaga ini
        // adalah lapisan kedua - untuk instalasi yang menjalankan kode dari salinan repo.
        if (app()->environment('production') && ! $this->option('saya-paham-risikonya')) {
            $this->error('Perintah ini alat pengembangan dan tidak boleh dijalankan di komputer toko.');
            $this->line('Ia menulis salinan simulasi ke folder backup, yang bisa tidak sengaja dipulihkan');
            $this->line('menggantikan seluruh data penjualan toko.');

            return self::FAILURE;
        }

        $this->info('Mulai membangun database simulasi Toko Masif JPOS (JOS MART SUPERMARKET)...');

        $tempPath = storage_path('app/private/temp_masif.sqlite');

        if (File::exists($tempPath)) {
            File::delete($tempPath);
        }

        File::ensureDirectoryExists(dirname($tempPath));
        touch($tempPath);

        // Pindah koneksi sementara ke temp db
        config(['database.connections.sqlite_temp' => [
            'driver' => 'sqlite',
            'database' => $tempPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('sqlite_temp');
        DB::setDefaultConnection('sqlite_temp');

        $this->line('Jalankan migrasi pada database baru...');
        Artisan::call('migrate:fresh', [
            '--database' => 'sqlite_temp',
            '--force' => true,
        ]);

        $this->generateData();
        $this->bangunLapisanMasif();
        $this->rapikanStokMinus();

        // Verifikasi Keseimbangan Neraca
        $posisi = Akuntansi::posisiPada(now()->toDateString());
        $this->info('--- PEMERIKSAAN KESEIMBANGAN NERACA TOKO SIMULASI ---');
        $this->line('Total Aset        : Rp ' . number_format($posisi->total_aset, 0, ',', '.'));
        $this->line('Total Kewajiban   : Rp ' . number_format($posisi->total_kewajiban, 0, ',', '.'));
        $this->line('Total Modal       : Rp ' . number_format($posisi->total_modal, 0, ',', '.'));
        $this->line('Selisih Neraca    : Rp ' . number_format($posisi->selisih, 0, ',', '.'));

        if (abs($posisi->selisih) >= 0.01) {
            // BUKAN TAMBALAN, DAN INI PENTING DIPAHAMI.
            //
            // Stok awal toko simulasi dibuat langsung ke tabel produk - tanpa nota pembelian,
            // persis seperti toko sungguhan yang sudah berjalan sebelum mulai membukukan.
            // Barang itu nyata ada di rak, jadi nilainya MEMANG bagian dari modal pemilik;
            // yang belum ada cuma catatannya.
            //
            // Mengisi modal awal sebesar selisih itu justru pencatatan yang BENAR, dan itu
            // pula yang disarankan aplikasi kepada pemilik toko sungguhan lewat
            // Akuntansi::saranModalAwal(). Terbukti di data client: selisihnya persis sama
            // dengan persediaan sekarang + HPP seluruh riwayat.
            $pembukuan = Setting::get('pembukuan');
            Setting::set('pembukuan', array_merge($pembukuan, [
                'modal_awal' => Akuntansi::saranModalAwal(now()->toDateString()),
            ]));

            $posisi = Akuntansi::posisiPada(now()->toDateString());
            $this->line('Modal awal disetel ke nilai stok awal yang tidak bertorehan nota.');
            $this->line('Selisih setelah disetel: Rp ' . number_format($posisi->selisih, 0, ',', '.'));
        }

        if (abs($posisi->selisih) >= 0.01) {
            $this->error('GAGAL: neraca simulasi tidak seimbang. Database tidak disimpan.');
            DB::disconnect('sqlite_temp');
            DB::setDefaultConnection('sqlite');
            File::delete($tempPath);

            return self::FAILURE;
        }

        $this->bekukanNeracaHariIni();

        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        DB::disconnect('sqlite_temp');
        DB::setDefaultConnection('sqlite');

        // Salin ke dua lokasi: database/toko_masif_simulasi.sqlite dan storage/app/private/backups/
        // --tujuan dipakai test otomatis supaya pemeriksaannya tidak menimpa berkas simulasi
        // yang sedang dipakai orang untuk mengamati aplikasi.
        $dbDest = $this->option('tujuan') ?: database_path('toko_masif_simulasi.sqlite');
        File::ensureDirectoryExists(dirname($dbDest));
        File::copy($tempPath, $dbDest);

        if ($this->option('tujuan')) {
            File::delete($tempPath);
            $this->info('Database simulasi dibuat di: ' . $dbDest);

            return self::SUCCESS;
        }

        // SENGAJA BUKAN ke folder backup sungguhan.
        //
        // storage/app/private/backups dibaca menu Pengaturan > Backup & Restore. Menaruh
        // salinan simulasi di sana membuatnya tampil berdampingan dengan backup asli, dan
        // sekali klik Restore berarti SELURUH DATA PENJUALAN TOKO diganti data karangan.
        $simulasiDir = storage_path('app/simulasi');
        File::ensureDirectoryExists($simulasiDir);
        $backupDest = $simulasiDir . DIRECTORY_SEPARATOR . 'toko_masif_simulasi-' . now()->format('Y-m-d_His') . '.sqlite';
        File::copy($tempPath, $backupDest);

        File::delete($tempPath);

        $this->info('BERHASIL!');
        $this->info('Database simulasi dibuat di dua lokasi:');
        $this->line('  1. ' . $dbDest);
        $this->line('  2. ' . $backupDest);
        $this->newLine();
        $this->line('Cara memakainya:');
        $this->line('  DB_CONNECTION=sqlite DB_DATABASE=' . $dbDest . ' php artisan serve');
        $this->newLine();
        $this->comment('Salinan di storage/app/simulasi SENGAJA tidak ditaruh di folder backup,');
        $this->comment('supaya tidak bisa tidak sengaja dipulihkan menimpa data toko sungguhan.');

        return self::SUCCESS;
    }

    /**
     * Membekukan neraca HARI INI - satu-satunya tanggal yang angkanya benar-benar sahih.
     *
     * Dipanggil setelah modal awal disetel, supaya snapshot yang tersimpan seimbang tepat nol
     * dan bisa dipakai sebagai pembanding saat menguji fitur Tutup Buku.
     */
    /**
     * Menambahkan katalog dan riwayat transaksi berskala besar di atas data buatan tangan.
     *
     * KENAPA DITUMPUK, BUKAN MENGGANTI. Data buatan tangan di generateData() berisi nama
     * produk, pemasok, dan pelanggan yang masuk akal - itulah yang membuat layar Kasir dan
     * laporan terlihat seperti toko sungguhan saat dilihat orang. Yang kurang cuma
     * JUMLAHNYA. Jadi yang ditambahkan di sini adalah massanya, bukan penggantinya.
     *
     * SEMUANYA LEWAT BULK INSERT. Membuat 2.000 produk dan ribuan transaksi lewat Eloquent
     * satu per satu berarti puluhan ribu query - pembuatan databasenya sendiri akan makan
     * waktu lebih lama daripada seluruh pengamatan yang mau dilakukan di atasnya. Model event
     * dan cast sengaja dilewati; yang dibutuhkan di sini barisnya, bukan perilakunya.
     *
     * STOK TIDAK DIPOTONG oleh transaksi lapisan ini, dan itu disengaja. Stok awalnya dibuat
     * besar, dan yang sedang diamati adalah perilaku aplikasi pada data banyak - bukan
     * kebenaran mutasi stok, yang sudah dijaga test tersendiri. Konsekuensinya ikut
     * diperhitungkan: HPP transaksi ini tetap terekam lewat cost_price_snapshot, jadi laba
     * rugi tetap masuk akal, dan modal awal disetel di akhir supaya neracanya seimbang.
     */
    /**
     * Membetulkan stok yang terlanjur minus di data buatan tangan.
     *
     * Lapisan buatan tangan menjual dari daftar produk yang pendek selama berbulan-bulan
     * dengan kuantitas acak, jadi beberapa produk habis lalu menembus nol. Stok minus bukan
     * keadaan yang bisa terjadi di toko sungguhan, dan akibatnya nyata: nilai persediaan jadi
     * NEGATIF, yang membuat pos Persediaan di Neraca lebih kecil daripada seharusnya.
     *
     * Yang diperbaiki di sini stoknya, bukan riwayat penjualannya - penjualan itu yang membuat
     * laporan terlihat hidup. Penyesuaiannya dicatat sebagai StockMovement bertipe `adjustment`
     * supaya jejaknya jujur: seseorang yang menelusuri mutasi stok akan melihat penyesuaian
     * itu, bukan angka yang muncul entah dari mana.
     *
     * Nilainya diserap modal awal yang disetel sesudah ini - persis seperti stok awal toko
     * sungguhan yang tidak punya nota pembelian.
     */
    private function rapikanStokMinus(): void
    {
        $minus = Product::where('stock', '<', 0)->get();

        if ($minus->isEmpty()) {
            return;
        }

        foreach ($minus as $produk) {
            $sebelum = (float) $produk->stock;
            $produk->stock = rand(15, 80);
            $produk->save();

            StockMovement::create([
                'product_id' => $produk->id,
                'type' => 'adjustment',
                'qty' => Angka::bulat($produk->stock - $sebelum),
                'stock_after' => $produk->stock,
                'note' => 'Penyesuaian stok awal simulasi (sebelumnya ' . Angka::qty($sebelum) . ')',
                'user_id' => User::where('username', 'admin')->value('id'),
            ]);
        }

        $this->line('  ' . $minus->count() . ' produk berstok minus disesuaikan.');
    }

    private function bangunLapisanMasif(): void
    {
        $skala = self::SKALA[$this->option('skala')] ?? self::SKALA['masif'];

        $this->line(sprintf(
            'Membangun lapisan masif: %s produk, %s hari, ~%s transaksi/hari...',
            number_format($skala['produk']), $skala['hari'], $skala['trx_per_hari']
        ));

        $kategoriId = Category::pluck('id')->all();
        $supplierId = Supplier::pluck('id')->all();
        $pelangganId = Customer::pluck('id')->all();
        $kasirId = User::pluck('id')->all();
        $satuan = ['Pcs', 'Botol', 'Bungkus', 'Sachet', 'Kaleng'];
        $waktu = now()->toDateTimeString();

        // ---- produk -----------------------------------------------------------------
        $nomorAwal = (int) Product::max('id') + 1;
        $barisProduk = [];

        for ($i = 0; $i < $skala['produk']; $i++) {
            $modal = rand(2, 60) * 500;

            $barisProduk[] = [
                'name' => 'Produk Massal ' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'sku' => 'SIM' . str_pad((string) ($nomorAwal + $i), 7, '0', STR_PAD_LEFT),
                'barcode' => '899' . str_pad((string) ($nomorAwal + $i), 10, '0', STR_PAD_LEFT),
                'category_id' => $kategoriId ? $kategoriId[array_rand($kategoriId)] : null,
                'supplier_id' => $supplierId ? $supplierId[array_rand($supplierId)] : null,
                'unit' => $satuan[array_rand($satuan)],
                'cost_price' => $modal,
                'sell_price' => Angka::bulat($modal * (1 + rand(12, 40) / 100)),
                'stock' => rand(20, 400),
                'min_stock' => 10,
                'is_taxable' => 0,
                'is_active' => 1,
                'type' => 'barang',
                'multi_unit_enabled' => 0,
                'hpp_calc_enabled' => 0,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];
        }

        foreach (array_chunk($barisProduk, 400) as $potongan) {
            DB::table('products')->insert($potongan);
        }

        $this->line('  ' . number_format(count($barisProduk)) . ' produk ditambahkan.');

        // ---- transaksi --------------------------------------------------------------
        $produk = DB::table('products')->where('type', 'barang')
            ->select('id', 'name', 'cost_price', 'sell_price')->get()->all();
        $jumlahProduk = count($produk);

        $idSale = (int) Sale::max('id') + 1;
        $urut = (int) Sale::count() + 1;

        $sales = [];
        $items = [];

        for ($h = $skala['hari']; $h >= 0; $h--) {
            $tanggal = now()->subDays($h);
            $jumlahTrx = max(1, (int) round($skala['trx_per_hari'] * (rand(60, 140) / 100)));

            for ($t = 0; $t < $jumlahTrx; $t++) {
                $waktuTrx = $tanggal->copy()->setTime(rand(8, 20), rand(0, 59), rand(0, 59));
                $subtotal = 0.0;
                $barisItem = [];

                foreach (range(1, rand(1, 5)) as $ignored) {
                    $p = $produk[rand(0, $jumlahProduk - 1)];
                    $qty = rand(1, 4);
                    $nilai = Angka::bulat($qty * $p->sell_price);
                    $subtotal += $nilai;

                    $barisItem[] = [
                        'sale_id' => $idSale,
                        'product_id' => $p->id,
                        'product_name' => $p->name,
                        'price' => $p->sell_price,
                        'qty' => $qty,
                        'returned_qty' => 0,
                        'unit_label' => null,
                        'unit_conversion' => 1,
                        'cost_price_snapshot' => $p->cost_price,
                        'subtotal' => $nilai,
                        'created_at' => $waktuTrx->toDateTimeString(),
                        'updated_at' => $waktuTrx->toDateTimeString(),
                    ];
                }

                $subtotal = Angka::bulat($subtotal);
                $bayar = Angka::bulat($subtotal + rand(0, 4) * 5000);

                $sales[] = [
                    'id' => $idSale,
                    'invoice_no' => 'SIM' . $waktuTrx->format('ymd') . str_pad((string) $urut++, 5, '0', STR_PAD_LEFT),
                    'customer_id' => $pelangganId ? $pelangganId[array_rand($pelangganId)] : null,
                    'user_id' => $kasirId ? $kasirId[array_rand($kasirId)] : null,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'total' => $subtotal,
                    'paid_amount' => $bayar,
                    'change_amount' => Angka::bulat($bayar - $subtotal),
                    'payment_method' => rand(0, 3) === 0 ? 'qris' : 'tunai',
                    'status' => 'completed',
                    'order_status' => 'completed',
                    'created_at' => $waktuTrx->toDateTimeString(),
                    'updated_at' => $waktuTrx->toDateTimeString(),
                ];

                foreach ($barisItem as $satu) {
                    $items[] = $satu;
                }

                $idSale++;
            }
        }

        foreach (array_chunk($sales, 300) as $potongan) {
            DB::table('sales')->insert($potongan);
        }

        foreach (array_chunk($items, 400) as $potongan) {
            DB::table('sale_items')->insert($potongan);
        }

        $this->line('  ' . number_format(count($sales)) . ' transaksi, '
            . number_format(count($items)) . ' baris item ditambahkan.');
    }

    private function bekukanNeracaHariIni(): void
    {
        $tanggal = now()->toDateString();
        $p = Akuntansi::posisiPada($tanggal);

        NeracaSnapshot::create([
            'tanggal' => $tanggal,
            'kas' => $p->kas,
            'piutang' => $p->sisa_tagihan_pesanan,
            'persediaan' => $p->persediaan,
            'aset_tetap' => $p->aset_tetap,
            'total_aset' => $p->total_aset,
            'hutang_usaha' => $p->hutang_usaha,
            'total_kewajiban' => $p->total_kewajiban,
            'modal_awal' => $p->modal_awal,
            'tambahan_modal' => $p->tambahan_modal,
            'prive' => $p->prive,
            'laba_ditahan' => $p->laba_ditahan,
            'total_modal' => $p->total_modal,
            'selisih' => $p->selisih,
            'note' => 'Tutup buku otomatis dari data simulasi',
            'user_id' => User::where('username', 'admin')->value('id'),
        ]);

        $this->line('Neraca hari ini dibekukan (1 snapshot, seimbang nol).');
    }

    private function generateData(): void
    {
        // 1. Roles & Users
        $adminRole = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => array_keys(Role::allMenuKeys()),
            'is_system' => true,
        ]);

        $kasirRole = Role::create([
            'name' => 'Kasir Senior',
            'slug' => 'kasir',
            'permissions' => ['dashboard', 'kasir', 'retur', 'pelanggan', 'planogram'],
            'is_system' => true,
        ]);

        $spvRole = Role::create([
            'name' => 'Supervisor Toko',
            'slug' => 'supervisor',
            'permissions' => ['dashboard', 'produk', 'kategori', 'satuan', 'supplier', 'pelanggan', 'kasir', 'retur', 'pembelian', 'kas', 'laporan', 'planogram'],
            'is_system' => false,
        ]);

        $adminUser = User::create([
            'name' => 'Pak Hendra (Pemilik)',
            'username' => 'admin',
            'email' => 'hendra@josmart.co.id',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $kasir1 = User::create([
            'name' => 'Siti Aminah',
            'username' => 'kasir1',
            'email' => 'siti@josmart.co.id',
            'password' => Hash::make('password'),
            'role_id' => $kasirRole->id,
            'is_active' => true,
        ]);

        $kasir2 = User::create([
            'name' => 'Budi Santoso',
            'username' => 'kasir2',
            'email' => 'budi@josmart.co.id',
            'password' => Hash::make('password'),
            'role_id' => $kasirRole->id,
            'is_active' => true,
        ]);

        $spvUser = User::create([
            'name' => 'Rian Hidayat',
            'username' => 'spv',
            'email' => 'rian@josmart.co.id',
            'password' => Hash::make('password'),
            'role_id' => $spvRole->id,
            'is_active' => true,
        ]);

        // 2. Master Satuan
        $uPcs = Unit::firstOrCreate(['name' => 'Pcs'], ['is_weighable' => false]);
        $uDus = Unit::firstOrCreate(['name' => 'Dus'], ['is_weighable' => false]);
        $uPack = Unit::firstOrCreate(['name' => 'Pack'], ['is_weighable' => false]);
        $uBotol = Unit::firstOrCreate(['name' => 'Botol'], ['is_weighable' => false]);
        $uKg = Unit::firstOrCreate(['name' => 'Kg'], ['is_weighable' => true]);
        $uGram = Unit::firstOrCreate(['name' => 'Gram'], ['is_weighable' => true]);
        $uKaleng = Unit::firstOrCreate(['name' => 'Kaleng'], ['is_weighable' => false]);

        // 3. Kategori
        $catSembako = Category::create(['name' => 'Sembako & Dapur', 'slug' => 'sembako-dapur']);
        $catMinuman = Category::create(['name' => 'Minuman Kemasan', 'slug' => 'minuman-kemasan']);
        $catSnack = Category::create(['name' => 'Makanan Ringan', 'slug' => 'makanan-ringan']);
        $catKebersihan = Category::create(['name' => 'Kebersihan & Sabun', 'slug' => 'kebersihan-sabun']);
        $catPerawatan = Category::create(['name' => 'Perawatan Diri', 'slug' => 'perawatan-diri']);
        $catBuah = Category::create(['name' => 'Buah & Sayur Segar', 'slug' => 'buah-sayur-segar']);
        $catRokok = Category::create(['name' => 'Rokok & Tembakau', 'slug' => 'rokok-tembakau']);
        $catJasa = Category::create(['name' => 'Jasa & Layanan', 'slug' => 'jasa-layanan']);

        // 4. Supplier
        $supIndofood = Supplier::create(['name' => 'PT Indofood Sukses Makmur', 'contact_person' => 'Bpk. Aris', 'phone' => '031-778899', 'address' => 'Kawasan Industri Rungkut, Surabaya']);
        $supUnilever = Supplier::create(['name' => 'PT Unilever Indonesia Tbk', 'contact_person' => 'Ibu Rina', 'phone' => '021-554433', 'address' => 'BSD City, Tangerang']);
        $supMayora = Supplier::create(['name' => 'PT Mayora Indah Tbk', 'contact_person' => 'Bpk. Deni', 'phone' => '021-889900', 'address' => 'Daan Mogot, Jakarta']);
        $supSembako = Supplier::create(['name' => 'CV Sumber Sembako Utama', 'contact_person' => 'H. Ahmad', 'phone' => '081234567890', 'address' => 'Pasar Turi Blok B No. 12, Surabaya']);
        $supBuah = Supplier::create(['name' => 'Buah Segar Nusantara', 'contact_person' => 'Pak Joko', 'phone' => '085678901234', 'address' => 'Batu, Malang']);

        // 5. Customer
        $custUmum = Customer::create(['name' => 'Pelanggan Umum', 'phone' => '-']);
        $custGrosir = Customer::create(['name' => 'Toko Berkah (Grosir)', 'phone' => '081122334455', 'address' => 'Jl. Mawar No. 45']);
        $custResto = Customer::create(['name' => 'Resto Bu Sumi', 'phone' => '081998877665', 'address' => 'Jl. Pemuda No. 12']);
        $custHendra = Customer::create(['name' => 'Bpk. Hendra Wijaya', 'phone' => '087712345678', 'address' => 'Perum Asri A-10']);
        $custMaya = Customer::create(['name' => 'Ibu Maya Restu', 'phone' => '085234567890', 'address' => 'Jl. Diponegoro No. 88']);

        // 6. Master Produk
        $produkList = [];

        // Produk 1: Indomie Goreng (Multi-Unit: Pcs, Dus = 40 Pcs)
        $pIndomie = Product::create([
            'sku' => 'SKU-INDO-GRG',
            'barcode' => '8998866200114',
            'name' => 'Indomie Goreng Spasial 85g',
            'type' => 'barang',
            'category_id' => $catSembako->id,
            'supplier_id' => $supIndofood->id,
            'unit' => 'Pcs',
            'cost_price' => 2700,
            'sell_price' => 3200,
            'wholesale_price' => 3000,
            'wholesale_min_qty' => 10,
            'stock' => 320, // 8 Dus
            'min_stock' => 40,
            'multi_unit_enabled' => true,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $pIndomie->units()->create([
            'unit_id' => $uDus->id,
            'ratio_to_previous' => 40,
            'conversion' => 40,
            'sort_order' => 0,
            'price' => 122000,
            'cost_price' => 108000,
            'wholesale_price' => 119000,
            'wholesale_min_qty' => 5,
            'allow_decimal' => false,
        ]);
        $produkList['indomie'] = $pIndomie;

        // Produk 2: Minyak Goreng Bimoli 2L
        $pBimoli = Product::create([
            'sku' => 'SKU-BIMOLI-2L',
            'barcode' => '8998866300221',
            'name' => 'Minyak Goreng Bimoli Refill 2 Litur',
            'type' => 'barang',
            'category_id' => $catSembako->id,
            'supplier_id' => $supIndofood->id,
            'unit' => 'Pcs',
            'cost_price' => 33500,
            'sell_price' => 38000,
            'wholesale_price' => 36500,
            'wholesale_min_qty' => 6,
            'stock' => 85,
            'min_stock' => 12,
            'multi_unit_enabled' => false,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $produkList['bimoli'] = $pBimoli;

        // Produk 3: Beras Premium Super 5Kg
        $pBeras = Product::create([
            'sku' => 'SKU-BERAS-5KG',
            'barcode' => '8991001005544',
            'name' => 'Beras Premium Super Pandan Wangi 5Kg',
            'type' => 'barang',
            'category_id' => $catSembako->id,
            'supplier_id' => $supSembako->id,
            'unit' => 'Pack',
            'cost_price' => 68000,
            'sell_price' => 76000,
            'wholesale_price' => 74000,
            'wholesale_min_qty' => 4,
            'stock' => 45,
            'min_stock' => 10,
            'multi_unit_enabled' => false,
            'is_taxable' => false,
            'is_active' => true,
        ]);
        $produkList['beras'] = $pBeras;

        // Produk 4: Gula Pasir Curah Premium (Satuan Timbangan Kg)
        $pGula = Product::create([
            'sku' => 'SKU-GULA-CURAH',
            'barcode' => '8991001009900',
            'name' => 'Gula Pasir Kristal Putih (Timbangan)',
            'type' => 'barang',
            'category_id' => $catSembako->id,
            'supplier_id' => $supSembako->id,
            'unit' => 'Kg',
            'cost_price' => 14000,
            'sell_price' => 16500,
            'wholesale_price' => 15800,
            'wholesale_min_qty' => 10,
            'stock' => 142.5, // 142.5 Kg
            'min_stock' => 20,
            'multi_unit_enabled' => false,
            'is_taxable' => false,
            'is_active' => true,
        ]);
        $produkList['gula'] = $pGula;

        // Produk 5: Apel Fuji Import (Satuan Timbangan Kg)
        $pApel = Product::create([
            'sku' => 'SKU-APEL-FUJI',
            'barcode' => '8997001001122',
            'name' => 'Apel Fuji Super Import (Timbangan)',
            'type' => 'barang',
            'category_id' => $catBuah->id,
            'supplier_id' => $supBuah->id,
            'unit' => 'Kg',
            'cost_price' => 28000,
            'sell_price' => 38000,
            'stock' => 34.8, // 34.8 Kg
            'min_stock' => 5,
            'multi_unit_enabled' => false,
            'is_taxable' => false,
            'is_active' => true,
        ]);
        $produkList['apel'] = $pApel;

        // Produk 6: Teh Pucuk Harum 350ml (Multi-Unit: Botol, Dus = 24 Botol)
        $pTehPucuk = Product::create([
            'sku' => 'SKU-TEH-PUCUK',
            'barcode' => '8998888110012',
            'name' => 'Teh Pucuk Harum 350ml',
            'type' => 'barang',
            'category_id' => $catMinuman->id,
            'supplier_id' => $supMayora->id,
            'unit' => 'Botol',
            'cost_price' => 2600,
            'sell_price' => 3500,
            'wholesale_price' => 3200,
            'wholesale_min_qty' => 12,
            'stock' => 192, // 8 Dus
            'min_stock' => 24,
            'multi_unit_enabled' => true,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $pTehPucuk->units()->create([
            'unit_id' => $uDus->id,
            'ratio_to_previous' => 24,
            'conversion' => 24,
            'sort_order' => 0,
            'price' => 78000,
            'cost_price' => 62400,
            'allow_decimal' => false,
        ]);
        $produkList['tehpucuk'] = $pTehPucuk;

        // Produk 7: Coca Cola 390ml Pet
        $pCoke = Product::create([
            'sku' => 'SKU-COCA-COLA',
            'barcode' => '8992761001005',
            'name' => 'Coca Cola Pet 390ml',
            'type' => 'barang',
            'category_id' => $catMinuman->id,
            'supplier_id' => $supMayora->id,
            'unit' => 'Botol',
            'cost_price' => 4200,
            'sell_price' => 5500,
            'stock' => 60,
            'min_stock' => 12,
            'multi_unit_enabled' => false,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $produkList['coke'] = $pCoke;

        // Produk 8: Chitato Sapi Panggang 68g
        $pChitato = Product::create([
            'sku' => 'SKU-CHITATO-SP',
            'barcode' => '8998866400551',
            'name' => 'Chitato Sapi Panggang 68g',
            'type' => 'barang',
            'category_id' => $catSnack->id,
            'supplier_id' => $supIndofood->id,
            'unit' => 'Pcs',
            'cost_price' => 8800,
            'sell_price' => 11500,
            'stock' => 42,
            'min_stock' => 10,
            'multi_unit_enabled' => false,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $produkList['chitato'] = $pChitato;

        // Produk 9: Sabun Rinso Anti Noda 770g
        $pRinso = Product::create([
            'sku' => 'SKU-RINSO-770G',
            'barcode' => '8999999002233',
            'name' => 'Rinso Anti Noda Deterjen Bubuk 770g',
            'type' => 'barang',
            'category_id' => $catKebersihan->id,
            'supplier_id' => $supUnilever->id,
            'unit' => 'Pcs',
            'cost_price' => 18500,
            'sell_price' => 22000,
            'stock' => 38,
            'min_stock' => 8,
            'multi_unit_enabled' => false,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $produkList['rinso'] = $pRinso;

        // Produk 10: Shampo Lifebuoy Strong 170ml
        $pLifebuoy = Product::create([
            'sku' => 'SKU-LIFEBUOY-SH',
            'barcode' => '8999999004455',
            'name' => 'Lifebuoy Shampoo Strong & Shiny 170ml',
            'type' => 'barang',
            'category_id' => $catPerawatan->id,
            'supplier_id' => $supUnilever->id,
            'unit' => 'Botol',
            'cost_price' => 16200,
            'sell_price' => 19500,
            'stock' => 25,
            'min_stock' => 6,
            'multi_unit_enabled' => false,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $produkList['lifebuoy'] = $pLifebuoy;

        // Produk 11: Rokok Sampoerna Mild 16
        $pSampoerna = Product::create([
            'sku' => 'SKU-SAMPOERNA-16',
            'barcode' => '8999988001122',
            'name' => 'Sampoerna A Mild 16 Batang',
            'type' => 'barang',
            'category_id' => $catRokok->id,
            'supplier_id' => $supSembako->id,
            'unit' => 'Pack',
            'cost_price' => 30500,
            'sell_price' => 33500,
            'stock' => 95,
            'min_stock' => 20,
            'multi_unit_enabled' => false,
            'is_taxable' => false,
            'is_active' => true,
        ]);
        $produkList['sampoerna'] = $pSampoerna;

        // Produk 12: Jasa Layanan Packing & Delivery Khusus
        $pJasaAntar = Product::create([
            'sku' => 'SKU-JASA-DELIVERY',
            'barcode' => null,
            'name' => 'Jasa Antar Barang Paket VIP (Radius 5Km)',
            'type' => 'jasa',
            'category_id' => $catJasa->id,
            'supplier_id' => null,
            'unit' => 'transaksi',
            'cost_price' => 5000,
            'sell_price' => 15000,
            'stock' => 0,
            'min_stock' => 0,
            'multi_unit_enabled' => false,
            'is_taxable' => false,
            'is_active' => true,
        ]);

        // Catat stok awal ke StockMovement untuk barang fisik
        foreach ($produkList as $p) {
            if ($p->stock > 0) {
                StockMovement::create([
                    'product_id' => $p->id,
                    'type' => 'in',
                    'qty' => $p->stock,
                    'stock_after' => $p->stock,
                    'note' => 'Stok awal setup master data',
                    'user_id' => $adminUser->id,
                    'created_at' => Carbon::parse('2026-01-01 08:00:00'),
                ]);
            }
        }

        // 7. Planogram (Rak & Slot)
        $rakA = Rack::create(['name' => 'Rak A (Sembako & Dapur)', 'description' => 'Lorong Utama Kiri', 'rows' => 4, 'cols' => 6]);
        $rakB = Rack::create(['name' => 'Rak B (Minuman & Snack)', 'description' => 'Lorong Tengah', 'rows' => 4, 'cols' => 6]);
        $rakC = Rack::create(['name' => 'Rak C (Kebersihan & Diri)', 'description' => 'Lorong Kanan', 'rows' => 4, 'cols' => 6]);

        RackSlot::create(['rack_id' => $rakA->id, 'product_id' => $pIndomie->id, 'row' => 0, 'col' => 0, 'facings' => 4]);
        RackSlot::create(['rack_id' => $rakA->id, 'product_id' => $pBimoli->id, 'row' => 1, 'col' => 0, 'facings' => 2]);
        RackSlot::create(['rack_id' => $rakA->id, 'product_id' => $pBeras->id, 'row' => 2, 'col' => 0, 'facings' => 1]);
        RackSlot::create(['rack_id' => $rakB->id, 'product_id' => $pTehPucuk->id, 'row' => 0, 'col' => 0, 'facings' => 6]);
        RackSlot::create(['rack_id' => $rakB->id, 'product_id' => $pChitato->id, 'row' => 1, 'col' => 1, 'facings' => 3]);
        RackSlot::create(['rack_id' => $rakC->id, 'product_id' => $pRinso->id, 'row' => 0, 'col' => 0, 'facings' => 2]);

        // 8. Pengaturan Toko & Pembukuan
        Setting::set('store_profile', [
            'name' => 'JOS MART SUPERMARKET',
            'address' => 'Jl. Ahmad Yani No. 88, Surabaya, Jawa Timur',
            'phone' => '031-8889999',
            'email' => 'info@josmart.co.id',
            'logo' => null,
            'footer_note' => 'Terima kasih atas kunjungan Anda! Barang yang sudah dibeli tidak dapat ditukar.',
        ]);

        Setting::set('tax', [
            'enabled' => true,
            'name' => 'PPN',
            'percent' => 11,
            'include_in_price' => false,
        ]);

        Setting::set('printer_struk', [
            'paper_size' => 80,
            'margin' => 0,
            'font_size' => 12,
            'auto_print' => false,
            'printer_name' => 'EPSON TM-T82 Thermal',
        ]);

        Setting::set('template_struk', [
            'show_logo' => false,
            'show_address' => true,
            'show_phone' => true,
            'show_cashier' => true,
            'show_customer' => true,
            'header_note' => '=== JOS MART SUPERMARKET ===',
            'footer_note' => 'Selamat Berbelanja Kembali!',
        ]);

        // Titik Awal Pembukuan: 1 Jan 2026
        // Saldo Kas Awal: 50.000.000
        // Modal Awal: 185.000.000 (Menyeimbangkan Kas Awal + Stok Awal)
        $nilaiStokAwal = 0;
        foreach ($produkList as $p) {
            $nilaiStokAwal += ($p->stock * $p->cost_price);
        }

        $saldoKasAwal = 50000000;
        $modalAwalCalculated = Angka::bulat($saldoKasAwal + $nilaiStokAwal);

        Setting::set('pembukuan', [
            'tanggal_mulai' => '2026-01-01',
            'saldo_awal_kas' => $saldoKasAwal,
            'modal_awal' => $modalAwalCalculated,
        ]);

        // 9. Aset Tetap (Fixed Assets) + Kas Keluar Aset Tetap
        $faEtalase = FixedAsset::create([
            'name' => 'Etalase & Rak Supermarket Utama',
            'acquired_at' => '2026-01-02',
            'acquisition_cost' => 15000000,
            'useful_life_months' => 60,
            'note' => 'Dibeli tunai dari Kas toko',
            'user_id' => $adminUser->id,
        ]);
        CashTransaction::create([
            'type' => 'out',
            'category' => 'aset_tetap',
            'amount' => 15000000,
            'note' => 'Beli ' . $faEtalase->name,
            'user_id' => $adminUser->id,
            'created_at' => Carbon::parse('2026-01-02 10:00:00'),
        ]);

        $faAC = FixedAsset::create([
            'name' => 'AC Daikin Inverter 2 PK (2 Unit)',
            'acquired_at' => '2026-01-03',
            'acquisition_cost' => 9000000,
            'useful_life_months' => 36,
            'note' => 'Dibeli tunai',
            'user_id' => $adminUser->id,
        ]);
        CashTransaction::create([
            'type' => 'out',
            'category' => 'aset_tetap',
            'amount' => 9000000,
            'note' => 'Beli ' . $faAC->name,
            'user_id' => $adminUser->id,
            'created_at' => Carbon::parse('2026-01-03 11:30:00'),
        ]);

        // 10. Transaksi Pembelian Barang (Purchases & Payments)
        // Purchase #1: Kulakan Indofood Tunai Lunas (15 Jan 2026)
        $pur1 = Purchase::create([
            'purchase_no' => 'PUR-20260115-0001',
            'supplier_id' => $supIndofood->id,
            'supplier_invoice_no' => 'INV-IND-9988',
            'purchase_date' => '2026-01-15',
            'subtotal' => 5400000, // 50 Dus Indomie @ 108.000
            'other_cost' => 100000, // Ongkir
            'total' => 5500000,
            'paid_amount' => 5500000,
            'sisa_hutang' => 0,
            'due_date' => null,
            'note' => 'Kulakan Indomie Goreng 50 Dus + Ongkir',
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-01-15 09:00:00'),
        ]);
        PurchaseItem::create([
            'purchase_id' => $pur1->id,
            'product_id' => $pIndomie->id,
            'product_name' => $pIndomie->name,
            'qty' => 50,
            'unit_label' => 'Dus',
            'unit_conversion' => 40,
            'price' => 108000,
            'subtotal' => 5400000,
        ]);
        // Update stok & cost_price
        $baseUnits1 = 50 * 40; // 2000 Pcs
        $pIndomie->stock = Angka::bulat($pIndomie->stock + $baseUnits1);
        $pIndomie->cost_price = round(5500000 / $baseUnits1, 2); // 2750
        $pIndomie->save();

        StockMovement::create([
            'product_id' => $pIndomie->id,
            'type' => 'in',
            'qty' => $baseUnits1,
            'stock_after' => $pIndomie->stock,
            'note' => 'Pembelian ' . $pur1->purchase_no,
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-01-15 09:00:00'),
        ]);
        CashTransaction::create([
            'type' => 'out',
            'category' => 'pembelian',
            'amount' => 5500000,
            'note' => 'Pembelian ' . $pur1->purchase_no . ' - ' . $supIndofood->name,
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-01-15 09:00:00'),
        ]);

        // Purchase #2: Kulakan Unilever Kredit (10 Feb 2026) - Total 12.000.000, DP 5.000.000, Sisa 7.000.000
        $pur2 = Purchase::create([
            'purchase_no' => 'PUR-20260210-0002',
            'supplier_id' => $supUnilever->id,
            'supplier_invoice_no' => 'UNIL-INV-1102',
            'purchase_date' => '2026-02-10',
            'subtotal' => 12000000,
            'other_cost' => 0,
            'total' => 12000000,
            'paid_amount' => 5000000,
            'sisa_hutang' => 7000000,
            'due_date' => '2026-04-10',
            'note' => 'Kulakan Sabun Rinso & Shampo Lifebuoy Kredit Tempo 60 Hari',
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-02-10 14:00:00'),
        ]);
        PurchaseItem::create([
            'purchase_id' => $pur2->id,
            'product_id' => $pRinso->id,
            'product_name' => $pRinso->name,
            'qty' => 300,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'price' => 18500,
            'subtotal' => 5550000,
        ]);
        PurchaseItem::create([
            'purchase_id' => $pur2->id,
            'product_id' => $pLifebuoy->id,
            'product_name' => $pLifebuoy->name,
            'qty' => 398,
            'unit_label' => 'Botol',
            'unit_conversion' => 1,
            'price' => 16200,
            'subtotal' => 6450000,
        ]);
        $pRinso->stock = Angka::bulat($pRinso->stock + 300);
        $pRinso->save();
        $pLifebuoy->stock = Angka::bulat($pLifebuoy->stock + 398);
        $pLifebuoy->save();

        StockMovement::create([
            'product_id' => $pRinso->id,
            'type' => 'in',
            'qty' => 300,
            'stock_after' => $pRinso->stock,
            'note' => 'Pembelian ' . $pur2->purchase_no,
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-02-10 14:00:00'),
        ]);
        StockMovement::create([
            'product_id' => $pLifebuoy->id,
            'type' => 'in',
            'qty' => 398,
            'stock_after' => $pLifebuoy->stock,
            'note' => 'Pembelian ' . $pur2->purchase_no,
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-02-10 14:00:00'),
        ]);
        CashTransaction::create([
            'type' => 'out',
            'category' => 'pembelian',
            'amount' => 5000000,
            'note' => 'Pembelian ' . $pur2->purchase_no . ' - ' . $supUnilever->name,
            'user_id' => $spvUser->id,
            'created_at' => Carbon::parse('2026-02-10 14:00:00'),
        ]);

        // Cicilan Pembayaran Hutang Purchase #2 (15 Maret 2026) - Bayar Rp 4.000.000 -> Sisa Hutang Rp 3.000.000
        PurchasePayment::create([
            'purchase_id' => $pur2->id,
            'amount' => 4000000,
            'paid_at' => '2026-03-15',
            'note' => 'Cicilan Tahap 1 Nota UNIL-INV-1102',
            'user_id' => $adminUser->id,
            'created_at' => Carbon::parse('2026-03-15 11:00:00'),
        ]);
        $pur2->paid_amount = 9000000;
        $pur2->sisa_hutang = 3000000;
        $pur2->save();
        CashTransaction::create([
            'type' => 'out',
            'category' => 'pembelian',
            'amount' => 4000000,
            'note' => 'Bayar hutang ' . $pur2->purchase_no . ' - ' . $supUnilever->name,
            'user_id' => $adminUser->id,
            'created_at' => Carbon::parse('2026-03-15 11:00:00'),
        ]);

        // 11. Transaksi Penjualan Masif (Sales, Items, DP Waiting, Retur)
        // Penjualan Januari 2026 s/d Agustus 2026
        $startDate = Carbon::parse('2026-01-05');
        $endDate = Carbon::parse('2026-08-10');
        $invSeq = 1;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDays(rand(1, 2))) {
            $numTrx = rand(2, 5);

            for ($i = 0; $i < $numTrx; $i++) {
                $invoiceNo = 'INV' . $date->format('y0m0d') . str_pad($invSeq++, 4, '0', STR_PAD_LEFT);
                $cashier = rand(0, 1) ? $kasir1 : $kasir2;
                $customer = rand(0, 3) === 0 ? $custGrosir : ($i % 2 === 0 ? $custUmum : $custResto);

                // Simulasi checkout 2-4 item
                $itemsToBuy = [];
                $prodKeys = array_rand($produkList, rand(2, 4));
                if (!is_array($prodKeys)) $prodKeys = [$prodKeys];

                $subtotal = 0;
                $taxableSubtotal = 0;
                $saleLineItems = [];

                foreach ($prodKeys as $pk) {
                    $prod = $produkList[$pk];
                    if ($prod->isJasa()) continue;

                    // Qty timbangan vs biasa
                    $qty = $prod->unit === 'Kg' ? (float) (rand(5, 35) / 10) : (float) rand(1, 4);
                    if ($qty <= 0) $qty = 1.0;

                    // Cek ketersediaan stok
                    if ($prod->stock < $qty) continue;

                    $price = ($qty >= ($prod->wholesale_min_qty ?? 999)) ? (float) $prod->wholesale_price : (float) $prod->sell_price;
                    $lineSubtotal = Angka::bulat($qty * $price);

                    $subtotal += $lineSubtotal;
                    if ($prod->is_taxable) {
                        $taxableSubtotal += $lineSubtotal;
                    }

                    $saleLineItems[] = [
                        'product' => $prod,
                        'qty' => $qty,
                        'price' => $price,
                        'subtotal' => $lineSubtotal,
                    ];
                }

                if (empty($saleLineItems)) continue;

                $discount = rand(0, 10) > 8 ? 5000 : 0;
                $taxPercent = 11;
                $taxBase = max($taxableSubtotal - ($discount * ($taxableSubtotal / max($subtotal, 1))), 0);
                $taxAmount = round($taxBase * $taxPercent / 100);
                $total = $subtotal - $discount + $taxAmount;
                $paid = $total + (rand(0, 5) * 5000);

                $saleTime = $date->copy()->addHours(rand(8, 19))->addMinutes(rand(0, 59));

                $sale = Sale::create([
                    'invoice_no' => $invoiceNo,
                    'customer_id' => $customer->id,
                    'user_id' => $cashier->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'paid_amount' => $paid,
                    'change_amount' => $paid - $total,
                    'payment_method' => rand(0, 3) === 0 ? 'qris' : 'tunai',
                    'status' => 'completed',
                    'order_status' => 'completed',
                    'due_date' => null,
                    'note' => 'Penjualan kasir harian',
                    'created_at' => $saleTime,
                    'updated_at' => $saleTime,
                ]);

                foreach ($saleLineItems as $sli) {
                    $prod = $sli['product'];
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $prod->id,
                        'product_name' => $prod->name,
                        'price' => $sli['price'],
                        'cost_price_snapshot' => $prod->cost_price,
                        'qty' => $sli['qty'],
                        'unit_label' => $prod->unit,
                        'unit_conversion' => 1,
                        'subtotal' => $sli['subtotal'],
                        'created_at' => $saleTime,
                        'updated_at' => $saleTime,
                    ]);

                    $stockAfter = $prod->ubahStok(-$sli['qty']);
                    StockMovement::create([
                        'product_id' => $prod->id,
                        'type' => 'sale',
                        'qty' => -$sli['qty'],
                        'stock_after' => $stockAfter,
                        'note' => 'Penjualan ' . $sale->invoice_no,
                        'user_id' => $cashier->id,
                        'created_at' => $saleTime,
                        'updated_at' => $saleTime,
                    ]);
                }
            }
        }

        // Simulasi 1 Transaksi Waiting List / DP Aktif (Resto Bu Sumi katering acara per 5 Aug 2026)
        $dpDate = Carbon::parse('2026-08-05 10:00:00');
        $saleDP = Sale::create([
            'invoice_no' => 'INV2608059999',
            'customer_id' => $custResto->id,
            'user_id' => $kasir1->id,
            'subtotal' => 2000000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 2000000,
            'paid_amount' => 800000, // DP Rp 800.000 (Kewajiban Uang Muka)
            'change_amount' => 0,
            'payment_method' => 'transfer',
            'status' => 'completed',
            'order_status' => 'waiting',
            'due_date' => '2026-08-20',
            'note' => 'Pesanan DP Katering Acara Resto Bu Sumi',
            'created_at' => $dpDate,
            'updated_at' => $dpDate,
        ]);
        SaleItem::create([
            'sale_id' => $saleDP->id,
            'product_id' => $pBimoli->id,
            'product_name' => $pBimoli->name,
            'price' => 38000,
            'cost_price_snapshot' => $pBimoli->cost_price,
            'qty' => 20,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'subtotal' => 760000,
            'created_at' => $dpDate,
        ]);
        SaleItem::create([
            'sale_id' => $saleDP->id,
            'product_id' => $pBeras->id,
            'product_name' => $pBeras->name,
            'price' => 76000,
            'cost_price_snapshot' => $pBeras->cost_price,
            'qty' => 16,
            'unit_label' => 'Pack',
            'unit_conversion' => 1,
            'subtotal' => 1240000,
            'created_at' => $dpDate,
        ]);
        // Stok dipotong langsung untuk DP (direservasi)
        $pBimoli->ubahStok(-20);
        StockMovement::create([
            'product_id' => $pBimoli->id,
            'type' => 'sale',
            'qty' => -20,
            'stock_after' => $pBimoli->stock,
            'note' => 'Pesanan (DP) ' . $saleDP->invoice_no,
            'user_id' => $kasir1->id,
            'created_at' => $dpDate,
        ]);
        $pBeras->ubahStok(-16);
        StockMovement::create([
            'product_id' => $pBeras->id,
            'type' => 'sale',
            'qty' => -16,
            'stock_after' => $pBeras->stock,
            'note' => 'Pesanan (DP) ' . $saleDP->invoice_no,
            'user_id' => $kasir1->id,
            'created_at' => $dpDate,
        ]);

        // Simulasi 1 Retur Penjualan (15 April 2026)
        $saleToReturn = Sale::where('order_status', 'completed')->first();
        if ($saleToReturn && $saleToReturn->items->isNotEmpty()) {
            $itemToReturn = $saleToReturn->items->first();
            $retQty = 1.0;
            if ($itemToReturn->qty >= $retQty) {
                $retTime = Carbon::parse('2026-04-15 14:00:00');
                $retur = SaleReturn::create([
                    'return_no' => 'RET-20260415-0001',
                    'sale_id' => $saleToReturn->id,
                    'user_id' => $kasir2->id,
                    'reason' => 'Barang kemasan cacat dikembalikan pelanggan',
                    'total' => $itemToReturn->price * $retQty,
                    'created_at' => $retTime,
                ]);
                SaleReturnItem::create([
                    'sale_return_id' => $retur->id,
                    'sale_item_id' => $itemToReturn->id,
                    'product_id' => $itemToReturn->product_id,
                    'qty' => $retQty,
                    'price' => $itemToReturn->price,
                    'subtotal' => $itemToReturn->price * $retQty,
                    'created_at' => $retTime,
                ]);
                $itemToReturn->returned_qty = $retQty;
                $itemToReturn->save();

                $saleToReturn->status = 'partial_returned';
                $saleToReturn->save();

                if ($itemToReturn->product_id) {
                    $pRet = Product::find($itemToReturn->product_id);
                    if ($pRet) {
                        $stkAfter = $pRet->ubahStok($retQty);
                        StockMovement::create([
                            'product_id' => $pRet->id,
                            'type' => 'return',
                            'qty' => $retQty,
                            'stock_after' => $stkAfter,
                            'note' => 'Retur ' . $retur->return_no,
                            'user_id' => $kasir2->id,
                            'created_at' => $retTime,
                        ]);
                    }
                }
            }
        }

        // 12. Transaksi Kas Masuk & Keluar Operasional
        $kasOps = [
            ['date' => '2026-01-30', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1200000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Jan 2026'],
            ['date' => '2026-01-31', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Jan 2026'],
            ['date' => '2026-02-01', 'type' => 'out', 'cat' => 'sewa', 'amount' => 5000000, 'note' => 'Bayar Sewa Ruko Bulan Feb 2026'],
            ['date' => '2026-02-28', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1350000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Feb 2026'],
            ['date' => '2026-02-28', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Feb 2026'],
            ['date' => '2026-03-10', 'type' => 'in', 'cat' => 'lain_lain', 'amount' => 450000, 'note' => 'Hasil Penjualan Kardus Bekas Kulakan'],
            ['date' => '2026-03-31', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1400000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Mar 2026'],
            ['date' => '2026-03-31', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Mar 2026'],
            ['date' => '2026-04-15', 'type' => 'out', 'cat' => 'ambil_pribadi', 'amount' => 3000000, 'note' => 'Pengambilan Prive Pemilik (Pak Hendra)'],
            ['date' => '2026-04-30', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1280000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Apr 2026'],
            ['date' => '2026-04-30', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Apr 2026'],
            ['date' => '2026-05-10', 'type' => 'in', 'cat' => 'modal_tambahan', 'amount' => 10000000, 'note' => 'Setoran Modal Tambahan Pemilik untuk Ekspan Stok'],
            ['date' => '2026-05-31', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1500000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Mei 2026'],
            ['date' => '2026-05-31', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Mei 2026'],
            ['date' => '2026-06-30', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1450000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Jun 2026'],
            ['date' => '2026-06-30', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Jun 2026'],
            ['date' => '2026-07-31', 'type' => 'out', 'cat' => 'operasional', 'amount' => 1520000, 'note' => 'Bayar Tagihan Listrik & Air Bulan Jul 2026'],
            ['date' => '2026-07-31', 'type' => 'out', 'cat' => 'gaji', 'amount' => 6000000, 'note' => 'Gaji Kasir & Staff Bulan Jul 2026'],
        ];

        foreach ($kasOps as $ko) {
            CashTransaction::create([
                'type' => $ko['type'],
                'category' => $ko['cat'],
                'amount' => $ko['amount'],
                'note' => $ko['note'],
                'user_id' => $adminUser->id,
                'created_at' => Carbon::parse($ko['date'] . ' 17:00:00'),
            ]);
        }

        // 13. SNAPSHOT TUTUP BUKU - SENGAJA HANYA UNTUK HARI INI.
        //
        // Versi sebelumnya membekukan tujuh snapshot bulanan (Jan-Jul), dan KEDELAPANNYA
        // timpang 10-16 juta. Itu bukan cacat aplikasi: Akuntansi::posisiPada() menghitung
        // persediaan dari stok DAN harga modal SAAT INI, karena stock_movements menyimpan
        // kuantitas tanpa nilai. Neraca tanggal lampau memang hanya perkiraan - batasan yang
        // tercatat di BLUEPRINT §14 nomor 1.
        //
        // Membekukan tanggal lampau dari data hari ini menghasilkan angka yang terlihat rusak
        // padahal aplikasinya benar. Untuk database yang tujuannya SCREENING, itu racun: yang
        // memeriksanya akan mengejar cacat yang tidak ada.
        //
        // Snapshot hari ini dibuat di handle(), setelah modal awal disetel dan selisihnya
        // dipastikan nol.
    }
}
