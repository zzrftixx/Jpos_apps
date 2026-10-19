/**
 * JPOS Kasir Tablet Offline Engine
 * Menggunakan IndexedDB untuk penyimpanan lokal 100% offline & Web Bluetooth untuk printer thermal.
 */

// 1. Inisialisasi Database IndexedDB Lokal
const DB_NAME = 'JPosTabletDB';
const DB_VERSION = 1;

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('products')) {
                const pStore = db.createObjectStore('products', { keyPath: 'id' });
                pStore.createIndex('name', 'name', { unique: false });
                pStore.createIndex('barcode', 'barcode', { unique: false });
                pStore.createIndex('category_id', 'category_id', { unique: false });
            }
            if (!db.objectStoreNames.contains('sales')) {
                const sStore = db.createObjectStore('sales', { keyPath: 'offline_id' });
                sStore.createIndex('synced', 'synced', { unique: false });
                sStore.createIndex('created_at', 'created_at', { unique: false });
            }
            if (!db.objectStoreNames.contains('settings')) {
                db.createObjectStore('settings', { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

// Helper Operasi Database
const dbOp = {
    async getAll(storeName) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    },
    async put(storeName, val) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const req = store.put(val);
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    },
    async putMany(storeName, items) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            for (const it of items) {
                store.put(it);
            }
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    },
    async clear(storeName) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const req = store.clear();
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    },
    async getSetting(key, defaultVal = null) {
        const db = await openDB();
        return new Promise((resolve) => {
            const tx = db.transaction('settings', 'readonly');
            const store = tx.objectStore('settings');
            const req = store.get(key);
            req.onsuccess = () => resolve(req.result ? req.result.val : defaultVal);
            req.onerror = () => resolve(defaultVal);
        });
    },
    async setSetting(key, val) {
        return this.put('settings', { key, val });
    }
};

