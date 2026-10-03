<!DOCTYPE html>
<html lang="id" x-data="{ sidebarOpen: false, showPromoShiftModal: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</title>
    <link rel="icon" type="image/png" href="{{ !empty($storeProfile['logo']) ? url('media/'.$storeProfile['logo']) : asset('images/logo-jpos.png') }}">

    @include('partials.head-assets')
    <style>
        [x-cloak]{display:none!important}
        @view-transition { navigation: auto; }
        ::view-transition-old(root),
        ::view-transition-new(root) {
            animation-duration: 90ms;
        }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-800 antialiased" style="font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

<div class="flex h-screen overflow-hidden">
    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed z-40 inset-y-0 left-0 w-64 bg-white border-r border-slate-200 text-slate-700 shadow-xl lg:shadow-none transform transition-transform flex flex-col {{ request()->routeIs('kasir.index') ? '' : 'lg:translate-x-0 lg:static' }}">
        <div class="flex items-center justify-between px-4 h-16 border-b border-slate-200/80 bg-slate-50/50 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                @if(!empty($storeProfile['logo']))
                    <img src="{{ url('media/'.$storeProfile['logo']) }}" alt="{{ $storeProfile['name'] ?? 'Logo' }}" class="w-10 h-10 rounded-xl object-cover border border-slate-200/80 shadow-2xs shrink-0">
                @else
                    <div class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center font-bold text-base shadow-xs shrink-0">
                        {{ strtoupper(substr($storeProfile['name'] ?? 'J', 0, 1)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="font-bold text-slate-900 text-sm leading-tight truncate">{{ $storeProfile['name'] ?? 'JPOS' }}</div>
                    <div class="text-[11px] text-brand-600 font-semibold leading-tight mt-0.5">{{ !empty($storeProfile['name']) ? 'JPOS Point of Sale' : 'JaylaTech POS' }}</div>
                </div>
            </div>
            <button type="button" @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition" title="Tutup Menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        {{-- data-sidebar-nav dibaca public/vendor/jpos-sidebar.js untuk mengingat posisi
             gulirnya antar halaman. Tanpa itu, menu melompat balik ke atas setiap kali
             halaman berpindah - dan menu ini cukup panjang sehingga Laporan, Manajemen,
             dan Pengaturan harus digulir ulang setiap kali. --}}
        <nav data-sidebar-nav class="flex-1 overflow-y-auto py-3 px-3 space-y-1 text-sm">

            @php $u = auth()->user(); @endphp

            @if($u && $u->can_access('dashboard'))
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">
                <x-icon.grid /> Dashboard
            </a>
            @endif

            @if($u && ($u->can_access('produk') || $u->can_access('kategori') || $u->can_access('satuan') || $u->can_access('supplier') || $u->can_access('pelanggan')))
            <div class="nav-group-title flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Master Data</div>
            @if($u->can_access('produk'))
            <a href="{{ route('produk.index') }}" class="nav-link {{ request()->routeIs('produk.*') ? 'nav-active' : '' }}"><x-icon.box /> Produk</a>
            @endif
            @if($u->can_access('kategori'))
            <a href="{{ route('kategori.index') }}" class="nav-link {{ request()->routeIs('kategori.*') ? 'nav-active' : '' }}"><x-icon.tag /> Kategori</a>
            @endif
            @if($u->can_access('satuan'))
            <a href="{{ route('satuan.index') }}" class="nav-link {{ request()->routeIs('satuan.*') ? 'nav-active' : '' }}"><x-icon.tag /> Satuan</a>
            @endif
            @if($u->can_access('planogram'))
            <a href="{{ route('planogram.index') }}" class="nav-link {{ request()->routeIs('planogram.*') ? 'nav-active' : '' }}"><x-icon.grid /> Planogram</a>
            @endif
            @if($u->can_access('supplier'))
            <a href="{{ route('supplier.index') }}" class="nav-link {{ request()->routeIs('supplier.*') ? 'nav-active' : '' }}"><x-icon.truck /> Supplier</a>
            @endif
            @if($u->can_access('pelanggan'))
            <a href="{{ route('pelanggan.index') }}" class="nav-link {{ request()->routeIs('pelanggan.*') ? 'nav-active' : '' }}"><x-icon.users /> Pelanggan</a>
            @endif
            @endif

            @if($u && ($u->can_access('kasir') || $u->can_access('shift') || $u->can_access('retur') || $u->can_access('pembelian') || $u->can_access('kas')))
            <div class="nav-group-title flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Transaksi</div>
            @if($u->can_access('kasir'))
            <a href="{{ route('kasir.index') }}" class="nav-link {{ request()->routeIs('kasir.index') ? 'nav-active' : '' }}"><x-icon.cart /> Modul Kasir</a>
            @if(\App\Models\Setting::shiftKasirEnabled())
            <a href="{{ route('shift.index') }}" class="nav-link {{ request()->routeIs('shift.*') ? 'nav-active' : '' }}">
                <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; flex-shrink: 0;" class="w-4.5 h-4.5 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Shift Kasir
                @php $hasOpenShift = isset($activeShift) ? ($activeShift !== null) : \App\Models\CashierShift::where('status', 'open')->where('user_id', $u->id)->exists(); @endphp
                @if($hasOpenShift)
                    <span class="ml-auto w-2 h-2 rounded-full bg-emerald-500" title="Shift Aktif Terbuka"></span>
                @endif
            </a>
            @else
            <button type="button" @click="showPromoShiftModal = true" class="nav-link w-full text-left flex items-center justify-between group cursor-pointer">
                <span class="flex items-center gap-2">
                    <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; flex-shrink: 0;" class="w-4.5 h-4.5 shrink-0 text-slate-400 group-hover:text-amber-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Shift Kasir</span>
                </span>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    PRO
                </span>
            </button>
            @endif
            <a href="{{ route('kasir.waiting-list') }}" class="nav-link {{ request()->routeIs('kasir.waiting-list*') ? 'nav-active' : '' }}">
                <x-icon.receipt /> Pesanan / Waiting List
                @php $waitingCount = isset($waitingCount) ? $waitingCount : (isset($jumlahPesanan) ? $jumlahPesanan : \App\Models\Sale::pesanan()->count()); @endphp
                @if($waitingCount > 0)
                    <span class="ml-auto bg-amber-500 text-white text-[10px] font-bold rounded-full px-2 py-0.5 leading-none">{{ $waitingCount }}</span>
                @endif
            </a>
            <a href="{{ route('barcode.index') }}" class="nav-link {{ request()->routeIs('barcode.*') ? 'nav-active' : '' }}"><x-icon.barcode /> Cetak Barcode</a>
            @endif
            @if($u->can_access('retur'))
            <a href="{{ route('retur.index') }}" class="nav-link {{ request()->routeIs('retur.*') ? 'nav-active' : '' }}"><x-icon.return /> Retur</a>
            @endif
            @if($u->can_access('pembelian'))
            <a href="{{ route('pembelian.index') }}" class="nav-link {{ request()->routeIs('pembelian.*') ? 'nav-active' : '' }}">
                <x-icon.truck /> Pembelian &amp; Restock
                @php $hutangCount = \App\Models\Purchase::where('sisa_hutang', '>', 0)->count(); @endphp
                @if($hutangCount > 0)
                    <span class="ml-auto bg-orange-500 text-white text-[10px] font-bold rounded-full px-2 py-0.5 leading-none">{{ $hutangCount }}</span>
                @endif
            </a>
            @endif
            @if($u->can_access('kas'))
            <a href="{{ route('kas.index') }}" class="nav-link {{ request()->routeIs('kas.*') ? 'nav-active' : '' }}"><x-icon.cash /> Kas Masuk/Keluar</a>
            @endif
            @endif

            @if($u && $u->can_access('laporan'))
            <a href="{{ route('laporan.penjualan') }}" class="nav-link {{ request()->routeIs('laporan.*') ? 'nav-active' : '' }}"><x-icon.chart /> Laporan</a>
            @endif

            @if($u && ($u->can_access('user') || $u->can_access('role')))
            <div class="nav-group-title flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> Manajemen</div>
            @if($u->can_access('user'))
            <a href="{{ route('user.index') }}" class="nav-link {{ request()->routeIs('user.*') ? 'nav-active' : '' }}"><x-icon.user /> User</a>
            @endif
            @if($u->can_access('role'))
            <a href="{{ route('role.index') }}" class="nav-link {{ request()->routeIs('role.*') ? 'nav-active' : '' }}"><x-icon.shield /> Role Akses</a>
            @endif
            @endif

            @if($u && $u->can_access('pengaturan'))
            <div class="nav-group-title flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pengaturan</div>
            <a href="{{ route('pengaturan.profil-toko') }}" class="nav-link {{ request()->routeIs('pengaturan.profil-toko') ? 'nav-active' : '' }}"><x-icon.store /> Profil Toko</a>
            <a href="{{ route('pengaturan.tampilan-kasir') }}" class="nav-link {{ request()->routeIs('pengaturan.tampilan-kasir') ? 'nav-active' : '' }}"><x-icon.cart /> Tampilan Kasir</a>
            @if(\App\Models\Setting::shiftKasirEnabled())
            <a href="{{ route('pengaturan.shift-kasir') }}" class="nav-link {{ request()->routeIs('pengaturan.shift-kasir') ? 'nav-active' : '' }}">
                <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; flex-shrink: 0;" class="w-4.5 h-4.5 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Shift Kasir
            </a>
            @else
            <button type="button" @click="showPromoShiftModal = true" class="nav-link w-full text-left flex items-center justify-between group cursor-pointer">
                <span class="flex items-center gap-2">
                    <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; flex-shrink: 0;" class="w-4.5 h-4.5 shrink-0 text-slate-400 group-hover:text-amber-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Shift Kasir</span>
                </span>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    PRO
                </span>
            </button>
            @endif
            <a href="{{ route('pengaturan.jaringan') }}" class="nav-link {{ request()->routeIs('pengaturan.jaringan') ? 'nav-active' : '' }}"><x-icon.store /> Akses Jaringan (LAN)</a>
            <a href="{{ route('pengaturan.mode-produk') }}" class="nav-link {{ request()->routeIs('pengaturan.mode-produk') ? 'nav-active' : '' }}"><x-icon.box /> Mode Produk</a>
            <a href="{{ route('pengaturan.printer-struk') }}" class="nav-link {{ request()->routeIs('pengaturan.printer-struk') ? 'nav-active' : '' }}"><x-icon.printer /> Printer Struk</a>
            <a href="{{ route('pengaturan.printer-barcode') }}" class="nav-link {{ request()->routeIs('pengaturan.printer-barcode') ? 'nav-active' : '' }}"><x-icon.printer /> Printer Barcode</a>
            <a href="{{ route('pengaturan.template-barcode') }}" class="nav-link {{ request()->routeIs('pengaturan.template-barcode') ? 'nav-active' : '' }}"><x-icon.barcode /> Template Barcode</a>
            <a href="{{ route('pengaturan.template-struk') }}" class="nav-link {{ request()->routeIs('pengaturan.template-struk') ? 'nav-active' : '' }}"><x-icon.receipt /> Template Struk</a>
            <a href="{{ route('pengaturan.pajak') }}" class="nav-link {{ request()->routeIs('pengaturan.pajak') ? 'nav-active' : '' }}"><x-icon.percent /> Pajak</a>
            <a href="{{ route('pengaturan.backup-restore') }}" class="nav-link {{ request()->routeIs('pengaturan.backup-restore') ? 'nav-active' : '' }}"><x-icon.database /> Backup &amp; Restore</a>
            <a href="{{ route('pengaturan.database') }}" class="nav-link {{ request()->routeIs('pengaturan.database') ? 'nav-active' : '' }}"><x-icon.database /> Database</a>
            <a href="{{ route('pengaturan.tentang') }}" class="nav-link {{ request()->routeIs('pengaturan.tentang') ? 'nav-active' : '' }}"><x-icon.store /> Tentang Aplikasi</a>
            @endif
        </nav>
        <div class="p-3.5 border-t border-slate-200/80 bg-slate-50/70 text-xs text-slate-500 shrink-0">
            <div class="font-medium text-slate-700 truncate">&copy; {{ date('Y') }} {{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Sistem Kasir Modern &middot; JaylaTech</div>
        </div>
    </aside>
    {{-- Dimuat DI SINI dan TANPA defer, tepat setelah menunya ada di DOM: skrip ini
         mengembalikan posisi gulir, dan itu harus terjadi sebelum peramban menggambar.
         Kalau ditaruh di <head> dengan defer, menu sempat tergambar di posisi atas dulu
         lalu melompat - terlihat sebagai kedipan di setiap perpindahan halaman. --}}
    <script src="@aset('vendor/jpos-sidebar.js')"></script>

    <div class="fixed inset-0 bg-black/40 z-30 {{ request()->routeIs('kasir.index') ? '' : 'lg:hidden' }}" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
        @if(!request()->routeIs('kasir.index'))
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 lg:px-6 shrink-0">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="font-semibold text-lg text-slate-800">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-3" x-data="{ open: false }">
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-medium">{{ auth()->user()->name ?? '' }}</div>
                    <div class="text-xs text-slate-400">{{ auth()->user()->role->name ?? '-' }}</div>
                </div>
                <div class="relative">
                    <button @click="open = !open" class="w-9 h-9 rounded-full bg-brand-500 text-white flex items-center justify-center font-semibold">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </button>
                    <div x-show="open" @click.outside="open=false" x-cloak class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border py-1 text-sm z-50">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full text-left px-4 py-2 hover:bg-slate-50 text-red-600">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        @endif

        {{-- Kelas tambahan per halaman. Kosong untuk hampir semua halaman - hanya Modul
             Kasir yang mengisinya, supaya isinya bisa memakai TINGGI SISA alih-alih tinggi
             penuh. Tanpa ini, satu spanduk peringatan di atas mendorong panel keranjang ke
             bawah lipatan layar, dan tombol Bayar ikut terdorong bersamanya. --}}
        {{-- pb-24 di layar sempit: bilah navigasi bawah menutupi ~64px, dan tanpa
             ruang ini tombol terakhir tiap halaman berada persis di belakangnya. --}}
        <main class="flex-1 overflow-y-auto {{ request()->routeIs('kasir.index') ? 'p-2 sm:p-3 lg:p-4 pb-20 lg:pb-4' : 'p-4 lg:p-6 pb-24 lg:pb-6' }} @yield('kelas-main')">
            @if(session('pemulihan_login'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg flex items-start justify-between gap-4">
                    <div>
                        <span class="font-medium">Password akun "{{ session('pemulihan_login')['username'] }}" pernah dikembalikan ke bawaan lewat alat pemulihan di komputer ini</span>
                        pada {{ \Carbon\Carbon::parse(session('pemulihan_login')['waktu'])->format('d/m/Y H:i') }}.
                        Kalau bukan Anda yang melakukannya, ada orang lain yang sempat memegang komputer toko ini &mdash;
                        segera ganti password semua akun.
                    </div>
                    @if(auth()->user()?->can_access('user'))
                    <form method="POST" action="{{ route('pemulihan-login.tutup') }}" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs underline font-medium whitespace-nowrap">Saya sudah tahu</button>
                    </form>
                    @endif
                </div>
            @endif
            @if(session('memakai_password_bawaan'))
                <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 rounded-lg">
                    <span class="font-medium">Akun Anda masih memakai password bawaan.</span>
                    Siapa pun yang tahu password bawaan bisa membuka data penjualan toko Anda.
                    @if(auth()->user()?->can_access('user'))
                        Ganti sekarang di <a href="{{ route('user.index') }}" class="underline font-medium">Manajemen &rsaquo; User</a>.
                    @else
                        Minta admin menggantikannya.
                    @endif
                </div>
            @endif
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
                    <div class="font-medium mb-1">Data tidak bisa disimpan:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

{{-- ============================================================================
     NAVIGASI BAWAH - hanya layar sempit.

     Sidebar 27 menu itu benar untuk komputer kasir, dan salah untuk telepon: menu
     yang paling sering dipakai terkubur di balik tombol hamburger, dan tiap
     perpindahan halaman butuh dua ketukan plus satu gulungan.

     Empat tujuan yang paling sering dituju ditarik keluar ke bawah - tempat yang
     paling mudah dijangkau ibu jari - dan sisanya tetap lewat "Menu". Yang dipilih
     bukan tebakan: Kasir dan Pesanan adalah dua halaman tempat uang berpindah,
     Produk tempat barang dimasukkan, Laporan tempat hasilnya dilihat.

     Ikut aturan hak akses yang sama dengan sidebar - peran yang tidak boleh membuka
     Laporan tidak melihat tombolnya di sini juga. Tombolnya menyusut mengikuti
     jumlah yang tersisa.
     ============================================================================ --}}
@php
    $bawah = [];

    if ($u && $u->can_access('kasir')) {
        $bawah[] = ['rute' => 'kasir.index', 'aktif' => 'kasir.index', 'ikon' => 'cart', 'label' => 'Kasir'];
        $bawah[] = ['rute' => 'kasir.waiting-list', 'aktif' => 'kasir.waiting-list*', 'ikon' => 'receipt', 'label' => 'Pesanan', 'lencana' => $waitingCount ?? 0];
    }
    if ($u && $u->can_access('produk')) {
        $bawah[] = ['rute' => 'produk.index', 'aktif' => 'produk.*', 'ikon' => 'box', 'label' => 'Produk'];
    }
    if ($u && $u->can_access('laporan')) {
        $bawah[] = ['rute' => 'laporan.penjualan', 'aktif' => 'laporan.*', 'ikon' => 'chart', 'label' => 'Laporan'];
    }
@endphp

<nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 flex bg-white border-t border-slate-200 pb-safe">
    @foreach($bawah as $b)
        @php $ini = request()->routeIs($b['aktif']); @endphp
        <a href="{{ route($b['rute']) }}"
           class="relative flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium {{ $ini ? 'text-brand-600' : 'text-slate-500' }}">
            {{-- Garis di ATAS tombol yang sedang aktif, bukan warna latar: latar yang
                 berubah membuat bilah ini terlihat seperti empat kotak terpisah. --}}
            <span class="absolute top-0 inset-x-3 h-0.5 rounded-b {{ $ini ? 'bg-brand-500' : '' }}"></span>
            <span class="relative">
                <x-dynamic-component :component="'icon.' . $b['ikon']" />
                @if(($b['lencana'] ?? 0) > 0)
                    <span class="absolute -top-1.5 -right-2 bg-amber-500 text-white text-[9px] font-bold rounded-full px-1 leading-4 min-w-[16px] text-center">{{ $b['lencana'] }}</span>
                @endif
            </span>
            <span>{{ $b['label'] }}</span>
        </a>
    @endforeach

    {{-- Pintu ke sisa menu. Sidebar tetap ada seutuhnya - yang berubah cuma bahwa ia
         tidak lagi jadi satu-satunya jalan. --}}
    <button type="button" @click="sidebarOpen = true"
            class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium text-slate-500">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
        <span>Menu</span>
    </button>
</nav>


<style>
    /* Telepon dengan bilah geser di bawah layar menutupi ~34px terakhir. Tanpa ini,
       label navigasi bawah berada persis di balik bilah itu dan tidak bisa disentuh. */
     .pb-safe { padding-bottom: env(safe-area-inset-bottom, 0px); }
     /* SVG size helper to guarantee icons never expand uncontrollably */
     svg { flex-shrink: 0; }
     .w-3\.5 { width: 14px !important; }
     .h-3\.5 { height: 14px !important; }
     .w-4 { width: 16px !important; }
     .h-4 { height: 16px !important; }
     .w-5 { width: 20px !important; }
     .h-5 { height: 20px !important; }
     .w-6 { width: 24px !important; }
     .h-6 { height: 24px !important; }
     .nav-link { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:12px; color:#475569; font-weight:600; font-size:13px; transition:all .15s ease; }
    .nav-link:hover { background:#f0f7ff; color:#1c6ff0; }
    .nav-link svg { width:18px; height:18px; flex-shrink:0; color:#64748b; transition:transform .15s ease, color .15s ease; }
    .nav-link:hover svg { color:#1c6ff0; transform:scale(1.08); }
    .nav-active { background:#eef7ff !important; color:#1c6ff0 !important; font-weight:700; border:1px solid #bce0ff; box-shadow:0 1px 4px rgba(28,111,240,.12); }
    .nav-active svg { color:#1c6ff0 !important; transform:scale(1.08); }
    .nav-group-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; padding:14px 12px 4px; }
    .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:14px; font-weight:500; }
    .btn-primary { background:#1c6ff0; color:#fff; }
    .btn-primary:hover { background:#1857c4; }
    .btn-outline { background:#fff; border:1px solid #e2e8f0; color:#334155; }
    .btn-outline:hover { background:#f8fafc; }
    .btn-danger { background:#fee2e2; color:#b91c1c; }
    .btn-danger:hover { background:#fecaca; }
    .form-input, .form-select, .form-textarea { width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; font-size:14px; }
    .form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:#1c6ff0; box-shadow:0 0 0 2px rgba(28,111,240,.15); }
    .form-label { font-size:13px; font-weight:500; color:#475569; margin-bottom:4px; display:block; }
    .card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
    .card-gerigi {
        background: #fff;
        border-radius: 12px 12px 0 0;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,.06));
        position: relative;
        margin-bottom: 12px;
    }
    .card-gerigi::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        right: 0;
        height: 10px;
        background-size: 16px 10px;
        background-repeat: repeat-x;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 10' preserveAspectRatio='none'%3E%3Cpolygon points='0,0 8,10 16,0' fill='%23ffffff'/%3E%3C/svg%3E");
        pointer-events: none;
    }
    table.data-table th { text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.03em; color:#64748b; padding:10px 14px; border-bottom:1px solid #e2e8f0; }
    table.data-table td { padding:10px 14px; border-bottom:1px solid #f1f5f9; font-size:14px; }
</style>

{{-- MODAL PROMOSI FITUR SHIFT KASIR (MARKETING HOOK) --}}
<div x-show="showPromoShiftModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
     @keydown.escape.window="showPromoShiftModal = false">
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all"
         @click.away="showPromoShiftModal = false">
        {{-- Header Nuansa Emas / Amber Pro --}}
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 px-6 py-5 text-white relative">
            <button type="button"
                    @click="showPromoShiftModal = false"
                    class="absolute top-4 right-4 text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10 transition cursor-pointer"
                    title="Tutup">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-black/20 text-amber-100 text-xs font-bold uppercase tracking-wider mb-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Fitur Tambahan Pro
            </div>
            <h3 class="text-lg font-bold text-white leading-snug">Modul Shift Kasir &amp; Rekonsiliasi Laci</h3>
            <p class="text-xs text-amber-100 mt-1">Solusi kontrol ketat modal awal kasir dan serah terima uang fisik laci toko Anda.</p>
        </div>

        {{-- Manfaat Fitur --}}
        <div class="p-6 space-y-4 text-slate-700 text-sm">
            <div class="space-y-2.5">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800">Kontrol Modal Awal Laci</div>
                        <div class="text-xs text-slate-500">Kasir wajib input modal receh saat buka shift sebelum mulai bertransaksi.</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800">Deteksi Selisih Kas Otomatis</div>
                        <div class="text-xs text-slate-500">Aplikasi otomatis membandingkan uang fisik vs data sistem saat pergantian shift.</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800">Cetak Laporan Z-Report Kasir</div>
                        <div class="text-xs text-slate-500">Cetak bukti serah terima kas langsung dari printer thermal struk.</div>
                    </div>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900 flex items-center gap-2">
                <span class="text-base shrink-0">💡</span>
                <span>Fitur ini saat ini dinonaktifkan untuk toko Anda. Ingin mengaktifkannya? Hubungi developer kami.</span>
            </div>

            {{-- Actions --}}
            <div class="pt-2 flex items-center justify-end gap-2.5">
                <button type="button"
                        @click="showPromoShiftModal = false"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                    Nanti Saja
                </button>
                <a href="https://wa.me/6281234567890?text=Halo%20Developer%20JPOS,%20saya%20tertarik%20mengaktifkan%20Fitur%20Shift%20Kasir%20di%20aplikasi%20toko%20saya"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.173.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.072.043.419-.101.824z"/>
                    </svg>
                    <span>Hubungi Developer</span>
                </a>
            </div>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
