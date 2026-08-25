<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pencatatan pembelian barang dagangan dan hutang ke pemasok.
 *
 * Selama ini stok bertambah tanpa jejak uang sama sekali: menambah produk baru atau
 * menyesuaikan stok langsung menaikkan angka di rak, tanpa mencatat uangnya keluar maupun
 * hutangnya. Barang muncul dari udara.
 *
 * Akibatnya bukan cuma pembukuan yang tidak rapi - NERACA TIDAK AKAN PERNAH SEIMBANG.
 * Sisi aset bertambah (persediaan) tanpa ada penambahan di sisi kewajiban atau modal, dan
 * selisihnya tumbuh terus setiap kali toko kulakan.
 *
 * Tiga tabel:
 *   purchases         - satu nota pembelian
 *   purchase_items    - barang apa saja di nota itu
 *   purchase_payments - cicilan pembayaran hutang, supaya bisa dilunasi bertahap
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // Nomor nota dari pemasok - dipakai mencocokkan saat menagih atau protes.
            $table->string('supplier_invoice_no')->nullable();
            $table->date('purchase_date');

            $table->decimal('subtotal', 15, 2)->default(0);
            // Ongkir, bongkar muat, retribusi - dibagi rata ke barang sesuai nilainya supaya
            // harga modal per barang mencerminkan biaya sampai di toko, bukan cuma harga beli.
            $table->decimal('other_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->decimal('paid_amount', 15, 2)->default(0);
            // Disimpan, bukan dihitung tiap kali: neraca membacanya berkali-kali dan
            // menjumlahkan pembayaran di setiap pembacaan akan membebani laporan tanpa perlu.
            $table->decimal('sisa_hutang', 15, 2)->default(0);
            $table->date('due_date')->nullable();

            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('purchase_date');
            $table->index('supplier_id');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Disalin, bukan cuma direlasikan: nota pembelian harus tetap terbaca utuh
            // walaupun produknya kemudian dihapus dari master.
            $table->string('product_name');

            $table->decimal('qty', 15, 4);
            $table->string('unit_label')->nullable();
            $table->decimal('unit_conversion', 15, 4)->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();

            $table->index('purchase_id');
            $table->index('product_id');
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('purchase_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
