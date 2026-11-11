@extends('layouts.app')

@section('title', 'Pengelolaan Meja & Barcode Meja')

@section('content')
<div class="space-y-6" x-data="kelolaMejaApp()">
    {{-- Header Halaman --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-600 mb-1">
                <span>🍽️ MODUL RESTORAN / F&B</span>
                <span>&bull;</span>
                <span>SELF-ORDER SYSTEM</span>
            </div>
            <h1 class="text-xl md:text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-lg">🪑</span>
                Pengelolaan Meja &amp; Barcode Meja
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1 max-w-2xl">
                Kelola meja makan, cetak QR Code &amp; Barcode untuk tent card meja pelanggan, dan terima pesanan mandiri (self-order) yang langsung masuk ke kasir F&amp;B.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('kasir.index') }}" class="btn btn-outline border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold py-2 px-3 inline-flex items-center gap-1.5 shadow-xs">
                <span>🖥️</span> Buka Kasir F&amp;B
            </a>
            <a :href="'{{ route('meja.cetak-barcode') }}' + (selectedIds.length > 0 ? '?ids=' + selectedIds.join(',') : '')" target="_blank"
               class="btn btn-outline border-amber-300 text-amber-700 bg-amber-50/50 hover:bg-amber-100/60 text-xs font-semibold py-2 px-3 inline-flex items-center gap-1.5 shadow-xs">
                <span>🖨️</span> Cetak Barcode Meja (<span x-text="selectedIds.length > 0 ? selectedIds.length + ' Terpilih' : 'Semua'"></span>)
            </a>
            <button type="button" @click="showQuickGenerateModal = true" class="btn btn-outline border-blue-300 text-blue-700 bg-blue-50/50 hover:bg-blue-100/60 text-xs font-semibold py-2 px-3 inline-flex items-center gap-1.5 shadow-xs">
                <span>⚡</span> Buat Cepat Meja
            </button>
            <button type="button" @click="bukaModalTambah()" class="btn btn-primary text-xs font-semibold py-2 px-3.5 inline-flex items-center gap-1.5 shadow-xs">
                <span>+</span> Tambah Meja
            </button>
        </div>
    </div>

    {{-- Kartu Ringkasan Statistik --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Meja</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ $stats['total'] }}</div>
            <div class="text-[11px] text-slate-500 mt-0.5">Meja terdaftar di sistem</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-emerald-200/80 bg-emerald-50/20 shadow-2xs">
            <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Meja Tersedia
            </div>
            <div class="text-2xl font-black text-emerald-700 mt-1">{{ $stats['available'] }}</div>
            <div class="text-[11px] text-emerald-600 mt-0.5">Siap digunakan tamu</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-rose-200/80 bg-rose-50/20 shadow-2xs">
            <div class="text-[11px] font-bold text-rose-700 uppercase tracking-wider flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span> Meja Terisi
            </div>
            <div class="text-2xl font-black text-rose-700 mt-1">{{ $stats['occupied'] }}</div>
            <div class="text-[11px] text-rose-600 mt-0.5">Sedang ada tamu</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-amber-200/80 bg-amber-50/20 shadow-2xs">
            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Reservasi
            </div>
            <div class="text-2xl font-black text-amber-700 mt-1">{{ $stats['reserved'] }}</div>
            <div class="text-[11px] text-amber-600 mt-0.5">Dibooking tamu</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-purple-200/80 bg-purple-50/20 shadow-2xs col-span-2 sm:col-span-1">
            <div class="text-[11px] font-bold text-purple-700 uppercase tracking-wider flex items-center gap-1">
                <span>🔔</span> Pesanan Menunggu
            </div>
            <div class="text-2xl font-black text-purple-700 mt-1">{{ $stats['active_orders'] }}</div>
            <div class="text-[11px] text-purple-600 mt-0.5">Pesanan meja di kasir</div>
        </div>
    </div>

    {{-- Banner Panduan Pemesanan Mandiri Meja --}}
    <div class="bg-gradient-to-r from-amber-500/10 via-orange-500/5 to-amber-500/10 border border-amber-200 rounded-2xl p-4.5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xl shrink-0 shadow-xs">
                📲
            </div>
            <div>
                <h3 class="text-sm font-black text-slate-800">Bagaimana Cara Kerja Pesan Lewat Barcode Meja?</h3>
                <p class="text-xs text-slate-600 mt-0.5">
                    <strong>1.</strong> Cetak kartu barcode meja (tent card) lalu taruh di atas meja.
                    <strong>2.</strong> Tamu scan QR Code dengan kamera HP tanpa perlu install aplikasi.
                    <strong>3.</strong> Tamu memilih menu makanan/minuman dan klik Kirim Pesanan.
                    <strong>4.</strong> Pesanan otomatis muncul di Kasir F&amp;B dengan nomor meja yang tepat!
                </p>
            </div>
        </div>
        <div class="text-xs bg-white/90 border border-amber-200 text-slate-700 rounded-xl px-3.5 py-2 shrink-0">
            <div class="text-[10px] uppercase font-bold text-slate-400">Alamat Akses Jaringan Lokal (WiFi):</div>
            <div class="font-mono font-bold text-blue-700 mt-0.5">{{ url('/meja/KODE_MEJA') }}</div>
        </div>
    </div>

    {{-- Filter & Daftar Meja --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        {{-- Toolbar Filter --}}
        <div class="p-4 border-b border-slate-200/80 bg-slate-50/50 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <form action="{{ route('meja.index') }}" method="GET" class="flex flex-1 flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px] max-w-md">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, kode meja, area..."
                           class="form-input text-xs pl-8 pr-3 py-1.5 w-full bg-white">
                    <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
                </div>
                <select name="status" onchange="this.form.submit()" class="form-select text-xs py-1.5 bg-white">
                    <option value="">Semua Status</option>
                    <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Tersedia</option>
                    <option value="occupied" {{ request('status') === 'occupied' ? 'selected' : '' }}>Terisi</option>
                    <option value="reserved" {{ request('status') === 'reserved' ? 'selected' : '' }}>Reservasi</option>
                </select>
                @if(request('q') || request('status'))
                    <a href="{{ route('meja.index') }}" class="btn btn-outline text-xs py-1.5 px-2.5 text-slate-600 bg-white hover:bg-slate-100">
                        Reset
                    </a>
                @endif
            </form>

            <div class="flex items-center gap-2 text-xs">
                <button type="button" @click="pilihSemuaMeja()" class="text-blue-600 hover:text-blue-800 font-semibold">
                    <span x-text="semuaTerpilih ? 'Batalkan Pilihan' : 'Pilih Semua'"></span>
                </button>
                <span class="text-slate-300">|</span>
                <span class="text-slate-500 font-medium">{{ $tables->count() }} Meja</span>
            </div>
        </div>

        {{-- Grid Kartu Meja --}}
        <div class="p-4.5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($tables as $table)
                @php
                    $activeOrder = $activeOrdersByTable->get($table->name);
                    $orderCount = $activeOrder ? $activeOrder->count : 0;
                    $isOccupied = $table->status === 'occupied' || $orderCount > 0;
                @endphp
                <div class="bg-white rounded-xl border p-4 transition hover:shadow-md relative flex flex-col justify-between {{ $orderCount > 0 ? 'border-purple-300 bg-purple-50/10' : ($isOccupied ? 'border-rose-200' : 'border-slate-200') }}">
                    {{-- Header Kartu Meja --}}
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" value="{{ $table->id }}" x-model="selectedIds"
                                       class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 w-4 h-4 cursor-pointer">
                                <div>
                                    <h3 class="font-black text-slate-800 text-base leading-tight">{{ $table->name }}</h3>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                                            {{ $table->code }}
                                        </span>
                                        @if($table->capacity)
                                            <span class="text-[11px] text-slate-500 font-medium">👥 {{ $table->capacity }} Kursi</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Status Badge --}}
                            <div>
                                @if($orderCount > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-black bg-purple-100 text-purple-700 animate-pulse border border-purple-200">
                                        🔔 {{ $orderCount }} Pesanan
                                    </span>
                                @elseif($table->status === 'occupied')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                                        Terisi
                                    </span>
                                @elseif($table->status === 'reserved')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                                        Reservasi
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        Tersedia
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if($table->description)
                            <div class="text-[11px] text-slate-500 mt-2 line-clamp-1 italic">
                                📍 {{ $table->description }}
                            </div>
                        @endif

                        {{-- Barcode & QR Code Preview Meja --}}
                        <div class="mt-3.5 p-3 bg-slate-50 rounded-lg border border-slate-200/70 text-center">
                            <div class="flex items-center justify-center gap-3">
                                {{-- Kotak QR Code --}}
                                <div class="w-16 h-16 bg-white p-1 rounded border border-slate-200 flex items-center justify-center shadow-2xs">
                                    <div class="qrcode-preview w-full h-full" data-url="{{ $table->order_url }}"></div>
                                </div>
                                {{-- Kotak Barcode 1D --}}
                                <div class="flex-1 text-center overflow-hidden">
                                    <svg class="barcode-preview w-full" data-code="{{ $table->code }}" style="max-height: 38px;"></svg>
                                    <div class="text-[10px] text-slate-400 mt-0.5 font-mono">Scan Kasir: {{ $table->code }}</div>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 text-[10px]">URL Pesan Tamu:</span>
                                <a href="{{ $table->order_url }}" target="_blank" class="text-blue-600 hover:underline font-semibold flex items-center gap-0.5 text-[11px]">
                                    Buka Menu HP ↗
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Tombol Aksi Meja --}}
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-1 text-xs">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('meja.buka-kasir', $table) }}" class="btn btn-xs btn-outline text-amber-700 border-amber-300 hover:bg-amber-50 py-1 px-2 font-semibold" title="Buka / Hubungkan ke Kasir">
                                🖥️ Kasir
                            </a>
                            <a href="{{ route('meja.cetak-barcode') }}?ids={{ $table->id }}" target="_blank" class="btn btn-xs btn-outline text-slate-600 border-slate-300 hover:bg-slate-100 py-1 px-2" title="Cetak Kartu Meja">
                                🖨️ Cetak
                            </a>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="bukaModalEdit({{ json_encode($table) }})" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded" title="Edit Data Meja">
                                ✏️
                            </button>
                            <form action="{{ route('meja.destroy', $table) }}" method="POST" onsubmit="return confirm('Hapus meja {{ $table->name }}?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded" title="Hapus Meja">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-slate-500">
                    <div class="text-4xl mb-2">🪑</div>
                    <div class="font-bold text-slate-700">Belum Ada Meja yang Sesuai</div>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Klik tombol <strong>+ Tambah Meja</strong> atau gunakan <strong>⚡ Buat Cepat Meja</strong> untuk membuat meja secara instan.
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- MODAL TAMBAH / EDIT MEJA --}}
    <div x-show="showFormModal" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-4"
         @keydown.escape.window="showFormModal = false">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden" @click.away="showFormModal = false">
            <div class="p-4.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-800 text-base flex items-center gap-2">
                    <span x-text="editId ? '✏️ Edit Data Meja' : '➕ Tambah Meja Baru'"></span>
                </h3>
                <button type="button" @click="showFormModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form :action="editId ? '{{ url('kelola-meja') }}/' + editId : '{{ route('meja.store') }}'" method="POST" class="p-5 space-y-4">
                @csrf
                <template x-if="editId">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Meja <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" x-model="form.name" required placeholder="Contoh: Meja 01, Meja VIP 1, Outdoor 3..." class="form-input text-sm w-full">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kode / Barcode Meja <span class="text-rose-500">*</span></label>
                        <input type="text" name="code" x-model="form.code" required placeholder="Contoh: M01, VIP01..." class="form-input text-sm w-full font-mono uppercase">
                        <span class="text-[10px] text-slate-400">Kode unik untuk QR URL &amp; Barcode</span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kapasitas Kursi</label>
                        <input type="number" name="capacity" x-model="form.capacity" min="1" max="100" placeholder="4" class="form-input text-sm w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Area / Keterangan Lokasi</label>
                    <input type="text" name="description" x-model="form.description" placeholder="Contoh: Lantai 1 Area Non-Smoking, Dekat Jendela..." class="form-input text-sm w-full">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status Awal</label>
                        <select name="status" x-model="form.status" class="form-select text-sm w-full">
                            <option value="available">🟢 Tersedia</option>
                            <option value="occupied">🔴 Terisi</option>
                            <option value="reserved">🟡 Reservasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Urutan Tampil</label>
                        <input type="number" name="sort_order" x-model="form.sort_order" placeholder="0" class="form-input text-sm w-full">
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="modal_is_active" value="1" x-model="form.is_active" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <label for="modal_is_active" class="text-xs font-semibold text-slate-700 cursor-pointer">Meja Aktif &amp; Siap Menerima Pesanan</label>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="showFormModal = false" class="btn btn-outline text-xs py-2 px-3.5 text-slate-600">Batal</button>
                    <button type="submit" class="btn btn-primary text-xs py-2 px-4 font-bold" x-text="editId ? 'Simpan Perubahan' : 'Tambah Meja'"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL QUICK GENERATE MEJA --}}
    <div x-show="showQuickGenerateModal" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-4"
         @keydown.escape.window="showQuickGenerateModal = false">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden" @click.away="showQuickGenerateModal = false">
            <div class="p-4.5 border-b border-slate-100 bg-blue-50/50 flex items-center justify-between">
                <h3 class="font-black text-slate-800 text-base flex items-center gap-2">
                    <span>⚡ Buat Cepat Sekaligus Meja Baru</span>
                </h3>
                <button type="button" @click="showQuickGenerateModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form action="{{ route('meja.quick-generate') }}" method="POST" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Awalan Nama Meja (Prefix)</label>
                    <input type="text" name="prefix" value="Meja" required placeholder="Meja" class="form-input text-sm w-full">
                    <span class="text-[10px] text-slate-400">Contoh: 'Meja' akan menghasilkan Meja 01, Meja 02, dst.</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Mulai Nomor</label>
                        <input type="number" name="start_number" value="{{ ($tables->max('id') ?? 0) + 1 }}" min="1" required class="form-input text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Meja</label>
                        <input type="number" name="count" value="10" min="1" max="50" required class="form-input text-sm w-full">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kapasitas Kursi</label>
                        <input type="number" name="capacity" value="4" min="1" class="form-input text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Area / Keterangan</label>
                        <input type="text" name="description" value="Area Utama" class="form-input text-sm w-full">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="showQuickGenerateModal = false" class="btn btn-outline text-xs py-2 px-3.5 text-slate-600">Batal</button>
                    <button type="submit" class="btn btn-primary text-xs py-2 px-4 font-bold bg-blue-600 hover:bg-blue-700">⚡ Generate Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Skrip JSBarcode & QRCode untuk Preview Cepat di Halaman --}}
