@echo off
REM ===================================================================
REM  JPOS - Buka Izin Windows Firewall untuk Akses LAN (Multi-Kasir)
REM
REM  Jalankan berkas ini dengan: KLIK KANAN -> "Run as administrator"
REM  agar perangkat kasir lain (Komputer B, HP, Tablet) bisa membuka
REM  aplikasi JPOS melalui jaringan Wi-Fi / kabel LAN lokal.
REM ===================================================================
setlocal
echo.
echo  ===================================================================
echo    JPOS - Buka Izin Windows Firewall (Port 8000-8099)
echo  ===================================================================
echo.
echo  Membuka izin port masuk TCP 8000-8099...
netsh advfirewall firewall add rule name="JPOS Multi-Device LAN" dir=in action=allow protocol=TCP localport=8000-8099
if %ERRORLEVEL% EQU 0 (
    echo.
    echo  [BERHASIL] Port 8000-8099 berhasil dibuka di Windows Firewall!
    echo  Komputer kasir lain, HP, dan tablet sekarang bisa mengakses JPOS.
) else (
    echo.
    echo  [GAGAL] Anda harus menjalankan berkas ini sebagai Administrator:
    echo  Klik kanan berkas ini -> pilih "Run as administrator".
)
echo.
pause
endlocal
