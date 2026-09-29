@echo off
REM ===================================================================
REM  JPOS - Pengelola Lisensi & Aktivasi Fitur Shift Kasir
REM
REM  Jalankan berkas ini untuk mengaktifkan (UNLOCK) atau mengunci (LOCK)
REM  fitur Shift Kasir di aplikasi kasir JPOS.
REM ===================================================================
setlocal
cd /d "%~dp0"
echo.
php\php.exe artisan jpos:kelola-shift
echo.
pause
endlocal