// 2. State & Logika Utama Aplikasi (Alpine Component)
document.addEventListener('alpine:init', () => {
    Alpine.data('tabletKasir', () => ({
        // Server & Koneksi
        serverUrl: localStorage.getItem('jpos_server_url') || (window.location.origin.includes('8000') ? window.location.origin : 'http://' + window.location.hostname + ':8000'),
        tabletToken: localStorage.getItem('jpos_tablet_token') || '',
        isOnline: false,
        isSyncing: false,
        unsyncedCount: 0,
        syncMessage: '',

        getAuthHeaders(extra = {}) {
            const headers = { ...extra };
            if (this.tabletToken && this.tabletToken.trim() !== '') {
                headers['X-JPOS-TABLET-TOKEN'] = this.tabletToken.trim();
            }
            return headers;
        },

        // Data Toko & Kasir
        storeProfile: {
            name: 'JPOS Kasir Tablet',
            address: 'Offline Store',
            phone: '',
            footer: 'Terima kasih atas kunjungan Anda!'
        },
        cashierName: localStorage.getItem('jpos_cashier_name') || 'Kasir Tablet 1',

        // Katalog Produk
        categories: [],
        selectedCategory: 'all',
        searchQuery: '',
        products: [],
        filteredProducts: [],

        // Keranjang Belanja
        cart: [],
        discount: 0,
        taxPercent: 0,

        // Modal Pembayaran
        showPayModal: false,
        paymentMethod: 'cash',
        paidAmount: 0,
        quickCashNominals: [10000, 20000, 50000, 100000, 200000],

        // Modal Struk & Cetak
        showReceiptModal: false,
        currentReceipt: null,
        bluetoothDevice: null,
        bluetoothCharacteristic: null,
        isPrinting: false,

        // Modal Pengaturan
        showSettingsModal: false,

        async init() {
            // Registrasi Service Worker untuk Offline
            if ('serviceWorker' in navigator) {
                try {
                    await navigator.serviceWorker.register('/tablet/sw.js');
                } catch (e) {
                    console.log('SW registration error:', e);
                }
            }

            await this.loadLocalData();
            this.checkConnection();
            setInterval(() => this.checkConnection(), 15000);
        },

        async loadLocalData() {
            // Ambil profil toko tersimpan
            const savedStore = await dbOp.getSetting('store_profile');
            if (savedStore) this.storeProfile = savedStore;

            // Ambil kategori tersimpan
            const savedCats = await dbOp.getSetting('categories');
            if (savedCats) this.categories = savedCats;

            // Ambil produk lokal
            this.products = await dbOp.getAll('products');
            this.filterProducts();

            // Hitung transaksi yang belum sinkron
            await this.updateUnsyncedCount();

            // Jika produk lokal kosong, coba unduh otomatis dari server
            if (this.products.length === 0) {
                this.pullCatalogFromServer();
            }
        },

        async updateUnsyncedCount() {
            const allSales = await dbOp.getAll('sales');
            this.unsyncedCount = allSales.filter(s => !s.synced).length;
        },

        async checkConnection() {
            try {
                const res = await fetch(`${this.serverUrl}/api/tablet/status`, {
                    headers: this.getAuthHeaders(),
                    signal: AbortSignal.timeout(3500)
                });
                if (res.ok) {
                    const data = await res.json();
                    this.isOnline = true;
                    if (data.server_name) {
                        this.storeProfile.name = data.server_name;
                    }
                    return true;
                }
            } catch (e) {
                this.isOnline = false;
            }
            return false;
        },

        filterProducts() {
            let list = this.products;
            if (this.selectedCategory !== 'all') {
                list = list.filter(p => p.category_id == this.selectedCategory);
            }
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(p =>
                    (p.name && p.name.toLowerCase().includes(q)) ||
                    (p.barcode && p.barcode.toLowerCase().includes(q)) ||
                    (p.sku && p.sku.toLowerCase().includes(q))
                );
            }
            this.filteredProducts = list;
        },

        // Operasi Keranjang
        addToCart(product) {
            const existing = this.cart.find(item => item.product_id === product.id && item.unit_type === 'base');
            if (existing) {
                existing.qty += 1;
                existing.subtotal = existing.qty * existing.price;
            } else {
                this.cart.push({
                    product_id: product.id,
                    name: product.name,
                    price: product.price,
                    cost_price: product.cost_price || 0,
                    qty: 1,
                    unit_label: product.unit || 'Pcs',
                    unit_type: 'base',
                    unit_conversion: 1,
                    subtotal: product.price,
                    stock: product.stock
                });
            }
        },

        updateQty(index, delta) {
            const item = this.cart[index];
            if (!item) return;
            const newQty = item.qty + delta;
            if (newQty <= 0) {
                this.cart.splice(index, 1);
            } else {
                item.qty = newQty;
                item.subtotal = item.qty * item.price;
            }
        },

        removeItem(index) {
            this.cart.splice(index, 1);
        },

        clearCart() {
            this.cart = [];
            this.discount = 0;
        },

        get subtotal() {
            return this.cart.reduce((sum, it) => sum + it.subtotal, 0);
        },

        get taxAmount() {
            return (this.subtotal - this.discount) * (this.taxPercent / 100);
        },

        get total() {
            return Math.max(0, this.subtotal - this.discount + this.taxAmount);
        },

        get changeAmount() {
            return Math.max(0, this.paidAmount - this.total);
        },

        // Pembayaran
        openPaymentModal() {
            if (this.cart.length === 0) return;
            this.paidAmount = this.total;
            this.paymentMethod = 'cash';
            this.showPayModal = true;
        },

        setQuickCash(nominal) {
            this.paidAmount = nominal;
        },

        async processCheckout() {
            if (this.paidAmount < this.total && this.paymentMethod === 'cash') {
                alert('Uang pembayaran tunai masih kurang!');
                return;
            }

            const offlineId = 'TAB-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7).toUpperCase();
            const dateStr = new Date().toISOString();

            const saleRecord = {
                offline_id: offlineId,
                invoice_no: 'TAB-' + new Date().toISOString().slice(2,10).replace(/-/g,'') + '-' + Math.floor(100 + Math.random() * 900),
                created_at: dateStr,
                cashier_name: this.cashierName,
                customer_id: null,
                items: JSON.parse(JSON.stringify(this.cart)),
                subtotal: this.subtotal,
                discount: this.discount,
                tax_amount: this.taxAmount,
                total: this.total,
                paid_amount: this.paidAmount,
                change_amount: this.changeAmount,
                payment_method: this.paymentMethod,
                note: 'Penjualan Tablet Kasir',
                synced: 0
            };

            // 1. Simpan ke IndexedDB lokal
            await dbOp.put('sales', saleRecord);

            // 2. Kurangi stok produk lokal di tablet
            for (const item of this.cart) {
                const prod = this.products.find(p => p.id === item.product_id);
                if (prod && !prod.is_jasa) {
                    prod.stock = Math.max(0, prod.stock - item.qty);
                    await dbOp.put('products', prod);
                }
            }

            // 3. Tampilkan struk
            this.currentReceipt = saleRecord;
            this.showPayModal = false;
            this.showReceiptModal = true;
            this.clearCart();
            await this.updateUnsyncedCount();

            // 4. Jika sedang online, otomatis kirim ke server di background
            if (this.isOnline) {
                this.pushSalesToServer();
            }
        },

        // Sinkronisasi: Unduh Produk dari PC
        async pullCatalogFromServer() {
            this.isSyncing = true;
            this.syncMessage = 'Mengunduh produk dari server...';
            try {
                const res = await fetch(`${this.serverUrl}/api/tablet/katalog`, {
                    headers: this.getAuthHeaders()
                });
                if (!res.ok) throw new Error('Server merespons gagal: ' + res.status);
                const data = await res.json();

                if (data.success) {
                    this.products = data.products || [];
                    this.categories = data.categories || [];
                    if (data.store) this.storeProfile = data.store;

                    // Simpan ke IndexedDB lokal
                    await dbOp.clear('products');
                    await dbOp.putMany('products', this.products);
                    await dbOp.setSetting('categories', this.categories);
                    await dbOp.setSetting('store_profile', this.storeProfile);

                    this.filterProducts();
                    this.isOnline = true;
                    this.syncMessage = 'Katalog berhasil diperbarui!';
                }
            } catch (e) {
                this.isOnline = false;
                this.syncMessage = 'Gagal terhubung ke server PC: ' + e.message;
            } finally {
                this.isSyncing = false;
                setTimeout(() => this.syncMessage = '', 4000);
            }
        },

        // Sinkronisasi: Kirim Transaksi Offline ke PC
        async pushSalesToServer() {
            const allSales = await dbOp.getAll('sales');
            const unsynced = allSales.filter(s => !s.synced);

            if (unsynced.length === 0) {
                this.syncMessage = 'Semua transaksi sudah tersinkron.';
                setTimeout(() => this.syncMessage = '', 3000);
                return;
            }

            this.isSyncing = true;
            this.syncMessage = `Mengirim ${unsynced.length} transaksi ke PC...`;

            try {
                const res = await fetch(`${this.serverUrl}/api/tablet/sync-transaksi`, {
                    method: 'POST',
                    headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ sales: unsynced })
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);
                const result = await res.json();

                if (result.success && Array.isArray(result.synced_ids)) {
                    for (const id of result.synced_ids) {
                        const rec = unsynced.find(s => s.offline_id === id);
                        if (rec) {
                            rec.synced = 1;
                            await dbOp.put('sales', rec);
                        }
                    }
                    await this.updateUnsyncedCount();
                    this.syncMessage = `Berhasil kirim ${result.synced_ids.length} transaksi!`;
                }
            } catch (e) {
                this.syncMessage = 'Gagal kirim transaksi: ' + e.message;
            } finally {
                this.isSyncing = false;
                setTimeout(() => this.syncMessage = '', 4000);
            }
        },

        // Tombol Sinkronisasi Lengkap (Tarik + Kirim)
        async syncAll() {
            await this.pullCatalogFromServer();
            await this.pushSalesToServer();
        },

        // Web Bluetooth Thermal Printing (ESC/POS)
        async connectBluetoothPrinter() {
            if (!navigator.bluetooth) {
                alert('Browser ini belum mendukung Web Bluetooth. Gunakan Google Chrome versi terbaru di Android.');
                return;
            }
            try {
                this.bluetoothDevice = await navigator.bluetooth.requestDevice({
                    acceptAllDevices: true,
                    optionalServices: [
                        '000018f0-0000-1000-8000-00805f9b34fb', // Standard Printer Service
                        '0000e0ff-0000-1000-8000-00805f9b34fb',
                        '49535343-fe7d-4ae5-8fa9-9fafd205e455'
                    ]
                });

                const server = await this.bluetoothDevice.gatt.connect();
                const services = await server.getPrimaryServices();
                for (const svc of services) {
                    const chars = await svc.getCharacteristics();
                    for (const ch of chars) {
                        if (ch.properties.write || ch.properties.writeWithoutResponse) {
                            this.bluetoothCharacteristic = ch;
                            break;
                        }
                    }
                    if (this.bluetoothCharacteristic) break;
                }
                alert('Terhubung ke printer Bluetooth: ' + this.bluetoothDevice.name);
            } catch (e) {
                alert('Gagal sambung Bluetooth: ' + e.message);
            }
        },

        async printThermalReceipt() {
            if (!this.currentReceipt) return;

            // Jika belum ada koneksi Bluetooth, tawarkan cetak browser / connect
            if (!this.bluetoothCharacteristic) {
                window.print();
                return;
            }

            try {
                this.isPrinting = true;
                const encoder = new TextEncoder();
                const r = this.currentReceipt;

                let esc = [
                    0x1B, 0x40,             // Init printer
                    0x1B, 0x61, 0x01,       // Align Center
                ];

                const sendText = async (txt) => {
                    await this.bluetoothCharacteristic.writeValue(encoder.encode(txt + '\n'));
                };

                await this.bluetoothCharacteristic.writeValue(new Uint8Array(esc));
                await sendText(this.storeProfile.name || 'JPOS STORE');
                if (this.storeProfile.address) await sendText(this.storeProfile.address);
                if (this.storeProfile.phone) await sendText('Telp: ' + this.storeProfile.phone);
                await sendText('--------------------------------');

                // Align Left
                await this.bluetoothCharacteristic.writeValue(new Uint8Array([0x1B, 0x61, 0x00]));
                await sendText('No: ' + r.invoice_no);
                await sendText('Tgl: ' + new Date(r.created_at).toLocaleString('id-ID'));
                await sendText('Kasir: ' + (r.cashier_name || 'Kasir'));
                await sendText('--------------------------------');

                // Items
                for (const it of r.items) {
                    await sendText(it.name);
                    const qtyPrice = `  ${it.qty} x ${it.price.toLocaleString('id-ID')}`;
                    const sub = it.subtotal.toLocaleString('id-ID');
                    const spaces = Math.max(1, 32 - qtyPrice.length - sub.length);
                    await sendText(qtyPrice + ' '.repeat(spaces) + sub);
                }

                await sendText('--------------------------------');
                // Total
                const totStr = `TOTAL:    Rp ${r.total.toLocaleString('id-ID')}`;
                await sendText(totStr);
                await sendText(`BAYAR:    Rp ${r.paid_amount.toLocaleString('id-ID')}`);
                await sendText(`KEMBALI:  Rp ${r.change_amount.toLocaleString('id-ID')}`);
                await sendText('--------------------------------');

                // Align Center Footer
                await this.bluetoothCharacteristic.writeValue(new Uint8Array([0x1B, 0x61, 0x01]));
                await sendText(this.storeProfile.footer || 'Terima Kasih');
                await sendText('\n\n\n'); // Feed
            } catch (e) {
                alert('Gagal cetak via Bluetooth, mengalihkan ke cetak biasa: ' + e.message);
                window.print();
            } finally {
                this.isPrinting = false;
            }
        },

        saveSettings() {
            localStorage.setItem('jpos_server_url', this.serverUrl);
            localStorage.setItem('jpos_cashier_name', this.cashierName);
            localStorage.setItem('jpos_tablet_token', (this.tabletToken || '').trim());
            this.showSettingsModal = false;
            this.checkConnection();
        },

        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }
    }));
});
