<?php

/**
 * Pesan validasi bahasa Indonesia.
 *
 * APP_LOCALE sudah 'id' sejak awal, tapi folder lang/ belum pernah ada sehingga semua
 * pesan error tampil dalam bahasa Inggris ke kasir. Key yang tidak ada di sini otomatis
 * jatuh ke fallback_locale (en), jadi file ini cukup memuat aturan yang benar-benar
 * dipakai aplikasi.
 */
return [

    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi ketika :other bernilai :value.',
    'required_with' => ':attribute wajib diisi ketika ada :values.',

    'string' => ':attribute harus berupa teks.',
    'numeric' => ':attribute harus berupa angka.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'array' => ':attribute harus berupa daftar.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
    'file' => ':attribute harus berupa file.',
    'image' => ':attribute harus berupa file gambar.',

    'in' => 'Pilihan :attribute tidak valid.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'unique' => ':attribute sudah dipakai. Gunakan nilai lain.',

    'mimes' => ':attribute harus berformat: :values.',
    'mimetypes' => ':attribute harus berformat: :values.',

    // Pesan bawaan Laravel untuk dimensions ("has invalid image dimensions") tidak
    // memberi tahu apa yang harus dilakukan. Gambar beresolusi ekstrem inilah yang dulu
    // membuat server mati dengan HTTP 500, jadi pesannya dibuat sejelas mungkin.
    'dimensions' => 'Resolusi :attribute terlalu besar (maksimal 8000 x 8000 piksel). Perkecil dulu gambarnya, lalu unggah ulang.',

    'max' => [
        'array' => ':attribute maksimal :max item.',
        'file' => 'Ukuran :attribute maksimal :max KB.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],

    'min' => [
        'array' => ':attribute minimal :min item.',
        'file' => 'Ukuran :attribute minimal :min KB.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],

    'between' => [
        'array' => ':attribute harus antara :min sampai :max item.',
        'file' => 'Ukuran :attribute harus antara :min sampai :max KB.',
        'numeric' => ':attribute harus antara :min sampai :max.',
        'string' => ':attribute harus antara :min sampai :max karakter.',
    ],

    'custom' => [],

    /**
     * Nama field dalam bahasa yang dimengerti pengguna kasir, bukan nama kolom database.
     */
    'attributes' => [
        'name' => 'Nama',
        'type' => 'Tipe',
        'sku' => 'SKU',
        'barcode' => 'Barcode',
        'category_id' => 'Kategori',
        'supplier_id' => 'Supplier',
        'customer_id' => 'Pelanggan',
        'unit' => 'Satuan',
        'cost_price' => 'Harga Modal',
        'sell_price' => 'Harga Jual',
        'wholesale_price' => 'Harga Grosir',
        'wholesale_min_qty' => 'Min. Qty Grosir',
        'stock' => 'Stok',
        'min_stock' => 'Stok Minimum',
        'is_active' => 'Status Aktif',
        'is_taxable' => 'Kena Pajak',
        'description' => 'Deskripsi',
        'image' => 'Gambar produk',
        'logo' => 'Logo toko',
        'units' => 'Satuan tambahan',
        'units.*.unit_id' => 'Satuan',
        'units.*.conversion' => 'Konversi satuan',
        'units.*.price' => 'Harga satuan',
        'units.*.wholesale_price' => 'Harga grosir satuan',
        'units.*.wholesale_min_qty' => 'Min. qty grosir satuan',
        'items' => 'Daftar item',
        'items.*.product_id' => 'Produk',
        'items.*.qty' => 'Qty',
        'add_items' => 'Item tambahan',
        'add_items.*.product_id' => 'Produk',
        'add_items.*.qty' => 'Qty',
        'qty' => 'Qty',
        'discount' => 'Diskon',
        'paid_amount' => 'Jumlah Bayar',
        'payment_method' => 'Metode Pembayaran',
        'due_date' => 'Tanggal Jatuh Tempo',
        'note' => 'Catatan',
        'amount' => 'Jumlah',
        'category' => 'Kategori',
        'reason' => 'Alasan',
        'additional_payment' => 'Pembayaran Tambahan',
        'username' => 'Username',
        'password' => 'Password',
        'role_id' => 'Role',
        'phone' => 'No. HP',
        'email' => 'Email',
        'address' => 'Alamat',
        'footer_note' => 'Catatan Kaki',
        'header_note' => 'Catatan Atas',
        'permissions' => 'Hak Akses',
        'profile' => 'Profil Kertas',
        'custom_width' => 'Lebar Kertas',
        'margin' => 'Margin',
        'font_size' => 'Ukuran Font',
        'printer_name' => 'Nama Printer',
        'label_width' => 'Lebar Label',
        'label_height' => 'Tinggi Label',
        'barcode_type' => 'Tipe Barcode',
        'layout' => 'Layout',
        'percent' => 'Persentase',
        'mode' => 'Mode',
        'default_view' => 'Tampilan Default',
        'backup_file' => 'File Backup',
        'confirmation' => 'Teks Konfirmasi',
    ],

];
