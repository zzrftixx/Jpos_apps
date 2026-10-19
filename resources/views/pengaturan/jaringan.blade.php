@extends('layouts.app')

@section('title', 'Akses Jaringan (LAN) - Pengaturan')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Akses Jaringan (Client - Server LAN)</h1>
            <p class="text-xs text-slate-500 mt-1">Hubungkan banyak meja kasir (Komputer B, Tablet, HP) ke database server ini secara bersamaan.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
            Server Aktif (Port {{ $port }})
        </span>
    </div>

    {{-- ALERT NOTIFIKASI --}}
    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-green-50 border border-green-200 text-green-800 text-xs flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- KARTU KHUSUS KASIR TABLET ANDROID OFFLINE & TOKEN PENGAMAN --}}
    <div class="card p-5 space-y-4 bg-gradient-to-br from-blue-50/50 via-white to-slate-50/30 border border-blue-200 shadow-xs" x-data="{ copiedTablet: false, tokenDisalin: false }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-base shadow-xs shrink-0">
                    📱
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Aplikasi Kasir Tablet Android (Offline POS &amp; Sync)</h2>
                    <p class="text-xs text-slate-500">Khusus tablet kasir: transaksi offline, simpan data lokal, cetak Bluetooth, dan sinkronisasi aman.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700 border border-green-200 shrink-0">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                Proteksi Token Aktif
            </span>
        </div>

        <div class="p-3 bg-white rounded-xl border border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <span class="text-[11px] text-slate-500 font-medium">Buka tautan ini di browser Chrome Tablet Android Anda:</span>
                <div class="font-mono text-sm font-bold text-blue-700 mt-0.5">
                    {{ url('/tablet') }}
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ url('/tablet') }}" target="_blank" class="btn btn-xs bg-white hover:bg-slate-100 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg font-semibold transition">
                    Buka Kasir Tablet ↗
                </a>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ url('/tablet') }}'); copiedTablet = true; setTimeout(() => copiedTablet = false, 2000)"
                        class="btn btn-xs border px-3 py-1.5 rounded-lg font-semibold transition shadow-2xs"
                        :class="copiedTablet ? 'bg-green-600 text-white border-green-600' : 'bg-blue-600 hover:bg-blue-700 text-white border-blue-600'">
                    <span x-text="copiedTablet ? '✓ Tersalin' : 'Salin Tautan'">Salin Tautan</span>
                </button>
            </div>
        </div>

        {{-- SECRET SYNC TOKEN --}}
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <label class="block text-xs font-semibold text-slate-700">
                    Secret Sync Token Tablet (Header: <code class="font-mono text-blue-700">X-JPOS-TABLET-TOKEN</code>):
                </label>
                <span class="text-[10px] text-slate-400">Wajib dimasukkan di Pengaturan Tablet</span>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <div class="flex-1 p-2.5 rounded-xl border border-slate-300 bg-white font-mono text-xs font-bold text-slate-900 select-all tracking-wider truncate">
                    {{ $tabletSyncToken }}
                </div>

                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $tabletSyncToken }}'); tokenDisalin = true; setTimeout(() => tokenDisalin = false, 2000)"
                        class="btn btn-xs px-3.5 py-2.5 rounded-xl font-bold border transition shrink-0"
                        :class="tokenDisalin ? 'bg-green-600 text-white border-green-600' : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300'">
                    <span x-text="tokenDisalin ? '✓ Token Tersalin' : 'Salin Token'">Salin Token</span>
                </button>

                <form action="{{ route('pengaturan.jaringan.regenerate-tablet-token') }}" method="POST"
                      onsubmit="return confirm('Buat ulang Secret Sync Token? Semua tablet kasir harus diperbarui dengan token baru.')" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn btn-xs px-3 py-2.5 rounded-xl font-semibold border border-slate-300 bg-white hover:bg-slate-100 text-slate-600">
                        🔄 Generate Ulang
                    </button>
                </form>
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">
                🔒 <strong>Fungsi Keamanan:</strong> Mencegah injeksi transaksi palsu atau pencurian data stok oleh perangkat asing yang terhubung ke Wi-Fi toko.
            </p>
        </div>

        <p class="text-[11px] text-slate-600 leading-relaxed">
            💡 <strong>Cara Pasang sebagai APK di Tablet:</strong> Buka tautan di atas di Chrome tablet &rarr; klik titik tiga (⋮) di Chrome &rarr; pilih <strong>"Instal Aplikasi"</strong> (atau *"Tambahkan ke Layar Utama"*). Buka aplikasi lalu klik ikon Pengaturan untuk menempelkan Secret Sync Token di atas.
        </p>
    </div>

    {{-- FORM SETTING NAMA DOMAIN KUSTOM --}}
    <div class="card p-5 space-y-4 border border-blue-100 bg-gradient-to-br from-white to-blue-50/20" x-data="{ domainInput: '{{ old('custom_domain', $customDomain) }}' }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                    Nama Domain Kustom (DNS Lokal Toko)
                </h2>
                <p class="text-xs text-slate-600 mt-0.5">
                    Gunakan nama domain toko Anda sendiri agar staf kasir tidak perlu menghafal dan mengetik deretan angka IP.
                </p>
            </div>
            @if($customDomain)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 border border-blue-200 shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Aktif: {{ $customDomain }}
                </span>
            @endif
        </div>

        <form action="{{ route('pengaturan.jaringan.update') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label for="custom_domain" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Nama Domain yang Diinginkan
                </label>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <div class="relative flex-1 flex rounded-xl shadow-2xs border border-slate-300 overflow-hidden focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500 bg-white">
                        <span class="inline-flex items-center px-3 text-xs font-mono text-slate-400 bg-slate-50 border-r border-slate-200 select-none">
                            http://
                        </span>
                        <input type="text"
                               name="custom_domain"
                               id="custom_domain"
                               x-model="domainInput"
                               placeholder="contoh: kasir.toko atau pos.lan"
                               class="flex-1 px-3 py-2 text-sm font-semibold text-slate-800 placeholder-slate-400 border-0 focus:ring-0 focus:outline-none">
                        @if($port != 80)
                            <span class="inline-flex items-center px-3 text-xs font-mono text-slate-400 bg-slate-50 border-l border-slate-200 select-none">
                                :{{ $port }}
                            </span>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary px-5 py-2 text-xs font-bold shrink-0 rounded-xl shadow-2xs">
                        Simpan Domain
                    </button>
                </div>
                @error('custom_domain')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <span class="text-[11px] text-slate-500 font-medium">Saran nama cepat:</span>
                <button type="button"
                        @click="domainInput = 'kasir.toko'"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition">
                    kasir.toko
                </button>
                <button type="button"
                        @click="domainInput = 'pos.lan'"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition">
                    pos.lan
                </button>
                <button type="button"
                        @click="domainInput = 'toko.local'"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition">
                    toko.local
                </button>
                @if($customDomain)
                    <button type="button"
                            @click="domainInput = ''"
                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 transition ml-auto">
                        ✕ Kosongkan / Hapus Domain
                    </button>
                @endif
            </div>
        </form>
    </div>

    {{-- KARTU ALAMAT URL SERVER --}}
    <div class="card p-5 space-y-4">
        <div>
            <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                Alamat URL Akses untuk Komputer Kasir Lain / HP / Tablet
            </h2>
            <p class="text-xs text-slate-600 mt-1">
                Perangkat kasir tambahan <strong>TIDAK PERLU menginstal aplikasi apa pun</strong> (Zero-Install). Cukup buka Google Chrome atau Microsoft Edge di perangkat tersebut dan ketik alamat di bawah ini:
            </p>
        </div>

        <div class="space-y-2.5">
            {{-- 1. URL DOMAIN KUSTOM (JIKA SUDAH DISIMPAN) --}}
            @if($customDomainUrl)
                <div class="p-4 rounded-xl border-2 border-brand-500 bg-brand-50/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs"
                     x-data="{ disalin: false }">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-base font-bold text-brand-700 tracking-tight">{{ $customDomainUrl }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-600 text-white uppercase tracking-wider">🌟 Domain Pilihan Anda</span>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            Akan diarahkan ke IP Server Toko: <code class="font-mono font-bold text-slate-800">{{ $primaryIp }}</code>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ $customDomainUrl }}" target="_blank" rel="noopener"
                           class="btn btn-xs bg-white hover:bg-slate-100 text-brand-700 border border-brand-200 px-3 py-1.5 rounded-lg font-semibold transition">
                            Buka ↗
                        </a>
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $customDomainUrl }}'); disalin = true; setTimeout(() => disalin = false, 2000)"
                                class="btn btn-xs border px-3 py-1.5 rounded-lg font-semibold transition shadow-2xs"
                                :class="disalin ? 'bg-green-600 text-white border-green-600' : 'bg-brand-600 hover:bg-brand-700 text-white border-brand-600'">
                            <span x-text="disalin ? '✓ Tersalin' : 'Salin URL'">Salin URL</span>
                        </button>
                    </div>
                </div>

                {{-- BANTUAN BILA SERVER NOT FOUND --}}
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="space-y-1 text-[11px] leading-relaxed">
                        <p class="font-bold text-amber-900">
                            Muncul pesan <em>"Can't find the server"</em> atau <em>"Tidak dapat tersambung"</em> saat membuka domain di atas?
                        </p>
                        <p class="text-amber-800">
                            Domain lokal (seperti <code class="font-mono font-bold">{{ $customDomain }}</code>) tidak terdaftar di internet publik. Agar browser di komputer/laptop Anda dapat mengenalinya, jalankan <strong>perintah 1-baris pada bagian Petunjuk di bawah</strong> (atau gunakan alamat <strong>⚡ Otomatis .local</strong> yang langsung berfungsi tanpa konfigurasi apa pun).
                        </p>
                    </div>
                </div>
            @endif

            {{-- 2. URL MDNS BAWAAN (.LOCAL) --}}
            <div class="p-3.5 rounded-xl border border-blue-200 bg-blue-50/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                 x-data="{ disalin: false }">
                <div class="space-y-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-bold text-blue-800">{{ $mdnsUrl }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200">⚡ Otomatis (mDNS .local)</span>
                    </div>
                    <p class="text-[11px] text-slate-600">
                        Standar bawaan tanpa perlu edit file hosts atau setting router. Didukung langsung oleh Windows 10/11, iOS, iPad, Mac, dan Android modern.
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ $mdnsUrl }}" target="_blank" rel="noopener"
                       class="btn btn-xs bg-white hover:bg-slate-100 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg font-semibold transition">
                        Buka ↗
                    </a>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $mdnsUrl }}'); disalin = true; setTimeout(() => disalin = false, 2000)"
                            class="btn btn-xs border px-3 py-1.5 rounded-lg font-semibold transition"
                            :class="disalin ? 'bg-green-600 text-white border-green-600' : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300'">
                        <span x-text="disalin ? '✓ Tersalin' : 'Salin URL'">Salin URL</span>
                    </button>
                </div>
            </div>

            {{-- 3. URL BERBASIS ALAMAT IP --}}
            @foreach($urls as $u)
                <div class="flex items-center justify-between p-3 rounded-xl border {{ $u['is_recommended'] ? 'bg-blue-50/50 border-blue-200' : 'bg-slate-50 border-slate-200' }}"
                     x-data="{ disalin: false }">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-bold {{ $u['is_recommended'] ? 'text-blue-700' : 'text-slate-700' }}">{{ $u['url'] }}</span>
                            @if($u['is_recommended'])
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">IP LAN Utama</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">Antarmuka Jaringan: {{ $u['ip'] }}</p>
                    </div>

                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $u['url'] }}'); disalin = true; setTimeout(() => disalin = false, 2000)"
                            class="btn btn-xs border px-3 py-1.5 rounded-lg font-semibold transition"
                            :class="disalin ? 'bg-green-600 text-white border-green-600' : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300'">
                        <span x-text="disalin ? '✓ Tersalin' : 'Salin URL'">Salin URL</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- PANDUAN MENGHUBUNGKAN DOMAIN KUSTOM --}}
    <div class="card p-5 space-y-4" x-data="{ tab: 'pc_windows', psCopied: false, hostsCopied: false }">
        <div class="border-b border-slate-200 pb-3">
            <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Petunjuk: Cara Agar Kasir Client / Tablet Mengenali Domain Ini
            </h2>
            <p class="text-xs text-slate-600 mt-1">
                Pilih metode yang paling cocok untuk perangkat di toko Anda:
            </p>
        </div>

        {{-- Navigasi Tab --}}
        <div class="flex gap-2 border-b border-slate-200 pb-2 flex-wrap">
            <button type="button"
                    @click="tab = 'pc_windows'"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                    :class="tab === 'pc_windows' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                1. Komputer Kasir 2 (Client Windows)
            </button>
            <button type="button"
                    @click="tab = 'server_ini'"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                    :class="tab === 'server_ini' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                2. Buka di Komputer Server Ini
            </button>
            <button type="button"
                    @click="tab = 'router'"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                    :class="tab === 'router' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                3. Tablet &amp; HP (Via Router Wi-Fi)
            </button>
            <button type="button"
                    @click="tab = 'mdns'"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                    :class="tab === 'mdns' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                4. Tanpa Setting (mDNS .local)
            </button>
        </div>

        {{-- KONTEN TAB 1: PC WINDOWS (POWERSHELL / HOSTS) --}}
        <div x-show="tab === 'pc_windows'" class="space-y-3 text-xs">
            <p class="text-slate-700 leading-relaxed">
                Di Komputer Kasir Client (Komputer B), jalankan perintah 1-baris ini <strong>sekali saja</strong> di <strong>PowerShell Administrator</strong> agar komputer mengenali domain <code class="font-mono font-bold text-brand-600">{{ $customDomain ?: 'kasir.toko' }}</code>:
            </p>

            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <pre class="flex-1 p-2.5 rounded-lg bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto select-all">{{ $psHostsCmd }}</pre>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ addslashes($psHostsCmd) }}'); psCopied = true; setTimeout(() => psCopied = false, 2000)"
                            class="btn btn-xs border px-3 py-2 rounded-lg font-semibold shrink-0 shadow-2xs"
                            :class="psCopied ? 'bg-green-600 text-white border-green-600' : 'bg-brand-600 hover:bg-brand-700 text-white border-brand-600'">
                        <span x-text="psCopied ? '✓ Tersalin' : 'Salin Perintah'">Salin Perintah</span>
                    </button>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1 text-[11px] text-slate-600">
                <p class="font-bold text-slate-700">Cara Alternatif Manual (Edit Notepad):</p>
                <ol class="list-decimal list-inside space-y-0.5 ml-1">
                    <li>Buka aplikasi <strong>Notepad</strong> dengan klik kanan -> <em>Run as Administrator</em>.</li>
                    <li>Buka berkas: <code class="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">C:\Windows\System32\drivers\etc\hosts</code></li>
                    <li>Tambahkan baris berikut di baris paling bawah lalu Simpan (Ctrl+S):</li>
                </ol>
                <div class="flex items-center gap-2 mt-1.5">
                    <code class="font-mono font-bold bg-white px-2 py-1 rounded border border-slate-300 text-blue-700 select-all">{{ $hostsEntry }}</code>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $hostsEntry }}'); hostsCopied = true; setTimeout(() => hostsCopied = false, 2000)"
                            class="px-2 py-0.5 rounded text-[10px] font-bold border border-slate-300 hover:bg-slate-100 transition"
                            :class="hostsCopied ? 'bg-green-600 text-white border-green-600' : 'bg-white text-slate-700'">
                        <span x-text="hostsCopied ? '✓ Baris Tersalin' : 'Salin Baris'">Salin Baris</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- KONTEN TAB: SERVER INI (KOMPUTER TEMPAT JPOS TERINSTAL) --}}
        <div x-show="tab === 'server_ini'" class="space-y-3 text-xs" style="display: none;" x-data="{ srvCopied: false }">
            <p class="text-slate-700 leading-relaxed">
                Jika Anda ingin mengetik alamat <code class="font-mono font-bold text-brand-600">{{ $customDomain ?: 'kasir.toko' }}</code> langsung di peramban komputer ini sendiri:
            </p>

            <div class="space-y-2">
                <p class="font-bold text-slate-700 text-[11px]">
                    Jalankan perintah ini di {{ PHP_OS_FAMILY === 'Windows' ? 'PowerShell Administrator' : 'Terminal Mac / Linux' }} pada komputer ini:
                </p>
                <div class="flex items-center gap-2">
                    <pre class="flex-1 p-2.5 rounded-lg bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto select-all">{{ $serverHostsCmd }}</pre>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ addslashes($serverHostsCmd) }}'); srvCopied = true; setTimeout(() => srvCopied = false, 2000)"
                            class="btn btn-xs border px-3 py-2 rounded-lg font-semibold shrink-0 shadow-2xs"
                            :class="srvCopied ? 'bg-green-600 text-white border-green-600' : 'bg-brand-600 hover:bg-brand-700 text-white border-brand-600'">
                        <span x-text="srvCopied ? '✓ Tersalin' : 'Salin Perintah'">Salin Perintah</span>
                    </button>
                </div>
            </div>

            <p class="text-[11px] text-slate-500">
                Setelah perintah dijalankan, ketik <code class="font-mono font-semibold">{{ $customDomainUrl ?: 'http://kasir.toko:8000' }}</code> di Google Chrome/Safari komputer ini &mdash; halaman akan langsung terbuka!
            </p>
        </div>

        {{-- KONTEN TAB 3: ROUTER WI-FI (UNTUK SEMUA HP & TABLET) --}}
        <div x-show="tab === 'router'" class="space-y-3 text-xs" style="display: none;">
            <p class="text-slate-700 leading-relaxed">
                Jika Anda ingin <strong>seluruh Tablet Android, iPad, dan HP karyawan</strong> dapat langsung mengetik domain <code class="font-mono font-bold text-brand-600">{{ $customDomain ?: 'kasir.toko' }}</code> tanpa perlu setting di masing-masing HP:
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[10px] font-bold">1</span>
                        Buka Admin Router Wi-Fi
                    </div>
                    <p class="text-slate-600 text-[11px] leading-relaxed">
                        Buka peramban di komputer server, akses alamat router Wi-Fi toko (biasanya <code>192.168.1.1</code> atau <code>192.168.0.1</code>) lalu login.
                    </p>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[10px] font-bold">2</span>
                        Menu DNS / DHCP Host
                    </div>
                    <p class="text-slate-600 text-[11px] leading-relaxed">
                        Cari menu <strong>Local DNS</strong>, <strong>DNS Binding</strong>, atau <strong>Static Lease</strong> di pengaturan LAN/DHCP router Anda.
                    </p>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[10px] font-bold">3</span>
                        Arahkan ke IP Server
                    </div>
                    <p class="text-slate-600 text-[11px] leading-relaxed">
                        Masukkan Nama Host: <code class="font-mono font-bold">{{ $customDomain ?: 'kasir.toko' }}</code> dan arahkan ke IP Server: <code class="font-mono font-bold text-blue-700">{{ $primaryIp }}</code>.
                    </p>
                </div>
            </div>

            <p class="text-[11px] text-green-800 bg-green-50 border border-green-200 p-2.5 rounded-lg">
                💡 <strong>Kelebihan Metode Router:</strong> Begitu tersimpan di router, siapapun yang terhubung ke Wi-Fi toko (tablet kasir, hp pramusaji, laptop gudang) langsung bisa membuka <code class="font-mono font-bold">{{ $customDomainUrl ?: 'http://kasir.toko:' . $port }}</code> tanpa menginstal apa pun!
            </p>
        </div>

        {{-- KONTEN TAB 4: mDNS .LOCAL (OTOMATIS TANPA SETTING) --}}
        <div x-show="tab === 'mdns'" class="space-y-3 text-xs" style="display: none;">
            <p class="text-slate-700 leading-relaxed">
                Anda tidak ingin melakukan pengaturan apa pun? Cukup gunakan alamat mDNS bawaan komputer server ini:
            </p>

            <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-between gap-3">
                <span class="font-mono font-bold text-blue-900 text-sm">{{ $mdnsUrl }}</span>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $mdnsUrl }}'); disalin = true; setTimeout(() => disalin = false, 2000)"
                        class="btn btn-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-semibold transition shadow-2xs">
                    Salin URL mDNS
                </button>
            </div>

            <ul class="list-disc list-inside space-y-1 text-[11px] text-slate-600 ml-1">
                <li>Otomatis didukung oleh <strong>Windows 10 & 11</strong> (via mDNS built-in).</li>
                <li>Otomatis didukung oleh <strong>Apple iPad, iPhone, dan Mac</strong> (via Bonjour).</li>
                <li>Otomatis didukung oleh sebagian besar perangkat <strong>Android versi baru</strong>.</li>
            </ul>
        </div>
    </div>

    {{-- CARA PENGGUNAAN KASIR TAMBAHAN --}}
    <div class="card p-5 space-y-4">
        <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Petunjuk Menjalankan Transaksi di Meja Kasir 2, 3, dan Tablet
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[11px] font-bold">1</span>
                    Satu Jaringan Wi-Fi/LAN
                </div>
                <p class="text-slate-600 text-[11px] leading-relaxed">
                    Pastikan komputer kasir kedua atau tablet HP terhubung ke Wi-Fi atau kabel switch LAN yang sama dengan komputer server ini.
                </p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[11px] font-bold">2</span>
                    Buka Peramban Browser
                </div>
                <p class="text-slate-600 text-[11px] leading-relaxed">
                    Di komputer kasir kedua / tablet, buka Google Chrome atau Edge, lalu ketik alamat domain atau URL di atas pada kotak alamat browser.
                </p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[11px] font-bold">3</span>
                    Login &amp; Buka Shift Kasir
                </div>
                <p class="text-slate-600 text-[11px] leading-relaxed">
                    Kasir kedua login menggunakan akun kasir masing-masing, buka shift kasirnya sendiri, dan transaksi berjalan serentak tanpa saling tumpang tindih.
                </p>
            </div>
        </div>
    </div>

    {{-- WINDOWS FIREWALL HELPER --}}
    <div class="card p-5 space-y-3" x-data="{ cmdSalin: false }">
        <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Bila Perangkat Lain Tidak Bisa Membuka Halaman (Diblokir Windows Firewall)
        </h2>
        <p class="text-xs text-slate-600 leading-relaxed">
            Jika Komputer B menampilkan pesan <em>"Tidak dapat tersambung"</em> atau <em>"Connection Refused"</em>, kemungkinan besar Windows Defender Firewall di Komputer Server memblokir port masuk {{ $port }}. Jalankan perintah ini di PowerShell Administrator pada Komputer Server:
        </p>

        <div class="flex items-center gap-2">
            <pre class="flex-1 p-2.5 rounded-lg bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto select-all">{{ $firewallCmd }}</pre>
            <button type="button"
                    @click="navigator.clipboard.writeText('{{ $firewallCmd }}'); cmdSalin = true; setTimeout(() => cmdSalin = false, 2000)"
                    class="btn btn-xs border px-3 py-2 rounded-lg font-semibold shrink-0"
                    :class="cmdSalin ? 'bg-green-600 text-white border-green-600' : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300'">
                <span x-text="cmdSalin ? '✓ Tersalin' : 'Salin Perintah'">Salin Perintah</span>
            </button>
        </div>
    </div>

    {{-- PERINGATAN KESELAMATAN DATA --}}
    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1.5">
        <div class="font-bold flex items-center gap-1.5 text-amber-800">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Peringatan Integritas Database SQLite
        </div>
        <p class="text-amber-800 text-[11px] leading-relaxed">
            <strong>Dilarang membagikan folder database (Windows File Sharing / SMB share)!</strong> Membuka berkas SQLite yang sama secara langsung lewat jaringan SMB Windows akan merusak database (<em>malformed disk image</em>) karena SMB tidak mendukung memori bersama WAL. Semua perangkat kasir wajib mengakses melalui protokol HTTP di peramban web seperti yang tertera di atas.
        </p>
    </div>
</div>
@endsection
