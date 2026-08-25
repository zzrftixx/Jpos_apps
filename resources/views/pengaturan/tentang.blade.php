@extends('layouts.app')
@section('title', 'Tentang Aplikasi')

@push('scripts')
{{-- Script komponen + data ikonnya, keduanya lokal. Tanpa file kedua, komponen
     tetap mengunduh tiap ikon dari api.iconify.design dan kosong saat offline. --}}
<script src="@aset('vendor/iconify-icon-2.1.0.min.js')"></script>
<script src="@aset('vendor/iconify-icons-jpos.js')"></script>
@endpush

@section('content')
<div class="w-full">

    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-2xl mb-5 shadow-xl">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-800 to-slate-900"></div>
        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 20px 20px;"></div>
        <div class="absolute -top-16 -left-10 w-56 h-56 bg-blue-400 rounded-full mix-blend-screen filter blur-3xl opacity-40"></div>
        <div class="absolute -bottom-20 -right-10 w-64 h-64 bg-cyan-300 rounded-full mix-blend-screen filter blur-3xl opacity-25"></div>

        <div class="relative px-6 py-12 text-center">
            <img src="{{ asset('images/logo-jpos.png') }}" alt="JPOS" class="h-40 mx-auto mb-4"
                 style="filter: drop-shadow(0 0 3px rgba(255,255,255,0.9)) drop-shadow(0 0 8px rgba(255,255,255,0.6)) drop-shadow(0 0 20px rgba(255,255,255,0.35));">
            <h2 class="text-2xl font-bold text-white tracking-tight">{{ $app['nama'] }}</h2>
            <p class="text-blue-100/80 text-sm mb-4">by {{ $app['produk_oleh'] }}</p>
            <span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/20 text-white text-xs font-medium px-3 py-1.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Versi {{ $app['versi'] }}
            </span>
        </div>
    </div>

    {{-- Info grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div class="rounded-xl p-5 text-center bg-gradient-to-br from-slate-800 to-slate-900 shadow-lg flex flex-col items-center gap-2">
            <iconify-icon icon="mdi:application-brackets-outline" class="text-3xl text-blue-300"></iconify-icon>
            <div class="text-[10px] uppercase tracking-wider text-blue-300/70">Aplikasi</div>
            <div class="text-white font-semibold text-sm">{{ $app['nama'] }}</div>
        </div>
        <div class="rounded-xl p-5 text-center bg-gradient-to-br from-slate-800 to-slate-900 shadow-lg flex flex-col items-center gap-2">
            <iconify-icon icon="mdi:tag-outline" class="text-3xl text-blue-300"></iconify-icon>
            <div class="text-[10px] uppercase tracking-wider text-blue-300/70">Versi</div>
            <div class="text-white font-semibold text-sm">{{ $app['versi'] }}</div>
        </div>
        <div class="rounded-xl p-5 text-center bg-gradient-to-br from-slate-800 to-slate-900 shadow-lg flex flex-col items-center gap-2">
            <iconify-icon icon="mdi:certificate-outline" class="text-3xl text-blue-300"></iconify-icon>
            <div class="text-[10px] uppercase tracking-wider text-blue-300/70">Produk oleh</div>
            <div class="text-white font-semibold text-sm">{{ $app['produk_oleh'] }}</div>
        </div>
    </div>

    {{-- Contact / order info --}}
    <div class="card p-6 relative overflow-hidden">
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-blue-100 rounded-full blur-3xl opacity-60"></div>
        <div class="relative">
            <h3 class="font-semibold mb-1 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center">
                    <iconify-icon icon="mdi:cart-outline" class="text-lg"></iconify-icon>
                </span>
                Info Pemesanan Paket Kasir
            </h3>
            <p class="text-xs text-slate-400 mb-4 ml-10">Hubungi kami untuk pemasangan / pemesanan paket kasir JPOS.</p>

            <div class="grid sm:grid-cols-3 gap-3">
                <a href="https://instagram.com/{{ $app['kontak']['instagram'] }}" target="_blank"
                   class="group flex flex-col items-center gap-2 rounded-xl px-4 py-5 bg-gradient-to-br from-pink-50 to-purple-50 border border-pink-100 hover:shadow-lg hover:-translate-y-0.5 transition">
                    <iconify-icon icon="skill-icons:instagram" class="text-3xl group-hover:scale-110 transition"></iconify-icon>
                    <div class="text-center">
                        <div class="text-[11px] text-slate-400">Instagram</div>
                        <div class="text-sm font-semibold text-slate-700">{{ '@'.$app['kontak']['instagram'] }}</div>
                    </div>
                </a>
                <a href="https://tiktok.com/@{{ $app['kontak']['tiktok'] }}" target="_blank"
                   class="group flex flex-col items-center gap-2 rounded-xl px-4 py-5 bg-gradient-to-br from-slate-50 to-cyan-50 border border-slate-200 hover:shadow-lg hover:-translate-y-0.5 transition">
                    <iconify-icon icon="logos:tiktok-icon" class="text-3xl group-hover:scale-110 transition"></iconify-icon>
                    <div class="text-center">
                        <div class="text-[11px] text-slate-400">TikTok</div>
                        <div class="text-sm font-semibold text-slate-700">{{ '@'.$app['kontak']['tiktok'] }}</div>
                    </div>
                </a>
                <a href="https://wa.me/62{{ ltrim($app['kontak']['whatsapp'], '0') }}" target="_blank"
                   class="group flex flex-col items-center gap-2 rounded-xl px-4 py-5 bg-gradient-to-br from-green-50 to-emerald-50 border border-green-100 hover:shadow-lg hover:-translate-y-0.5 transition">
                    <iconify-icon icon="logos:whatsapp-icon" class="text-3xl group-hover:scale-110 transition"></iconify-icon>
                    <div class="text-center">
                        <div class="text-[11px] text-slate-400">WhatsApp</div>
                        <div class="text-sm font-semibold text-slate-700">{{ $app['kontak']['whatsapp'] }}</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-5">&copy; {{ date('Y') }} {{ $app['nama'] }} by {{ $app['produk_oleh'] }}. All rights reserved.</p>
</div>
@endsection
