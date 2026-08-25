<?php

namespace App\Http\Controllers;

use App\Console\Commands\PulihkanLoginCommand;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Percobaan gagal yang ditoleransi sebelum login dijeda. */
    private const BATAS_PERCOBAAN = 5;

    /** Lama jeda setelah batas terlampaui, dalam detik. */
    private const JEDA_DETIK = 60;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $kunci = $this->kunciPembatas($request, $credentials['username']);

        $this->pastikanBelumTerlaluSering($kunci);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (! Auth::user()->is_active) {
                Auth::logout();
                return back()->withErrors(['username' => 'Akun Anda tidak aktif. Hubungi admin.']);
            }

            RateLimiter::clear($kunci);
            $request->session()->regenerate();

            // Dicek di sini, sekali per login, lalu disimpan di sesi. Memeriksanya di
            // setiap halaman berarti satu operasi bcrypt (BCRYPT_ROUNDS=12) per request,
            // yang justru memperlambat kasir.
            $request->session()->put('memakai_password_bawaan', $credentials['password'] === 'password');

            $this->bacaCatatanPemulihan($request);

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($kunci, self::JEDA_DETIK);

        return back()->withErrors(['username' => 'Username atau password salah.'])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /**
     * Tahan tebakan password beruntun.
     *
     * Sampai versi ini halaman login menerima percobaan sebanyak apa pun tanpa jeda sedetik
     * pun. Itu berbahaya justru KARENA aplikasi ini punya password bawaan yang harus selalu
     * bisa dipulihkan: password bawaannya diketahui umum, dan daftar password toko yang
     * lazim dipakai pendek sekali. Tanpa pembatas, siapa pun yang bisa membuka halaman
     * login punya waktu tak terbatas untuk mencoba semuanya.
     *
     * Batasnya dihitung per username per alamat, bukan global, supaya seorang kasir yang
     * salah ketik berulang kali tidak ikut mengunci kasir lain di komputer yang sama.
     *
     * Ini bukan pengganti password yang baik - ini yang membuat password yang biasa-biasa
     * saja tetap ada gunanya.
     */
    private function kunciPembatas(Request $request, string $username): string
    {
        return 'login:' . Str::lower($username) . '|' . $request->ip();
    }

    private function pastikanBelumTerlaluSering(string $kunci): void
    {
        if (! RateLimiter::tooManyAttempts($kunci, self::BATAS_PERCOBAAN)) {
            return;
        }

        $sisa = RateLimiter::availableIn($kunci);

        throw ValidationException::withMessages([
            'username' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . $sisa . ' detik.',
        ]);
    }

    /**
     * Ambil catatan pemulihan login, sekali per login, lalu simpan di sesi.
     *
     * Alasannya sama dengan pemeriksaan password bawaan di atas: peringatannya muncul di
     * setiap halaman, tapi tidak boleh menambah satu query pun di setiap halaman. Server
     * ini melayani satu permintaan pada satu waktu; query yang menempel di semua halaman
     * langsung terasa di meja kasir.
     */
    private function bacaCatatanPemulihan(Request $request): void
    {
        $catatan = Setting::get(PulihkanLoginCommand::KUNCI_CATATAN);

        if (is_array($catatan) && isset($catatan['username'], $catatan['waktu'])) {
            $request->session()->put('pemulihan_login', $catatan);
        }
    }
}
