<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FnbProductSeeder extends Seeder
{
    private string $fontBold = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';

    public function run(): void
    {
        $targetDir = storage_path('app/public/products');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Pastikan kategori F&B ada
        $catMakanan = Category::firstOrCreate(['slug' => 'makanan'], ['name' => 'Makanan']);
        $catMinuman = Category::firstOrCreate(['slug' => 'minuman'], ['name' => 'Minuman']);
        $catSnack   = Category::firstOrCreate(['slug' => 'snack'],   ['name' => 'Snack']);

        $supplier = Supplier::firstOrCreate(['name' => 'Supplier F&B Resto'], [
            'contact_person' => 'Dapur Utama',
            'phone' => '081234567890',
            'address' => 'Gudang Pusat Kuliner',
        ]);

        $menuItems = [
            // ==================== MAKANAN ====================
            [
                'name' => 'Nasi Goreng Spesial',
                'category_id' => $catMakanan->id,
                'cost_price' => 12000,
                'sell_price' => 25000,
                'stock' => 80,
                'unit' => 'Porsi',
                'barcode' => '899100100001',
                'description' => 'Nasi goreng harum dengan telur mata sapi, ayam suwir, acar, dan kerupuk renyah.',
                'image_file' => 'nasi-goreng-spesial.png',
                'badge' => 'FAVORITE',
                'theme' => 'nasi_goreng',
            ],
            [
                'name' => 'Mie Goreng Seafood',
                'category_id' => $catMakanan->id,
                'cost_price' => 14000,
                'sell_price' => 28000,
                'stock' => 65,
                'unit' => 'Porsi',
                'barcode' => '899100100002',
                'description' => 'Mie goreng kenyal dengan udang, cumi segar, telur orak-arik, dan sawi hijau.',
                'image_file' => 'mie-goreng-seafood.png',
                'badge' => 'BEST SELLER',
                'theme' => 'mie_goreng',
            ],
            [
                'name' => 'Ayam Bakar Madu',
                'category_id' => $catMakanan->id,
                'cost_price' => 16000,
                'sell_price' => 30000,
                'stock' => 50,
                'unit' => 'Porsi',
                'barcode' => '899100100003',
                'description' => 'Ayam bakar dengan bumbu karamel madu rempah, lalapan segar, dan sambal terasi.',
                'image_file' => 'ayam-bakar-madu.png',
                'badge' => 'REKOMENDASI',
                'theme' => 'ayam_bakar',
            ],
            [
                'name' => 'Ayam Geprek Sambal Bawang',
                'category_id' => $catMakanan->id,
                'cost_price' => 13000,
                'sell_price' => 24000,
                'stock' => 75,
                'unit' => 'Porsi',
                'barcode' => '899100100004',
                'description' => 'Ayam krispi renyah digeprek dengan sambal bawang cabai rawit pedas nampol.',
                'image_file' => 'ayam-geprek-sambal-bawang.png',
                'badge' => 'EXTRA PEDAS',
                'theme' => 'ayam_geprek',
            ],
            [
                'name' => 'Burger Sapi Keju (Cheeseburger)',
                'category_id' => $catMakanan->id,
                'cost_price' => 18000,
                'sell_price' => 32000,
                'stock' => 45,
                'unit' => 'Porsi',
                'barcode' => '899100100005',
                'description' => 'Beef patty juicy dengan keju cheddar leleh, selada segar, tomat, dan saus BBQ spesial.',
                'image_file' => 'cheeseburger-beef.png',
                'badge' => "CHEF'S SPECIAL",
                'theme' => 'burger',
            ],
            [
                'name' => 'Kwetiau Siram Sapi',
                'category_id' => $catMakanan->id,
                'cost_price' => 17000,
                'sell_price' => 32000,
                'stock' => 40,
                'unit' => 'Porsi',
                'barcode' => '899100100006',
                'description' => 'Kwetiau beras lembut disiram kuah kental gurih dengan irisan daging sapi empuk.',
                'image_file' => 'kwetiau-siram-sapi.png',
                'badge' => 'SPESIAL',
                'theme' => 'kwetiau',
            ],
            [
                'name' => 'Soto Betawi Daging',
                'category_id' => $catMakanan->id,
                'cost_price' => 20000,
                'sell_price' => 35000,
                'stock' => 50,
                'unit' => 'Porsi',
                'barcode' => '899100100007',
                'description' => 'Soto daging sapi empuk dengan kuah santan gurih berempah, emping, dan tomat segar.',
                'image_file' => 'soto-betawi-daging.png',
                'badge' => 'TRADISIONAL',
                'theme' => 'soto',
            ],
            [
                'name' => 'Spaghetti Bolognese',
                'category_id' => $catMakanan->id,
                'cost_price' => 15000,
                'sell_price' => 28000,
                'stock' => 50,
                'unit' => 'Porsi',
                'barcode' => '899100100008',
                'description' => 'Pasta al dente disajikan dengan saus daging cincang tomat segar dan keju parmesan.',
                'image_file' => 'spaghetti-bolognese.png',
                'badge' => 'ITALIAN',
                'theme' => 'spaghetti',
            ],

            // ==================== MINUMAN ====================
            [
                'name' => 'Es Teh Manis Melati',
                'category_id' => $catMinuman->id,
                'cost_price' => 2000,
                'sell_price' => 6000,
                'stock' => 150,
                'unit' => 'Gelas',
                'barcode' => '899100100009',
                'description' => 'Teh melati wangi dingin segar dengan tingkat kemanisan yang pas.',
                'image_file' => 'es-teh-manis.png',
                'badge' => 'SEGAR',
                'theme' => 'es_teh',
            ],
            [
                'name' => 'Es Kopi Susu Gula Aren',
                'category_id' => $catMinuman->id,
                'cost_price' => 8000,
                'sell_price' => 18000,
                'stock' => 120,
                'unit' => 'Cup',
                'barcode' => '899100100010',
                'description' => 'Espresso house blend dipadu susu creamy dan manis legit gula aren murni.',
                'image_file' => 'kopi-susu-gula-aren.png',
                'badge' => 'SIGNATURE',
                'theme' => 'kopi_susu',
            ],
            [
                'name' => 'Matcha Green Tea Latte',
                'category_id' => $catMinuman->id,
                'cost_price' => 10000,
                'sell_price' => 22000,
                'stock' => 90,
                'unit' => 'Cup',
                'barcode' => '899100100011',
                'description' => 'Bubuk matcha Jepang murni dengan susu segar berbusa lembut dan es segar.',
                'image_file' => 'matcha-latte.png',
                'badge' => 'PREMIUM',
                'theme' => 'matcha',
            ],
            [
                'name' => 'Jus Alpukat Kocok',
                'category_id' => $catMinuman->id,
                'cost_price' => 9000,
                'sell_price' => 20000,
                'stock' => 80,
                'unit' => 'Gelas',
                'barcode' => '899100100012',
                'description' => 'Alpukat mentega asli dikocok creamy dengan lelehan kental manis cokelat.',
                'image_file' => 'jus-alpukat.png',
                'badge' => 'FAVORITE',
                'theme' => 'jus_alpukat',
            ],
            [
                'name' => 'Lemon Tea Dingin',
                'category_id' => $catMinuman->id,
                'cost_price' => 4000,
                'sell_price' => 12000,
                'stock' => 110,
                'unit' => 'Gelas',
                'barcode' => '899100100013',
                'description' => 'Perpaduan teh hitam segar dan perasan jeruk lemon asli yang asam menyegarkan.',
                'image_file' => 'lemon-tea-dingin.png',
                'badge' => 'FRESH',
                'theme' => 'lemon_tea',
            ],
            [
                'name' => 'Hot / Iced Cappuccino',
                'category_id' => $catMinuman->id,
                'cost_price' => 9000,
                'sell_price' => 22000,
                'stock' => 95,
                'unit' => 'Cup',
                'barcode' => '899100100014',
                'description' => 'Espresso arabika dengan microfoam susu lembut berhias latte art dan taburan kakao.',
                'image_file' => 'cappuccino.png',
                'badge' => 'BARISTA PICK',
                'theme' => 'cappuccino',
            ],

            // ==================== SNACK & DESSERT ====================
            [
                'name' => 'Kentang Goreng (French Fries)',
                'category_id' => $catSnack->id,
                'cost_price' => 8000,
                'sell_price' => 18000,
                'stock' => 100,
                'unit' => 'Porsi',
                'barcode' => '899100100015',
                'description' => 'Kentang goreng crinkle-cut renyah dengan cocolan saus keju dan saus tomat/cabai.',
                'image_file' => 'kentang-goreng.png',
                'badge' => 'CRISPY',
                'theme' => 'kentang_goreng',
            ],
            [
                'name' => 'Roti Bakar Cokelat Keju',
                'category_id' => $catSnack->id,
                'cost_price' => 9000,
                'sell_price' => 20000,
                'stock' => 70,
                'unit' => 'Porsi',
                'barcode' => '899100100016',
                'description' => 'Roti brioche tebal dipanggang renyah dengan lelehan cokelat dan taburan keju cheddar.',
                'image_file' => 'roti-bakar-cokelat-keju.png',
                'badge' => 'SWEET',
                'theme' => 'roti_bakar',
            ],
            [
                'name' => 'Dimsum Ayam Mentai (4 pcs)',
                'category_id' => $catSnack->id,
                'cost_price' => 12000,
                'sell_price' => 24000,
                'stock' => 60,
                'unit' => 'Porsi',
                'barcode' => '899100100017',
                'description' => 'Siomay ayam lembut kukus dibalut saus mentai gurih beraroma smoky dan nori.',
                'image_file' => 'dimsum-ayam-mentai.png',
                'badge' => 'BEST SELLER',
                'theme' => 'dimsum',
            ],
            [
                'name' => 'Pisang Goreng Crispy Keju',
                'category_id' => $catSnack->id,
                'cost_price' => 7000,
                'sell_price' => 16000,
                'stock' => 75,
                'unit' => 'Porsi',
                'barcode' => '899100100018',
                'description' => 'Pisang raja manis berbalut tepung kremes renyah ditaburi keju dan susu kental.',
                'image_file' => 'pisang-goreng-crispy.png',
                'badge' => 'KREMES',
                'theme' => 'pisang_goreng',
            ],
            [
                'name' => 'Cireng Salju Bumbu Rujak',
                'category_id' => $catSnack->id,
                'cost_price' => 6000,
                'sell_price' => 15000,
                'stock' => 85,
                'unit' => 'Porsi',
                'barcode' => '899100100019',
                'description' => 'Cireng renyah di luar kenyal di dalam disajikan dengan sambal rujak gula aren pedas.',
                'image_file' => 'cireng-rujak.png',
                'badge' => 'TRADISIONAL',
                'theme' => 'cireng',
            ],
        ];

        foreach ($menuItems as $item) {
            // 1. Gambar Produk
            $imageRelPath = 'products/' . $item['image_file'];
            $imageFullPath = $targetDir . '/' . $item['image_file'];

            $this->generateFoodImage($imageFullPath, $item['name'], $item['theme'], $item['badge']);

            // 2. Simpan / Perbarui Produk di Database
            // Cek produk dengan nama sama atau barcode sama
            $product = Product::where('name', $item['name'])
                ->orWhere('barcode', $item['barcode'])
                ->first();

            if (!$product) {
                // Cari apakah ada produk dummy lama yang cocok (mis. 'Nasi Goreng' -> update jadi 'Nasi Goreng Spesial')
                if (str_contains($item['name'], 'Nasi Goreng')) {
                    $product = Product::where('name', 'Nasi Goreng')->first();
                } elseif (str_contains($item['name'], 'Mie Goreng')) {
                    $product = Product::where('name', 'Mie Goreng')->first();
                } elseif (str_contains($item['name'], 'Es Teh')) {
                    $product = Product::where('name', 'Es Teh Manis')->first();
                }
            }

            $sku = $product?->sku ?: ('FNB-' . strtoupper(Str::random(6)));

            if ($product) {
                $product->update([
                    'name' => $item['name'],
                    'category_id' => $item['category_id'],
                    'supplier_id' => $supplier->id,
                    'cost_price' => $item['cost_price'],
                    'sell_price' => $item['sell_price'],
                    'stock' => max($product->stock, $item['stock']),
                    'unit' => $item['unit'],
                    'barcode' => $item['barcode'],
                    'description' => $item['description'],
                    'image' => $imageRelPath,
                    'type' => 'barang',
                    'track_stock' => $item['track_stock'] ?? true,
                    'is_taxable' => true,
                    'is_active' => true,
                ]);
            } else {
                Product::create([
                    'name' => $item['name'],
                    'sku' => $sku,
                    'barcode' => $item['barcode'],
                    'category_id' => $item['category_id'],
                    'supplier_id' => $supplier->id,
                    'unit' => $item['unit'],
                    'cost_price' => $item['cost_price'],
                    'sell_price' => $item['sell_price'],
                    'stock' => $item['stock'],
                    'track_stock' => $item['track_stock'] ?? true,
                    'min_stock' => 5,
                    'is_taxable' => true,
                    'image' => $imageRelPath,
                    'description' => $item['description'],
                    'type' => 'barang',
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Gambar kartu makanan/minuman beresolusi 500x360 px dengan GD
     */
    private function generateFoodImage(string $filePath, string $name, string $theme, string $badge): void
    {
        $w = 500;
        $h = 360;
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);
        imagesavealpha($im, true);

        // Gradient & Warna Berdasarkan Tema
        $themeColors = $this->getThemeColors($theme);
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / $h;
            $r = (int) ($themeColors['top'][0] + ($themeColors['bottom'][0] - $themeColors['top'][0]) * $ratio);
            $g = (int) ($themeColors['top'][1] + ($themeColors['bottom'][1] - $themeColors['top'][1]) * $ratio);
            $b = (int) ($themeColors['top'][2] + ($themeColors['bottom'][2] - $themeColors['top'][2]) * $ratio);
            $lineCol = imagecolorallocate($im, $r, $g, $b);
            imageline($im, 0, $y, $w, $y, $lineCol);
        }

        // Ambient Studio Glow
        $glow = imagecolorallocatealpha($im, 255, 255, 255, 115);
        imagefilledellipse($im, $w / 2, $h / 2, 290, 290, $glow);

        // Gambar Komponen Visual Ilustrasi Kuliner
        $this->drawFoodGraphic($im, $w, $h, $theme);

        // Badge Top Right
        $badgeBg = imagecolorallocatealpha($im, $themeColors['badge_bg'][0], $themeColors['badge_bg'][1], $themeColors['badge_bg'][2], 10);
        $badgeW = 125;
        $badgeH = 28;
        imagefilledrectangle($im, $w - $badgeW - 15, 15, $w - 15, 15 + $badgeH, $badgeBg);

        $badgeTextCol = imagecolorallocate($im, 255, 255, 255);
        if (file_exists($this->fontBold)) {
            $bbox = imagettfbbox(9, 0, $this->fontBold, $badge);
            $textW = abs($bbox[4] - $bbox[0]);
            $textX = $w - 15 - ($badgeW / 2) - ($textW / 2);
            imagettftext($im, 9, 0, (int) $textX, 33, $badgeTextCol, $this->fontBold, $badge);
        }

        imagepng($im, $filePath);
    }

    private function getThemeColors(string $theme): array
    {
        return match ($theme) {
            'nasi_goreng' => [
                'top' => [153, 27, 27],      // Deep Red
                'bottom' => [234, 88, 12],   // Warm Orange
                'badge_bg' => [185, 28, 28],
            ],
            'mie_goreng' => [
                'top' => [124, 45, 18],     // Deep Rust
                'bottom' => [217, 119, 6],   // Amber
                'badge_bg' => [180, 83, 9],
            ],
            'ayam_bakar' => [
                'top' => [69, 26, 3],       // Dark Mahogany
                'bottom' => [180, 83, 9],    // Honey Bronze
                'badge_bg' => [146, 64, 14],
            ],
            'ayam_geprek' => [
                'top' => [185, 28, 28],     // Fiery Red
                'bottom' => [249, 115, 22],  // Bright Orange
                'badge_bg' => [220, 38, 38],
            ],
            'burger' => [
                'top' => [66, 32, 6],       // Dark Cafe
                'bottom' => [217, 119, 6],   // Golden Brown
                'badge_bg' => [234, 88, 12],
            ],
            'kwetiau' => [
                'top' => [120, 53, 15],     // Soy Amber
                'bottom' => [202, 138, 4],   // Wok Golden
                'badge_bg' => [161, 98, 7],
            ],
            'soto' => [
                'top' => [136, 19, 55],     // Rich Betawi Terracotta
                'bottom' => [217, 119, 6],   // Golden Santan
                'badge_bg' => [190, 18, 60],
            ],
            'spaghetti' => [
                'top' => [159, 18, 57],     // Rich Bolognese Red
                'bottom' => [225, 29, 72],   // Bright Tomato
                'badge_bg' => [190, 24, 93],
            ],
            'es_teh' => [
                'top' => [30, 58, 138],     // Chilled Deep Blue
                'bottom' => [217, 119, 6],   // Amber Tea
                'badge_bg' => [2, 132, 199],
            ],
            'kopi_susu' => [
                'top' => [28, 25, 23],      // Dark Espresso
                'bottom' => [180, 83, 9],    // Caramel Gula Aren
                'badge_bg' => [120, 53, 15],
            ],
            'matcha' => [
                'top' => [6, 78, 59],       // Dark Matcha
                'bottom' => [16, 185, 129],  // Fresh Mint
                'badge_bg' => [5, 150, 105],
            ],
            'jus_alpukat' => [
                'top' => [20, 83, 45],      // Forest Avocado
                'bottom' => [101, 163, 13],  // Creamy Lime
                'badge_bg' => [22, 101, 52],
            ],
            'lemon_tea' => [
                'top' => [161, 98, 7],      // Citrus Gold
                'bottom' => [234, 179, 8],   // Vibrant Lemon
                'badge_bg' => [202, 138, 4],
            ],
            'cappuccino' => [
                'top' => [41, 37, 36],      // Rich Roast
                'bottom' => [168, 85, 247],  // Cafe Violet / Mocha
                'badge_bg' => [120, 53, 15],
            ],
            'kentang_goreng' => [
                'top' => [153, 27, 27],     // Diner Red
                'bottom' => [234, 179, 8],   // Crispy Potato Gold
                'badge_bg' => [185, 28, 28],
            ],
            'roti_bakar' => [
                'top' => [59, 7, 100],      // Choco Plum
                'bottom' => [217, 119, 6],   // Toasted Golden
                'badge_bg' => [126, 34, 206],
            ],
            'dimsum' => [
                'top' => [88, 28, 135],     // Steamer Violet
                'bottom' => [234, 88, 12],   // Mentai Orange
                'badge_bg' => [147, 51, 234],
            ],
            'pisang_goreng' => [
                'top' => [133, 77, 14],     // Warm Honey
                'bottom' => [245, 158, 11],  // Crunchy Golden
                'badge_bg' => [180, 83, 9],
            ],
            'cireng' => [
                'top' => [136, 19, 55],     // Rujak Ruby
                'bottom' => [225, 29, 72],   // Chili Red
                'badge_bg' => [159, 18, 57],
            ],
            default => [
                'top' => [30, 41, 59],
                'bottom' => [71, 85, 105],
                'badge_bg' => [51, 65, 85],
            ],
        };
    }

    private function drawFoodGraphic($im, int $w, int $h, string $theme): void
    {
        $cx = $w / 2;
        $cy = $h / 2;

        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 95);
        $plateWhite = imagecolorallocate($im, 248, 250, 252);
        $plateRim = imagecolorallocate($im, 226, 232, 240);

        switch ($theme) {
            case 'nasi_goreng':
                // Plate
                imagefilledellipse($im, $cx + 5, $cy + 45, 260, 170, $shadow);
                imagefilledellipse($im, $cx, $cy + 30, 250, 160, $plateWhite);
                imagefilledellipse($im, $cx, $cy + 30, 210, 130, $plateRim);
                // Rice
                $rice = imagecolorallocate($im, 217, 119, 6);
                imagefilledellipse($im, $cx, $cy + 25, 160, 100, $rice);
                // Fried Egg White
                $eggWhite = imagecolorallocate($im, 255, 255, 255);
                imagefilledellipse($im, $cx - 15, $cy + 15, 75, 60, $eggWhite);
                // Golden Yolk
                $yolk = imagecolorallocate($im, 245, 158, 11);
                imagefilledellipse($im, $cx - 15, $cy + 15, 38, 38, $yolk);
                // Cucumber slices
                $cucGreen = imagecolorallocate($im, 34, 197, 94);
                $cucLight = imagecolorallocate($im, 187, 247, 208);
                imagefilledellipse($im, $cx + 60, $cy + 15, 35, 20, $cucGreen);
                imagefilledellipse($im, $cx + 60, $cy + 15, 25, 14, $cucLight);
                imagefilledellipse($im, $cx + 70, $cy + 32, 35, 20, $cucGreen);
                imagefilledellipse($im, $cx + 70, $cy + 32, 25, 14, $cucLight);
                // Chili flakes
                $chili = imagecolorallocate($im, 239, 68, 68);
                imagefilledellipse($im, $cx + 25, $cy + 10, 8, 4, $chili);
                imagefilledellipse($im, $cx + 10, $cy + 45, 10, 5, $chili);
                break;

            case 'mie_goreng':
                // Ceramic Bowl
                imagefilledellipse($im, $cx + 5, $cy + 45, 250, 150, $shadow);
                $bowlDark = imagecolorallocate($im, 30, 41, 59);
                imagefilledellipse($im, $cx, $cy + 30, 240, 140, $bowlDark);
                // Noodles
                $noodle = imagecolorallocate($im, 245, 158, 11);
                imagefilledellipse($im, $cx, $cy + 25, 200, 110, $noodle);
                // Shrimp (Pink Arcs)
                $shrimp = imagecolorallocate($im, 244, 63, 94);
                imagefilledarc($im, $cx - 30, $cy + 10, 50, 30, 30, 210, $shrimp, IMG_ARC_PIE);
                imagefilledarc($im, $cx + 35, $cy + 25, 50, 30, 150, 330, $shrimp, IMG_ARC_PIE);
                // Greens
                $green = imagecolorallocate($im, 22, 163, 74);
                imagefilledellipse($im, $cx + 10, $cy + 5, 40, 18, $green);
                imagefilledellipse($im, $cx - 50, $cy + 35, 35, 15, $green);
                break;

            case 'ayam_bakar':
                // Wooden / Clay Platter
                imagefilledellipse($im, $cx + 5, $cy + 45, 260, 160, $shadow);
                $bananaLeaf = imagecolorallocate($im, 22, 101, 52);
                imagefilledellipse($im, $cx, $cy + 30, 250, 150, $bananaLeaf);
                // Roasted Chicken Thigh
                $chicken = imagecolorallocate($im, 120, 53, 15);
                imagefilledellipse($im, $cx - 15, $cy + 25, 150, 95, $chicken);
                $caramel = imagecolorallocate($im, 69, 26, 3);
                imagefilledellipse($im, $cx - 15, $cy + 25, 120, 75, $caramel);
                // Grill marks
                $char = imagecolorallocate($im, 30, 15, 5);
                imagesetthickness($im, 3);
                imageline($im, $cx - 50, $cy + 5, $cx - 10, $cy + 45, $char);
                imageline($im, $cx - 30, $cy + 0, $cx + 10, $cy + 40, $char);
                imageline($im, $cx - 10, $cy - 5, $cx + 30, $cy + 35, $char);
                imagesetthickness($im, 1);
                // Sambal bowl
                $sambalBowl = imagecolorallocate($im, 255, 255, 255);
                $sambalRed = imagecolorallocate($im, 220, 38, 38);
                imagefilledellipse($im, $cx + 65, $cy + 15, 50, 35, $sambalBowl);
                imagefilledellipse($im, $cx + 65, $cy + 15, 40, 26, $sambalRed);
                break;

            case 'ayam_geprek':
                // Platter
                imagefilledellipse($im, $cx + 5, $cy + 45, 250, 150, $shadow);
                imagefilledellipse($im, $cx, $cy + 30, 240, 140, $plateWhite);
                // Crispy Chicken Fillet (golden batter)
                $batter = imagecolorallocate($im, 217, 119, 6);
                imagefilledellipse($im, $cx, $cy + 25, 170, 95, $batter);
                $batterLight = imagecolorallocate($im, 245, 158, 11);
                imagefilledellipse($im, $cx - 10, $cy + 20, 140, 75, $batterLight);
                // Generous Sambal Bawang Red on Top
                $sambal = imagecolorallocate($im, 220, 38, 38);
                imagefilledellipse($im, $cx, $cy + 20, 110, 55, $sambal);
                // Chili seeds (yellow specks)
                $seed = imagecolorallocate($im, 254, 240, 138);
                imagefilledellipse($im, $cx - 20, $cy + 15, 6, 4, $seed);
                imagefilledellipse($im, $cx + 15, $cy + 10, 6, 4, $seed);
                imagefilledellipse($im, $cx + 2, $cy + 30, 6, 4, $seed);
                imagefilledellipse($im, $cx - 35, $cy + 25, 6, 4, $seed);
                break;

            case 'burger':
                // Burger illustration (sesame bun, lettuce, patty, dripping cheese, tomato)
                imagefilledellipse($im, $cx, $cy + 75, 220, 45, $shadow);
                $board = imagecolorallocate($im, 146, 64, 14);
                imagefilledellipse($im, $cx, $cy + 65, 240, 50, $board);
                // Bottom bun
                $bun = imagecolorallocate($im, 217, 119, 6);
                imagefilledarc($im, $cx, $cy + 45, 170, 45, 0, 180, $bun, IMG_ARC_PIE);
                // Lettuce
                $lettuce = imagecolorallocate($im, 34, 197, 94);
                imagefilledellipse($im, $cx, $cy + 32, 180, 22, $lettuce);
                // Patty
                $patty = imagecolorallocate($im, 69, 26, 3);
                imagefilledrectangle($im, $cx - 80, $cy + 8, $cx + 80, $cy + 26, $patty);
                // Melted Cheese
                $cheese = imagecolorallocate($im, 245, 158, 11);
                imagefilledpolygon($im, [
                    $cx - 85, $cy + 10,
                    $cx + 85, $cy + 10,
                    $cx + 70, $cy + 26,
                    $cx + 35, $cy + 34,
                    $cx + 15, $cy + 22,
                    $cx - 15, $cy + 38,
                    $cx - 40, $cy + 20,
                    $cx - 75, $cy + 28,
                ], $cheese);
                // Tomato
                $tomato = imagecolorallocate($im, 239, 68, 68);
                imagefilledrectangle($im, $cx - 70, $cy - 6, $cx + 70, $cy + 8, $tomato);
                // Top Bun
                imagefilledarc($im, $cx, $cy - 5, 170, 115, 180, 360, $bun, IMG_ARC_PIE);
                // Sesame seeds
                $sesame = imagecolorallocate($im, 254, 243, 199);
                $seeds = [[-40, -35], [-15, -45], [15, -42], [40, -32], [-20, -20], [5, -24], [25, -18]];
                foreach ($seeds as $s) {
                    imagefilledellipse($im, $cx + $s[0], $cy + $s[1], 5, 3, $sesame);
                }
                break;

            case 'kwetiau':
                imagefilledellipse($im, $cx + 5, $cy + 45, 250, 150, $shadow);
                imagefilledellipse($im, $cx, $cy + 30, 240, 140, $plateWhite);
                // White flat noodles
                $kwe = imagecolorallocate($im, 254, 243, 199);
                imagefilledellipse($im, $cx, $cy + 25, 180, 100, $kwe);
                // Thick brown gravy
                $gravy = imagecolorallocatealpha($im, 180, 83, 9, 30);
                imagefilledellipse($im, $cx, $cy + 25, 160, 85, $gravy);
                // Beef slices
                $beef = imagecolorallocate($im, 88, 28, 135);
                imagefilledellipse($im, $cx - 30, $cy + 15, 50, 25, $beef);
                imagefilledellipse($im, $cx + 25, $cy + 25, 45, 22, $beef);
                // Green veggies
                $veg = imagecolorallocate($im, 22, 163, 74);
                imagefilledellipse($im, $cx + 5, $cy + 5, 45, 15, $veg);
                break;

            case 'soto':
                imagefilledellipse($im, $cx + 5, $cy + 45, 250, 150, $shadow);
                $sotoBowl = imagecolorallocate($im, 180, 83, 9);
                imagefilledellipse($im, $cx, $cy + 30, 240, 140, $sotoBowl);
                // Creamy Santan Soup
                $santan = imagecolorallocate($im, 254, 249, 195);
                imagefilledellipse($im, $cx, $cy + 25, 200, 110, $santan);
                // Beef cubes
                $beef = imagecolorallocate($im, 69, 26, 3);
                imagefilledrectangle($im, $cx - 40, $cy + 10, $cx - 15, $cy + 30, $beef);
                imagefilledrectangle($im, $cx + 15, $cy + 15, $cx + 40, $cy + 35, $beef);
                // Emping chips (pale yellow discs)
                $emping = imagecolorallocate($im, 253, 224, 71);
                imagefilledellipse($im, $cx - 10, $cy + 5, 40, 22, $emping);
                // Tomato
                $tom = imagecolorallocate($im, 239, 68, 68);
                imagefilledarc($im, $cx + 35, $cy + 5, 35, 25, 0, 180, $tom, IMG_ARC_PIE);
                break;

            case 'spaghetti':
                imagefilledellipse($im, $cx + 5, $cy + 45, 250, 150, $shadow);
                imagefilledellipse($im, $cx, $cy + 30, 240, 140, $plateWhite);
                // Spaghetti nest
                $pasta = imagecolorallocate($im, 253, 224, 71);
                imagefilledellipse($im, $cx, $cy + 25, 170, 95, $pasta);
                // Meat Sauce
                $bolognese = imagecolorallocate($im, 185, 28, 28);
                imagefilledellipse($im, $cx, $cy + 20, 120, 65, $bolognese);
                // Basil leaf
                $basil = imagecolorallocate($im, 22, 163, 74);
                imagefilledellipse($im, $cx, $cy + 15, 25, 12, $basil);
                break;

            case 'es_teh':
            case 'lemon_tea':
                // Glass shadow
                imagefilledellipse($im, $cx, $cy + 105, 120, 35, $shadow);
                // Tall Glass Body
                $teaCol = $theme === 'lemon_tea' ? imagecolorallocate($im, 217, 119, 6) : imagecolorallocate($im, 180, 83, 9);
                imagefilledrectangle($im, $cx - 50, $cy - 60, $cx + 50, $cy + 90, $teaCol);
                imagefilledellipse($im, $cx, $cy + 90, 100, 30, $teaCol);
                imagefilledellipse($im, $cx, $cy - 60, 100, 25, $teaCol);
                // Ice cubes
                $ice = imagecolorallocatealpha($im, 255, 255, 255, 60);
                imagefilledrectangle($im, $cx - 30, $cy - 20, $cx - 5, $cy + 10, $ice);
                imagefilledrectangle($im, $cx + 5, $cy - 35, $cx + 32, $cy - 5, $ice);
                imagefilledrectangle($im, $cx - 15, $cy + 25, $cx + 15, $cy + 55, $ice);
                // Lemon slice on rim
                $lemon = imagecolorallocate($im, 250, 204, 21);
                imagefilledellipse($im, $cx + 45, $cy - 65, 45, 45, $lemon);
                $lemonInner = imagecolorallocate($im, 254, 240, 138);
                imagefilledellipse($im, $cx + 45, $cy - 65, 35, 35, $lemonInner);
                // Straw
                $straw = imagecolorallocate($im, 239, 68, 68);
                imagesetthickness($im, 6);
                imageline($im, $cx - 15, $cy - 95, $cx + 15, $cy + 80, $straw);
                imagesetthickness($im, 1);
                break;

            case 'kopi_susu':
                imagefilledellipse($im, $cx, $cy + 105, 120, 35, $shadow);
                // Glass - Layer 1: Dark Palm Sugar at bottom
                $gulaAren = imagecolorallocate($im, 69, 26, 3);
                imagefilledrectangle($im, $cx - 50, $cy + 40, $cx + 50, $cy + 90, $gulaAren);
                imagefilledellipse($im, $cx, $cy + 90, 100, 30, $gulaAren);
                // Layer 2: Creamy Milk in middle
                $milk = imagecolorallocate($im, 254, 243, 199);
                imagefilledrectangle($im, $cx - 50, $cy - 10, $cx + 50, $cy + 40, $milk);
                // Layer 3: Rich Espresso top
                $espresso = imagecolorallocate($im, 120, 53, 15);
                imagefilledrectangle($im, $cx - 50, $cy - 60, $cx + 50, $cy - 10, $espresso);
                imagefilledellipse($im, $cx, $cy - 60, 100, 25, $espresso);
                // Straw
                $straw = imagecolorallocate($im, 16, 185, 129);
                imagesetthickness($im, 6);
                imageline($im, $cx + 20, $cy - 95, $cx - 10, $cy + 75, $straw);
                imagesetthickness($im, 1);
                break;

            case 'matcha':
                imagefilledellipse($im, $cx, $cy + 105, 120, 35, $shadow);
                // Vibrant green matcha body
                $matchaCol = imagecolorallocate($im, 16, 185, 129);
                imagefilledrectangle($im, $cx - 50, $cy - 30, $cx + 50, $cy + 90, $matchaCol);
                imagefilledellipse($im, $cx, $cy + 90, 100, 30, $matchaCol);
                // Silky milk foam layer on top
                $foam = imagecolorallocate($im, 255, 255, 255);
                imagefilledrectangle($im, $cx - 50, $cy - 60, $cx + 50, $cy - 30, $foam);
                imagefilledellipse($im, $cx, $cy - 60, 100, 25, $foam);
                // Matcha powder dust
                $dust = imagecolorallocate($im, 5, 150, 105);
                imagefilledellipse($im, $cx - 10, $cy - 60, 25, 8, $dust);
                break;

            case 'jus_alpukat':
                imagefilledellipse($im, $cx, $cy + 105, 120, 35, $shadow);
                // Avocado shake body
                $avo = imagecolorallocate($im, 132, 204, 22);
                imagefilledrectangle($im, $cx - 50, $cy - 50, $cx + 50, $cy + 90, $avo);
                imagefilledellipse($im, $cx, $cy + 90, 100, 30, $avo);
                imagefilledellipse($im, $cx, $cy - 50, 100, 25, $avo);
                // Chocolate drizzle swirl on glass
                $choco = imagecolorallocate($im, 69, 26, 3);
                imagesetthickness($im, 6);
                imageline($im, $cx - 40, $cy - 30, $cx + 35, $cy + 20, $choco);
                imageline($im, $cx + 30, $cy + 20, $cx - 30, $cy + 65, $choco);
                imagesetthickness($im, 1);
                break;

            case 'cappuccino':
                // Saucer
                imagefilledellipse($im, $cx, $cy + 65, 220, 50, $shadow);
                imagefilledellipse($im, $cx, $cy + 55, 210, 45, $plateWhite);
                // Cup
                $cup = imagecolorallocate($im, 241, 245, 249);
                imagefilledarc($im, $cx, $cy + 10, 160, 120, 0, 180, $cup, IMG_ARC_PIE);
                // Coffee surface
                $coffee = imagecolorallocate($im, 120, 53, 15);
                imagefilledellipse($im, $cx, $cy + 10, 150, 50, $coffee);
                // Latte art heart foam
                $latteWhite = imagecolorallocate($im, 255, 255, 255);
                imagefilledellipse($im, $cx - 15, $cy + 8, 30, 25, $latteWhite);
                imagefilledellipse($im, $cx + 15, $cy + 8, 30, 25, $latteWhite);
                imagefilledpolygon($im, [$cx - 28, $cy + 12, $cx + 28, $cy + 12, $cx, $cy + 30], $latteWhite);
                break;

            case 'kentang_goreng':
                imagefilledellipse($im, $cx, $cy + 85, 200, 40, $shadow);
                // Red fry container box
                $box = imagecolorallocate($im, 220, 38, 38);
                imagefilledpolygon($im, [$cx - 55, $cy + 10, $cx + 55, $cy + 10, $cx + 40, $cy + 85, $cx - 40, $cy + 85], $box);
                // Crispy fry batons
                $fry = imagecolorallocate($im, 245, 158, 11);
                $fryDark = imagecolorallocate($im, 217, 119, 6);
                $fryList = [
                    [-35, -40, -30, 25],
                    [-20, -55, -15, 20],
                    [-5, -60, -2, 22],
                    [10, -50, 12, 24],
                    [25, -45, 28, 25],
                    [-12, -35, -10, 25],
                    [8, -38, 10, 22],
                ];
                imagesetthickness($im, 10);
                foreach ($fryList as $f) {
                    imageline($im, $cx + $f[0], $cy + $f[1], $cx + $f[2], $cy + $f[3], $fry);
                }
                imagesetthickness($im, 1);
                // Cheese dip cup
                $dipCup = imagecolorallocate($im, 255, 255, 255);
                $dipCheese = imagecolorallocate($im, 250, 204, 21);
                imagefilledellipse($im, $cx + 65, $cy + 65, 45, 30, $dipCup);
                imagefilledellipse($im, $cx + 65, $cy + 65, 35, 20, $dipCheese);
                break;

            case 'roti_bakar':
                imagefilledellipse($im, $cx, $cy + 75, 220, 45, $shadow);
                imagefilledellipse($im, $cx, $cy + 65, 240, 50, $plateWhite);
                // Stack of toast triangles
                $toast = imagecolorallocate($im, 245, 158, 11);
                $toastDark = imagecolorallocate($im, 180, 83, 9);
                imagefilledpolygon($im, [$cx - 60, $cy + 40, $cx + 40, $cy - 20, $cx + 60, $cy + 40], $toast);
                // Grill marks
                imagesetthickness($im, 3);
                imageline($im, $cx - 30, $cy + 25, $cx + 10, $cy - 5, $toastDark);
                imageline($im, $cx - 10, $cy + 35, $cx + 30, $cy + 5, $toastDark);
                imagesetthickness($im, 1);
                // Chocolate drizzle & Cheese
                $choco = imagecolorallocate($im, 69, 26, 3);
                imagesetthickness($im, 4);
                imageline($im, $cx - 20, $cy + 10, $cx + 30, $cy + 25, $choco);
                imageline($im, $cx - 10, $cy + 20, $cx + 40, $cy + 38, $choco);
                imagesetthickness($im, 1);
                $cheeseGrated = imagecolorallocate($im, 254, 240, 138);
                imagefilledrectangle($im, $cx - 15, $cy + 5, $cx + 25, $cy + 15, $cheeseGrated);
                break;

            case 'dimsum':
                // Bamboo Steamer Basket
                imagefilledellipse($im, $cx, $cy + 65, 230, 55, $shadow);
                $steamer = imagecolorallocate($im, 217, 119, 6);
                imagefilledellipse($im, $cx, $cy + 50, 220, 90, $steamer);
                $steamerInner = imagecolorallocate($im, 254, 243, 199);
                imagefilledellipse($im, $cx, $cy + 45, 195, 75, $steamerInner);
                // 3 Dimsum Dumplings
                $dimWhite = imagecolorallocate($im, 255, 255, 255);
                $mentai = imagecolorallocate($im, 249, 115, 22);
                $pos = [[-45, 40], [0, 25], [45, 40]];
                foreach ($pos as $p) {
                    imagefilledellipse($im, $cx + $p[0], $cy + $p[1], 48, 40, $dimWhite);
                    imagefilledellipse($im, $cx + $p[0], $cy + $p[1] - 4, 32, 22, $mentai);
                }
                break;

            case 'pisang_goreng':
                imagefilledellipse($im, $cx, $cy + 65, 230, 55, $shadow);
                imagefilledellipse($im, $cx, $cy + 50, 220, 80, $plateWhite);
                // Golden banana fritters
                $crisp = imagecolorallocate($im, 245, 158, 11);
                imagefilledellipse($im, $cx - 35, $cy + 45, 80, 40, $crisp);
                imagefilledellipse($im, $cx + 30, $cy + 40, 85, 42, $crisp);
                // Grated cheese toppings
                $cheese = imagecolorallocate($im, 254, 240, 138);
                imagefilledrectangle($im, $cx - 40, $cy + 30, $cx + 40, $cy + 42, $cheese);
                break;

            case 'cireng':
                imagefilledellipse($im, $cx, $cy + 65, 230, 55, $shadow);
                imagefilledellipse($im, $cx, $cy + 50, 220, 80, $plateWhite);
                // White chewy cireng discs
                $cirengCol = imagecolorallocate($im, 248, 250, 252);
                $cirengRim = imagecolorallocate($im, 203, 213, 225);
                imagefilledellipse($im, $cx - 40, $cy + 45, 55, 35, $cirengRim);
                imagefilledellipse($im, $cx - 40, $cy + 43, 48, 28, $cirengCol);
                imagefilledellipse($im, $cx + 10, $cy + 52, 55, 35, $cirengRim);
                imagefilledellipse($im, $cx + 10, $cy + 50, 48, 28, $cirengCol);
                // Dark red rujak sauce bowl
                $rujakBowl = imagecolorallocate($im, 255, 255, 255);
                $rujakSauce = imagecolorallocate($im, 136, 19, 55);
                imagefilledellipse($im, $cx + 55, $cy + 30, 45, 30, $rujakBowl);
                imagefilledellipse($im, $cx + 55, $cy + 30, 35, 20, $rujakSauce);
                break;
        }
    }
}
