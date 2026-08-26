<!DOCTYPE html>
<html lang="id" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</title>
    <link rel="icon" type="image/png" href="{{ !empty($storeProfile['logo']) ? url('media/'.$storeProfile['logo']) : asset('images/logo-jpos.png') }}">

    @include('partials.head-assets')
    <style>[x-cloak]{display:none!important}</style>
    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-800 antialiased" style="font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

<div class="flex h-screen overflow-hidden">
    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed z-40 inset-y-0 left-0 w-64 bg-slate-900 text-slate-200 transform transition-transform lg:translate-x-0 lg:static lg:flex flex-col">
        <div class="flex items-center gap-2 px-5 h-16 border-b border-slate-800">
            @if(!empty($storeProfile['logo']))
                <img src="{{ url('media/'.$storeProfile['logo']) }}" alt="{{ $storeProfile['name'] ?? 'Logo' }}" class="w-12 h-12 object-cover shrink-0">
            @else
                <div class="w-12 h-12 rounded-lg bg-brand-500 flex items-center justify-center font-bold text-white shrink-0 text-lg">{{ strtoupper(substr($storeProfile['name'] ?? 'J', 0, 1)) }}</div>
            @endif
            <div class="min-w-0">
                <div class="font-bold text-white leading-tight truncate">{{ $storeProfile['name'] ?? 'JPOS' }}</div>
                <div class="text-[11px] text-slate-400 leading-tight">{{ !empty($storeProfile['name']) ? 'Powered by JPOS · JaylaTech' : 'by JaylaTech' }}</div>
            </div>
        </div>
        {{-- data-sidebar-nav dibaca public/vendor/jpos-sidebar.js untuk mengingat posisi
             gulirnya antar halaman. Tanpa itu, menu melompat balik ke atas setiap kali
             halaman berpindah - dan menu ini cukup panjang sehingga Laporan, Manajemen,
             dan Pengaturan harus digulir ulang setiap kali. --}}
        <nav data-sidebar-nav class="flex-1 overflow-y-auto py-3 px-2 space-y-1 text-sm">

            @php $u = auth()->user(); @endphp

            @if($u && $u->can_access('dashboard'))
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">
                <x-icon.grid /> Dashboard
            </a>
            @endif

            @if($u && ($u->can_access('produk') || $u->can_access('kategori') || $u->can_access('satuan') || $u->can_access('supplier') || $u->can_access('pelanggan')))
            <div class="nav-group-title">Master Data</div>
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

            @if($u && ($u->can_access('kasir') || $u->can_access('retur') || $u->can_access('pembelian') || $u->can_access('kas')))
            <div class="nav-group-title">Transaksi</div>
            @if($u->can_access('kasir'))
            <a href="{{ route('kasir.index') }}" class="nav-link {{ request()->routeIs('kasir.index') ? 'nav-active' : '' }}"><x-icon.cart /> Modul Kasir</a>
            <a href="{{ route('kasir.waiting-list') }}" class="nav-link {{ request()->routeIs('kasir.waiting-list*') ? 'nav-active' : '' }}">
                <x-icon.receipt /> Pesanan / Waiting List
                @php $waitingCount = \App\Models\Sale::where('order_status', 'waiting')->count(); @endphp
                @if($waitingCount > 0)
                    <span class="ml-auto bg-amber-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5">{{ $waitingCount }}</span>
                @endif
            </a>
            <a href="{{ route('barcode.index') }}" class="nav-link {{ request()->routeIs('barcode.*') ? 'nav-active' : '' }}"><x-icon.barcode /> Cetak Barcode</a>
            @endif
            @if($u->can_access('retur'))
            <a href="{{ route('retur.index') }}" class="nav-link {{ request()->routeIs('retur.*') ? 'nav-active' : '' }}"><x-icon.return /> Retur</a>
            @endif
            @if($u->can_access('pembelian'))
            <a href="{{ route('pembelian.index') }}" class="nav-link {{ request()->routeIs('pembelian.*') ? 'nav-active' : '' }}">
                <x-icon.truck /> Pembelian &amp; Hutang
                @php $hutangCount = \App\Models\Purchase::where('sisa_hutang', '>', 0)->count(); @endphp
                @if($hutangCount > 0)
                    <span class="ml-auto bg-orange-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5">{{ $hutangCount }}</span>
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
            <div class="nav-group-title">Manajemen</div>
            @if($u->can_access('user'))
            <a href="{{ route('user.index') }}" class="nav-link {{ request()->routeIs('user.*') ? 'nav-active' : '' }}"><x-icon.user /> User</a>
            @endif
            @if($u->can_access('role'))
            <a href="{{ route('role.index') }}" class="nav-link {{ request()->routeIs('role.*') ? 'nav-active' : '' }}"><x-icon.shield /> Role Akses</a>
            @endif
            @endif

            @if($u && $u->can_access('pengaturan'))
            <div class="nav-group-title">Pengaturan</div>
            <a href="{{ route('pengaturan.profil-toko') }}" class="nav-link {{ request()->routeIs('pengaturan.profil-toko') ? 'nav-active' : '' }}"><x-icon.store /> Profil Toko</a>
            <a href="{{ route('pengaturan.tampilan-kasir') }}" class="nav-link {{ request()->routeIs('pengaturan.tampilan-kasir') ? 'nav-active' : '' }}"><x-icon.cart /> Tampilan Kasir</a>
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
        <div class="p-3 border-t border-slate-800 text-xs text-slate-400">
            &copy; {{ date('Y') }} {{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}
            @if(!empty($storeProfile['name']))
                <div class="text-[10px] text-slate-500 mt-0.5">Powered by JPOS &middot; JaylaTech</div>
            @endif
        </div>
    </aside>
    {{-- Dimuat DI SINI dan TANPA defer, tepat setelah menunya ada di DOM: skrip ini
         mengembalikan posisi gulir, dan itu harus terjadi sebelum peramban menggambar.
         Kalau ditaruh di <head> dengan defer, menu sempat tergambar di posisi atas dulu
         lalu melompat - terlihat sebagai kedipan di setiap perpindahan halaman. --}}
    <script src="@aset('vendor/jpos-sidebar.js')"></script>

    <div class="fixed inset-0 bg-black/40 z-30 lg:hidden" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
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

        {{-- Kelas tambahan per halaman. Kosong untuk hampir semua halaman - hanya Modul
             Kasir yang mengisinya, supaya isinya bisa memakai TINGGI SISA alih-alih tinggi
             penuh. Tanpa ini, satu spanduk peringatan di atas mendorong panel keranjang ke
             bawah lipatan layar, dan tombol Bayar ikut terdorong bersamanya. --}}
        <main class="flex-1 overflow-y-auto p-4 lg:p-6 @yield('kelas-main')">
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

<style>
    .nav-link { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; color:#cbd5e1; transition:.15s; }
    .nav-link:hover { background:#1e293b; color:#fff; }
    .nav-active { background:#1c6ff0; color:#fff !important; }
    .nav-group-title { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; padding:14px 12px 4px; }
    .nav-link svg { width:18px; height:18px; flex-shrink:0; }
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
    table.data-table th { text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.03em; color:#64748b; padding:10px 14px; border-bottom:1px solid #e2e8f0; }
    table.data-table td { padding:10px 14px; border-bottom:1px solid #f1f5f9; font-size:14px; }
</style>

@stack('scripts')
</body>
</html>
