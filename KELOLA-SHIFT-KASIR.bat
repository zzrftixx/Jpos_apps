@echo off
REM ===================================================================
REM  JPOS - Pengelola Lisensi & Aktivasi Fitur Shift Kasir
REM
REM  Jalankan berkas ini untuk mengaktifkan (UNLOCK) atau mengunci (LOCK)
REM  fitur Shift Kasir di aplikasi kasir JPOS.
REM ===================================================================
setlocal
cd /d "%~dp0"

REM Cari runtime PHP:
REM 1. Subfolder lokal php\php.exe (paket portable klien)
REM 2. Perintah php global di PATH (komputer dev / server)
REM 3. Laragon PHP default
set "PHP_BIN="
if exist "php\php.exe" (
    set "PHP_BIN=php\php.exe"
) else (
    where php >nul 2>&1
    if not errorlevel 1 (
        set "PHP_BIN=php"
    ) else if exist "D:\MAINSERVER\laragon\bin\php\php-8.5.8-Win32-vs17-x64\php.exe" (
        set "PHP_BIN=D:\MAINSERVER\laragon\bin\php\php-8.5.8-Win32-vs17-x64\php.exe"
    )
)

if "%PHP_BIN%"=="" (
    echo.
    echo  ============================================================
    echo    GAGAL: Runtime PHP tidak ditemukan!
    echo  ============================================================
    echo    Pastikan file ini berada di dalam folder instalasi JPOS
    echo    atau runtime PHP telah terpasang di komputer ini.
    echo.
    pause
    exit /b 1
)

echo.
"%PHP_BIN%" artisan jpos:kelola-shift
echo.
pause
endlocal