<script src="@aset('vendor/jsbarcode-3.11.6.all.min.js')"></script>
<script src="@aset('vendor/qrcode.min.js')"></script>

<script>
function kelolaMejaApp() {
    return {
        selectedIds: [],
        showFormModal: false,
        showQuickGenerateModal: false,
        editId: null,
        form: {
            name: '',
            code: '',
            capacity: 4,
            description: '',
            status: 'available',
            sort_order: 0,
            is_active: true
        },

        get semuaTerpilih() {
            const semuaId = @json($tables->pluck('id'));
            return semuaId.length > 0 && this.selectedIds.length === semuaId.length;
        },

        pilihSemuaMeja() {
            const semuaId = @json($tables->pluck('id'));
            if (this.semuaTerpilih) {
                this.selectedIds = [];
            } else {
                this.selectedIds = [...semuaId];
            }
        },

        bukaModalTambah() {
            this.editId = null;
            this.form = {
                name: '',
                code: '',
                capacity: 4,
                description: '',
                status: 'available',
                sort_order: {{ ($tables->max('sort_order') ?? 0) + 1 }},
                is_active: true
            };
            this.showFormModal = true;
        },

        bukaModalEdit(table) {
            this.editId = table.id;
            this.form = {
                name: table.name,
                code: table.code,
                capacity: table.capacity || 4,
                description: table.description || '',
                status: table.status,
                sort_order: table.sort_order || 0,
                is_active: !!table.is_active
            };
            this.showFormModal = true;
        },

        init() {
            this.$nextTick(() => {
                // Render Barcode 1D preview
                document.querySelectorAll('.barcode-preview').forEach(el => {
                    const code = el.dataset.code;
                    if (code && typeof JsBarcode === 'function') {
                        try {
                            JsBarcode(el, code, {
                                format: 'CODE128',
                                displayValue: false,
                                height: 26,
                                margin: 0,
                                background: 'transparent'
                            });
                        } catch (e) {
                            console.error('JsBarcode preview error:', e);
                        }
                    }
                });

                // Render QR Code preview
                document.querySelectorAll('.qrcode-preview').forEach(el => {
                    const url = el.dataset.url;
                    if (url && typeof QRCode === 'function') {
                        try {
                            el.innerHTML = '';
                            new QRCode(el, {
                                text: url,
                                width: 56,
                                height: 56,
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        } catch (e) {
                            console.error('QRCode preview error:', e);
                        }
                    }
                });
            });
        }
    };
}
</script>
@endsection
