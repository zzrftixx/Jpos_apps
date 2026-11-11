{{--
    KOMPONEN NOTIFIKASI & POP-UP PESANAN MEJA (SELF-ORDER BARCODE)
    Fitur:
    1. POP-UP MODAL BESAR langsung muncul di tengah layar kasir saat pesanan masuk.
    2. Banner terapung cadangan di pojok kanan atas.
    3. Bunyi bel pesanan masuk (Restaurant Chime) via Web Audio API murni (100% offline).
    4. Tombol aksi instan untuk kasir:
       - "⚡ Muat Kasir" (load langsung ke keranjang kasir aktif)
       - "🖨️ Cetak Dapur" (print kitchen order ticket)
       - "💳 Langsung Lunasi" (modal bayar cepat tanpa pindah halaman)
    5. Polling cepat setiap 1.8 detik & BroadcastChannel instan (0ms).
--}}

<div x-data="notifikasiPesananMeja()"
     x-init="init()"
     class="relative">

    {{-- =========================================================================
         1. POP-UP MODAL UTAMA DI TENGAH LAYAR (MODAL BESAR, JELAS, TIDAK BISA LEWAT)
         ========================================================================= --}}
    <div x-show="showModal && currentOrder"
         x-cloak
         style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 9999999 !important; background-color: rgba(0, 0, 0, 0.7) !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 1rem !important;">

        <div style="position: relative !important; width: 100% !important; max-width: 520px !important; background: #ffffff !important; border-radius: 1.25rem !important; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5) !important; border: 3px solid #f59e0b !important; overflow: hidden !important;"
             @click.outside="tutupModal()">

            {{-- Header Pop-up: Nuansa Oranye-Merah dengan Animasi Bel --}}
            <div style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 50%, #e11d48 100%) !important; color: #ffffff !important; padding: 1rem 1.25rem !important; display: flex !important; align-items: center !important; justify-content: space-between !important;">
                <div class="flex items-center gap-3">
                    <div style="font-size: 2rem !important; line-height: 1 !important; animation: bounce 1s infinite !important;">
                        🔔
                    </div>
                    <div>
                        <h3 style="font-weight: 900 !important; font-size: 1.125rem !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; margin: 0 !important; line-height: 1.2 !important; color: #ffffff !important;">
                            Pesanan Meja Baru Masuk!
                        </h3>
                        <p style="font-size: 0.75rem !important; color: #fef3c7 !important; margin-top: 0.25rem !important; margin-bottom: 0 !important; font-weight: 600 !important;">
                            Pelanggan baru saja memesan dari meja makan
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="putarBunyiBel()" title="Bunyikan ulang bel"
                            style="padding: 0.4rem 0.6rem !important; border-radius: 0.5rem !important; background: rgba(255,255,255,0.2) !important; color: #ffffff !important; font-weight: bold !important; cursor: pointer !important; border: none !important;">
                        🔊 Tes Bel
                    </button>
                    <button type="button" @click="tutupModal()" title="Tutup pop-up ini"
                            style="padding: 0.4rem 0.6rem !important; border-radius: 0.5rem !important; background: rgba(255,255,255,0.2) !important; color: #ffffff !important; font-size: 1.25rem !important; font-weight: bold !important; cursor: pointer !important; border: none !important; line-height: 1 !important;">
                        &times;
                    </button>
                </div>
            </div>

            {{-- Konten Pop-up: Nomor Meja, Pelanggan, Menu & Total --}}
            <div style="padding: 1.25rem !important; background-color: #fffbeb !important;">
                <template x-if="currentOrder">
                    <div>
                        {{-- Baris Meja & Waktu --}}
                        <div style="display: flex !important; align-items: center !important; justify-content: space-between !important; margin-bottom: 0.75rem !important; padding-bottom: 0.75rem !important; border-bottom: 1px solid #fde68a !important;">
                            <div class="flex items-center gap-2">
                                <span style="background-color: #f59e0b !important; color: #ffffff !important; font-size: 1.125rem !important; font-weight: 900 !important; padding: 0.35rem 0.85rem !important; border-radius: 0.75rem !important; box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important; display: inline-flex !important; align-items: center !important; gap: 0.35rem !important;">
                                    <span>🪑</span>
                                    <span x-text="currentOrder.table_no || 'Meja Tamu'"></span>
                                </span>
                                <span style="font-weight: 700 !important; font-size: 0.875rem !important; color: #1e293b !important;" x-text="currentOrder.customer_name"></span>
                            </div>
                            <div class="text-right">
                                <div style="font-size: 0.75rem !important; font-family: monospace !important; font-weight: bold !important; color: #64748b !important;" x-text="currentOrder.invoice_no"></div>
                                <div style="font-size: 0.75rem !important; color: #b45309 !important; font-weight: 700 !important;" x-text="currentOrder.time_ago || 'Baru saja'"></div>
                            </div>
                        </div>

                        {{-- Rincian Menu yang Dipesan --}}
                        <div style="background-color: #ffffff !important; border-radius: 0.75rem !important; border: 1px solid #e2e8f0 !important; padding: 0.75rem !important; margin-bottom: 0.75rem !important; max-height: 180px !important; overflow-y: auto !important;">
                            <div style="font-weight: 800 !important; font-size: 0.75rem !important; text-transform: uppercase !important; color: #64748b !important; margin-bottom: 0.5rem !important; display: flex !important; justify-content: space-between !important;">
                                <span>Menu yang Dipesan:</span>
                                <span x-text="(currentOrder.items_count || 0) + ' macam menu'"></span>
                            </div>

                            <div style="display: flex !important; flex-direction: column !important; gap: 0.5rem !important;">
                                <template x-for="(it, idx) in (currentOrder.items || [])" :key="idx">
                                    <div style="display: flex !important; justify-content: space-between !important; align-items: flex-start !important; font-size: 0.8125rem !important; border-bottom: 1px solid #f1f5f9 !important; padding-bottom: 0.4rem !important;">
                                        <div>
                                            <span style="font-weight: 900 !important; color: #1e293b !important;" x-text="it.qty + 'x '"></span>
                                            <span style="font-weight: 700 !important; color: #334155 !important;" x-text="it.product_name"></span>
                                            <template x-if="it.note">
                                                <div style="font-size: 0.6875rem !important; color: #b45309 !important; font-style: italic !important; padding-left: 1rem !important;" x-text="'Catatan: ' + it.note"></div>
                                            </template>
                                        </div>
                                        <span style="font-family: monospace !important; font-weight: 700 !important; color: #0f172a !important; margin-left: 0.5rem !important;" x-text="'Rp ' + Math.round(it.subtotal).toLocaleString('id-ID')"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Catatan Khusus Tamu jika ada --}}
                        <template x-if="currentOrder.note && !currentOrder.note.includes('[Self-Order')">
                            <div style="background-color: #fef3c7 !important; border: 1px solid #fcd34d !important; color: #92400e !important; padding: 0.5rem 0.75rem !important; border-radius: 0.5rem !important; font-size: 0.75rem !important; margin-bottom: 0.75rem !important;">
                                <strong>💬 Catatan Meja:</strong> <span x-text="currentOrder.note"></span>
                            </div>
                        </template>

                        {{-- Kotak Total Tagihan Besar --}}
                        <div style="background-color: #ecfdf5 !important; border: 1.5px solid #a7f3d0 !important; border-radius: 0.75rem !important; padding: 0.75rem 1rem !important; margin-bottom: 1rem !important; display: flex !important; align-items: center !important; justify-content: space-between !important;">
                            <div>
                                <span style="font-weight: 700 !important; font-size: 0.75rem !important; color: #065f46 !important; text-transform: uppercase !important;">Total Tagihan:</span>
                                <div style="font-size: 0.7rem !important; color: #047857 !important;">Menunggu konfirmasi / pelunasan kasir</div>
                            </div>
                            <div style="font-size: 1.5rem !important; font-weight: 900 !important; color: #065f46 !important; font-family: monospace !important;" x-text="currentOrder.total_formatted"></div>
                        </div>

                        {{-- Tombol Aksi Utama Kasir --}}
                        <div style="display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 0.5rem !important; margin-bottom: 0.5rem !important;">
                            {{-- 1. Muat ke Kasir --}}
                            <a :href="currentOrder.load_url"
                               @click="tutupModal()"
                               style="background-color: #2563eb !important; color: #ffffff !important; padding: 0.75rem 0.5rem !important; border-radius: 0.75rem !important; font-weight: 900 !important; font-size: 0.8125rem !important; text-align: center !important; text-decoration: none !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 0.4rem !important; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3) !important;">
                                <span>⚡</span>
                                <span>Muat ke Kasir</span>
                            </a>

                            {{-- 2. Langsung Lunasi --}}
                            <button type="button"
                                    @click="bukaPelunasan(currentOrder)"
                                    style="background-color: #059669 !important; color: #ffffff !important; padding: 0.75rem 0.5rem !important; border-radius: 0.75rem !important; font-weight: 900 !important; font-size: 0.8125rem !important; text-align: center !important; cursor: pointer !important; border: none !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 0.4rem !important; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.3) !important;">
                                <span>💳</span>
                                <span>Langsung Lunasi</span>
                            </button>
                        </div>

                        <div style="display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 0.5rem !important;">
                            {{-- 3. Cetak Tiket Dapur (KOT) --}}
                            <button type="button"
                                    @click="bukaStrukDapur(currentOrder.kitchen_url)"
                                    style="background-color: #ffffff !important; color: #334155 !important; border: 1.5px solid #cbd5e1 !important; padding: 0.6rem 0.5rem !important; border-radius: 0.75rem !important; font-weight: 700 !important; font-size: 0.75rem !important; text-align: center !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 0.4rem !important;">
                                <span>🖨️</span>
                                <span>Cetak Tiket Dapur</span>
                            </button>

                            {{-- 4. Tutup / Nanti --}}
                            <button type="button"
                                    @click="tutupModal()"
                                    style="background-color: #f1f5f9 !important; color: #64748b !important; border: 1px solid #e2e8f0 !important; padding: 0.6rem 0.5rem !important; border-radius: 0.75rem !important; font-weight: 700 !important; font-size: 0.75rem !important; text-align: center !important; cursor: pointer !important;">
                                <span>Nanti Saja</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         2. BANNER CADANGAN DI POJOK KANAN ATAS (JIKA MODAL DITUTUP)
         ========================================================================= --}}
    <div x-show="showBanner && currentOrder"
         x-cloak
         style="position: fixed !important; top: 1rem !important; right: 1rem !important; z-index: 9999995 !important; width: 100% !important; max-width: 390px !important; background: #ffffff !important; border-radius: 1rem !important; box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.3) !important; border: 2.5px solid #f59e0b !important; overflow: hidden !important;">

        {{-- Header Banner --}}
        <div style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%) !important; color: #ffffff !important; padding: 0.6rem 0.85rem !important; display: flex !important; align-items: center !important; justify-content: space-between !important;">
            <div class="flex items-center gap-2">
                <span style="font-size: 1.25rem !important;">🔔</span>
                <div>
                    <h4 style="font-weight: 900 !important; font-size: 0.8125rem !important; text-transform: uppercase !important; margin: 0 !important; color: #ffffff !important;">
                        Pesanan Meja Masuk!
                    </h4>
                    <span style="font-size: 0.6875rem !important; color: #fef3c7 !important;" x-text="currentOrder ? currentOrder.time_ago : 'Baru saja'"></span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" @click="showModal = true" title="Buka detail pop-up"
                        style="padding: 0.25rem 0.5rem !important; border-radius: 0.375rem !important; background: rgba(255,255,255,0.25) !important; color: #ffffff !important; font-size: 0.75rem !important; font-weight: bold !important; cursor: pointer !important; border: none !important;">
                    Lihat
                </button>
                <button type="button" @click="tutupBanner()" title="Tutup banner"
                        style="padding: 0.25rem 0.5rem !important; border-radius: 0.375rem !important; background: rgba(255,255,255,0.25) !important; color: #ffffff !important; font-size: 0.875rem !important; font-weight: bold !important; cursor: pointer !important; border: none !important;">
                    &times;
                </button>
            </div>
        </div>

        {{-- Isi Banner Singkat --}}
        <div style="padding: 0.75rem !important; background-color: #fffbeb !important;">
            <template x-if="currentOrder">
                <div>
                    <div style="display: flex !important; align-items: center !important; justify-content: space-between !important; margin-bottom: 0.5rem !important;">
                        <span style="background-color: #fef3c7 !important; border: 1px solid #fcd34d !important; color: #92400e !important; font-weight: 900 !important; font-size: 0.75rem !important; padding: 0.2rem 0.5rem !important; border-radius: 0.375rem !important;">
                            🪑 <span x-text="currentOrder.table_no"></span>
                        </span>
                        <span style="font-weight: 900 !important; font-size: 0.875rem !important; color: #047857 !important;" x-text="currentOrder.total_formatted"></span>
                    </div>

                    <div style="font-size: 0.75rem !important; color: #475569 !important; background: #ffffff !important; padding: 0.4rem 0.5rem !important; border-radius: 0.5rem !important; border: 1px solid #e2e8f0 !important; margin-bottom: 0.5rem !important;"
                         class="line-clamp-2" x-text="currentOrder.items_summary"></div>

                    <div style="display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 0.4rem !important;">
                        <a :href="currentOrder.load_url"
                           @click="tutupBanner()"
                           style="background-color: #2563eb !important; color: #ffffff !important; font-size: 0.75rem !important; font-weight: 800 !important; text-align: center !important; padding: 0.45rem !important; border-radius: 0.5rem !important; text-decoration: none !important;">
                            ⚡ Muat Kasir
                        </a>
                        <button type="button"
                                @click="bukaPelunasan(currentOrder)"
                                style="background-color: #059669 !important; color: #ffffff !important; font-size: 0.75rem !important; font-weight: 800 !important; text-align: center !important; padding: 0.45rem !important; border-radius: 0.5rem !important; border: none !important; cursor: pointer !important;">
                            💳 Lunasi
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- =========================================================================
         3. MODAL PELUNASAN CEPAT
         ========================================================================= --}}
    <div x-show="showPayModal" x-cloak
         style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; z-index: 10000000 !important; background-color: rgba(0, 0, 0, 0.6) !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 1rem !important;">
        <div style="width: 100% !important; max-width: 400px !important; background: #ffffff !important; border-radius: 1rem !important; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4) !important; border: 1px solid #cbd5e1 !important; padding: 1.25rem !important;"
             @click.outside="showPayModal = false">
            <div style="display: flex !important; align-items: center !important; justify-content: space-between !important; padding-bottom: 0.75rem !important; border-bottom: 1px solid #e2e8f0 !important;">
                <div class="flex items-center gap-2">
                    <div style="width: 2rem !important; height: 2rem !important; border-radius: 0.5rem !important; background-color: #d1fae5 !important; color: #047857 !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: bold !important;">
                        💳
                    </div>
                    <div>
                        <h4 style="font-weight: 900 !important; font-size: 0.875rem !important; color: #0f172a !important; margin: 0 !important;">Pelunasan Pesanan Meja</h4>
                        <p style="font-size: 0.75rem !important; color: #64748b !important; margin: 0 !important; font-weight: 600 !important;" x-text="payOrder ? (payOrder.invoice_no + ' (' + payOrder.table_no + ')') : ''"></p>
                    </div>
                </div>
                <button type="button" @click="showPayModal = false" style="background: none !important; border: none !important; font-size: 1.25rem !important; color: #94a3b8 !important; cursor: pointer !important;">
                    &times;
                </button>
            </div>

            <template x-if="payOrder">
                <form @submit.prevent="submitPelunasan()" style="margin-top: 1rem !important; display: flex !important; flex-direction: column !important; gap: 0.75rem !important;">
                    <div style="background-color: #fef3c7 !important; border: 1px solid #fcd34d !important; border-radius: 0.5rem !important; padding: 0.6rem 0.75rem !important; display: flex !important; justify-content: space-between !important; align-items: center !important;">
                        <span style="font-size: 0.75rem !important; font-weight: 700 !important; color: #92400e !important;">Sisa Tagihan:</span>
                        <strong style="font-size: 1rem !important; color: #78350f !important; font-family: monospace !important;" x-text="payOrder.remaining_formatted || ('Rp ' + Math.round(payOrder.remaining).toLocaleString('id-ID'))"></strong>
                    </div>

                    <div>
                        <label style="display: block !important; font-size: 0.75rem !important; font-weight: 700 !important; color: #334155 !important; margin-bottom: 0.25rem !important;">Jumlah Bayar (Rp):</label>
                        <input type="number" step="any" x-model="payAmount" required
                               style="width: 100% !important; height: 2.5rem !important; padding: 0 0.75rem !important; border-radius: 0.5rem !important; border: 1.5px solid #cbd5e1 !important; font-family: monospace !important; font-weight: 800 !important; font-size: 0.875rem !important;">
                    </div>

                    <div>
                        <label style="display: block !important; font-size: 0.75rem !important; font-weight: 700 !important; color: #334155 !important; margin-bottom: 0.25rem !important;">Metode Pembayaran:</label>
                        <select x-model="payMethod" style="width: 100% !important; height: 2.5rem !important; padding: 0 0.5rem !important; border-radius: 0.5rem !important; border: 1.5px solid #cbd5e1 !important; font-size: 0.75rem !important; font-weight: 700 !important; color: #334155 !important;">
                            @foreach(\App\Support\MetodeBayar::DAFTAR as $kunci => $labelMetode)
                                <option value="{{ $kunci }}">{{ $labelMetode }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: flex !important; justify-content: flex-end !important; gap: 0.5rem !important; padding-top: 0.5rem !important; border-top: 1px solid #f1f5f9 !important;">
                        <button type="button" @click="showPayModal = false" style="background: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #475569 !important; padding: 0.5rem 0.85rem !important; border-radius: 0.5rem !important; font-size: 0.75rem !important; font-weight: 700 !important; cursor: pointer !important;">
                            Batal
                        </button>
                        <button type="submit" :disabled="isPaying" style="background-color: #059669 !important; color: #ffffff !important; border: none !important; padding: 0.5rem 1rem !important; border-radius: 0.5rem !important; font-size: 0.75rem !important; font-weight: 800 !important; cursor: pointer !important;">
                            <span x-text="isPaying ? 'Menyimpan...' : 'Simpan Pembayaran'"></span>
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    {{-- =========================================================================
         4. DRAWER / DAFTAR SEMUA PESANAN MEJA
         ========================================================================= --}}
    <div x-show="showAllOrdersModal" x-cloak
         style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; z-index: 9999990 !important; background-color: rgba(0, 0, 0, 0.45) !important; display: flex !important; align-items: center !important; justify-content: flex-end !important;">
        <div style="width: 100% !important; max-width: 420px !important; height: 100% !important; background: #ffffff !important; box-shadow: -10px 0 30px rgba(0, 0, 0, 0.25) !important; display: flex !important; flex-direction: column !important; border-left: 1px solid #cbd5e1 !important;"
             @click.outside="showAllOrdersModal = false">
            <div style="padding: 1rem !important; background-color: #0f172a !important; color: #ffffff !important; display: flex !important; align-items: center !important; justify-content: space-between !important;">
                <div class="flex items-center gap-2">
                    <span style="font-size: 1.25rem !important;">🔔</span>
                    <div>
                        <h4 style="font-weight: 900 !important; font-size: 0.875rem !important; text-transform: uppercase !important; margin: 0 !important; color: #ffffff !important;">Pesanan Meja Masuk</h4>
                        <p style="font-size: 0.75rem !important; color: #cbd5e1 !important; margin: 0 !important;" x-text="recentOrders.length + ' pesanan belum selesai'"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="toggleSound()"
                            style="padding: 0.35rem 0.6rem !important; border-radius: 0.375rem !important; font-size: 0.75rem !important; font-weight: bold !important; cursor: pointer !important; border: 1px solid #cbd5e1 !important; background: transparent !important; color: #ffffff !important;">
                        <span x-text="soundEnabled ? '🔊 Suara Aktif' : '🔇 Suara Mati'"></span>
                    </button>
                    <button type="button" @click="showAllOrdersModal = false" style="background: none !important; border: none !important; color: #ffffff !important; font-size: 1.5rem !important; cursor: pointer !important;">
                        &times;
                    </button>
                </div>
            </div>

            <div style="flex: 1 1 0% !important; overflow-y: auto !important; padding: 1rem !important; display: flex !important; flex-direction: column !important; gap: 0.75rem !important;">
                <template x-for="ord in recentOrders" :key="ord.id">
                    <div style="background: #ffffff !important; border-radius: 0.75rem !important; border: 1.5px solid #e2e8f0 !important; padding: 0.75rem !important; display: flex !important; flex-direction: column !important; gap: 0.5rem !important;">
                        <div style="display: flex !important; align-items: flex-start !important; justify-content: space-between !important;">
                            <div>
                                <span style="background-color: #fef3c7 !important; border: 1px solid #fcd34d !important; color: #92400e !important; font-weight: 900 !important; font-size: 0.75rem !important; padding: 0.2rem 0.5rem !important; border-radius: 0.375rem !important;"
                                      x-text="'🪑 ' + (ord.table_no || 'Meja')"></span>
                                <span style="font-size: 0.75rem !important; font-weight: 600 !important; color: #475569 !important; margin-left: 0.35rem !important;" x-text="ord.customer_name"></span>
                            </div>
                            <span style="font-weight: 900 !important; font-size: 0.875rem !important; color: #047857 !important;" x-text="ord.total_formatted"></span>
                        </div>

                        <div style="font-size: 0.75rem !important; color: #475569 !important; background: #f8fafc !important; padding: 0.5rem !important; border-radius: 0.5rem !important; border: 1px solid #f1f5f9 !important;"
                             class="line-clamp-2" x-text="ord.items_summary"></div>

                        <div style="display: flex !important; align-items: center !important; justify-content: space-between !important; padding-top: 0.25rem !important; font-size: 0.6875rem !important; color: #94a3b8 !important;">
                            <span x-text="ord.invoice_no + ' &middot; ' + ord.time_ago"></span>
                            <div style="display: flex !important; align-items: center !important; gap: 0.35rem !important;">
                                <a :href="ord.load_url"
                                   @click="dismissOrder(ord.id); showAllOrdersModal = false;"
                                   style="background-color: #2563eb !important; color: #ffffff !important; font-size: 0.6875rem !important; font-weight: 800 !important; padding: 0.25rem 0.5rem !important; border-radius: 0.375rem !important; text-decoration: none !important;">
                                    Muat
                                </a>
                                <button type="button" @click="bukaStrukDapur(ord.kitchen_url)"
                                        style="background: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #334155 !important; font-size: 0.6875rem !important; font-weight: 700 !important; padding: 0.25rem 0.5rem !important; border-radius: 0.375rem !important; cursor: pointer !important;">
                                    Dapur
                                </button>
                                <button type="button" @click="bukaPelunasan(ord); showAllOrdersModal = false;"
                                        style="background-color: #059669 !important; color: #ffffff !important; font-size: 0.6875rem !important; font-weight: 800 !important; padding: 0.25rem 0.5rem !important; border-radius: 0.375rem !important; border: none !important; cursor: pointer !important;">
                                    Lunasi
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="recentOrders.length === 0" style="text-align: center !important; color: #94a3b8 !important; padding: 3rem 1rem !important; font-size: 0.875rem !important;">
                    Belum ada pesanan meja aktif saat ini.
                </div>
            </div>

            <div style="padding: 0.75rem !important; background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; text-align: center !important;">
                <a href="{{ route('kasir.waiting-list') }}" style="font-size: 0.75rem !important; font-weight: 700 !important; color: #2563eb !important; text-decoration: none !important;">
                    Buka Halaman Waiting List Lengkap &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function notifikasiPesananMeja() {
    return {
        recentOrders: [],
        totalWaiting: 0,
        lastKnownOrderId: null,
        chimedOrderIds: new Set(),
        currentOrder: null,
        showModal: false,          // Pop-up modal besar di tengah layar
        showBanner: false,         // Banner terapung di pojok kanan atas
        showPayModal: false,       // Modal pelunasan
        showAllOrdersModal: false, // Drawer daftar pesanan
        payOrder: null,
        payAmount: 0,
        payMethod: 'tunai',
        isPaying: false,
        soundEnabled: localStorage.getItem('jpos_table_sound_enabled') !== 'false',
        audioCtx: null,
        lastSig: '',
        pollTimer: null,

        init() {
            // Inisialisasi AudioContext pada interaksi pertama pengguna untuk kebijakan autoplay browser
            const unlockAudio = () => {
                this.initAudioContext();
            };
            window.addEventListener('click', unlockAudio, { once: true });
            window.addEventListener('keydown', unlockAudio, { once: true });
            window.addEventListener('touchstart', unlockAudio, { once: true });

            // Dengarkan perintah buka daftar pesanan dari header tombol kasir
            window.addEventListener('jpos:buka-pesanan-meja', () => {
                this.showAllOrdersModal = true;
            });

            // Dengarkan siaran instan lintas-tab / peramban lokal (0ms)
            window.addEventListener('storage', (e) => {
                if (e.key === 'jpos_table_order_event' && e.newValue) {
                    this.lastSig = '';
                    this.pollOrders(true);
                }
            });

            if (window.BroadcastChannel) {
                try {
                    const bc = new BroadcastChannel('jpos_table_orders');
                    bc.onmessage = () => {
                        this.lastSig = '';
                        this.pollOrders(true);
                    };
                } catch(e) {}
            }

            // Saat tab kembali terlihat, segera cek pesanan terbaru agar tidak tertinggal
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.pollOrders(true);
                }
            });

            // Tunda polling awal agar HTML & aset utama halaman selesai dimuat lebih dulu di LAN.
            // Di halaman Kasir polling lebih responsif (2.5 dtk); di modul lain cukup 8 detik.
            const isKasirPage = @json(request()->routeIs('kasir.*'));
            const initialDelay = isKasirPage ? 600 : 1800;
            const pollInterval = isKasirPage ? 2500 : 8000;

            setTimeout(() => this.pollOrders(true), initialDelay);

            this.pollTimer = setInterval(() => {
                this.pollOrders(false);
            }, pollInterval);
        },

        getDismissedOrderIds() {
            try {
                return JSON.parse(sessionStorage.getItem('jpos_dismissed_orders') || '[]');
            } catch (e) {
                return [];
            }
        },

        dismissOrder(id) {
            try {
                const ids = this.getDismissedOrderIds();
                if (!ids.includes(id)) {
                    ids.push(id);
                    sessionStorage.setItem('jpos_dismissed_orders', JSON.stringify(ids));
                }
            } catch (e) {}
        },

        initAudioContext() {
            try {
                if (!this.audioCtx) {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        this.audioCtx = new AudioContext();
                    }
                }
                if (this.audioCtx && this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }
            } catch (e) {
                console.warn('Gagal inisialisasi AudioContext:', e);
            }
        },

        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            localStorage.setItem('jpos_table_sound_enabled', this.soundEnabled ? 'true' : 'false');
            if (this.soundEnabled) {
                this.putarBunyiBel();
            }
        },

        /**
         * Bunyikan Bel Chime Restoran (D5 -> A5 -> D6 Chime) menggunakan Web Audio API murni.
         * 100% offline, tanpa unduh file audio, tidak pernah gagal karena 404.
         */
        putarBunyiBel() {
            if (!this.soundEnabled) return;
            try {
                this.initAudioContext();
                if (!this.audioCtx) return;

                const ctx = this.audioCtx;
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                const now = ctx.currentTime;

                // Nada 1: D5 (587.33 Hz) - Denting pembuka
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now);
                gain1.gain.setValueAtTime(0.4, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.6);

                // Nada 2: A5 (880.00 Hz) - Denting puncak lonceng
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880.00, now + 0.16);
                gain2.gain.setValueAtTime(0.5, now + 0.16);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.9);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.16);
                osc2.stop(now + 0.9);

                // Nada 3: D6 (1174.66 Hz) - Denting harmonik berkilau
                const osc3 = ctx.createOscillator();
                const gain3 = ctx.createGain();
                osc3.type = 'triangle';
                osc3.frequency.setValueAtTime(1174.66, now + 0.32);
                gain3.gain.setValueAtTime(0.35, now + 0.32);
                gain3.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
                osc3.connect(gain3);
                gain3.connect(ctx.destination);
                osc3.start(now + 0.32);
                osc3.stop(now + 1.2);

                if (navigator.vibrate) {
                    navigator.vibrate([200, 100, 200]);
                }
            } catch (err) {
                console.warn('Audio playback error:', err);
            }
        },

        async pollOrders(force = false) {
            if (!force && document.hidden) return;

            try {
                const baseUrl = '{{ route("meja.active-orders", [], false) }}';
                const url = this.lastSig ? `${baseUrl}?sig=${encodeURIComponent(this.lastSig)}` : baseUrl;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;

                const data = await res.json();
                if (!data.success) return;
                if (data.unchanged) return;
                this.lastSig = data.sig || '';

                this.recentOrders = data.recent_orders || [];
                this.totalWaiting = data.total_waiting || 0;

                const latestId = Number(data.latest_order_id || 0);

                // Kirim event global ke komponen kasir (FnB / Klasik / Retail) untuk sinkronisasi badge
                window.dispatchEvent(new CustomEvent('jpos:table-orders-updated', { detail: data }));

                // Cari pesanan waiting dari meja yang belum ditutup/di-dismiss kasir
                const dismissed = this.getDismissedOrderIds();
                const unhandledOrder = this.recentOrders.find(ord => !dismissed.includes(ord.id));

                if (unhandledOrder) {
                    this.currentOrder = unhandledOrder;
                    this.showModal = true;   // <--- POP-UP BESAR LANGSUNG MUNCUL DI TENGAH LAYAR
                    this.showBanner = true;  // <--- Banner cadangan pojok kanan atas juga aktif

                    // Bunyikan bel jika pesanan ini belum pernah dibunyikan belnya pada sesi ini
                    if (!this.chimedOrderIds.has(unhandledOrder.id)) {
                        this.chimedOrderIds.add(unhandledOrder.id);
                        this.putarBunyiBel();
                    }
                } else {
                    // Jika pesanan yang sedang ditampilkan sudah lunas/dibatalkan/dihapus, tutup pop-up
                    if (this.currentOrder && !this.recentOrders.some(ord => ord.id === this.currentOrder.id)) {
                        this.showModal = false;
                        this.showBanner = false;
                        this.currentOrder = null;
                    }
                }

                this.lastKnownOrderId = Math.max(this.lastKnownOrderId || 0, latestId);
            } catch (e) {
                // Abaikan kesalahan koneksi sementara saat polling di latar belakang
            }
        },

        tutupModal() {
            if (this.currentOrder) {
                this.dismissOrder(this.currentOrder.id);
            }
            this.showModal = false;
            // Banner di pojok kanan atas tetap dibiarkan ada jika kasir ingin melihat kembali
        },

        tutupBanner() {
            if (this.currentOrder) {
                this.dismissOrder(this.currentOrder.id);
            }
            this.showBanner = false;
        },

        bukaStrukDapur(url) {
            if (url) {
                window.open(url, '_blank', 'width=420,height=600');
            }
        },

        bukaPelunasan(order) {
            this.payOrder = order;
            this.payAmount = order.remaining || order.total || 0;
            this.payMethod = 'tunai';
            this.showModal = false;
            this.showPayModal = true;
        },

        async submitPelunasan() {
            if (!this.payOrder) return;
            this.isPaying = true;

            try {
                const url = '{{ url("kasir/waiting-list") }}/' + this.payOrder.id + '/pay';
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || '{{ csrf_token() }}';

                const formData = new FormData();
                formData.append('_token', token);
                formData.append('amount', this.payAmount);
                formData.append('method', this.payMethod);

                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                });

                if (res.ok) {
                    const data = await res.json();
                    const paidId = this.payOrder.id;
                    this.dismissOrder(paidId);
                    this.showPayModal = false;
                    this.showModal = false;
                    this.showBanner = false;

                    // Tampilkan notifikasi sukses dan opsi cetak struk
                    if (confirm((data.message || 'Pembayaran berhasil disimpan!') + '\n\nCetak struk pembayaran sekarang?')) {
                        if (data.receipt_url) {
                            window.open(data.receipt_url, '_blank');
                        }
                    }

                    // Muat ulang daftar pesanan
                    this.pollOrders();
                } else {
                    alert('Gagal memproses pembayaran. Silakan coba kembali.');
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan saat memproses pelunasan.');
            } finally {
                this.isPaying = false;
            }
        }
    };
}
</script>
