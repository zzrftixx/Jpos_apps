@extends('layouts.app')
@section('title', 'Pembelian & Restock')

@push('styles')
<style>
    /* JPos SVG Safety Sizing - Mencegah SVG membesar / menutupi teks tombol */
    svg { flex-shrink: 0; }
    .w-3\.5 { width: 14px !important; }
    .h-3\.5 { height: 14px !important; }
    .w-4 { width: 16px !important; }
    .h-4 { height: 16px !important; }
    .w-5 { width: 20px !important; }
    .h-5 { height: 20px !important; }
    .w-6 { width: 24px !important; }
    .h-6 { height: 24px !important; }
</style>
@endpush

@section('content')
<script>
function pembelianApp(initialProducts = []) {
    return {
        aktifTab: '{{ request('tab', (request('status') === 'hutang' || request('status') === 'tempo' ? 'hutang' : 'riwayat')) }}',
        showBayar: false,
        showDetail: false,
        notaBayar: null,
        detailNota: null,
        supplierId: '{{ old('supplier_id', '') }}',
        supplierInvoiceNo: '{{ old('supplier_invoice_no', '') }}',
        purchaseDate: '{{ old('purchase_date', now()->toDateString()) }}',
        note: '{{ old('note', '') }}',
        products: initialProducts,
        baris: [],
        kunciBaris: 1,
        biayaLain: {{ (float) old('other_cost', 0) }},
        caraBayar: '{{ old('bayar', 'tunai') }}',
        dibayar: {{ (float) old('paid_amount', 0) }},
        dueDate: '{{ old('due_date', '') }}',

        cariQuery: '',
        hasilCari: [],
        fokusHasilIndex: -1,
        pesanScan: '',
        pesanScanError: false,

        showModalCari: false,
        modalCariQuery: '',
        modalUnitPilihan: {},
        modalFilter: 'semua',
        modalToast: '',
        _lastPilihTime: 0,

        angka(n) { return window.JposNumber ? window.JposNumber.format(n) : Math.round(n || 0).toLocaleString('id-ID'); },
        qty(n) { return window.JposNumber ? window.JposNumber.formatQty(n) : String(n ?? 0); },

        formatTanggal(str) {
            if (!str) return '-';
            const s = String(str).split('T')[0];
            const parts = s.split('-');
            if (parts.length === 3) {
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            return str;
        },

        init() {
            // Tabel dimulai dalam keadaan bersih tanpa baris dummy
        },

        produk(id) { return this.products.find(p => p.id === Number(id)) || null; },

        /* Satuan yang bisa dipakai membeli: satuan dasar produk, plus seluruh satuan
           tambahannya. Toko biasanya kulakan per Dus walau menjualnya per Pcs. */
        satuanProduk(row) {
            const p = this.produk(row.product_id);
            if (! p) return [];
            return [{ value: 'base', label: p.unit, conversion: 1 }].concat(
                (p.units || []).map(u => ({ value: 'unit_' + u.id, label: u.name, conversion: u.conversion }))
            );
        },
        konversi(row) {
            const s = this.satuanProduk(row).find(x => x.value === row.unit_type);
            return s ? Number(s.conversion) : 1;
        },
        labelSatuan(row) {
            const s = this.satuanProduk(row).find(x => x.value === row.unit_type);
            return s ? s.label : '';
        },

        subtotalBaris(row) { return Math.round((Number(row.qty) || 0) * (Number(row.price) || 0)); },
        subtotalSub(u) { return Math.round((Number(u.qty) || 0) * (Number(u.price) || 0)); },
        keuntunganSub(u) { return (Number(u.sell_price) || 0) - (Number(u.price) || 0); },
        subtotal() {
            return this.baris.reduce((t, r) => {
                let s = this.subtotalBaris(r);
                if (r.other_units && Array.isArray(r.other_units)) {
                    s += r.other_units.reduce((acc, u) => acc + this.subtotalSub(u), 0);
                }
                return t + s;
            }, 0);
        },
        total() { return this.subtotal() + (Number(this.biayaLain) || 0); },
        totalQty() {
            return this.baris.reduce((t, r) => {
                let q = Number(r.qty) || 0;
                if (r.other_units && Array.isArray(r.other_units)) {
                    q += r.other_units.reduce((acc, u) => acc + (Number(u.qty) || 0), 0);
                }
                return t + q;
            }, 0);
        },

        /* Harga modal per satuan dasar setelah biaya kirim dibagi sebanding nilai barangnya.
           Ditampilkan hidup supaya pemilik toko langsung melihat apakah harga jualnya masih
           masuk akal - sebelum notanya disimpan. */
        modalPerSatuanDasar(row) {
            const isi = (Number(row.qty) || 0) * this.konversi(row);
            if (isi <= 0) return 0;
            const sub = this.subtotalBaris(row);
            const total = this.subtotal();
            const porsi = total > 0 ? (Number(this.biayaLain) || 0) * (sub / total) : 0;
            return Math.round(((sub + porsi) / isi) * 100) / 100;
        },

        hargaModalSaatIni(row) {
            const p = this.produk(row.product_id);
            if (!p) return 0;
            if (row.unit_type === 'base' || !row.unit_type) return Number(p.cost_price) || 0;
            const u = (p.units || []).find(x => ('unit_' + x.id) === row.unit_type);
            if (u && u.cost_price) return Number(u.cost_price) || 0;
            return (Number(p.cost_price) || 0) * this.konversi(row);
        },

        hargaJualSaatIni(row) {
            const p = this.produk(row.product_id);
            if (!p) return 0;
            if (row.unit_type === 'base' || !row.unit_type) return Number(p.sell_price) || 0;
            const u = (p.units || []).find(x => ('unit_' + x.id) === row.unit_type);
            if (u && u.price) return Number(u.price) || 0;
            return (Number(p.sell_price) || 0) * this.konversi(row);
        },

        hargaJualPerSatuanDasar(row) {
            const p = this.produk(row.product_id);
            return p ? (Number(p.sell_price) || 0) : 0;
        },

        selisihMarginPerDasar(row) {
            const jual = this.hargaJualPerSatuanDasar(row);
            const modal = this.modalPerSatuanDasar(row);
            if (jual <= 0 || modal <= 0) return null;
            return Math.round((jual - modal) * 100) / 100;
        },

        persenMargin(row) {
            const jual = this.hargaJualPerSatuanDasar(row);
            const selisih = this.selisihMarginPerDasar(row);
            if (jual <= 0 || selisih === null) return null;
            return Math.round((selisih / jual) * 1000) / 10;
        },

        tambahBaris() {
            this.bukaModalCari();
        },

        bukaModalCari() {
            this.showModalCari = true;
            this.modalCariQuery = '';
            this.modalFilter = 'semua';
            this.hasilCari = [];
            this.modalToast = '';
            this.$nextTick(() => {
                this.$refs.inputModalCari?.focus();
            });
        },

        tutupModalCari() {
            this.showModalCari = false;
            this.modalToast = '';
        },

        tampilkanModalToast(pesan) {
            this.modalToast = pesan;
            setTimeout(() => {
                if (this.modalToast === pesan) this.modalToast = '';
            }, 3000);
        },

        getModalProdukList() {
            const q = (this.modalCariQuery || '').trim().toLowerCase();
            let list = this.products;

            if (this.modalFilter === 'faktur') {
                list = list.filter(p => this.qtyDiFaktur(p.id) > 0);
            } else if (this.modalFilter === 'multi') {
                list = list.filter(p => (p.units || []).length > 0);
            } else if (this.modalFilter === 'kritis') {
                list = list.filter(p => Number(p.stock) <= 10);
            }

            if (!q) return list;
            return list.filter(p => {
                const nameMatch = (p.name || '').toLowerCase().includes(q);
                const skuMatch = (p.sku || '').toLowerCase().includes(q);
                const barcodeMatch = (p.barcode || '').toLowerCase().includes(q);
                const unitBarcodeMatch = (p.units || []).some(u => (u.barcode || '').toLowerCase().includes(q));
                return nameMatch || skuMatch || barcodeMatch || unitBarcodeMatch;
            });
        },

        qtyDiFaktur(productId, unitType = null) {
            if (unitType) {
                const r = this.baris.find(b => b.product_id === productId && b.unit_type === unitType);
                if (r) return Number(r.qty) || 0;
                const parent = this.baris.find(b => b.product_id === productId);
                if (parent && parent.other_units) {
                    const sub = parent.other_units.find(u => u.unit_type === unitType);
                    if (sub) return Number(sub.qty) || 0;
                }
                return 0;
            }
            return this.baris.filter(b => b.product_id === productId).reduce((t, b) => {
                let subSum = (b.other_units || []).reduce((st, u) => st + (Number(u.qty) || 0), 0);
                return t + (Number(b.qty) || 0) + subSum;
            }, 0);
        },

        labelUnitModal(p, unitType = 'base') {
            if (unitType === 'base' || !unitType) return p.unit || '';
            const u = (p.units || []).find(x => ('unit_' + x.id) === unitType);
            return u ? u.name : (p.unit || '');
        },

        modalHargaBeli(p, unitType = 'base') {
            if (unitType === 'base' || !unitType) return Number(p.cost_price) || 0;
            const u = (p.units || []).find(x => ('unit_' + x.id) === unitType);
            if (u && u.cost_price !== null && u.cost_price !== undefined) return Number(u.cost_price);
            return Math.round((Number(p.cost_price) || 0) * (u ? Number(u.conversion) : 1));
        },

        modalHargaJual(p, unitType = 'base') {
            if (unitType === 'base' || !unitType) return Number(p.sell_price) || 0;
            const u = (p.units || []).find(x => ('unit_' + x.id) === unitType);
            if (u && u.price !== null && u.price !== undefined) return Number(u.price);
            return Math.round((Number(p.sell_price) || 0) * (u ? Number(u.conversion) : 1));
        },

        tambahDariModal(p, unitType = 'base') {
            if (!p) return;
            this.tambahProdukKeBaris(p, unitType);
            this.tampilkanPesanScan('Berhasil menambahkan: ' + p.name);
            const q = this.qtyDiFaktur(p.id, unitType);
            const label = this.labelUnitModal(p, unitType);
            this.tampilkanModalToast('✓ ' + p.name + ' (' + label + ') masuk faktur [Qty: ' + q + ']');
        },

        kurangiDariModal(p, unitType = 'base') {
            const r = this.baris.find(b => b.product_id === p.id && b.unit_type === unitType);
            const label = this.labelUnitModal(p, unitType);
            if (r) {
                let q = (Number(r.qty) || 0) - 1;
                if (q <= 0) {
                    const hasActiveSub = (r.other_units || []).some(u => Number(u.qty) > 0);
                    if (hasActiveSub) {
                        r.qty = 0;
                        this.tampilkanModalToast('Qty ' + p.name + ' (' + label + ') diatur 0');
                    } else {
                        this.baris = this.baris.filter(b => b._key !== r._key);
                        this.tampilkanPesanScan('Dihapus dari faktur: ' + p.name);
                        this.tampilkanModalToast('Dihapus dari faktur: ' + p.name + ' (' + label + ')');
                    }
                } else {
                    r.qty = Math.round(q * 10000) / 10000;
                    this.tampilkanModalToast('✓ ' + p.name + ' (' + label + ') qty: ' + r.qty);
                }
                return;
            }
            const parent = this.baris.find(b => b.product_id === p.id);
            if (parent && parent.other_units) {
                const sub = parent.other_units.find(u => u.unit_type === unitType);
                if (sub) {
                    let q = (Number(sub.qty) || 0) - 1;
                    if (q < 0) q = 0;
                    sub.qty = Math.round(q * 10000) / 10000;
                    this.tampilkanModalToast('✓ ' + p.name + ' (' + label + ') qty: ' + sub.qty);
                }
            }
        },
        hapusBaris(key) {
            this.baris = this.baris.filter(r => r._key !== key);
        },
        ubahQty(row, delta) {
            let q = (Number(row.qty) || 0) + delta;
            if (q < 0.0001) q = 0.0001;
            row.qty = Math.round(q * 10000) / 10000;
        },

        hitungMarginDariJual(row) {
            this.$nextTick(() => {
                const beli = Number(row.price) || 0;
                const jual = Number(row.sell_price) || 0;
                if (beli > 0) {
                    row.margin_percent = Math.round(((jual - beli) / beli) * 1000) / 10;
                } else {
                    row.margin_percent = 0;
                }
            });
        },

        hitungJualDariMargin(row) {
            this.$nextTick(() => {
                const beli = Number(row.price) || 0;
                const persen = Number(row.margin_percent) || 0;
                row.sell_price = Math.round(beli * (1 + (persen / 100)));
            });
        },

        ubahHargaBeli(row) {
            this.$nextTick(() => {
                const beli = Number(row.price) || 0;
                const persen = Number(row.margin_percent);
                if (persen && persen > 0) {
                    row.sell_price = Math.round(beli * (1 + (persen / 100)));
                } else {
                    this.hitungMarginDariJual(row);
                }
                this.updateProportionalModalOtherUnits(row);
            });
        },

        gantiSatuan(row) {
            const p = this.produk(row.product_id);
            if (!p) return;
            let defaultBeli = 0;
            let defaultJual = 0;
            if (row.unit_type === 'base' || !row.unit_type) {
                defaultBeli = Number(p.cost_price) || 0;
                defaultJual = Number(p.sell_price) || 0;
            } else {
                const u = (p.units || []).find(x => ('unit_' + x.id) === row.unit_type);
                if (u) {
                    defaultBeli = u.cost_price !== null ? Number(u.cost_price) : Math.round((Number(p.cost_price) || 0) * Number(u.conversion));
                    defaultJual = u.price !== null ? Number(u.price) : Math.round((Number(p.sell_price) || 0) * Number(u.conversion));
                }
            }
            row.price = defaultBeli;
            row.sell_price = defaultJual;
            this.hitungMarginDariJual(row);
            this.sinkronisasiOtherUnits(row);
        },

        ubahQtySub(u, delta) {
            let q = (Number(u.qty) || 0) + delta;
            if (q < 0) q = 0;
            u.qty = Math.round(q * 10000) / 10000;
        },

        ubahHargaBeliSub(u, row) {
            this.$nextTick(() => {
                const beli = Number(u.price) || 0;
                const persen = Number(u.margin_percent);
                if (persen && persen > 0) {
                    u.sell_price = Math.round(beli * (1 + (persen / 100)));
                } else {
                    this.hitungMarginDariJualSub(u);
                }
            });
        },

        hitungMarginDariJualSub(u) {
            this.$nextTick(() => {
                const beli = Number(u.price) || 0;
                const jual = Number(u.sell_price) || 0;
                if (beli > 0) {
                    u.margin_percent = Math.round(((jual - beli) / beli) * 1000) / 10;
                } else {
                    u.margin_percent = 0;
                }
            });
        },

        hitungJualDariMarginSub(u) {
            this.$nextTick(() => {
                const beli = Number(u.price) || 0;
                const persen = Number(u.margin_percent) || 0;
                u.sell_price = Math.round(beli * (1 + (persen / 100)));
            });
        },

        updateProportionalModalOtherUnits(row) {
            if (!row.other_units || !row.other_units.length) return;
            const modalDasar = this.konversi(row) > 0 ? (Number(row.price) || 0) / this.konversi(row) : (Number(row.price) || 0);
            row.other_units.forEach(u => {
                if (Number(u.qty) <= 0) {
                    u.price = Math.round(modalDasar * Number(u.conversion) * 100) / 100;
                    this.hitungMarginDariJualSub(u);
                }
            });
        },

        sinkronisasiOtherUnits(row) {
            const p = this.produk(row.product_id);
            if (!p) { row.other_units = []; return; }
            const allUnits = this.satuanProduk(row);
            if (allUnits.length <= 1) {
                row.other_units = [];
                return;
            }
            const otherUnits = allUnits.filter(x => x.value !== row.unit_type);
            const modalDasar = this.konversi(row) > 0 ? (Number(row.price) || 0) / this.konversi(row) : (Number(row.price) || 0);

            const existingMap = new Map((row.other_units || []).map(u => [u.unit_type, u]));
            row.other_units = otherUnits.map(s => {
                const exist = existingMap.get(s.value);
                const propCost = Math.round(modalDasar * Number(s.conversion) * 100) / 100;
                let defaultSell = 0;
                let defaultCost = propCost > 0 ? propCost : 0;
                if (s.value === 'base') {
                    defaultSell = Number(p.sell_price) || 0;
                    if (defaultCost === 0) defaultCost = Number(p.cost_price) || 0;
                } else {
                    const pu = (p.units || []).find(x => ('unit_' + x.id) === s.value);
                    if (pu) {
                        defaultSell = pu.price !== null ? Number(pu.price) : Math.round((Number(p.sell_price) || 0) * Number(pu.conversion));
                        if (defaultCost === 0) defaultCost = pu.cost_price !== null ? Number(pu.cost_price) : Math.round((Number(p.cost_price) || 0) * Number(pu.conversion));
                    }
                }

                if (exist) {
                    if (Number(exist.qty) <= 0 && propCost > 0) {
                        exist.price = propCost;
                        if (exist.sell_price > 0) {
                            exist.margin_percent = Math.round(((exist.sell_price - exist.price) / exist.price) * 1000) / 10;
                        }
                    }
                    exist.conversion = s.conversion;
                    exist.label = s.label;
                    return exist;
                }

                const sellPrice = defaultSell > 0 ? defaultSell : (defaultCost > 0 ? Math.round(defaultCost * 1.2) : 0);
                let margin = 0;
                if (defaultCost > 0 && sellPrice > 0) {
                    margin = Math.round(((sellPrice - defaultCost) / defaultCost) * 1000) / 10;
                }

                return {
                    _key: this.kunciBaris++,
                    unit_type: s.value,
                    label: s.label,
                    conversion: s.conversion,
                    qty: 0,
                    price: defaultCost,
                    sell_price: sellPrice,
                    margin_percent: margin,
                };
            });
        },

        keuntunganPerUnit(row) {
            return (Number(row.sell_price) || 0) - (Number(row.price) || 0);
        },

        cariProdukList() {
            const q = (this.cariQuery || '').trim().toLowerCase();
            // Kaidah POS: Scanner input tidak menampilkan popup saat kosong/fokus biasa
            if (!q || q.length < 2) {
                this.hasilCari = [];
                this.fokusHasilIndex = -1;
                return;
            }
            this.hasilCari = this.products.filter(p => {
                const nameMatch = (p.name || '').toLowerCase().includes(q);
                const skuMatch = (p.sku || '').toLowerCase().includes(q);
                const barcodeMatch = (p.barcode || '').toLowerCase().includes(q);
                const unitBarcodeMatch = (p.units || []).some(u => (u.barcode || '').toLowerCase().includes(q));
                return nameMatch || skuMatch || barcodeMatch || unitBarcodeMatch;
            }).slice(0, 6);
            this.fokusHasilIndex = this.hasilCari.length > 0 ? 0 : -1;
        },

        pindaiBarcode(kode) {
            if (!kode) return;
            const bersih = String(kode).trim().toLowerCase();

            // 1. Cek apakah cocok barcode produk utama
            let cocok = this.products.find(p => (p.barcode || '').toLowerCase() === bersih);
            let unitType = 'base';

            // 2. Jika tidak, cek barcode satuan tambahan
            if (!cocok) {
                for (const p of this.products) {
                    const u = (p.units || []).find(un => (un.barcode || '').toLowerCase() === bersih);
                    if (u) {
                        cocok = p;
                        unitType = 'unit_' + u.id;
                        break;
                    }
                }
            }

            // 3. Jika tidak, cek kecocokan persis SKU
            if (!cocok) {
                cocok = this.products.find(p => (p.sku || '').toLowerCase() === bersih);
            }

            if (cocok) {
                this.tambahProdukKeBaris(cocok, unitType);
                this.tampilkanPesanScan('Berhasil menambahkan: ' + cocok.name);
                this.cariQuery = '';
                this.hasilCari = [];
                this.fokusHasilIndex = -1;
            } else {
                this.tampilkanPesanScan('Barcode / SKU "' + kode + '" tidak ditemukan.', true);
            }
        },

        eksekusiCari() {
            const q = (this.cariQuery || '').trim();
            if (!q) return;

            // 1. Prioritaskan kecocokan persis Barcode atau SKU (perilaku scanner barcode fisik)
            const bersih = q.toLowerCase();
            const barcodeCocok = this.products.find(p => (p.barcode || '').toLowerCase() === bersih || (p.sku || '').toLowerCase() === bersih || (p.units || []).some(u => (u.barcode || '').toLowerCase() === bersih));
            if (barcodeCocok) {
                this.pindaiBarcode(q);
                return;
            }

            // 2. Jika ada item yang dipilih dari dropdown hasil cari
            if (this.fokusHasilIndex >= 0 && this.hasilCari[this.fokusHasilIndex]) {
                this.pilihHasilCari(this.hasilCari[this.fokusHasilIndex]);
                return;
            }

            if (this.hasilCari.length === 1) {
                this.pilihHasilCari(this.hasilCari[0]);
                return;
            }

            if (this.hasilCari.length > 1) {
                this.pilihHasilCari(this.hasilCari[0]);
                return;
            }

            this.pindaiBarcode(q);
        },

        pilihHasilCari(p, unitType = 'base') {
            if (!p) return;
            const now = Date.now();
            if (this._lastPilihTime && (now - this._lastPilihTime < 250)) return;
            this._lastPilihTime = now;

            this.tambahProdukKeBaris(p, unitType);
            this.tampilkanPesanScan('Berhasil menambahkan: ' + p.name);
            this.cariQuery = '';
            this.hasilCari = [];
            this.fokusHasilIndex = -1;
            this.$nextTick(() => { this.$refs.inputCari?.focus(); });
        },

        tambahProdukKeBaris(p, unitType = 'base') {
            const ada = this.baris.find(r => r.product_id === p.id);
            if (ada) {
                if (ada.unit_type === unitType) {
                    ada.qty = Math.round(((Number(ada.qty) || 0) + 1) * 10000) / 10000;
                    return;
                }
                if (!ada.other_units || !ada.other_units.length) {
                    this.sinkronisasiOtherUnits(ada);
                }
                const sub = (ada.other_units || []).find(u => u.unit_type === unitType);
                if (sub) {
                    sub.qty = Math.round(((Number(sub.qty) || 0) + 1) * 10000) / 10000;
                    return;
                }
            }

            let hargaBeliDefault = Number(p.cost_price) || 0;
            let hargaJualDefault = Number(p.sell_price) || 0;
            if (unitType !== 'base') {
                const u = (p.units || []).find(x => ('unit_' + x.id) === unitType);
                if (u) {
                    hargaBeliDefault = u.cost_price !== null ? Number(u.cost_price) : Math.round((Number(p.cost_price) || 0) * Number(u.conversion));
                    hargaJualDefault = u.price !== null ? Number(u.price) : Math.round((Number(p.sell_price) || 0) * Number(u.conversion));
                }
            }

            let marginPercent = 0;
            if (hargaBeliDefault > 0 && hargaJualDefault > 0) {
                marginPercent = Math.round(((hargaJualDefault - hargaBeliDefault) / hargaBeliDefault) * 1000) / 10;
            }

            const barisBaru = {
                _key: this.kunciBaris++,
                product_id: p.id,
                product_name: p.name,
                barcode: p.barcode || '',
                sku: p.sku || '',
                unit: p.unit || '',
                unit_type: unitType,
                qty: 1,
                price: hargaBeliDefault,
                sell_price: hargaJualDefault,
                margin_percent: marginPercent,
                other_units: [],
            };
            this.sinkronisasiOtherUnits(barisBaru);
            this.baris.push(barisBaru);
        },

        tampilkanPesanScan(pesan, isError = false) {
            this.pesanScan = pesan;
            this.pesanScanError = isError;
            setTimeout(() => {
                if (this.pesanScan === pesan) this.pesanScan = '';
            }, 3500);
        },

        bukaInputBaru() {
            this.aktifTab = 'input';
            this.$nextTick(() => {
                this.$refs.inputCari?.focus();
            });
        },

        resetFormInput() {
            if (confirm('Kosongkan formulir input pembelian dan mulai baru?')) {
                this.baris = [];
                this.kunciBaris = 1;
                this.biayaLain = 0;
                this.caraBayar = 'tunai';
                this.dibayar = 0;
            }
        },

        bukaDetail(nota) {
            this.detailNota = nota;
            this.showDetail = true;
        },

        bukaBayar(item) {
            if (!item) return;
            this.notaBayar = {
                id: item.id,
                no: item.purchase_no || item.no || '',
                supplier: item.supplier ? (typeof item.supplier === 'object' ? item.supplier.name : item.supplier) : 'Tanpa Pemasok',
                total: Number(item.total) || (Number(item.sisa) || 0),
                paid: Number(item.paid_amount) || 0,
                sisa: Number(item.sisa_hutang !== undefined ? item.sisa_hutang : item.sisa) || 0,
            };
            this.showBayar = true;
            this.showDetail = false;
        },
    };
}
</script>

<div x-data="pembelianApp(@js($products))"
     @jpos:barcode-dipindai.window="pindaiBarcode($event.detail.kode)"
     @keydown.f2.window.prevent="if (aktifTab === 'input') bukaModalCari()">

    {{-- Header Halaman --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5">
        <div>
            <template x-if="aktifTab !== 'input'">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-brand-50 text-brand-600 border border-brand-100 shadow-2xs">
                            <svg width="24" height="24" style="width: 24px; height: 24px;" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </span>
                        Pembelian &amp; Restock
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Penerimaan barang masuk dari pemasok, penambahan stok gudang otomatis, penyesuaian harga modal (HPP), dan monitoring hutang tempo.
                    </p>
                </div>
            </template>
            <template x-if="aktifTab === 'input'">
                <div>
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-brand-600 mb-1">
                        <span @click="aktifTab = 'riwayat'" class="cursor-pointer hover:underline flex items-center gap-1">
                            <svg width="14" height="14" style="width: 14px; height: 14px;" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Menu Pembelian &amp; Restock
                        </span>
                        <span class="text-slate-300">/</span>
                        <span class="text-slate-700 font-bold">Catat Faktur Baru</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Catat Faktur Pembelian Baru</h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Input barang masuk dari nota fisik pemasok, pembaruan harga modal &amp; jual, dan pencatatan kas/hutang.
                    </p>
                </div>
            </template>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <template x-if="aktifTab !== 'input'">
                <div class="flex items-center gap-2.5 flex-wrap">
                    @if(auth()->user()->can_access('laporan'))
                        @include('laporan._ekspor', ['jenis' => 'hutang', 'filter' => array_filter(['q' => request('q'), 'supplier_id' => request('supplier_id'), 'periode' => request('periode')])])
                    @endif
                    <button type="button" @click="bukaInputBaru()" class="btn btn-primary inline-flex items-center gap-2 px-4 py-2.5 font-bold shadow-sm hover:shadow transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span>+ Catat Pembelian Baru</span>
                    </button>
                </div>
            </template>
            <template x-if="aktifTab === 'input'">
                <div class="flex items-center gap-2">
                    <button type="button" @click="aktifTab = 'riwayat'" class="btn btn-outline text-xs sm:text-sm font-bold py-2 px-3.5 inline-flex items-center gap-1.5 bg-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Kembali ke Riwayat</span>
                    </button>
                    <button type="button" @click="resetFormInput()" class="btn btn-ghost text-xs sm:text-sm font-bold py-2 px-3 text-slate-500 hover:text-red-600">
                        Kosongkan Form
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- Banner Notifikasi Berhasil Simpan & Tombol Cetak Langsung --}}
    @if(session('success'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200/90 rounded-2xl p-4 text-emerald-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-black shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <p class="font-bold text-sm text-emerald-950">{{ session('success') }}</p>
                    @if(session('last_purchase_no'))
                        <p class="text-xs text-emerald-700 mt-0.5">Faktur: <span class="font-mono font-bold">{{ session('last_purchase_no') }}</span> &bull; Stok dan harga modal produk telah berhasil diperbarui.</p>
                    @endif
                </div>
            </div>
            @if(session('last_purchase_id'))
                <a href="{{ route('pembelian.cetak', session('last_purchase_id')) }}" target="_blank"
                   class="btn bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-100 text-xs font-bold inline-flex items-center gap-2 px-4 py-2 rounded-xl shadow-2xs shrink-0 transition">
                    <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Faktur Pembelian</span>
                </a>
            @endif
        </div>
    @endif

    {{-- 4 Kartu Metrik Ringkasan --}}
    <div x-show="aktifTab !== 'input'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Card 1: Pembelian Bulan Ini --}}
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-slate-500">Pembelian Bulan Ini</span>
                <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-black text-slate-900 mt-2">
                Rp {{ number_format($ringkasan->total_bulan_ini ?? 0, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1 font-medium">
                <span>Bulan {{ now()->translatedFormat('F Y') }}</span>
            </div>
        </div>

        {{-- Card 2: Total Sisa Hutang --}}
        <a href="{{ route('pembelian.index', ['status' => 'hutang']) }}" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:border-amber-300 hover:shadow-xs transition block">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-slate-500">Sisa Hutang Pemasok</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-black text-amber-600 mt-2">
                Rp {{ number_format($ringkasan->total_hutang, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 font-medium flex items-center justify-between">
                <span>Kewajiban aktif belum lunas</span>
                <span class="text-amber-700 font-bold">Filter &rarr;</span>
            </div>
        </a>

        {{-- Card 3: Nota Belum Lunas --}}
        <a href="{{ route('pembelian.index', ['status' => 'hutang']) }}" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:border-indigo-300 hover:shadow-xs transition block">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-slate-500">Nota Belum Lunas</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-black text-slate-900 mt-2 flex items-baseline gap-1.5">
                <span>{{ $ringkasan->jumlah_nota_hutang }}</span>
                <span class="text-xs font-medium text-slate-400">faktur</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-1 font-medium flex items-center justify-between">
                <span>Menunggu cicilan / pelunasan</span>
                <span class="text-indigo-600 font-bold">Lihat &rarr;</span>
            </div>
        </a>

        {{-- Card 4: Lewat Jatuh Tempo --}}
        <a href="{{ route('pembelian.index', ['status' => 'tempo']) }}" class="bg-white rounded-2xl p-4 border {{ $ringkasan->jatuh_tempo > 0 ? 'border-red-300 bg-red-50/20' : 'border-slate-200/80' }} shadow-2xs hover:border-red-400 hover:shadow-xs transition block">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold {{ $ringkasan->jatuh_tempo > 0 ? 'text-red-700' : 'text-slate-500' }}">Lewat Jatuh Tempo</span>
                <span class="w-8 h-8 rounded-xl {{ $ringkasan->jatuh_tempo > 0 ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-black {{ $ringkasan->jatuh_tempo > 0 ? 'text-red-600' : 'text-slate-900' }} mt-2 flex items-baseline gap-1.5">
                <span>{{ $ringkasan->jatuh_tempo }}</span>
                <span class="text-xs font-medium text-slate-400">faktur</span>
            </div>
            <div class="text-[11px] {{ $ringkasan->jatuh_tempo > 0 ? 'text-red-600 font-semibold' : 'text-slate-400' }} mt-1 font-medium flex items-center justify-between">
                <span>{{ $ringkasan->jatuh_tempo > 0 ? 'Prioritas pelunasan segera' : 'Semua pembayaran tepat waktu' }}</span>
                @if($ringkasan->jatuh_tempo > 0)
                    <span class="font-bold underline">Filter &rarr;</span>
                @endif
            </div>
        </a>
    </div>

    {{-- Tab Navigasi Standar POS --}}
    <div class="flex items-center gap-1 sm:gap-2 border-b border-slate-200 mb-5 overflow-x-auto">
        <button type="button" @click="aktifTab = 'riwayat'"
                :class="aktifTab === 'riwayat' ? 'border-brand-600 text-brand-700 font-bold bg-white shadow-2xs' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold hover:bg-slate-50'"
                class="px-4 py-3 border-b-2 text-xs sm:text-sm inline-flex items-center gap-2 transition rounded-t-xl shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Riwayat Faktur Pembelian</span>
        </button>

        <button type="button" @click="aktifTab = 'hutang'"
                :class="aktifTab === 'hutang' ? 'border-amber-600 text-amber-800 font-bold bg-white shadow-2xs' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold hover:bg-slate-50'"
                class="px-4 py-3 border-b-2 text-xs sm:text-sm inline-flex items-center gap-2 transition rounded-t-xl shrink-0">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Tagihan &amp; Hutang Tempo</span>
            @if($ringkasan->jumlah_nota_hutang > 0)
                <span class="px-2 py-0.5 text-[11px] rounded-full bg-amber-100 text-amber-800 font-bold">{{ $ringkasan->jumlah_nota_hutang }}</span>
            @endif
        </button>

        <button type="button" @click="aktifTab = 'rekap_produk'"
                :class="aktifTab === 'rekap_produk' ? 'border-brand-600 text-brand-700 font-bold bg-white shadow-2xs' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold hover:bg-slate-50'"
                class="px-4 py-3 border-b-2 text-xs sm:text-sm inline-flex items-center gap-2 transition rounded-t-xl shrink-0">
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            <span>Rekap Barang Dibeli</span>
            @if(isset($rekapRingkasan) && $rekapRingkasan->total_produk > 0)
                <span class="px-2 py-0.5 text-[11px] rounded-full bg-brand-100 text-brand-700 font-bold">{{ $rekapRingkasan->total_produk }}</span>
            @endif
        </button>

        <button type="button" @click="bukaInputBaru()"
                :class="aktifTab === 'input' ? 'border-brand-600 text-brand-700 font-bold bg-white shadow-2xs' : 'border-transparent text-slate-600 hover:text-brand-600 font-bold hover:bg-brand-50/40'"
                class="px-4 py-3 border-b-2 text-xs sm:text-sm inline-flex items-center gap-2 transition rounded-t-xl shrink-0">
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span class="text-brand-600">+ Catat Pembelian Baru</span>
        </button>
    </div>

    {{-- ======================================================== --}}
    {{-- TAB 1 & 2: RIWAYAT FAKTUR / MONITORING HUTANG TEMPO      --}}
    {{-- ======================================================== --}}
    <div x-show="aktifTab === 'riwayat' || aktifTab === 'hutang'" x-cloak>
        {{-- Filter Bar --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-5 shadow-2xs">
            <form method="GET" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3" data-live-search>
                <div class="flex flex-wrap items-center gap-2.5 flex-1">
                    {{-- Pencarian Nomor / Supplier (Flex Sibling, Bebas Tumpang Tindih) --}}
                    <div class="flex items-stretch rounded-xl border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden flex-1 min-w-[220px]">
                        <span class="inline-flex items-center px-3 bg-slate-50 text-slate-400 border-r border-slate-200 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari no. faktur / no. invoice / pemasok..." style="border:0; outline:none; box-shadow:none; padding:8px 12px;" class="w-full text-xs sm:text-sm">
                    </div>

                    {{-- Filter Pemasok --}}
                    <div class="w-48">
                        <select name="supplier_id" class="form-select w-full text-xs sm:text-sm" onchange="this.form.submit()">
                            <option value="">Semua Pemasok</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Periode Waktu --}}
                    <div class="w-44">
                        <select name="periode" class="form-select w-full text-xs sm:text-sm" onchange="this.form.submit()">
                            <option value="">Semua Periode</option>
                            <option value="hari_ini" {{ request('periode') === 'hari_ini' ? 'selected' : '' }}>Hari Ini</option>
                            <option value="7_hari" {{ request('periode') === '7_hari' ? 'selected' : '' }}>7 Hari Terakhir</option>
                            <option value="bulan_ini" {{ request('periode') === 'bulan_ini' ? 'selected' : '' }}>Bulan Ini</option>
                        </select>
                    </div>

                    {{-- Filter Status --}}
                    <div class="w-40">
                        <select name="status" class="form-select w-full text-xs sm:text-sm" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="hutang" {{ request('status') === 'hutang' ? 'selected' : '' }}>Masih Hutang</option>
                            <option value="tempo" {{ request('status') === 'tempo' ? 'selected' : '' }}>Jatuh Tempo</option>
                            <option value="lunas" {{ request('status') === 'lunas' ? 'selected' : '' }}>Lunas</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-outline text-xs sm:text-sm py-2 px-3.5">Filter</button>
                    @if(request('q') || request('supplier_id') || request('periode') || request('status'))
                        <a href="{{ route('pembelian.index') }}" class="text-xs text-slate-500 hover:text-slate-800 underline ml-1">Reset Filter</a>
                    @endif
                </div>

                <div class="hidden xl:flex items-center gap-2 text-xs text-slate-500 bg-slate-50 border border-slate-200/70 rounded-xl px-3 py-2 shrink-0 max-w-sm">
                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Klik <strong>🖨️</strong> pada baris transaksi untuk mencetak faktur penerimaan barang resmi.</span>
                </div>
            </form>
        </div>

        {{-- Tabel Data Pembelian --}}
        <div data-live-results>
            <div class="card overflow-hidden border border-slate-200/80 shadow-2xs bg-white rounded-2xl">
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="py-3 px-4 font-bold">No. Faktur</th>
                                <th class="py-3 px-4 font-bold">Tanggal</th>
                                <th class="py-3 px-4 font-bold">Pemasok</th>
                                <th class="py-3 px-4 font-bold">Barang Masuk</th>
                                <th class="py-3 px-4 font-bold text-right">Total Transaksi</th>
                                <th class="py-3 px-4 font-bold">Status &amp; Hutang</th>
                                <th class="py-3 px-4 font-bold text-center w-44">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
                            @forelse($purchases as $p)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 font-medium text-slate-900">
                                    <div class="flex items-center gap-1.5 font-bold">
                                        <span class="font-mono text-brand-700">{{ $p->purchase_no }}</span>
                                    </div>
                                    @if($p->supplier_invoice_no)
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5">Inv: {{ $p->supplier_invoice_no }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                    <div class="font-medium">{{ $p->purchase_date->format('d/m/Y') }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $p->purchase_date->diffForHumans() }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    @if($p->supplier)
                                        <div class="font-semibold text-slate-800">{{ $p->supplier->name }}</div>
                                        @if($p->supplier->phone)
                                            <div class="text-[11px] text-slate-400">{{ $p->supplier->phone }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400 italic">Tanpa Pemasok</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="space-y-1 max-w-xs">
                                        @foreach($p->items->take(2) as $it)
                                            <div class="text-xs flex items-center justify-between gap-2 text-slate-700">
                                                <span class="truncate font-medium">{{ $it->product_name }}</span>
                                                <span class="text-slate-500 font-mono shrink-0">@qty($it->qty) {{ $it->unit_label }}</span>
                                            </div>
                                        @endforeach
                                        @if($p->items->count() > 2)
                                            <button type="button" @click='bukaDetail(@json($p))' class="text-[11px] text-brand-600 font-semibold hover:underline">
                                                +{{ $p->items->count() - 2 }} barang lainnya...
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="font-bold text-slate-900">Rp {{ number_format($p->total, 0, ',', '.') }}</div>
                                    @if($p->other_cost > 0)
                                        <div class="text-[11px] text-slate-500">+ Biaya Rp {{ number_format($p->other_cost, 0, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($p->sudahLunas())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Lunas
                                        </span>
                                    @else
                                        <div>
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Sisa Rp {{ number_format($p->sisa_hutang, 0, ',', '.') }}
                                            </div>
                                            @if($p->due_date)
                                                @php $telat = $p->umurTunggakan(); @endphp
                                                <div class="text-[11px] mt-1 {{ $telat > 0 ? 'text-red-600 font-bold' : 'text-slate-400' }}">
                                                    @if($telat > 0)
                                                        ⚠️ Telat {{ $telat }} hari
                                                    @else
                                                        Tempo: {{ $p->due_date->format('d/m/Y') }}
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- Tombol Cetak Nota --}}
                                        <a href="{{ route('pembelian.cetak', $p) }}" target="_blank"
                                           class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-brand-600 transition"
                                           title="Cetak Faktur / Bukti Barang Masuk">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>

                                        {{-- Tombol Detail --}}
                                        <button type="button" @click='bukaDetail(@json($p))'
                                                class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition"
                                                title="Lihat Rincian Faktur">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>

                                        {{-- Tombol Bayar Hutang --}}
                                        @unless($p->sudahLunas())
                                        <button type="button" @click='bukaBayar(@json($p))'
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition inline-flex items-center gap-1"
                                                title="Catat Pembayaran Hutang">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                            <span>Bayar</span>
                                        </button>
                                        @endunless

                                        {{-- Tombol Batal Nota --}}
                                        <form method="POST" action="{{ route('pembelian.destroy', $p) }}" class="inline"
                                              onsubmit="return confirm('Batalkan pembelian {{ $p->purchase_no }}?\n\nStok yang masuk dari nota ini akan dikeluarkan kembali, dan uang yang sudah dibayar dikembalikan ke kas.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg border border-slate-200 hover:bg-red-50 text-slate-400 hover:text-red-600 transition" title="Batalkan Pembelian">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-12 text-slate-400">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <svg class="w-12 h-12 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                        <p class="text-sm font-semibold text-slate-600">Tidak ada transaksi ditemukan</p>
                                        <p class="text-xs text-slate-400">Silakan ubah kata kunci filter atau klik tab "+ Catat Pembelian Baru" untuk menginput stok.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $purchases->links() }}</div>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- TAB REKAP: AKUMULASI BARANG DIBELI (KULAKAN BARANG MASUK) --}}
    {{-- ======================================================== --}}
    <div x-show="aktifTab === 'rekap_produk'" x-cloak class="space-y-5">
        {{-- Filter Bar Khusus Rekap --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs">
            <form method="GET" action="{{ route('pembelian.index') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <input type="hidden" name="tab" value="rekap_produk">

                <div class="flex flex-wrap items-center gap-2.5 flex-1">
                    {{-- Pencarian Produk --}}
                    <div class="flex items-stretch rounded-xl border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden flex-1 min-w-[220px]">
                        <span class="inline-flex items-center px-3 bg-slate-50 text-slate-400 border-r border-slate-200 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama barang / barcode / SKU..." class="w-full px-3 py-2 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none">
                    </div>

                    {{-- Filter Supplier --}}
                    <select name="supplier_id" class="px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 bg-white text-slate-700 focus:border-brand-500 focus:outline-none shrink-0">
                        <option value="">Semua Pemasok / Supplier</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>

                    {{-- Filter Periode Cepat --}}
                    <select name="periode"
                            class="px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 bg-white text-slate-700 focus:border-brand-500 focus:outline-none shrink-0"
                            onchange="const el = document.getElementById('rekap-custom-dates'); if(this.value === 'custom') { el.classList.remove('hidden'); } else { el.classList.add('hidden'); }">
                        <option value="bulan_ini" {{ $rekapPeriode === 'bulan_ini' ? 'selected' : '' }}>Bulan Ini ({{ now()->translatedFormat('F Y') }})</option>
                        <option value="bulan_lalu" {{ $rekapPeriode === 'bulan_lalu' ? 'selected' : '' }}>Bulan Lalu</option>
                        <option value="7_hari" {{ $rekapPeriode === '7_hari' ? 'selected' : '' }}>7 Hari Terakhir</option>
                        <option value="hari_ini" {{ $rekapPeriode === 'hari_ini' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="semua" {{ $rekapPeriode === 'semua' ? 'selected' : '' }}>Semua Periode</option>
                        <option value="custom" {{ $rekapPeriode === 'custom' ? 'selected' : '' }}>Rentang Tanggal...</option>
                    </select>

                    {{-- Rentang Tanggal Custom --}}
                    <div id="rekap-custom-dates" class="{{ $rekapPeriode === 'custom' ? '' : 'hidden' }} flex items-center gap-1.5 shrink-0">
                        <input type="date" name="from" value="{{ $rekapFrom }}" class="px-2.5 py-1.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-700 focus:outline-none">
                        <span class="text-xs text-slate-400">s/d</span>
                        <input type="date" name="to" value="{{ $rekapTo }}" class="px-2.5 py-1.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-700 focus:outline-none">
                    </div>

                    {{-- Tombol Filter --}}
                    <button type="submit" class="btn btn-primary text-xs sm:text-sm py-2 px-4 inline-flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        <span>Terapkan</span>
                    </button>

                    @if(request()->filled('q') || request()->filled('supplier_id') || request()->filled('from') || (request()->filled('periode') && request('periode') !== 'bulan_ini'))
                        <a href="{{ route('pembelian.index', ['tab' => 'rekap_produk']) }}" class="btn btn-outline text-xs sm:text-sm py-2 px-3 text-slate-600 hover:text-slate-800">
                            Reset
                        </a>
                    @endif
                </div>

                {{-- Aksi Cetak & Ekspor --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" onclick="window.print()" class="btn btn-outline text-xs sm:text-sm py-2 px-3 inline-flex items-center gap-1.5 bg-white text-slate-700 hover:bg-slate-50 border-slate-300">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak</span>
                    </button>
                    @if(auth()->user()->can_access('laporan'))
                    <a href="{{ route('laporan.ekspor', array_merge(['jenis' => 'rekap-pembelian', 'format' => 'pdf'], request()->query())) }}"
                       target="_blank"
                       class="btn btn-outline text-xs sm:text-sm py-2 px-3 inline-flex items-center gap-1.5 bg-white text-slate-700 hover:bg-slate-50 border-slate-300">
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Unduh PDF</span>
                    </a>
                    <a href="{{ route('laporan.ekspor', array_merge(['jenis' => 'rekap-pembelian', 'format' => 'xlsx'], request()->query())) }}"
                       target="_blank"
                       class="btn btn-outline text-xs sm:text-sm py-2 px-3 inline-flex items-center gap-1.5 bg-white text-slate-700 hover:bg-slate-50 border-slate-300">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Unduh Excel</span>
                    </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Ringkasan 4 Kartu Metrik --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Nilai Kulakan</span>
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">
                    Rp {{ number_format($rekapRingkasan->total_nominal ?? 0, 0, ',', '.') }}
                </div>
                <div class="text-2xs text-slate-500 mt-1">Total uang modal kulakan belanja supplier</div>
            </div>

            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Jenis Barang Dibeli</span>
                    <div class="w-7 h-7 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-brand-700 tracking-tight">
                    {{ $rekapRingkasan->total_produk ?? 0 }} <span class="text-xs font-bold text-slate-500">Produk</span>
                </div>
                <div class="text-2xs text-slate-500 mt-1">Variasi item barang yang masuk di periode ini</div>
            </div>

            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Kuantitas Masuk</span>
                    <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">
                    {{ number_format($rekapRingkasan->total_base_qty ?? 0, 0, ',', '.') }} <span class="text-xs font-bold text-slate-500">Satuan Dasar</span>
                </div>
                <div class="text-2xs text-slate-500 mt-1">Akumulasi fisik barang masuk (tidak berkurang)</div>
            </div>

            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Faktur Terkait</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                </div>
                <div class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">
                    {{ $rekapRingkasan->total_faktur ?? 0 }} <span class="text-xs font-bold text-slate-500">Faktur/Nota</span>
                </div>
                <div class="text-2xs text-slate-500 mt-1">Transaksi nota supplier pada periode terpilih</div>
            </div>
        </div>

        {{-- Banner Penjelasan Khusus Petshop / Client --}}
        <div class="bg-blue-50 border border-blue-200/80 rounded-2xl p-4 flex items-start gap-3 text-xs text-blue-900 leading-relaxed shadow-2xs">
            <span class="text-xl shrink-0">📦</span>
            <div>
                <div class="font-bold text-sm text-blue-950 mb-0.5">Catatan Khusus Rekapitulasi Pembelian (Kulakan Barang Masuk)</div>
                <p class="text-blue-800">
                    Halaman ini merangkum seluruh barang yang Anda kulakan/beli dari faktur supplier dalam periode yang dipilih.
                    Kuantitas di bawah ini adalah <strong>akumulasi murni barang masuk</strong> (tidak berkurang oleh transaksi penjualan di kasir),
                    sehingga Anda dapat mengetahui dengan pasti berapa banyak stok pakan/barang yang sudah dibeli dan berapa modal yang dikeluarkan.
                </p>
            </div>
        </div>

        {{-- Tabel Data Rekapitulasi Produk --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-2xs uppercase tracking-wider">
                            <th class="py-3 px-3 w-12 text-center">No</th>
                            <th class="py-3 px-4">Nama Produk &amp; Kode</th>
                            <th class="py-3 px-4 text-center">Total Qty Kulakan</th>
                            <th class="py-3 px-4">Rincian Satuan Beli</th>
                            <th class="py-3 px-4 text-right">Rata-rata Modal</th>
                            <th class="py-3 px-4 text-right">Total Nilai Kulakan (Rp)</th>
                            <th class="py-3 px-4 text-center">Riwayat Faktur</th>
                            <th class="py-3 px-4 text-center">Stok Toko Saat Ini</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rekapProduk as $index => $item)
                        @php
                            $satuanList = $rincianSatuanMap[$item->product_id] ?? [];
                            $fakturList = $riwayatFakturMap[$item->product_id] ?? [];
                            $avgPrice = $item->total_base_qty > 0 ? round($item->total_nominal / $item->total_base_qty, 2) : 0;
                        @endphp
                        <tr x-data="{ bukaFaktur: false }" class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-3 text-center text-slate-400 text-xs">
                                {{ $rekapProduk->firstItem() + $index }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800 text-sm">{{ $item->nama_produk }}</div>
                                <div class="flex items-center gap-2 text-2xs text-slate-500 mt-0.5">
                                    @if($item->barcode)
                                        <span class="font-mono bg-slate-100 px-1.5 py-0.2 rounded text-slate-600">{{ $item->barcode }}</span>
                                    @endif
                                    @if($item->sku)
                                        <span class="text-slate-400">SKU: {{ $item->sku }}</span>
                                    @endif
                                    <span class="text-slate-400">Satuan dasar: {{ $item->satuan_dasar }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-brand-50 text-brand-700 font-extrabold text-sm border border-brand-200/60">
                                    {{ number_format($item->total_base_qty, 0, ',', '.') }} {{ $item->satuan_dasar }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if(count($satuanList) > 0)
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($satuanList as $sat)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                                                <span>{{ number_format($sat['total_qty'], 0, ',', '.') }}</span>
                                                <span class="text-slate-500 font-normal">{{ $sat['unit_label'] }}</span>
                                                @if($sat['conversion'] > 1)
                                                    <span class="text-2xs text-slate-400">(@ {{ $sat['conversion'] }} {{ $item->satuan_dasar }})</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-medium text-slate-600 text-xs sm:text-sm">
                                Rp {{ number_format($avgPrice, 0, ',', '.') }}
                                <div class="text-2xs text-slate-400">/ {{ $item->satuan_dasar }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="font-black text-slate-900 text-sm sm:text-base">
                                    Rp {{ number_format($item->total_nominal, 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if(count($fakturList) > 0)
                                    <button type="button"
                                            @click="bukaFaktur = !bukaFaktur"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold transition border border-slate-200 hover:border-brand-500 hover:text-brand-600 bg-white cursor-pointer">
                                        <span>{{ count($fakturList) }} Nota</span>
                                        <svg class="w-3.5 h-3.5 transition-transform" :class="bukaFaktur ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold {{ $item->sisa_stok_toko > 0 ? 'bg-slate-100 text-slate-700' : 'bg-red-100 text-red-700' }}">
                                    {{ number_format($item->sisa_stok_toko, 0, ',', '.') }} {{ $item->satuan_dasar }}
                                </span>
                            </td>
                        </tr>

                        {{-- Accordion Rincian Faktur Supplier --}}
                        @if(count($fakturList) > 0)
                        <tr x-show="bukaFaktur" x-cloak class="bg-brand-50/20 border-b border-slate-200">
                            <td colspan="8" class="py-3 px-6">
                                <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-2xs space-y-2">
                                    <div class="text-xs font-bold text-slate-700 flex items-center justify-between">
                                        <span>Rincian Faktur Pembelian: {{ $item->nama_produk }}</span>
                                        <span class="text-2xs text-slate-500">Terakhir dibeli: {{ $item->tgl_faktur_terakhir ? \Carbon\Carbon::parse($item->tgl_faktur_terakhir)->translatedFormat('d F Y') : '-' }}</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead>
                                                <tr class="text-2xs uppercase text-slate-400 border-b border-slate-100">
                                                    <th class="py-1.5 px-2">Tanggal</th>
                                                    <th class="py-1.5 px-2">No. Pembelian</th>
                                                    <th class="py-1.5 px-2">No. Faktur Supplier</th>
                                                    <th class="py-1.5 px-2">Pemasok</th>
                                                    <th class="py-1.5 px-2 text-right">Qty Beli</th>
                                                    <th class="py-1.5 px-2 text-right">Harga Beli</th>
                                                    <th class="py-1.5 px-2 text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach($fakturList as $fak)
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-1.5 px-2 text-slate-600">{{ \Carbon\Carbon::parse($fak['purchase_date'])->format('d/m/Y') }}</td>
                                                    <td class="py-1.5 px-2 font-mono font-bold text-brand-600">{{ $fak['purchase_no'] }}</td>
                                                    <td class="py-1.5 px-2 font-mono text-slate-700">{{ $fak['supplier_invoice_no'] ?: '-' }}</td>
                                                    <td class="py-1.5 px-2 text-slate-700">{{ $fak['supplier_name'] }}</td>
                                                    <td class="py-1.5 px-2 text-right font-bold text-slate-800">{{ number_format($fak['qty'], 0, ',', '.') }} {{ $fak['unit_label'] }}</td>
                                                    <td class="py-1.5 px-2 text-right text-slate-600">Rp {{ number_format($fak['price'], 0, ',', '.') }}</td>
                                                    <td class="py-1.5 px-2 text-right font-bold text-slate-900">Rp {{ number_format($fak['subtotal'], 0, ',', '.') }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                        @empty
                        <tr>
                            <td colspan="8" class="py-12 px-4 text-center">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-xl">📦</div>
                                    <p class="text-sm font-semibold text-slate-600">Belum ada barang dibeli pada periode ini</p>
                                    <p class="text-xs text-slate-400">Silakan ubah rentang tanggal/filter supplier, atau klik tab "+ Catat Pembelian Baru" untuk menginput faktur pembelian.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-200">
                {{ $rekapProduk->links() }}
            </div>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- TAB 3: WORKSPACE SPREADSHEET INPUT PEMBELIAN BARANG      --}}
    {{-- ======================================================== --}}
    <div x-show="aktifTab === 'input'" x-cloak class="space-y-4">
        <form method="POST" action="{{ route('pembelian.store') }}" @submit="if(baris.length === 0 || (baris.length === 1 && !baris[0].product_id)) { alert('Tambahkan minimal satu barang pembelian.'); $event.preventDefault(); }">
            @csrf

            {{-- 1. INFORMASI DOKUMEN FAKTUR & PEMASOK --}}
            <div class="bg-white rounded-xl border border-slate-300 p-3 sm:p-3.5 shadow-2xs">
                <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-brand-50 text-brand-700 flex items-center justify-center font-bold">
                            <svg width="14" height="14" style="width: 14px; height: 14px; flex-shrink: 0;" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">1. Dokumen Faktur &amp; Pemasok</span>
                    </div>
                    <span class="text-[11px] text-slate-400 hidden sm:inline">Data nota / surat jalan fisik dari distributor</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pemasok / Supplier</label>
                        <select name="supplier_id" x-model="supplierId" class="w-full text-xs rounded-lg border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-brand-500 font-medium py-1.5 px-2.5">
                            <option value="">- Tanpa pemasok (Beli Umum) -</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} {{ $s->phone ? "({$s->phone})" : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">No. Surat Jalan / Faktur Pemasok</label>
                        <input type="text" name="supplier_invoice_no" x-model="supplierInvoiceNo" class="w-full text-xs font-mono rounded-lg border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-brand-500 py-1.5 px-2.5" placeholder="Contoh: INV-SPL-2026/089">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Transaksi / Masuk</label>
                        <input type="date" name="purchase_date" x-model="purchaseDate" max="{{ now()->toDateString() }}" required class="w-full text-xs font-medium rounded-lg border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-brand-500 py-1.5 px-2.5">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Transaksi</label>
                        <input type="text" name="note" x-model="note" class="w-full text-xs rounded-lg border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-brand-500 py-1.5 px-2.5" placeholder="Contoh: Titipan sales, po kloter 1, dll">
                    </div>
                </div>
            </div>

            {{-- 2. SPREADSHEET PENERIMAAN BARANG --}}
            <div class="bg-white rounded-xl border border-slate-300 shadow-2xs">
                {{-- Spreadsheet Toolbar: Barcode Scanner & Tombol Katalog --}}
                <div class="p-2.5 sm:p-3 bg-slate-50/90 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-2.5">
                    {{-- Input Scanner Barcode & Tombol Modal Cari Produk --}}
                    <div class="flex items-center gap-2 flex-1 max-w-2xl">
                        <div class="relative flex-1">
                            <div class="flex items-stretch h-8 rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                                <span class="inline-flex items-center px-2.5 bg-slate-100 text-brand-600 border-r border-slate-200 shrink-0">
                                    <svg width="14" height="14" style="width: 14px; height: 14px; flex-shrink: 0;" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M4 6h2v12H4V6zm3 0h1v12H7V6zm2 0h2v12H9V6zm3 0h1v12h-1V6zm3 0h2v12h-2V6zm3 0h1v12h-1V6zm2 0h1v12h-1V6z"/>
                                    </svg>
                                </span>
                                <input type="text"
                                       x-ref="inputCari"
                                       x-model="cariQuery"
                                       @input="cariProdukList()"
                                       @keydown.enter.prevent="eksekusiCari()"
                                       @keydown.arrow-down.prevent="if (hasilCari.length) fokusHasilIndex = (fokusHasilIndex + 1) % hasilCari.length"
                                       @keydown.arrow-up.prevent="if (hasilCari.length) fokusHasilIndex = (fokusHasilIndex - 1 + hasilCari.length) % hasilCari.length"
                                       @keydown.escape="hasilCari = []; cariQuery = ''"
                                       placeholder="Scan barcode scanner atau ketik nama produk / SKU... (tekan Enter)"
                                       style="border: 0; outline: none; box-shadow: none; padding: 4px 8px;"
                                       class="w-full text-xs font-semibold text-slate-800 bg-white h-full">
                                <span class="inline-flex items-center px-2 bg-slate-50 text-[10px] font-semibold text-slate-500 border-l border-slate-200 shrink-0 select-none">
                                    Enter / Scan
                                </span>
                            </div>

                            {{-- Pesan Notifikasi Pindai Barcode --}}
                            <template x-if="pesanScan">
                                <div class="absolute left-0 right-0 z-40 mt-1 text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-2 shadow-md"
                                     :class="pesanScanError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200'">
                                    <template x-if="pesanScanError">
                                        <svg width="16" height="16" style="width: 16px; height: 16px; flex-shrink: 0;" class="w-4 h-4 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </template>
                                    <template x-if="!pesanScanError">
                                        <svg width="16" height="16" style="width: 16px; height: 16px; flex-shrink: 0;" class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </template>
                                    <span x-text="pesanScan"></span>
                                </div>
                            </template>

                            {{-- Dropdown Hasil Pencarian Autocomplete (Hanya jika mengetik >= 2 huruf) --}}
                            <div x-show="hasilCari.length > 0"
                                 @click.outside="hasilCari = []"
                                 class="absolute z-50 left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-slate-300 overflow-hidden max-h-72 overflow-y-auto divide-y divide-slate-100">
                                <div class="py-1.5 px-3 bg-slate-50 text-[11px] font-bold text-slate-500 border-b border-slate-200 flex items-center justify-between select-none">
                                    <span>Pilih Barang (Panah ↑ ↓ lalu Enter, atau Klik):</span>
                                    <span class="text-brand-600 font-semibold cursor-pointer hover:underline" @mousedown.prevent.stop="bukaModalCari()" @click="bukaModalCari()">Buka Modal Katalog (F2) &raquo;</span>
                                </div>
                                <template x-for="(p, idx) in hasilCari" :key="p.id">
                                    <div @mousedown.prevent="pilihHasilCari(p)"
                                         @click="pilihHasilCari(p)"
                                         :class="fokusHasilIndex === idx ? 'bg-blue-50 text-blue-900' : 'hover:bg-slate-50 text-slate-800'"
                                         class="p-2.5 cursor-pointer flex items-center justify-between gap-3 transition select-none group">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-bold text-xs sm:text-sm truncate flex items-center gap-1.5 group-hover:text-brand-600 transition">
                                                <span x-text="p.name"></span>
                                                <span class="text-[10px] text-slate-500 bg-slate-100 px-1 py-0.5 rounded font-mono" x-text="p.unit"></span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                                                <span x-show="p.barcode">Barcode: <strong class="text-slate-600 font-mono" x-text="p.barcode"></strong></span>
                                                <span x-show="p.sku">SKU: <span class="text-slate-600 font-mono" x-text="p.sku"></span></span>
                                                <span>Stok: <strong class="text-slate-700" x-text="qty(p.stock) + ' ' + p.unit"></strong></span>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0 flex items-center gap-2.5">
                                            <div class="flex flex-col items-end">
                                                <div class="text-xs">
                                                    <span class="text-slate-400">Modal Lama:</span>
                                                    <span class="font-semibold text-slate-700 font-mono">Rp <span x-text="angka(p.cost_price)"></span></span>
                                                </div>
                                                <div class="text-xs">
                                                    <span class="text-slate-400">Harga Jual:</span>
                                                    <span class="font-bold text-emerald-600 font-mono">Rp <span x-text="angka(p.sell_price)"></span></span>
                                                </div>
                                            </div>
                                            <template x-if="!p.units || p.units.length === 0">
                                                <button type="button"
                                                        @mousedown.prevent.stop="pilihHasilCari(p, 'base')"
                                                        @click.stop="pilihHasilCari(p, 'base')"
                                                        class="h-7 px-2.5 rounded-md text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 active:scale-95 inline-flex items-center justify-center shadow-2xs transition select-none"
                                                        style="height: 28px; font-size: 11px;">
                                                    + Tambah
                                                </button>
                                            </template>
                                            <template x-if="p.units && p.units.length > 0">
                                                <div class="flex items-center gap-1" @click.stop @mousedown.stop>
                                                    <button type="button"
                                                            @mousedown.prevent.stop="pilihHasilCari(p, 'base')"
                                                            @click.stop="pilihHasilCari(p, 'base')"
                                                            class="h-7 px-2 rounded-md text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 active:scale-95 inline-flex items-center justify-center shadow-2xs transition select-none"
                                                            style="height: 28px; font-size: 11px;"
                                                            :title="'Tambah satuan dasar ' + p.unit">
                                                        + <span x-text="p.unit"></span>
                                                    </button>
                                                    <template x-for="u in p.units" :key="u.id">
                                                        <button type="button"
                                                                @mousedown.prevent.stop="pilihHasilCari(p, 'unit_' + u.id)"
                                                                @click.stop="pilihHasilCari(p, 'unit_' + u.id)"
                                                                class="h-7 px-2 rounded-md text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 active:scale-95 inline-flex items-center justify-center shadow-2xs transition select-none"
                                                                style="height: 28px; font-size: 11px;"
                                                                :title="'Tambah satuan ' + u.name + ' (isi ' + u.conversion + ' ' + p.unit + ')'">
                                                            + <span x-text="u.name"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Tombol Buka Modal Cari Produk Lengkap (Standar POS F2) --}}
                        <button type="button"
                                @click="bukaModalCari()"
                                class="h-8 px-2.5 rounded-lg text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 active:scale-95 inline-flex items-center justify-center gap-1.5 shrink-0 shadow-2xs transition select-none whitespace-nowrap"
                                style="height: 32px; font-size: 11.5px; padding-left: 10px; padding-right: 10px;"
                                title="Buka katalog barang lengkap (F2)">
                            <svg width="14" height="14" style="width: 14px; height: 14px; min-width: 14px; flex-shrink: 0;" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span class="whitespace-nowrap">Cari di Katalog (F2)</span>
                        </button>
                    </div>

                    {{-- Indikator Metrik Spreadsheet --}}
                    <div class="flex items-center gap-2.5 text-xs text-slate-600 font-medium shrink-0 bg-white px-2.5 py-1 rounded-lg border border-slate-200 h-8">
                        <span>Item: <strong class="text-slate-900 font-bold" x-text="baris.length"></strong></span>
                        <span class="text-slate-300">|</span>
                        <span>Qty: <strong class="text-slate-900 font-bold" x-text="qty(totalQty())"></strong></span>
                        <span class="text-slate-300">|</span>
                        <span>Subtotal: <strong class="text-brand-700 font-bold font-mono">Rp <span x-text="angka(subtotal())"></span></strong></span>
                    </div>
                </div>

                {{-- Tabel Spreadsheet Bersih (Excel Style Grid) - Desain Kompak POS --}}
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-bold uppercase text-[10px] tracking-wider border-b border-slate-300 select-none">
                            <tr>
                                <th class="py-2 px-2 w-8 text-center border-r border-slate-200">#</th>
                                <th class="py-2 px-2 min-w-[220px] border-r border-slate-200">Nama Barang / Produk &amp; Stok</th>
                                <th class="py-2 px-2 w-28 sm:w-32 border-r border-slate-200">Satuan Beli</th>
                                <th class="py-2 px-2 w-28 text-center border-r border-slate-200">Qty Masuk</th>
                                <th class="py-2 px-2 w-32 text-right border-r border-slate-200">Harga Beli (Rp)</th>
                                <th class="py-2 px-2 w-32 text-right border-r border-slate-200">Harga Jual (Rp)</th>
                                <th class="py-2 px-2 w-24 text-center border-r border-slate-200">Margin (%)</th>
                                <th class="py-2 px-2 w-32 text-right border-r border-slate-200">Subtotal (Rp)</th>
                                <th class="py-2 px-1.5 w-10 text-center">Aksi</th>
                            </tr>
                        </thead>
                        {{-- State Saat Belum Ada Barang --}}
                        <tbody x-show="baris.length === 0" class="bg-white">
                            <tr>
                                <td colspan="9" class="py-10 px-4 text-center">
                                    <div class="max-w-md mx-auto space-y-2.5">
                                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto shadow-2xs border border-slate-200">
                                            <svg width="20" height="20" style="width: 20px; height: 20px; flex-shrink: 0;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-slate-800 text-xs sm:text-sm">Belum Ada Barang di Daftar Pembelian</h4>
                                            <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                                Gunakan barcode scanner atau ketik nama/SKU barang pada kolom pencarian di atas untuk memasukkan barang ke faktur ini.
                                            </p>
                                        </div>
                                        <div class="pt-1 flex justify-center">
                                            <button type="button"
                                                    @click="bukaModalCari()"
                                                    class="h-8 px-3 rounded-lg text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 hover:text-brand-600 border border-slate-300 active:scale-95 inline-flex items-center justify-center gap-1.5 shadow-2xs transition select-none whitespace-nowrap"
                                                    style="height: 32px; font-size: 11.5px;">
                                                <svg width="14" height="14" style="width: 14px; height: 14px; min-width: 14px; flex-shrink: 0;" class="w-3.5 h-3.5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                                <span class="whitespace-nowrap">Cari &amp; Tambah Barang</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        {{-- Daftar Baris Barang Pembelian (Mendukung Multi-Satuan per Produk) --}}
                        <template x-for="(row, index) in baris" :key="row._key">
                            <tbody class="divide-y divide-slate-100 border-b border-slate-300 bg-white">
                                {{-- Baris Utama Pembelian --}}
                                <tr class="hover:bg-blue-50/40 transition">
                                    {{-- Kolom # --}}
                                    <td class="py-2 px-2 text-center text-slate-400 font-mono font-bold border-r border-slate-200 align-top">
                                        <div class="h-8 flex items-center justify-center text-xs" x-text="index + 1"></div>
                                    </td>

                                    {{-- Kolom Nama Barang (Fiks Teks - Tanpa Dropdown) --}}
                                    <td class="py-2 px-2 border-r border-slate-200 align-top">
                                        <input type="hidden" :name="'items[' + row._key + '][product_id]'" :value="row.product_id">
                                        <div class="min-w-0">
                                            <div class="font-bold text-xs text-slate-900 leading-snug pt-1 truncate" x-text="row.product_name" :title="row.product_name"></div>
                                            <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-medium flex-wrap mt-0.5">
                                                <span class="bg-slate-100 text-slate-700 px-1 py-0.5 rounded font-mono">
                                                    Stok: <strong x-text="qty(produk(row.product_id)?.stock ?? 0) + ' ' + (produk(row.product_id)?.unit ?? '')"></strong>
                                                </span>
                                                <span x-show="row.barcode" class="font-mono text-slate-500" x-text="'[' + row.barcode + ']'"></span>
                                                <span x-show="row.sku && !row.barcode" class="font-mono text-slate-400" x-text="row.sku"></span>
                                                <span>Modal Lama: <strong class="text-slate-700 font-mono">Rp <span x-text="angka(hargaModalSaatIni(row))"></span></strong></span>
                                                <template x-if="row.other_units && row.other_units.length > 0">
                                                    <span class="text-[9.5px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1 py-0.5 rounded">
                                                        Multi-Satuan (+<span x-text="row.other_units.length"></span>)
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Kolom Satuan Beli --}}
                                    <td class="py-2 px-2 border-r border-slate-200 align-top">
                                        <select :name="'items[' + row._key + '][unit_type]'"
                                                x-model="row.unit_type"
                                                @change="gantiSatuan(row)"
                                                class="h-8 w-full text-xs font-semibold bg-white border border-slate-300 rounded-md px-2 focus:ring-1 focus:ring-brand-500 shadow-2xs">
                                            <template x-for="s in satuanProduk(row)" :key="s.value">
                                                <option :value="s.value" x-text="s.label + (s.conversion > 1 ? ' (x' + s.conversion + ' ' + (produk(row.product_id)?.unit || '') + ')' : '')"></option>
                                            </template>
                                        </select>
                                    </td>

                                    {{-- Kolom Qty Masuk --}}
                                    <td class="py-2 px-2 border-r border-slate-200 align-top">
                                        <div class="flex items-center justify-center gap-0.5 h-8">
                                            <button type="button" @click="ubahQty(row, -1)" class="w-6 h-8 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200 transition">-</button>
                                            <input type="text"
                                                   data-jpos-number
                                                   data-number-decimals="4"
                                                   :name="'items[' + row._key + '][qty]'"
                                                   x-number="row.qty"
                                                   required
                                                   class="w-14 h-8 font-bold text-xs text-slate-900 bg-white border border-slate-300 rounded-md text-center focus:ring-1 focus:ring-brand-500 shadow-2xs">
                                            <button type="button" @click="ubahQty(row, 1)" class="w-6 h-8 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200 transition">+</button>
                                        </div>
                                    </td>

                                    {{-- Kolom Harga Beli (Rp) --}}
                                    <td class="py-2 px-2 text-right border-r border-slate-200 align-top">
                                        <input type="text"
                                               data-jpos-number
                                               data-number-decimals="2"
                                               :name="'items[' + row._key + '][price]'"
                                               x-number="row.price"
                                               @input="ubahHargaBeli(row)"
                                               required
                                               class="h-8 w-full font-mono font-bold text-xs text-slate-900 bg-white border border-slate-300 rounded-md px-2 text-right focus:ring-1 focus:ring-brand-500 shadow-2xs">
                                        <template x-if="modalPerSatuanDasar(row) > 0 && konversi(row) > 1">
                                            <div class="text-[10px] text-blue-600 font-semibold font-mono mt-0.5 text-right whitespace-nowrap">
                                                = Rp <span x-text="angka(modalPerSatuanDasar(row))"></span> / <span x-text="produk(row.product_id)?.unit"></span>
                                            </div>
                                        </template>
                                    </td>

                                    {{-- Kolom Harga Jual (Rp) - LURUS dengan Kolom Lain --}}
                                    <td class="py-2 px-2 text-right border-r border-slate-200 align-top">
                                        <input type="text"
                                               data-jpos-number
                                               data-number-decimals="2"
                                               :name="'items[' + row._key + '][sell_price]'"
                                               x-number="row.sell_price"
                                               @input="hitungMarginDariJual(row)"
                                               class="h-8 w-full font-mono font-bold text-xs text-emerald-700 bg-white border border-slate-300 rounded-md px-2 text-right focus:ring-1 focus:ring-emerald-500 shadow-2xs">
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5 text-right whitespace-nowrap">
                                            <span>Jual:</span> <span class="font-semibold text-slate-600" x-text="'/' + labelSatuan(row)"></span>
                                        </div>
                                    </td>

                                    {{-- Kolom Margin (%) - LURUS dengan Kolom Lain --}}
                                    <td class="py-2 px-2 text-center border-r border-slate-200 align-top">
                                        <div class="flex items-stretch rounded-md border border-slate-300 overflow-hidden bg-white h-8 focus-within:ring-1 focus-within:ring-brand-500 shadow-2xs">
                                            <input type="number"
                                                   step="0.1"
                                                   x-model.number="row.margin_percent"
                                                   @input="hitungJualDariMargin(row)"
                                                   placeholder="0"
                                                   style="border: 0; outline: none; box-shadow: none;"
                                                   class="w-full h-full font-mono font-bold text-xs text-slate-800 bg-white px-1.5 text-right">
                                            <span class="inline-flex items-center px-1 bg-slate-100 text-[10px] font-bold text-slate-600 border-l border-slate-200 select-none">
                                                %
                                            </span>
                                        </div>
                                        <div class="text-[10px] font-mono mt-0.5 whitespace-nowrap text-center"
                                             :class="keuntunganPerUnit(row) < 0 ? 'text-red-600 font-semibold' : 'text-slate-500'">
                                            Untung: Rp <span x-text="angka(keuntunganPerUnit(row))"></span>
                                        </div>
                                    </td>

                                    {{-- Kolom Subtotal --}}
                                    <td class="py-2 px-2 text-right whitespace-nowrap border-r border-slate-200 align-top">
                                        <div class="h-8 flex items-center justify-end font-bold font-mono text-slate-900 text-xs">
                                            Rp <span x-text="angka(subtotalBaris(row))"></span>
                                        </div>
                                    </td>

                                    {{-- Kolom Aksi --}}
                                    <td class="py-2 px-1 text-center align-top">
                                        <div class="h-8 flex items-center justify-center">
                                            <button type="button" @click="hapusBaris(row._key)" class="p-1 text-slate-400 hover:text-red-600 rounded hover:bg-red-50 transition" title="Hapus Baris">
                                                <svg width="14" height="14" style="width: 14px; height: 14px; flex-shrink: 0;" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Sub-Rows untuk Pengaturan Satuan Lain pada Produk Multi-Satuan --}}
                                <template x-for="u in (row.other_units || [])" :key="u._key">
                                    <tr class="bg-indigo-50/20 hover:bg-indigo-50/40 border-t border-dashed border-indigo-200/80 transition">
                                        {{-- Kolom # Sub --}}
                                        <td class="py-1.5 px-2 text-center text-indigo-400 font-bold border-r border-slate-200 align-top">
                                            <div class="h-8 flex items-center justify-center font-mono text-sm">↳</div>
                                        </td>

                                        {{-- Kolom Info Satuan Lain --}}
                                        <td class="py-1.5 px-2 border-r border-slate-200 align-top">
                                            {{-- Hidden form bindings --}}
                                            <template x-if="Number(u.qty) > 0">
                                                <div class="hidden">
                                                    <input type="hidden" :name="'items[' + u._key + '][product_id]'" :value="row.product_id">
                                                    <input type="hidden" :name="'items[' + u._key + '][unit_type]'" :value="u.unit_type">
                                                </div>
                                            </template>
                                            <template x-if="Number(u.qty) <= 0">
                                                <div class="hidden">
                                                    <input type="hidden" :name="'other_unit_prices[' + u._key + '][product_id]'" :value="row.product_id">
                                                    <input type="hidden" :name="'other_unit_prices[' + u._key + '][unit_type]'" :value="u.unit_type">
                                                </div>
                                            </template>

                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-800 bg-indigo-100/90 border border-indigo-200 px-1.5 py-0.5 rounded">
                                                        Satuan Lain: <span x-text="u.label"></span>
                                                    </span>
                                                    <span class="text-[10px] text-indigo-600 font-mono font-semibold" x-text="'(1 ' + u.label + ' = ' + u.conversion + ' ' + (produk(row.product_id)?.unit ?? '') + ')'"></span>
                                                </div>
                                                <div class="text-[9.5px] text-slate-400 mt-0.5">
                                                    Atur harga jual satuan ini di sini. Masukkan Qty jika beli satuan ini sekaligus.
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Kolom Satuan Beli Sub --}}
                                        <td class="py-1.5 px-2 border-r border-slate-200 align-top">
                                            <div class="h-8 flex items-center">
                                                <span class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-800 font-bold text-xs w-full text-center truncate" x-text="u.label"></span>
                                            </div>
                                            <div class="text-[9.5px] text-indigo-500 font-semibold mt-0.5 text-center">Multi-Satuan</div>
                                        </td>

                                        {{-- Kolom Qty Masuk Sub --}}
                                        <td class="py-1.5 px-2 border-r border-slate-200 align-top">
                                            <div class="flex items-center justify-center gap-0.5 h-8">
                                                <button type="button" @click="ubahQtySub(u, -1)" class="w-6 h-8 rounded-md bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-300 shadow-2xs transition">-</button>
                                                <input type="text"
                                                       data-jpos-number
                                                       data-number-decimals="4"
                                                       :name="Number(u.qty) > 0 ? ('items[' + u._key + '][qty]') : null"
                                                       x-number="u.qty"
                                                       class="w-14 h-8 font-bold text-xs text-slate-900 bg-white border border-slate-300 rounded-md text-center focus:ring-1 focus:ring-brand-500 shadow-2xs">
                                                <button type="button" @click="ubahQtySub(u, 1)" class="w-6 h-8 rounded-md bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-300 shadow-2xs transition">+</button>
                                            </div>
                                            <div class="text-[9.5px] text-center font-mono mt-0.5">
                                                <span x-show="Number(u.qty) <= 0" class="text-slate-400 italic">0 = update harga</span>
                                                <span x-show="Number(u.qty) > 0" class="text-indigo-600 font-bold">+ Masuk Faktur</span>
                                            </div>
                                        </td>

                                        {{-- Kolom Harga Beli (Rp) Sub --}}
                                        <td class="py-1.5 px-2 text-right border-r border-slate-200 align-top">
                                            <input type="text"
                                                   data-jpos-number
                                                   data-number-decimals="2"
                                                   :name="Number(u.qty) > 0 ? ('items[' + u._key + '][price]') : ('other_unit_prices[' + u._key + '][cost_price]')"
                                                   x-number="u.price"
                                                   @input="ubahHargaBeliSub(u, row)"
                                                   class="h-8 w-full font-mono font-bold text-xs text-slate-900 bg-white border border-slate-300 rounded-md px-2 text-right focus:ring-1 focus:ring-brand-500 shadow-2xs">
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5 text-right whitespace-nowrap">
                                                <span>Modal /<span x-text="u.label"></span></span>
                                            </div>
                                        </td>

                                        {{-- Kolom Harga Jual (Rp) Sub --}}
                                        <td class="py-1.5 px-2 text-right border-r border-slate-200 align-top">
                                            <input type="text"
                                                   data-jpos-number
                                                   data-number-decimals="2"
                                                   :name="Number(u.qty) > 0 ? ('items[' + u._key + '][sell_price]') : ('other_unit_prices[' + u._key + '][sell_price]')"
                                                   x-number="u.sell_price"
                                                   @input="hitungMarginDariJualSub(u)"
                                                   class="h-8 w-full font-mono font-bold text-xs text-emerald-700 bg-white border border-slate-300 rounded-md px-2 text-right focus:ring-1 focus:ring-emerald-500 shadow-2xs">
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5 text-right whitespace-nowrap">
                                                <span>Jual:</span> <span class="font-semibold text-slate-600" x-text="'/' + u.label"></span>
                                            </div>
                                        </td>

                                        {{-- Kolom Margin (%) Sub --}}
                                        <td class="py-1.5 px-2 text-center border-r border-slate-200 align-top">
                                            <div class="flex items-stretch rounded-md border border-slate-300 overflow-hidden bg-white h-8 focus-within:ring-1 focus-within:ring-brand-500 shadow-2xs">
                                                <input type="number"
                                                       step="0.1"
                                                       x-model.number="u.margin_percent"
                                                       @input="hitungJualDariMarginSub(u)"
                                                       placeholder="0"
                                                       style="border: 0; outline: none; box-shadow: none;"
                                                       class="w-full h-full font-mono font-bold text-xs text-slate-800 bg-white px-1.5 text-right">
                                                <span class="inline-flex items-center px-1 bg-slate-100 text-[10px] font-bold text-slate-600 border-l border-slate-200 select-none">
                                                    %
                                                </span>
                                            </div>
                                            <div class="text-[10px] font-mono mt-0.5 whitespace-nowrap text-center"
                                                 :class="keuntunganSub(u) < 0 ? 'text-red-600 font-semibold' : 'text-slate-500'">
                                                Untung: Rp <span x-text="angka(keuntunganSub(u))"></span>
                                            </div>
                                        </td>

                                        {{-- Kolom Subtotal Sub --}}
                                        <td class="py-1.5 px-2 text-right whitespace-nowrap border-r border-slate-200 align-top">
                                            <div class="h-8 flex items-center justify-end font-bold font-mono text-xs"
                                                 :class="Number(u.qty) > 0 ? 'text-slate-900 font-bold' : 'text-slate-300'">
                                                Rp <span x-text="angka(subtotalSub(u))"></span>
                                            </div>
                                            <div class="text-[9.5px] text-right font-mono"
                                                 :class="Number(u.qty) > 0 ? 'text-emerald-600 font-semibold' : 'text-slate-400'">
                                                <span x-text="Number(u.qty) > 0 ? '+ Masuk Faktur' : 'Rp 0'"></span>
                                            </div>
                                        </td>

                                        {{-- Kolom Aksi Sub --}}
                                        <td class="py-1.5 px-1 text-center align-top">
                                            <div class="h-8 flex items-center justify-center">
                                                <template x-if="Number(u.qty) > 0">
                                                    <button type="button" @click="u.qty = 0" class="p-1 text-amber-500 hover:text-amber-700 rounded hover:bg-amber-50 transition" title="Kosongkan Qty (Harga jual tetap tersimpan)">
                                                        <svg width="14" height="14" style="width: 14px; height: 14px; flex-shrink: 0;" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    </button>
                                                </template>
                                                <template x-if="Number(u.qty) <= 0">
                                                    <span class="text-[9.5px] font-bold text-indigo-700 bg-indigo-100/80 border border-indigo-200 px-1 py-0.5 rounded" title="Satuan Tambahan Multi-Satuan">Multi</span>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                    </table>
                </div>

                {{-- Spreadsheet Footer Toolbar (Bar Tambah Baris Manual & Shortcut) --}}
                <div class="p-2.5 sm:p-3 bg-slate-50 border-t border-slate-300 flex flex-col sm:flex-row items-center justify-between gap-2.5">
                    <button type="button"
                            x-show="baris.length > 0"
                            x-cloak
                            @click="bukaModalCari()"
                            class="h-8 px-2.5 rounded-lg text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 hover:text-brand-600 border border-slate-300 active:scale-95 inline-flex items-center justify-center gap-1.5 shadow-2xs transition select-none whitespace-nowrap"
                            style="height: 32px; font-size: 11.5px; padding-left: 10px; padding-right: 10px;"
                            title="Buka modal katalog barang (F2)">
                        <svg width="14" height="14" style="width: 14px; height: 14px; min-width: 14px; flex-shrink: 0;" class="w-3.5 h-3.5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span class="whitespace-nowrap">+ Cari / Tambah Barang (F2)</span>
                    </button>

                    <div class="text-[11px] text-slate-500 flex items-center gap-2.5" :class="{'w-full justify-center': baris.length === 0}">
                        <span>Pintasan: <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[10px] font-mono font-bold">F2</kbd> Katalog</span>
                        <span>&bull;</span>
                        <span>Scan barcode kapan saja untuk otomatis menambah kuantiti</span>
                    </div>
                </div>
            </div>

            {{-- 3. PANEL SETTLEMENT & PEMBAYARAN (Input Group Bebas Tumpang Tindih) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                {{-- Panel Kiri: Biaya Lain & Metode Bayar --}}
                <div class="lg:col-span-7 bg-white rounded-xl p-4 sm:p-5 border border-slate-300 shadow-2xs space-y-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-700 pb-2 border-b border-slate-100 flex items-center justify-between">
                        <span>Biaya Tambahan &amp; Metode Pembayaran</span>
                        <span class="text-slate-400 font-normal">Pilih termin pembayaran</span>
                    </div>

                    {{-- Biaya Tambahan (Ongkir) dengan Prefix Rp Bebas Tumpang Tindih --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Biaya Tambahan (Ongkir / Bongkar Muat / Ekspedisi)</label>
                        <div class="flex items-stretch rounded-lg border border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                            <span class="inline-flex items-center px-3 bg-slate-100 text-slate-600 font-bold text-xs border-r border-slate-200 shrink-0 select-none">
                                Rp
                            </span>
                            <input type="text"
                                   data-jpos-number
                                   data-number-decimals="2"
                                   name="other_cost"
                                   x-number="biayaLain"
                                   style="border: 0; outline: none; box-shadow: none; padding: 8px 12px;"
                                   class="w-full text-sm font-bold text-slate-900 bg-white"
                                   placeholder="0">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">
                            * Otomatis dialokasikan ke harga pokok modal (HPP) setiap produk di atas secara proporsional.
                        </p>
                    </div>

                    {{-- Pilihan Cara Bayar: Tunai vs Hutang --}}
                    <div class="pt-2 border-t border-slate-100">
                        <label class="block text-xs font-bold text-slate-700 mb-2">Metode Pembayaran ke Pemasok</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="border-2 rounded-xl p-3.5 flex items-center gap-3 cursor-pointer transition"
                                   :class="caraBayar === 'tunai' ? 'border-brand-500 bg-brand-50/40 text-brand-900 ring-2 ring-brand-100' : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'">
                                <input type="radio" name="bayar" value="tunai" x-model="caraBayar" class="text-brand-600 focus:ring-brand-500">
                                <div>
                                    <div class="font-bold text-xs sm:text-sm">Tunai / Langsung Lunas</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Uang kas keluar sekarang dari laci toko.</div>
                                </div>
                            </label>

                            <label class="border-2 rounded-xl p-3.5 flex items-center gap-3 cursor-pointer transition"
                                   :class="caraBayar === 'hutang' ? 'border-amber-500 bg-amber-50/40 text-amber-900 ring-2 ring-amber-100' : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'">
                                <input type="radio" name="bayar" value="hutang" x-model="caraBayar" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <div class="font-bold text-xs sm:text-sm">Hutang / Bayar Tempo</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Dicatat sebagai tagihan tempo ke pemasok.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Form Tambahan Jika Hutang: DP dengan Prefix Rp Bebas Tumpang Tindih & Jatuh Tempo --}}
                    <template x-if="caraBayar === 'hutang'">
                        <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-amber-950 mb-1.5">Uang Muka / Bayar Sekarang (DP)</label>
                                <div class="flex items-stretch rounded-lg border border-amber-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 bg-white overflow-hidden shadow-2xs">
                                    <span class="inline-flex items-center px-3 bg-amber-100 text-amber-900 font-bold text-xs border-r border-amber-200 shrink-0 select-none">
                                        Rp
                                    </span>
                                    <input type="text"
                                           data-jpos-number
                                           data-number-decimals="2"
                                           data-number-empty="kosong"
                                           name="paid_amount"
                                           x-number="dibayar"
                                           style="border: 0; outline: none; box-shadow: none; padding: 8px 12px;"
                                           class="w-full text-sm font-bold text-slate-900 bg-white"
                                           placeholder="0 (jika tanpa DP)">
                                </div>
                                <p class="text-[10px] text-amber-800 mt-1">Kosongkan jika belum ada pembayaran uang muka.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-amber-950 mb-1.5">Tanggal Jatuh Tempo Pembayaran</label>
                                <input type="date" name="due_date" x-model="dueDate" class="w-full text-xs sm:text-sm font-semibold rounded-lg border-amber-300 bg-white focus:ring-2 focus:ring-amber-500 py-2 px-3">
                                <p class="text-[10px] text-amber-800 mt-1">Batas waktu pelunasan nota tempo.</p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Panel Kanan: Total POS Ringkasan & Tombol Simpan --}}
                <div class="lg:col-span-5 bg-white rounded-xl p-4 sm:p-5 border border-slate-300 shadow-2xs flex flex-col justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700 pb-2 border-b border-slate-100 mb-3 flex items-center justify-between">
                            <span>Ringkasan Nilai Faktur</span>
                            <span class="text-xs text-slate-400 font-mono" x-text="baris.length + ' item barang'"></span>
                        </div>

                        <div class="space-y-2.5 text-xs sm:text-sm">
                            <div class="flex justify-between text-slate-600">
                                <span>Subtotal Barang:</span>
                                <span class="font-semibold text-slate-800 font-mono">Rp <span x-text="angka(subtotal())"></span></span>
                            </div>

                            <div class="flex justify-between text-slate-600" x-show="Number(biayaLain) > 0">
                                <span>Biaya Ongkir / Lain:</span>
                                <span class="font-semibold text-slate-800 font-mono">Rp <span x-text="angka(biayaLain)"></span></span>
                            </div>

                            <div class="flex justify-between items-baseline pt-3 border-t-2 border-slate-200">
                                <span class="text-sm font-black text-slate-900 uppercase">TOTAL FAKTUR:</span>
                                <span class="text-2xl sm:text-3xl text-brand-600 font-black font-mono">Rp <span x-text="angka(total())"></span></span>
                            </div>

                            <template x-if="caraBayar === 'tunai'">
                                <div class="bg-emerald-50 text-emerald-900 border border-emerald-200 rounded-xl p-3 text-xs flex justify-between items-center font-bold">
                                    <span>Kas Toko Keluar (Lunas):</span>
                                    <span class="font-mono text-sm">Rp <span x-text="angka(total())"></span></span>
                                </div>
                            </template>

                            <template x-if="caraBayar === 'hutang'">
                                <div class="bg-amber-50 text-amber-900 border border-amber-200 rounded-xl p-3 space-y-1.5 text-xs font-medium">
                                    <div class="flex justify-between">
                                        <span>Dibayar Sekarang (DP):</span>
                                        <span class="font-bold font-mono">Rp <span x-text="angka(dibayar)"></span></span>
                                    </div>
                                    <div class="flex justify-between font-bold text-amber-950 pt-1 border-t border-amber-200 text-xs sm:text-sm">
                                        <span>Sisa Hutang Pemasok:</span>
                                        <span class="font-mono">Rp <span x-text="angka(Math.max(0, total() - (Number(dibayar) || 0)))"></span></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Tombol Eksekusi Simpan Faktur (Standar POS) --}}
                    <div class="pt-4 border-t border-slate-200 mt-4 space-y-2">
                        <button type="submit"
                                :disabled="baris.length === 0"
                                :class="baris.length === 0 ? 'opacity-50 cursor-not-allowed bg-slate-300' : 'bg-brand-600 hover:bg-brand-700 active:scale-[0.99]'"
                                class="w-full text-white font-bold py-3.5 px-4 rounded-xl shadow-md text-sm sm:text-base flex items-center justify-center gap-2 transition">
                            <svg width="20" height="20" style="width: 20px; height: 20px; flex-shrink: 0;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            <span>Simpan Faktur Pembelian</span>
                        </button>
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>* Stok gudang &amp; HPP langsung bertambah</span>
                            <span class="font-medium text-slate-500">Harga jual kasir otomatis diperbarui</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL POPUP CARI & TAMBAH BARANG PEMBELIAN               --}}
    {{-- ======================================================== --}}
    <div x-show="showModalCari"
         x-cloak
         class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-3 sm:p-6 backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="tutupModalCari()">
        <div @click.outside="tutupModalCari()"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] flex flex-col border border-slate-300 overflow-hidden">
            
            {{-- Header Modal --}}
            <div class="p-3 sm:p-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-600 border border-brand-200 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm sm:text-base text-slate-900">Katalog &amp; Pencarian Barang</h3>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800" x-text="products.length + ' Produk'"></span>
                        </div>
                        <p class="text-[11px] text-slate-500">Pilih produk dan satuan untuk langsung dimasukkan ke faktur pembelian.</p>
                    </div>
                </div>
                <button type="button" @click="tutupModalCari()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200 transition" title="Tutup (Esc)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Input Pencarian & Filter Cepat di dalam Modal --}}
            <div class="p-2.5 sm:p-3 border-b border-slate-200 bg-white space-y-2">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text"
                           x-ref="inputModalCari"
                           x-model="modalCariQuery"
                           @keydown.enter.prevent="if (getModalProdukList().length > 0) { tambahDariModal(getModalProdukList()[0], modalUnitPilihan[getModalProdukList()[0].id] || 'base'); }"
                           placeholder="Ketik nama barang, kode SKU, atau barcode... (tekan Enter atau klik baris untuk masukkan)"
                           class="w-full pl-9 pr-9 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 focus:bg-white focus:ring-1 focus:ring-brand-500 focus:border-brand-500 transition shadow-2xs">
                    <button type="button"
                            x-show="modalCariQuery"
                            @click="modalCariQuery = ''; $refs.inputModalCari?.focus()"
                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Toast Notifikasi Sukses Tambah Barang di Dalam Modal --}}
                <template x-if="modalToast">
                    <div class="px-3 py-1.5 bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-bold rounded-lg flex items-center justify-between shadow-xs">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span x-text="modalToast"></span>
                        </div>
                        <button type="button" @click="modalToast = ''" class="text-emerald-500 hover:text-emerald-800">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                {{-- Filter Status / Kategori Cepat --}}
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 overflow-x-auto">
                        <button type="button"
                                @click="modalFilter = 'semua'"
                                :class="modalFilter === 'semua' ? 'bg-brand-600 text-white font-bold shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                                class="text-xs py-1 px-2.5 sm:px-3 rounded-lg transition whitespace-nowrap">
                            Semua (<span x-text="products.length"></span>)
                        </button>
                        <button type="button"
                                @click="modalFilter = 'faktur'"
                                :class="modalFilter === 'faktur' ? 'bg-brand-600 text-white font-bold shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                                class="text-xs py-1 px-2.5 sm:px-3 rounded-lg transition whitespace-nowrap">
                            Sudah di Faktur (<span x-text="baris.length"></span>)
                        </button>
                        <button type="button"
                                @click="modalFilter = 'multi'"
                                :class="modalFilter === 'multi' ? 'bg-brand-600 text-white font-bold shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                                class="text-xs py-1 px-2.5 sm:px-3 rounded-lg transition whitespace-nowrap">
                            Multi-Satuan
                        </button>
                        <button type="button"
                                @click="modalFilter = 'kritis'"
                                :class="modalFilter === 'kritis' ? 'bg-brand-600 text-white font-bold shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                                class="text-xs py-1 px-2.5 sm:px-3 rounded-lg transition whitespace-nowrap">
                            Stok &le; 10
                        </button>
                    </div>
                    <div class="text-xs text-slate-400 font-medium shrink-0">
                        Menampilkan <strong class="text-slate-700 font-bold" x-text="getModalProdukList().length"></strong> produk <span class="text-slate-500 font-semibold">(Klik baris untuk menambah)</span>
                    </div>
                </div>
            </div>

            {{-- Tabel Produk Katalog (Data Table Standar POS) --}}
            <div class="flex-1 overflow-y-auto max-h-[58vh] overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[720px]">
                    <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10 text-[11px] font-bold text-slate-500 uppercase tracking-wider select-none shadow-2xs">
                        <tr>
                            <th class="py-2.5 px-3 w-10 text-center">#</th>
                            <th class="py-2.5 px-3 min-w-[200px]">Barang / Produk (Klik Baris)</th>
                            <th class="py-2.5 px-3 w-36 sm:w-44">Satuan Beli</th>
                            <th class="py-2.5 px-3 w-28 text-right">Stok Gudang</th>
                            <th class="py-2.5 px-3 w-32 text-right">Modal Lama</th>
                            <th class="py-2.5 px-3 w-32 text-right">Harga Jual</th>
                            <th class="py-2.5 px-3 w-36 text-center">Aksi Faktur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(p, pIdx) in getModalProdukList()" :key="p.id">
                            <tr @click="tambahDariModal(p, modalUnitPilihan[p.id] || 'base')"
                                :class="qtyDiFaktur(p.id, modalUnitPilihan[p.id] || 'base') > 0 ? 'bg-blue-50/50 hover:bg-blue-100/70' : 'hover:bg-slate-50'"
                                class="transition cursor-pointer select-none group">
                                {{-- Kolom # --}}
                                <td class="py-2.5 px-3 text-center text-xs font-mono text-slate-400 font-semibold align-middle" x-text="pIdx + 1"></td>

                                {{-- Kolom Produk --}}
                                <td class="py-2.5 px-3 align-middle">
                                    <div class="font-bold text-xs sm:text-sm text-slate-900 leading-snug group-hover:text-brand-600 transition" x-text="p.name"></div>
                                    <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5 flex-wrap">
                                        <span x-show="p.barcode" class="font-mono text-slate-500" x-text="'[' + p.barcode + ']'"></span>
                                        <span x-show="p.sku && !p.barcode" class="font-mono text-slate-400" x-text="p.sku"></span>
                                        <template x-if="(p.units || []).length > 0">
                                            <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                                Multi-Satuan (+<span x-text="p.units.length"></span>)
                                            </span>
                                        </template>
                                    </div>
                                </td>

                                {{-- Kolom Satuan Beli --}}
                                <td class="py-2.5 px-3 align-middle" @click.stop @mousedown.stop>
                                    <template x-if="p.units && p.units.length > 0">
                                        <select x-model="modalUnitPilihan[p.id]"
                                                class="h-8 w-full text-xs font-semibold bg-white border border-slate-300 rounded-lg px-2 focus:ring-1 focus:ring-brand-500 shadow-2xs">
                                            <option value="base" x-text="p.unit + ' (Dasar)'"></option>
                                            <template x-for="u in p.units" :key="u.id">
                                                <option :value="'unit_' + u.id" x-text="u.name + ' (x' + u.conversion + ')'"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="!p.units || p.units.length === 0">
                                        <span class="inline-block text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-1 rounded-md" x-text="p.unit"></span>
                                    </template>
                                </td>

                                {{-- Kolom Stok Gudang --}}
                                <td class="py-2.5 px-3 text-right align-middle font-mono">
                                    <div class="text-xs font-bold"
                                         :class="Number(p.stock) <= 0 ? 'text-red-600' : (Number(p.stock) <= 10 ? 'text-amber-600' : 'text-slate-800')"
                                         x-text="qty(p.stock) + ' ' + p.unit">
                                    </div>
                                    <div class="text-[10px] font-sans font-medium"
                                         :class="Number(p.stock) <= 0 ? 'text-red-500 font-bold' : (Number(p.stock) <= 10 ? 'text-amber-500' : 'text-slate-400')"
                                         x-text="Number(p.stock) <= 0 ? 'Habis' : (Number(p.stock) <= 10 ? 'Menipis' : 'Tersedia')">
                                    </div>
                                </td>

                                {{-- Kolom Modal Lama --}}
                                <td class="py-2.5 px-3 text-right align-middle font-mono text-xs">
                                    <div class="font-bold text-slate-800">
                                        Rp <span x-text="angka(modalHargaBeli(p, modalUnitPilihan[p.id] || 'base'))"></span>
                                    </div>
                                    <div class="text-[10px] font-sans text-slate-400">
                                        /<span x-text="labelUnitModal(p, modalUnitPilihan[p.id] || 'base')"></span>
                                    </div>
                                </td>

                                {{-- Kolom Harga Jual --}}
                                <td class="py-2.5 px-3 text-right align-middle font-mono text-xs">
                                    <div class="font-bold text-emerald-600">
                                        Rp <span x-text="angka(modalHargaJual(p, modalUnitPilihan[p.id] || 'base'))"></span>
                                    </div>
                                    <div class="text-[10px] font-sans text-slate-400">
                                        /<span x-text="labelUnitModal(p, modalUnitPilihan[p.id] || 'base')"></span>
                                    </div>
                                </td>

                                {{-- Kolom Aksi Faktur --}}
                                <td class="py-2.5 px-3 text-center align-middle whitespace-nowrap" @click.stop @mousedown.stop>
                                    <template x-if="qtyDiFaktur(p.id, modalUnitPilihan[p.id] || 'base') === 0">
                                        <button type="button"
                                                @click="tambahDariModal(p, modalUnitPilihan[p.id] || 'base')"
                                                class="h-8 px-3 rounded-lg text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 active:scale-95 inline-flex items-center justify-center gap-1.5 shadow-2xs transition">
                                            <svg class="w-3.5 h-3.5 shrink-0" style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            <span>+ Masukkan</span>
                                        </button>
                                    </template>
                                    <template x-if="qtyDiFaktur(p.id, modalUnitPilihan[p.id] || 'base') > 0">
                                        <div class="inline-flex items-center gap-1">
                                            <button type="button"
                                                    @click="kurangiDariModal(p, modalUnitPilihan[p.id] || 'base')"
                                                    title="Kurangi Qty"
                                                    class="h-8 w-8 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-300 active:scale-95 inline-flex items-center justify-center transition">
                                                -
                                            </button>
                                            <span class="h-8 px-2.5 rounded-lg text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-300 inline-flex items-center justify-center font-mono">
                                                <span x-text="qty(qtyDiFaktur(p.id, modalUnitPilihan[p.id] || 'base'))"></span>
                                            </span>
                                            <button type="button"
                                                    @click="tambahDariModal(p, modalUnitPilihan[p.id] || 'base')"
                                                    title="Tambah Qty"
                                                    class="h-8 w-8 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:scale-95 inline-flex items-center justify-center shadow-2xs transition">
                                                +
                                            </button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </template>

                        <template x-if="getModalProdukList().length === 0">
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" style="width: 40px; height: 40px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <p class="font-bold text-sm text-slate-700">Tidak ada produk ditemukan</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Coba gunakan kata kunci pencarian atau ubah filter kategori.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Footer Modal --}}
            <div class="p-3.5 sm:p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
                <div class="text-xs text-slate-600 font-medium">
                    <span>Di faktur saat ini: </span>
                    <strong class="text-slate-900 font-bold" x-text="baris.length + ' item'"></strong>
                    <span class="text-slate-300 mx-1">|</span>
                    <span>Total Qty: </span>
                    <strong class="text-brand-700 font-bold" x-text="qty(totalQty()) + ' unit'"></strong>
                </div>
                <button type="button"
                        @click="tutupModalCari()"
                        class="h-9 px-4 rounded-xl text-xs sm:text-sm font-bold text-white bg-slate-800 hover:bg-slate-900 inline-flex items-center gap-2 shadow-sm transition">
                    <span>Selesai &amp; Lanjut Faktur</span>
                    <span class="text-[11px] opacity-70 font-mono">(Esc)</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL DETAIL RINCIAN FAKTUR & BUKTI BARANG MASUK         --}}
    {{-- ======================================================== --}}
    <div x-show="showDetail" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto backdrop-blur-xs">
        <div @click.outside="showDetail=false" class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8 overflow-hidden border border-slate-200">
            <template x-if="detailNota">
                <div>
                    {{-- Header Modal --}}
                    <div class="p-6 pb-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 border border-brand-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-lg text-slate-900" x-text="'Faktur ' + detailNota.purchase_no"></h3>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="Number(detailNota.sisa_hutang) <= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                          x-text="Number(detailNota.sisa_hutang) <= 0 ? 'Lunas' : 'Belum Lunas / Hutang'">
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">Rincian lengkap transaksi penerimaan barang dan riwayat pembayaran hutang.</p>
                            </div>
                        </div>
                        <button type="button" @click="showDetail=false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-5">
                        {{-- Meta Info Grid --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 rounded-xl p-3.5 border border-slate-200/70 text-xs">
                            <div>
                                <span class="text-slate-400 block mb-0.5">Tanggal Beli</span>
                                <span class="font-bold text-slate-800" x-text="formatTanggal(detailNota.purchase_date)"></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">Pemasok</span>
                                <span class="font-bold text-slate-800" x-text="detailNota.supplier ? detailNota.supplier.name : 'Tanpa Pemasok'"></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">No. Faktur Pemasok</span>
                                <span class="font-bold font-mono text-slate-800" x-text="detailNota.supplier_invoice_no || '-'"></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block mb-0.5">Dicatat Oleh</span>
                                <span class="font-bold text-slate-800" x-text="detailNota.user ? detailNota.user.name : '-'"></span>
                            </div>
                        </div>

                        {{-- Tabel Rincian Barang --}}
                        <div>
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Daftar Barang Diterima</h4>
                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100/80 text-slate-700 font-semibold border-b border-slate-200">
                                        <tr>
                                            <th class="py-2.5 px-3 w-8 text-center">#</th>
                                            <th class="py-2.5 px-3">Nama Barang</th>
                                            <th class="py-2.5 px-3 text-center">Satuan / Konversi</th>
                                            <th class="py-2.5 px-3 text-right">Jumlah</th>
                                            <th class="py-2.5 px-3 text-right">Harga Beli</th>
                                            <th class="py-2.5 px-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(it, idx) in (detailNota.items || [])" :key="it.id || idx">
                                            <tr class="hover:bg-slate-50/60">
                                                <td class="py-2 px-3 text-center text-slate-400 font-medium" x-text="idx + 1"></td>
                                                <td class="py-2 px-3">
                                                    <div class="font-bold text-slate-900" x-text="it.product_name"></div>
                                                </td>
                                                <td class="py-2 px-3 text-center">
                                                    <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-medium" x-text="it.unit_label"></span>
                                                    <span x-show="Number(it.unit_conversion) > 1" class="text-[11px] text-slate-400 block mt-0.5" x-text="'(x' + qty(it.unit_conversion) + ' dasar)'"></span>
                                                </td>
                                                <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800" x-text="qty(it.qty)"></td>
                                                <td class="py-2 px-3 text-right font-mono text-slate-700" x-text="'Rp ' + angka(it.price)"></td>
                                                <td class="py-2 px-3 text-right font-mono font-bold text-slate-900" x-text="'Rp ' + angka(it.subtotal)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Ringkasan Keuangan & Biaya Lain --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                            {{-- Catatan / Jatuh Tempo --}}
                            <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200/70 text-xs space-y-2">
                                <div>
                                    <span class="text-slate-400 block">Catatan Transaksi:</span>
                                    <span class="text-slate-700 font-medium italic" x-text="detailNota.note || 'Tidak ada catatan.'"></span>
                                </div>
                                <div x-show="detailNota.due_date">
                                    <span class="text-slate-400 block">Jatuh Tempo Pembayaran:</span>
                                    <span class="font-semibold text-red-600" x-text="formatTanggal(detailNota.due_date)"></span>
                                </div>
                            </div>

                            {{-- Rincian Angka --}}
                            <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200/70 text-xs space-y-1.5">
                                <div class="flex justify-between text-slate-600">
                                    <span>Subtotal Barang:</span>
                                    <span class="font-semibold" x-text="'Rp ' + angka(detailNota.subtotal)"></span>
                                </div>
                                <div class="flex justify-between text-slate-600" x-show="Number(detailNota.other_cost) > 0">
                                    <span>Biaya Tambahan / Ongkir:</span>
                                    <span class="font-semibold" x-text="'Rp ' + angka(detailNota.other_cost)"></span>
                                </div>
                                <div class="flex justify-between text-slate-900 font-bold border-t border-slate-200 pt-1.5 text-sm">
                                    <span>Total Faktur:</span>
                                    <span x-text="'Rp ' + angka(detailNota.total)"></span>
                                </div>
                                <div class="flex justify-between text-emerald-700 font-semibold">
                                    <span>Sudah Dibayar:</span>
                                    <span x-text="'Rp ' + angka(detailNota.paid_amount)"></span>
                                </div>
                                <div class="flex justify-between text-base font-black border-t border-slate-200 pt-1.5"
                                     :class="Number(detailNota.sisa_hutang) > 0 ? 'text-amber-600' : 'text-emerald-600'">
                                    <span>Sisa Hutang:</span>
                                    <span x-text="'Rp ' + angka(detailNota.sisa_hutang)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Riwayat Pembayaran Hutang / Cicilan --}}
                        <div>
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Riwayat Pembayaran / Cicilan</h4>
                            <template x-if="detailNota.payments && detailNota.payments.length > 0">
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-100/80 text-slate-700 font-semibold border-b border-slate-200">
                                            <tr>
                                                <th class="py-2 px-3">Tanggal</th>
                                                <th class="py-2 px-3 text-right">Jumlah Bayar</th>
                                                <th class="py-2 px-3">Diterima Oleh</th>
                                                <th class="py-2 px-3">Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="pay in detailNota.payments" :key="pay.id">
                                                <tr class="hover:bg-slate-50/60">
                                                    <td class="py-2 px-3 font-medium text-slate-700" x-text="formatTanggal(pay.paid_at)"></td>
                                                    <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700" x-text="'Rp ' + angka(pay.amount)"></td>
                                                    <td class="py-2 px-3 text-slate-600" x-text="pay.user ? pay.user.name : '-'"></td>
                                                    <td class="py-2 px-3 text-slate-500 italic" x-text="pay.note || '-'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="!detailNota.payments || detailNota.payments.length === 0">
                                <div class="text-center py-3 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-slate-400 text-xs">
                                    <span x-show="Number(detailNota.sisa_hutang) <= 0">Pembelian ini dibayar lunas sekaligus pada saat dicatat.</span>
                                    <span x-show="Number(detailNota.sisa_hutang) > 0">Belum ada catatan cicilan untuk faktur hutang ini.</span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Footer Modal dengan Tombol Cetak Dokumen Resmi --}}
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showDetail=false" class="btn btn-outline text-xs sm:text-sm">
                                Tutup
                            </button>
                            <a :href="'{{ url('pembelian') }}/' + detailNota.id + '/cetak'" target="_blank"
                               class="btn btn-outline text-xs sm:text-sm inline-flex items-center gap-1.5 font-semibold text-slate-700">
                                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span>🖨️ Cetak Faktur Pembelian</span>
                            </a>
                        </div>

                        <template x-if="Number(detailNota.sisa_hutang) > 0">
                            <button type="button" @click="bukaBayar(detailNota)" class="btn btn-primary text-xs sm:text-sm inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                <span>Bayar Sisa Hutang Ini (Rp <span x-text="angka(detailNota.sisa_hutang)"></span>)</span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- MODAL BAYAR HUTANG KE PEMASOK                            --}}
    {{-- ======================================================== --}}
    <div x-show="showBayar" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-xs">
        <div @click.outside="showBayar=false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900">Catat Pembayaran Hutang</h3>
                        <p class="text-xs text-slate-500" x-text="notaBayar ? notaBayar.no + (notaBayar.supplier ? ' - ' + notaBayar.supplier : '') : ''"></p>
                    </div>
                </div>
                <button type="button" @click="showBayar=false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <template x-if="notaBayar">
                <form :action="'{{ url('pembelian') }}/' + notaBayar.id + '/bayar'" method="POST" class="space-y-4">
                    @csrf
                    {{-- Info Box Sisa Hutang --}}
                    <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-3.5 space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Total Faktur:</span>
                            <span class="font-semibold text-slate-800">Rp <span x-text="angka(notaBayar.total)"></span></span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Sudah Dibayar:</span>
                            <span class="font-semibold text-emerald-700">Rp <span x-text="angka(notaBayar.paid)"></span></span>
                        </div>
                        <div class="flex justify-between items-baseline pt-1.5 border-t border-amber-200/80">
                            <span class="font-bold text-slate-800">Sisa Hutang Aktif:</span>
                            <span class="text-base font-black text-amber-700">Rp <span x-text="angka(notaBayar.sisa)"></span></span>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="form-label text-xs mb-0 font-bold">Jumlah Bayar (Rp)</label>
                            <button type="button" @click="$refs.inputJumlahBayar.value = notaBayar.sisa; $refs.inputJumlahBayar.dispatchEvent(new Event('input'))" class="text-[11px] font-semibold text-brand-600 hover:underline">
                                Bayar Semua (Lunas)
                            </button>
                        </div>
                        <input type="text"
                               x-ref="inputJumlahBayar"
                               data-jpos-number
                               data-number-decimals="2"
                               name="amount"
                               x-number.oneway="notaBayar.sisa"
                               required
                               class="form-input text-base font-bold text-slate-900 w-full">
                    </div>

                    <div>
                        <label class="form-label text-xs font-semibold">Tanggal Pembayaran</label>
                        <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="form-input text-sm">
                    </div>

                    <div>
                        <label class="form-label text-xs font-semibold">Catatan Pembayaran</label>
                        <input type="text" name="note" class="form-input text-sm" placeholder="Contoh: Transfer BCA, Titip via Sales, dll">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showBayar=false" class="btn btn-outline text-sm">Batal</button>
                        <button type="submit" class="btn btn-primary text-sm font-bold">Simpan Pembayaran</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
@endsection
