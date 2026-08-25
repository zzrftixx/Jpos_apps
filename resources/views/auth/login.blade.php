<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</title>
    <link rel="icon" type="image/png" href="{{ !empty($storeProfile['logo']) ? url('media/'.$storeProfile['logo']) : asset('images/logo-jpos.png') }}">
    @include('partials.head-assets')
</head>
<body class="relative min-h-screen" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">

    {{-- Banner: full screen background --}}
    <img src="{{ asset('images/banner.png') }}" alt="JPOS by JaylaTech" class="fixed inset-0 w-full h-full object-cover -z-10">
    <div class="fixed inset-0 bg-black/20 -z-10"></div>

    {{-- Login form: positioned beside the POS terminal illustration in the banner --}}
    <div class="relative min-h-screen flex items-center justify-center lg:justify-start p-4 lg:pl-[36%]">
        <div class="w-full max-w-md" x-data="{ showPassword: false }">
            <div class="text-center mb-6">
                @if(!empty($storeProfile['logo']))
                    <img src="{{ url('media/'.$storeProfile['logo']) }}" alt="{{ $storeProfile['name'] ?? 'Logo' }}" class="w-32 h-32 object-cover mx-auto mb-3">
                @else
                    <img src="{{ asset('images/logo-jpos.png') }}" alt="JPOS" class="h-20 mx-auto mb-3"
                         style="filter: drop-shadow(0 0 3px rgba(255,255,255,0.9)) drop-shadow(0 0 8px rgba(255,255,255,0.6)) drop-shadow(0 0 20px rgba(255,255,255,0.35));">
                @endif
                <h1 class="text-white text-2xl font-bold tracking-tight" style="text-shadow: 0 2px 8px rgba(0,0,0,0.5);">{{ $storeProfile['name'] ?? 'JPOS' }}</h1>
                <p class="text-white/80 text-sm" style="text-shadow: 0 1px 4px rgba(0,0,0,0.5);">
                    @if(!empty($storeProfile['name']))
                        Powered by JPOS &middot; JaylaTech &mdash; Sistem Kasir
                    @else
                        by JaylaTech &mdash; Sistem Kasir
                    @endif
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl ring-1 ring-black/5 p-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-1">Masuk ke akun Anda</h2>
                <p class="text-sm text-slate-500 mb-6">Silakan login untuk melanjutkan.</p>

                @if($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">Username</label>
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <input type="text" name="username" value="{{ old('username') }}" required autofocus
                                class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" placeholder="admin">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <input :type="showPassword ? 'text' : 'password'" name="password" required
                                class="w-full border border-slate-200 rounded-lg pl-9 pr-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-3 text-slate-400 hover:text-slate-600">
                                <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="rounded"> Ingat saya
                    </label>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition shadow-lg shadow-blue-600/30 hover:shadow-blue-600/40 hover:-translate-y-0.5">
                        Masuk
                    </button>
                </form>
            </div>
            <p class="text-center text-white/70 text-xs mt-6" style="text-shadow: 0 1px 4px rgba(0,0,0,0.5);">&copy; {{ date('Y') }} {{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</p>
        </div>
    </div>
</body>
</html>
