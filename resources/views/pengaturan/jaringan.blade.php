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

    @if(session('success'))
        <div class="p-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- KARTU ALAMAT URL SERVER --}}
    <div class="card p-5 space-y-4">
        <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
            Alamat URL Akses untuk Komputer Kasir Lain / HP / Tablet
        </h2>
        <p class="text-xs text-slate-600">
            Perangkat kasir tambahan <strong>TIDAK PERLU menginstal aplikasi apapun</strong> (Zero-Install). Cukup buka Google Chrome atau Microsoft Edge di perangkat tersebut dan ketik alamat di bawah ini:
        </p>

        <div class="space-y-2">
            @foreach($urls as $u)
                <div class="flex items-center justify-between p-3.5 rounded-xl border {{ $u['is_recommended'] ? 'bg-blue-50/60 border-blue-200' : 'bg-slate-50 border-slate-200' }}"
                     x-data="{ disalin: false }">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-bold {{ $u['is_recommended'] ? 'text-blue-700' : 'text-slate-700' }}">{{ $u['url'] }}</span>
                            @if($u['is_recommended'])
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">Rekomendasi LAN</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">Antarmuka IP: {{ $u['ip'] }}</p>
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

    {{-- CARA PENGGUNAAN KASIR TAMBAHAN --}}
    <div class="card p-5 space-y-4">
        <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Petunjuk Menghubungkan Meja Kasir 2, 3, dan Tablet
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[11px] font-bold">1</span>
                    Satu Jaringan Wi-Fi/LAN
                </div>
                <p class="text-slate-600 text-[11px] leading-relaxed">
                    Pastikan komputer kasir kedua atau tablet HP terhubung ke Wi-Fi atau kabel router switch LAN yang sama dengan komputer server ini.
                </p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[11px] font-bold">2</span>
                    Buka Peramban Browser
                </div>
                <p class="text-slate-600 text-[11px] leading-relaxed">
                    Di komputer kasir kedua / tablet, buka Google Chrome atau Microsoft Edge, lalu ketik alamat URL rekomendasi di atas pada kotak alamat.
                </p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-[11px] font-bold">3</span>
                    Login &amp; Buka Shift
                </div>
                <p class="text-slate-600 text-[11px] leading-relaxed">
                    Kasir kedua login menggunakan akun kasir masing-masing, buka shift kasirnya sendiri, dan transaksi berjalan bersamaan tanpa bentrok.
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
