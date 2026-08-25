# =============================================================================
#  JPOS v2 - Pembuat Paket Portable Windows
# =============================================================================
#
#  Menghasilkan dua artefak di folder dist/:
#
#    1. JPOS_v2_Portable.zip      - instalasi baru yang bersih
#    2. JPOS_Update_<versi>.zip    - paket tambalan untuk client yang sudah jalan.
#                                    HANYA berisi kode. Folder database/ dan
#                                    storage/app/ tidak pernah disentuh.
#
#  CATATAN PENTING soal versi sebelumnya:
#  Script lama menyalin folder database/ dan storage/ apa adanya, sehingga
#  database milik developer (berisi data uji) DAN file backup lama ikut terkirim
#  ke dalam ZIP. Kalau client mengekstrak ZIP itu di atas instalasi mereka,
#  seluruh data penjualan mereka tertimpa. Daftar $ExcludeSelalu di bawah ini
#  adalah bagian terpenting dari script ini.
#
# =============================================================================

$ErrorActionPreference = "Stop"

$RootDir    = $PSScriptRoot
$DistDir    = Join-Path $RootDir "dist"
$TargetDir  = Join-Path $DistDir "JPOS_Portable"
$StageDir   = Join-Path $DistDir "_staging"
$PhpSource  = "D:\MAINSERVER\laragon\bin\php\php-8.5.8-Win32-vs17-x64"
$CscPath    = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"

$Version = (Get-Content (Join-Path $RootDir "VERSION") -Raw).Trim()

function Tahap($nomor, $teks) {
    Write-Host ""
    Write-Host "[$nomor/10] $teks" -ForegroundColor Yellow
}

function Gagal($teks) {
    throw $teks
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   JPOS v$Version - Membangun Paket Portable Windows      " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# -----------------------------------------------------------------------------
Tahap 1 "Memeriksa prasyarat"

if (-not (Test-Path $PhpSource))          { Gagal "Runtime PHP tidak ditemukan di: $PhpSource" }
if (-not (Test-Path $CscPath))            { Gagal "Compiler C# tidak ditemukan di: $CscPath" }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { Gagal "composer tidak ada di PATH." }
if (-not (Get-Command npx -ErrorAction SilentlyContinue))      { Gagal "npx (Node.js) tidak ada di PATH - dibutuhkan untuk membangun Tailwind CSS." }

# Composer butuh cara membuka arsip .zip saat mengunduh paket yang belum ada di cache:
# lewat ekstensi zip PHP, atau lewat unzip/7z di PATH. Kalau keduanya tidak ada, composer
# berhenti di tengah jalan dengan pesan yang tidak menyebut-nyebut build ini sama sekali.
# unzip.exe milik Git for Windows dipakai sebagai jalan keluar supaya php.ini sistem tidak
# perlu diubah.
$PunyaZip = (& php -r "echo extension_loaded('zip') ? 1 : 0;") -eq "1"
if (-not $PunyaZip -and -not (Get-Command unzip, 7z -ErrorAction SilentlyContinue)) {
    $Kandidat = @(
        (Join-Path $env:ProgramFiles "Git\usr\bin"),
        (Join-Path ${env:ProgramFiles(x86)} "Git\usr\bin"),
        (Join-Path $env:LOCALAPPDATA "Programs\Git\usr\bin"),
        (Join-Path $env:ProgramFiles "7-Zip")
    ) | Where-Object {
        $_ -and ((Test-Path (Join-Path $_ "unzip.exe")) -or (Test-Path (Join-Path $_ "7z.exe")))
    }

    if ($Kandidat) {
        $env:PATH = ($Kandidat -join ";") + ";" + $env:PATH
        Write-Host "  unzip ditambahkan ke PATH: $($Kandidat[0])" -ForegroundColor Yellow
    } else {
        # Backtick adalah karakter escape PowerShell, jadi nama setelan ditulis dengan
        # tanda kutip tunggal supaya tidak ikut termakan saat pesannya ditampilkan.
        Gagal ("Composer butuh ekstensi zip PHP atau perintah unzip/7z, dan keduanya tidak ada.`n" +
               "Aktifkan 'extension=zip' di php.ini, atau pasang Git for Windows / 7-Zip.")
    }
}

Get-Process -Name php, php-cgi, JPOS -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Milliseconds 500
Write-Host "  OK - PHP, csc, composer, npx tersedia" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 2 "Membangun Tailwind CSS"

# Wajib dijalankan supaya class Tailwind yang baru dipakai di Blade ikut terbawa.
& npx --yes tailwindcss@3.4.17 -c (Join-Path $RootDir "tailwind.config.cjs") `
    -i (Join-Path $RootDir "resources\css\jpos.css") `
    -o (Join-Path $RootDir "public\vendor\jpos.css") --minify
if ($LASTEXITCODE -ne 0) { Gagal "Pembangunan Tailwind CSS gagal." }

$CssSize = [math]::Round((Get-Item (Join-Path $RootDir "public\vendor\jpos.css")).Length / 1KB, 1)
Write-Host "  OK - public/vendor/jpos.css ($CssSize KB)" -ForegroundColor Green

# Modul format angka hidup di browser dan tidak terjangkau phpunit - padahal di sanalah
# pernah terjadi bug yang menggandakan harga produk seratus kali lipat setiap kali form
# Edit dibuka. Diuji di sini supaya paket tidak pernah dikemas dengan modul yang rusak.
& node (Join-Path $RootDir "tests\js\jpos-number.test.cjs")
if ($LASTEXITCODE -ne 0) { Gagal "Test modul format angka GAGAL. Paket tidak dikemas." }

# Penangkap alat pindai juga hidup di browser. Ia harus MENANGKAP pindaian di kasir, tapi
# DIAM di kolom barcode form produk - kalau salah satunya rusak, kasir tidak bisa memindai
# atau barcode produk baru tidak bisa didaftarkan sama sekali.
& node (Join-Path $RootDir "tests\js\jpos-pemindai.test.cjs")
if ($LASTEXITCODE -ne 0) { Gagal "Test penangkap alat pindai GAGAL. Paket tidak dikemas." }

# Seluruh test PHP hanya MERENDER halaman, tidak pernah menjalankan JavaScript-nya. Satu
# tanda kutip ganda di dalam x-data - bahkan cuma di dalam komentar - memutus atribut HTML
# di tengah jalan, Alpine mematikan seluruh komponen, dan setiap tombol di halaman itu
# berhenti bekerja sementara server tetap membalas 200 dan semua test tetap hijau.
& node (Join-Path $RootDir "tests\js\alpine-xdata.test.cjs")
if ($LASTEXITCODE -ne 0) { Gagal "Ada blok x-data yang rusak. Paket tidak dikemas." }

# -----------------------------------------------------------------------------
Tahap 3 "Menyiapkan folder"

if (Test-Path $TargetDir) { Remove-Item -Path $TargetDir -Recurse -Force }
if (Test-Path $StageDir)  { Remove-Item -Path $StageDir  -Recurse -Force }
New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null

Write-Host "  OK" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 4 "Menyalin & memangkas runtime PHP"

$PhpTarget = Join-Path $TargetDir "php"
Copy-Item -Path $PhpSource -Destination $PhpTarget -Recurse -Force

# Ekstensi yang benar-benar dipakai aplikasi. Sisanya dibuang beserta pustaka
# pendukungnya. OPcache tidak perlu didaftarkan - sudah tertanam di build ini.
$ExtDipakai = @(
    "php_bz2.dll", "php_curl.dll", "php_exif.dll", "php_fileinfo.dll", "php_gd.dll",
    "php_mbstring.dll", "php_openssl.dll", "php_pdo_sqlite.dll", "php_sqlite3.dll", "php_zip.dll"
)

Get-ChildItem -Path (Join-Path $PhpTarget "ext") -Filter *.dll |
    Where-Object { $ExtDipakai -notcontains $_.Name } |
    Remove-Item -Force

# ICU (~37 MB) hanya dibutuhkan ekstensi intl, yang tidak dipakai. Aman dibuang:
# Windows me-resolve import DLL saat load, jadi kalau PHP core memerlukannya,
# php.exe tidak akan bisa start sama sekali - dan itu diverifikasi di Tahap 9.
$FileDibuang = @(
    "dev", "extras",
    "php8embed.lib", "phpdbg.exe", "php8phpdbg.dll", "php-cgi.exe", "php-win.exe",
    "deplister.exe", "phar.phar.bat", "pharcommand.phar",
    "icudt77.dll", "icuin77.dll", "icuio77.dll", "icuuc77.dll",
    # libssh2.dll SENGAJA TIDAK dibuang: php_curl.dll meng-importnya. Membuangnya
    # membuat ekstensi curl gagal dimuat dan PHP mengeluarkan peringatan startup di
    # SETIAP pemanggilan - tapi hanya di komputer yang tidak punya instalasi PHP lain
    # di PATH, jadi di komputer pengembang hal itu tidak pernah terlihat.
    "libpq.dll", "libenchant2.dll",
    "glib-2.dll", "gmodule-2.dll", "gobject-2.dll", "libsodium.dll"
)

foreach ($item in $FileDibuang) {
    $path = Join-Path $PhpTarget $item
    if (Test-Path $path) { Remove-Item -Path $path -Recurse -Force }
}

# -----------------------------------------------------------------------------
#  RUNTIME VISUAL C++ - inilah yang menentukan aplikasi jalan atau tidak di
#  komputer kasir yang masih kosong.
#
#  php.exe dan hampir seluruh DLL pendampingnya meng-import VCRUNTIME140.DLL.
#  Di komputer pengembang berkas itu selalu ada di System32 karena dipasang oleh
#  entah apa saja yang pernah diinstal, jadi kekurangannya TIDAK PERNAH terlihat
#  selama pengujian. Di Windows yang baru dipasang, PHP gagal start dengan pesan
#  Windows yang tidak menyebut-nyebut JPOS sama sekali.
#
#  Windows mencari DLL di folder aplikasi LEBIH DULU daripada System32, jadi
#  menaruhnya di sebelah php.exe membuat paket berdiri sendiri sepenuhnya.
#  Redistributable Visual C++ memang boleh disertakan bersama aplikasi.
# -----------------------------------------------------------------------------
$VcDll = @("vcruntime140.dll", "vcruntime140_1.dll", "msvcp140.dll")
$VcSumber = @(
    (Join-Path $env:SystemRoot "System32"),
    (Join-Path ${env:ProgramFiles} "Microsoft Visual Studio\2022\Community\VC\Redist\MSVC")
)

foreach ($dll in $VcDll) {
    if (Test-Path (Join-Path $PhpTarget $dll)) { continue }

    $sumber = $VcSumber |
        ForEach-Object { Get-ChildItem -Path $_ -Filter $dll -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1 } |
        Where-Object { $_ } | Select-Object -First 1

    if (-not $sumber) { Gagal "Runtime Visual C++ '$dll' tidak ditemukan. Paket tidak akan jalan di komputer tanpa Visual C++ Redistributable." }

    Copy-Item -Path $sumber.FullName -Destination (Join-Path $PhpTarget $dll) -Force
}

Write-Host "  OK - runtime Visual C++ disertakan ($($VcDll -join ', '))" -ForegroundColor Green

$PhpIni = @"
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
zend.enable_gc = On
expose_php = Off
max_execution_time = 300
max_input_time = 60
memory_limit = 512M
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = "../storage/logs/php-error.log"
log_errors_max_len = 2048
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On
html_errors = Off
variables_order = "GPCS"
request_order = "GP"
register_argc_argv = Off
auto_globals_jit = On
post_max_size = 64M
default_mimetype = "text/html"
default_charset = "UTF-8"
enable_dl = Off
file_uploads = On
upload_max_filesize = 64M
max_file_uploads = 20
allow_url_fopen = On
allow_url_include = Off
default_socket_timeout = 60

extension_dir = "ext"

extension=bz2
extension=curl
extension=exif
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip

[Date]
date.timezone = "Asia/Jakarta"

[opcache]
; Tanpa ini seluruh framework dikompilasi ulang pada SETIAP request.
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.jit=disable
opcache.save_comments=1

; Jaring pengaman WAJIB. Di Windows, OPcache bisa gagal memakai memori bersama
; karena ASLR dan itu FATAL ERROR yang mematikan PHP sepenuhnya - aplikasi kasir
; tidak menyala sama sekali. Dengan fallback ini, kasus tersebut hanya membuat
; aplikasi lebih lambat, bukan mati. %TEMP% dipakai karena dijamin selalu ada;
; folder yang tidak ada juga menyebabkan fatal error saat PHP start.
opcache.file_cache="`${TEMP}"
opcache.file_cache_fallback=1

[performance]
realpath_cache_size=4096k
realpath_cache_ttl=600
"@

Set-Content -Path (Join-Path $PhpTarget "php.ini") -Value $PhpIni -Encoding UTF8

$PhpSize = [math]::Round((Get-ChildItem $PhpTarget -Recurse | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host "  OK - runtime PHP $PhpSize MB" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 5 "Menyalin berkas aplikasi"

# ---------------------------------------------------------------------------
#  DAFTAR PENGECUALIAN - bagian paling penting dari script ini.
#
#  database/*.sqlite  : data developer. Kalau ikut terkirim dan client
#                       mengekstrak ZIP di atas instalasi mereka, SELURUH data
#                       penjualan client tertimpa. Launcher membuat database
#                       baru + data awal sendiri saat pertama dijalankan.
#  storage/app/private: berisi file backup database, termasuk data nyata.
#  storage/app/public : gambar produk & logo milik developer.
#  storage/framework  : sesi, cache, dan view terkompilasi milik mesin build.
#  bootstrap/cache    : config cache berisi path absolut mesin build.
# ---------------------------------------------------------------------------
$ExcludeSelalu = @(
    ".DS_Store", "Thumbs.db", ".gitignore", ".gitattributes", ".editorconfig", ".npmrc"
)

function SalinBersih($sumber, $tujuan) {
    robocopy $sumber $tujuan /E /NFL /NDL /NJH /NJS /NC /NS /NP /XF ".DS_Store" "Thumbs.db" | Out-Null
    if ($LASTEXITCODE -ge 8) { Gagal "Gagal menyalin $sumber" }
}

foreach ($folder in @("app", "bootstrap", "config", "public", "resources", "routes", "lang")) {
    $src = Join-Path $RootDir $folder
    if (Test-Path $src) { SalinBersih $src (Join-Path $TargetDir $folder) }
}

# Alat pengembangan tidak ikut dikemas.
#
# jpos:generate-masif-db membuat data toko karangan dan menulis salinannya ke folder backup -
# folder yang sama dengan backup sungguhan. Di komputer toko, berkas itu muncul di menu
# Backup & Restore sebagai pilihan yang bisa dipulihkan, dan memulihkannya berarti mengganti
# seluruh data penjualan dengan data karangan.
$AlatPengembangan = @("app\Console\Commands\GenerateMasifDbCommand.php")
foreach ($berkas in $AlatPengembangan) {
    $jalur = Join-Path $TargetDir $berkas
    if (Test-Path $jalur) {
        Remove-Item $jalur -Force
        Write-Host "  Alat pengembangan dibuang dari paket: $berkas" -ForegroundColor Yellow
    }
}

# public/storage: TIDAK BOLEH ikut terkirim dalam bentuk apa pun.
#
# Proyek ini pernah dikembangkan di macOS, dan symlink public/storage ikut tersalin ke
# Windows sebagai berkas biasa berisi teks path lama. Server PHP bawaan meresolusi
# /storage/logo/xxx.png ke berkas itu dan membalas 404 SEBELUM Laravel sempat
# menanganinya - logo toko hilang di halaman login, pratinjau template struk, dan struk
# cetak, tanpa pesan error apa pun. Gambar dilayani MediaController lewat /media/...,
# jadi tidak ada satu pun fitur yang membutuhkan berkas ini.
$PublicStorage = Join-Path $TargetDir "public\storage"
if (Test-Path $PublicStorage) {
    Remove-Item $PublicStorage -Recurse -Force
    Write-Host "  public/storage dibuang dari paket (penyebab logo toko tidak muncul)" -ForegroundColor Yellow
}

# database/: hanya struktur (migrations, seeders, factories) - TANPA file .sqlite
SalinBersih (Join-Path $RootDir "database\migrations") (Join-Path $TargetDir "database\migrations")
SalinBersih (Join-Path $RootDir "database\seeders")    (Join-Path $TargetDir "database\seeders")
SalinBersih (Join-Path $RootDir "database\factories")  (Join-Path $TargetDir "database\factories")

foreach ($file in @("artisan", "server.php", "composer.json", "composer.lock", "VERSION", "README_JPOS.md", ".env.example")) {
    $src = Join-Path $RootDir $file
    if (Test-Path $src) { Copy-Item -Path $src -Destination (Join-Path $TargetDir $file) -Force }
}

# storage/: struktur folder kosong saja
foreach ($folder in @(
    "storage\app\public\products", "storage\app\public\logo", "storage\app\private\backups",
    # Ruang kerja dompdf: cache font dan berkas sementara saat merender PDF. Dibuat di sini
    # supaya rendering pertama tidak perlu membuat folder sendiri di komputer kasir.
    "storage\app\tmp\fonts",
    "storage\framework\cache\data", "storage\framework\sessions", "storage\framework\views",
    "storage\logs"
)) {
    New-Item -ItemType Directory -Path (Join-Path $TargetDir $folder) -Force | Out-Null
}

# bootstrap/cache harus kosong: config cache berisi path absolut mesin build.
$BootCache = Join-Path $TargetDir "bootstrap\cache"
if (Test-Path $BootCache) { Get-ChildItem $BootCache -File | Remove-Item -Force }
New-Item -ItemType Directory -Path $BootCache -Force | Out-Null

# Pemeriksaan pengaman: pastikan tidak ada satu pun file database atau backup ikut terbawa.
$Bocor = Get-ChildItem -Path $TargetDir -Recurse -Include *.sqlite, *.sqlite-wal, *.sqlite-shm -ErrorAction SilentlyContinue
if ($Bocor) {
    $Bocor | ForEach-Object { Write-Host "  BOCOR: $($_.FullName)" -ForegroundColor Red }
    Gagal "File database ikut terbawa ke dalam paket. Build dihentikan demi keselamatan data client."
}

Write-Host "  OK - tidak ada file database/backup yang ikut terbawa" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 6 "Memasang dependensi produksi ke dalam paket"

# WAJIB dijalankan DI DALAM folder paket.
#
# Percobaan sebelumnya membangun vendor di folder staging terpisah lalu menyalinnya.
# Itu menghasilkan paket yang sama sekali tidak bisa dijalankan: composer menyimpan
# path RELATIF di dalam classmap, dihitung dari lokasi vendor saat autoloader dibuat.
# Begitu vendor dipindahkan, seluruh path itu menunjuk ke luar folder aplikasi dan
# Laravel gagal memuat AppServiceProvider - instalasi baru mati sebelum sempat menyala.
& composer install --working-dir="$TargetDir" --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet
if ($LASTEXITCODE -ne 0) { Gagal "composer install --no-dev gagal." }
if (-not (Test-Path (Join-Path $TargetDir "vendor\autoload.php"))) { Gagal "vendor tidak terbentuk di dalam paket." }

Write-Host "  OK - vendor produksi terpasang (tanpa phpunit, faker, mockery, pint, pail, pao)" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 7 "Membuat .env bawaan"

# APP_KEY sengaja DIKOSONGKAN. Kunci ini mengenkripsi cookie sesi dan menandatangani URL,
# jadi harus UNIK di setiap toko. Kalau diisi kunci yang sama untuk semua paket, semua
# instalasi berbagi kunci enkripsi - dan launcher (JPOS_Launcher.cs) hanya membuat kunci baru
# kalau APP_KEY masih kosong. Launcher menghasilkan kunci acak sendiri saat pertama dijalankan.
$EnvContent = @"
APP_NAME="JPOS by JaylaTech"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost:8000

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_BUSY_TIMEOUT=5000
DB_JOURNAL_MODE=wal
DB_SYNCHRONOUS=normal

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
LOG_CHANNEL=stack
LOG_LEVEL=error
"@

Set-Content -Path (Join-Path $TargetDir ".env") -Value $EnvContent -Encoding UTF8
Write-Host "  OK - .env dibuat" -ForegroundColor Green

# Alat pemulihan login.
#
# Halaman login sengaja TIDAK punya tombol "lupa password": tombol itu berdiri di depan
# pintu dan bisa ditekan siapa saja yang membuka aplikasi. Jalan pulangnya dipindahkan ke
# berkas ini, yang mensyaratkan sesuatu yang tidak dimiliki peramban - akses ke berkas di
# komputer toko. Penjagaan selengkapnya ada di PulihkanLoginCommand.
$PulihkanBat = @"
@echo off
REM ===================================================================
REM  JPOS - Pemulihan Login
REM
REM  Dipakai kalau pemilik toko lupa password dan tidak bisa masuk.
REM  Password akun admin dikembalikan ke bawaan: password
REM
REM  TIDAK ADA data penjualan yang dihapus atau diubah.
REM  Salinan pengaman database dibuat lebih dulu secara otomatis.
REM
REM  Setiap pemulihan dicatat di storage\logs\pemulihan-login.log dan
REM  ditampilkan sebagai peringatan di dalam aplikasi.
REM ===================================================================
setlocal
cd /d "%~dp0"
echo.
echo  Tutup dulu jendela JPOS kalau masih terbuka, lalu tekan sembarang tombol.
pause >nul
php\php.exe artisan jpos:pulihkan-login
echo.
pause
endlocal
"@
Set-Content -Path (Join-Path $TargetDir "PULIHKAN-LOGIN.bat") -Value $PulihkanBat -Encoding ASCII
Write-Host "  OK - PULIHKAN-LOGIN.bat disertakan" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 8 "Mengompilasi JPOS.exe"

$ExeOutput   = Join-Path $TargetDir "JPOS.exe"
$LauncherSrc = Join-Path $RootDir "JPOS_Launcher.cs"
$IconPath    = Join-Path $RootDir "public\images\jpos.ico"

$cscArgs = @("-nologo", "-optimize+", "-target:winexe", "-out:$ExeOutput",
             "-r:System.dll,System.Drawing.dll,System.Windows.Forms.dll", $LauncherSrc)
if (Test-Path $IconPath) { $cscArgs = @("-win32icon:$IconPath") + $cscArgs }

& $CscPath $cscArgs
if (-not (Test-Path $ExeOutput)) { Gagal "Gagal mengompilasi JPOS.exe" }

Write-Host "  OK - JPOS.exe terbentuk" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 9 "Menguji paket hasil build"

$PhpExe = Join-Path $PhpTarget "php.exe"

# -----------------------------------------------------------------------------
#  Diuji dengan PATH DIKOSONGKAN sampai System32.
#
#  Ini bagian yang menentukan. Di komputer pengembang selalu ada instalasi PHP lain
#  di PATH, dan Windows dengan senang hati meminjam DLL dari sana - sehingga paket
#  yang sebenarnya kekurangan berkas tetap terlihat sehat di sini, lalu gagal di
#  komputer kasir yang masih kosong dengan pesan yang tidak menyebut JPOS sama
#  sekali. Membersihkan PATH membuat kekurangan itu ketahuan SEKARANG.
#
#  Kejadian nyata yang ditangkap pemeriksaan ini: libssh2.dll ikut terbuang saat
#  memangkas runtime, dan ekstensi curl diam-diam berhenti termuat.
# -----------------------------------------------------------------------------
$PathAsli = $env:PATH
try {
    $env:PATH = Join-Path $env:SystemRoot "System32"

    # Semua ekstensi yang dibutuhkan harus benar-benar termuat setelah pemangkasan.
    $Modules = & $PhpExe -m 2>&1
    $WajibAda = @("pdo_sqlite", "sqlite3", "gd", "mbstring", "openssl", "fileinfo", "curl", "zip", "exif", "Zend OPcache")
    foreach ($m in $WajibAda) {
        if ($Modules -notcontains $m) { Gagal "Ekstensi '$m' tidak termuat saat paket dijalankan tanpa bantuan PATH sistem." }
    }

    # Satu peringatan startup pun berarti ada DLL yang hilang dari paket.
    $keluaran = (& $PhpExe -r "echo 'ok';" 2>&1 | Out-String)
    if ($keluaran -match "Unable to load dynamic library") {
        Gagal "Ada ekstensi yang tidak bisa dimuat tanpa PATH sistem:`n$keluaran"
    }
    if ($keluaran -notmatch "ok") { Gagal "PHP tidak bisa dijalankan: $keluaran" }

    # -------------------------------------------------------------------------
    #  Ekspor PDF & Excel benar-benar dirender, bukan cuma dicek daftar ekstensinya.
    #
    #  Mendaftar ekstensi hanya membuktikan pustakanya BISA dimuat. Yang perlu
    #  dibuktikan adalah ia benar-benar menghasilkan berkas yang sah di komputer
    #  kosong: dompdf perlu menulis cache font, dan PhpSpreadsheet perlu membuat
    #  arsip ZIP. Dua-duanya menyentuh disk, dan dua-duanya bisa gagal walau
    #  ekstensinya lengkap.
    #
    #  Dijalankan dengan PATH kosong seperti pemeriksaan di atas, supaya
    #  kekurangannya ketahuan di sini - bukan saat pemilik toko menekan
    #  "Unduh Excel" di depan orang lain.
    # -------------------------------------------------------------------------
    $UjiEkspor = @'
require __DIR__ . "/vendor/autoload.php";
$tmp = sys_get_temp_dir() . "/jpos-uji-ekspor";
@mkdir($tmp, 0775, true);

$opsi = new Dompdf\Options();
$opsi->set("isRemoteEnabled", false);
$opsi->set("tempDir", $tmp);
$opsi->set("fontDir", $tmp);
$opsi->set("fontCache", $tmp);
$pdf = new Dompdf\Dompdf($opsi);
$pdf->loadHtml("<h1>Uji</h1><table><tr><td>Rp 1.000</td></tr></table>");
$pdf->render();
if (strpos($pdf->output(), "%PDF") !== 0) { fwrite(STDERR, "PDF tidak sah" . PHP_EOL); exit(1); }

$buku = new PhpOffice\PhpSpreadsheet\Spreadsheet();
$buku->getActiveSheet()->setCellValue("A1", "Uji");
$buku->getActiveSheet()->setCellValue("B1", 1000);
$berkas = $tmp . "/uji.xlsx";
(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($buku))->save($berkas);
$buku->disconnectWorksheets();
$zip = new ZipArchive();
if ($zip->open($berkas) !== true || $zip->locateName("xl/workbook.xml") === false) {
    fwrite(STDERR, "XLSX tidak sah" . PHP_EOL); exit(1);
}
$zip->close();
echo "ekspor-ok";
'@
    $BerkasUji = Join-Path $TargetDir "uji-ekspor.php"
    Set-Content -Path $BerkasUji -Value $UjiEkspor -Encoding UTF8
    $hasilEkspor = (& $PhpExe $BerkasUji 2>&1 | Out-String)
    Remove-Item $BerkasUji -Force -ErrorAction SilentlyContinue
    if ($hasilEkspor -notmatch "ekspor-ok") {
        Gagal "Ekspor PDF/Excel gagal di dalam paket:`n$hasilEkspor"
    }
    Write-Host "  OK - ekspor PDF & Excel berjalan tanpa bantuan PATH sistem" -ForegroundColor Green
} finally {
    $env:PATH = $PathAsli
}

# --- Uji nyala sesungguhnya, di salinan terpisah ---
#
# Memeriksa berkasnya ada saja TIDAK cukup. Percobaan sebelumnya menghasilkan paket
# yang lengkap isinya tapi sama sekali tidak bisa dijalankan, karena autoloader
# composer dibangun di lokasi lain sehingga path relatif di dalam classmap-nya
# menunjuk ke luar folder aplikasi. Satu-satunya cara menangkap kelas kesalahan
# seperti itu adalah benar-benar menjalankan paketnya, persis seperti yang akan
# dialami client saat pertama kali membuka aplikasi.
$UjiDir = Join-Path $DistDir "_uji"
if (Test-Path $UjiDir) { Remove-Item $UjiDir -Recurse -Force }
robocopy $TargetDir $UjiDir /E /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { Gagal "Gagal menyiapkan folder uji." }

$UjiPhp = Join-Path $UjiDir "php\php.exe"

# Laravel harus bisa di-bootstrap (di sinilah autoloader yang rusak ketahuan).
$versi = & $UjiPhp (Join-Path $UjiDir "artisan") "--version" 2>&1 | Out-String
if ($versi -notmatch "Laravel Framework") {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "Paket tidak bisa dijalankan - Laravel gagal di-bootstrap:`n$versi"
}

# Instalasi dari nol: database dibuat, migrasi jalan, data awal terisi.
$siap = & $UjiPhp (Join-Path $UjiDir "artisan") "jpos:prepare" 2>&1 | Out-String
if ($LASTEXITCODE -ne 0 -or $siap -notmatch "JPOS siap dijalankan") {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "Instalasi dari nol gagal:`n$siap"
}

$dbUji = Join-Path $UjiDir "database\database.sqlite"
if (-not (Test-Path $dbUji)) {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "Instalasi dari nol tidak menghasilkan file database."
}

# Data awal harus benar-benar ada, bukan sekadar tabel kosong.
$hitung = & $UjiPhp (Join-Path $UjiDir "artisan") "tinker" "--execute=echo App\Models\User::count().'/'.App\Models\Role::count();" 2>&1 | Out-String
if ($hitung -notmatch "\d+/\d+" -or $hitung -match "^0/") {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "Data awal (user & role) tidak terisi:`n$hitung"
}

# --- Alat pemulihan login benar-benar dijalankan (H13) ---
#
# Ini satu-satunya jalan pulang kalau pemilik toko lupa password. Kalau ia rusak, tidak ada
# yang akan tahu sampai ada toko yang benar-benar terkunci di luar datanya sendiri - dan saat
# itu sudah terlambat. Karena itu ia dijalankan sungguhan di sini, bukan sekadar dicek ada.
#
# Dijalankan setelah instalasi dari nol, jadi akun 'admin' memang sudah ada.
$PulihkanBatUji = Join-Path $UjiDir "PULIHKAN-LOGIN.bat"
if (-not (Test-Path $PulihkanBatUji)) {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "PULIHKAN-LOGIN.bat tidak ikut terbawa ke paket."
}

$pulih = & $UjiPhp (Join-Path $UjiDir "artisan") "jpos:pulihkan-login" "--user=admin" "--yakin" 2>&1 | Out-String
if ($LASTEXITCODE -ne 0 -or $pulih -notmatch "BERHASIL") {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "Alat pemulihan login gagal dijalankan di paket:`n$pulih"
}

# Jejaknya wajib ada. Tanpa jejak, alat yang sama berubah jadi pintu belakang (H13).
$JejakUji = Join-Path $UjiDir "storage\logs\pemulihan-login.log"
if (-not (Test-Path $JejakUji)) {
    Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue
    Gagal "Pemulihan login tidak meninggalkan jejak di storage\logs\pemulihan-login.log."
}

Write-Host "  OK - alat pemulihan login berjalan dan meninggalkan jejak" -ForegroundColor Green

# --- Server sungguhan dinyalakan, aset dan halaman benar-benar diminta ---
#
# server.php melayani aset statis sendiri supaya bisa memasang header cache - server
# bawaan PHP tidak mengirim satu pun, sehingga peramban mengunduh ulang 110 KB aset di
# SETIAP perpindahan halaman. Router itu berada di jalur yang dilewati SETIAP permintaan,
# jadi kalau ia rusak, seluruh aplikasi ikut rusak - bukan cuma asetnya.
#
# Karena itu ia diuji dengan menyalakan server sungguhan, bukan dengan membaca kodenya.
$PortUji = 8199
$SrvUji = Start-Process -FilePath $UjiPhp `
    -ArgumentList @("artisan","serve","--host=127.0.0.1","--port=$PortUji","--no-reload") `
    -WorkingDirectory $UjiDir -PassThru -WindowStyle Hidden

try {
    $siapMelayani = $false
    for ($i = 0; $i -lt 40; $i++) {
        Start-Sleep -Milliseconds 250
        try {
            $r = Invoke-WebRequest -Uri "http://127.0.0.1:$PortUji/login" -UseBasicParsing -TimeoutSec 3
            if ($r.StatusCode -eq 200) { $siapMelayani = $true; break }
        } catch { }
    }
    if (-not $siapMelayani) { Gagal "Server paket tidak siap melayani dalam 10 detik." }

    # 1. Aset berversi wajib membawa header cache panjang.
    $aset = Invoke-WebRequest -Uri "http://127.0.0.1:$PortUji/vendor/jpos.css?v=$Version" -UseBasicParsing -TimeoutSec 5
    if ($aset.StatusCode -ne 200) { Gagal "Aset jpos.css tidak terlayani (HTTP $($aset.StatusCode))." }
    if ($aset.Content.Length -lt 1000) { Gagal "Aset jpos.css terlayani kosong." }
    if ($aset.Headers["Cache-Control"] -notmatch "max-age=\d{5,}") {
        Gagal "Aset berversi tidak membawa header cache panjang: $($aset.Headers['Cache-Control'])"
    }
    $etag = $aset.Headers["ETag"]
    if (-not $etag) { Gagal "Aset tidak membawa ETag - peramban tidak bisa memvalidasi." }

    # 2. Permintaan ulang dengan ETag wajib dibalas 304, bukan seluruh isinya lagi.
    try {
        $ulang = Invoke-WebRequest -Uri "http://127.0.0.1:$PortUji/vendor/jpos.css?v=$Version" `
            -Headers @{ "If-None-Match" = $etag } -UseBasicParsing -TimeoutSec 5
        if ($ulang.StatusCode -ne 304) { Gagal "Aset tidak dibalas 304 walau ETag-nya sama (HTTP $($ulang.StatusCode))." }
    } catch [System.Net.WebException] {
        $kode = [int]$_.Exception.Response.StatusCode
        if ($kode -ne 304) { Gagal "Aset tidak dibalas 304 walau ETag-nya sama (HTTP $kode)." }
    }

    # 3. Aplikasinya sendiri harus tetap terlayani lewat router yang sama.
    $halaman = Invoke-WebRequest -Uri "http://127.0.0.1:$PortUji/login" -UseBasicParsing -TimeoutSec 5
    if ($halaman.Content -notmatch "Masuk") { Gagal "Halaman login tidak terbentuk lewat server.php." }

    # 4. Router tidak boleh bisa dipakai membaca berkas di luar public/.
    try {
        $bocor = Invoke-WebRequest -Uri "http://127.0.0.1:$PortUji/vendor/../../.env" -UseBasicParsing -TimeoutSec 5
        if ($bocor.Content -match "APP_KEY") { Gagal "server.php membocorkan .env lewat jalur ../" }
    } catch { }

    Write-Host "  OK - server.php melayani aset (cache + 304) dan halaman aplikasi" -ForegroundColor Green
} finally {
    if ($SrvUji -and -not $SrvUji.HasExited) { Stop-Process -Id $SrvUji.Id -Force -ErrorAction SilentlyContinue }
    Get-Process php -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -eq $UjiPhp } | Stop-Process -Force -ErrorAction SilentlyContinue
}

Remove-Item $UjiDir -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "  OK - ekstensi lengkap, Laravel ter-bootstrap, instalasi dari nol berhasil" -ForegroundColor Green

# -----------------------------------------------------------------------------
Tahap 10 "Mengemas artefak"

Add-Type -AssemblyName System.IO.Compression.FileSystem

# --- Artefak 1: instalasi baru ---
$ZipFull = Join-Path $DistDir "JPOS_v2_Portable.zip"
if (Test-Path $ZipFull) { Remove-Item $ZipFull -Force }
[System.IO.Compression.ZipFile]::CreateFromDirectory($TargetDir, $ZipFull)

# --- Artefak 2: paket tambalan untuk client yang sudah berjalan ---
#
# Hanya berisi kode. database/ dan storage/app/ SENGAJA tidak disertakan sama
# sekali, sehingga tidak ada cara file ini merusak data client.
$UpdateDir = Join-Path $DistDir "_update"
if (Test-Path $UpdateDir) { Remove-Item $UpdateDir -Recurse -Force }
New-Item -ItemType Directory -Path $UpdateDir -Force | Out-Null

foreach ($folder in @("app", "config", "lang", "public", "resources", "routes", "vendor", "database\migrations", "database\seeders")) {
    $src = Join-Path $TargetDir $folder
    if (Test-Path $src) { SalinBersih $src (Join-Path $UpdateDir $folder) }
}
foreach ($file in @("artisan", "server.php", "composer.json", "composer.lock", "VERSION", "README_JPOS.md", "JPOS.exe", "PULIHKAN-LOGIN.bat")) {
    Copy-Item -Path (Join-Path $TargetDir $file) -Destination (Join-Path $UpdateDir $file) -Force
}
New-Item -ItemType Directory -Path (Join-Path $UpdateDir "php") -Force | Out-Null
Copy-Item -Path (Join-Path $PhpTarget "php.ini") -Destination (Join-Path $UpdateDir "php\php.ini") -Force

$UpdateBat = @"
@echo off
REM ===================================================================
REM  JPOS - Pemasang Pembaruan
REM
REM  Script ini TIDAK PERNAH menyentuh folder database\ maupun
REM  storage\app\. Data penjualan, gambar produk, dan pengaturan toko
REM  Anda tetap utuh.
REM ===================================================================
setlocal
cd /d "%~dp0"

echo.
echo  ==========================================
echo    Pembaruan JPOS versi $Version
echo  ==========================================
echo.

if not exist "JPOS.exe" (
    echo  GAGAL: Letakkan folder ini di dalam folder JPOS Anda,
    echo         lalu jalankan lagi UPDATE.bat dari sana.
    echo.
    pause
    exit /b 1
)

echo  [1/4] Menutup JPOS yang sedang berjalan...
taskkill /IM JPOS.exe /F >nul 2>&1
taskkill /IM php.exe /F >nul 2>&1
ping -n 3 127.0.0.1 >nul

echo  [2/4] Membuat cadangan database...
php\php.exe artisan jpos:backup --prefix=pre-update
if errorlevel 1 (
    echo.
    echo  GAGAL membuat cadangan database. Pembaruan DIBATALKAN.
    echo  Tidak ada satu pun berkas yang diubah.
    echo.
    pause
    exit /b 1
)

echo  [3/4] Memasang berkas baru...
robocopy "_baru" "." /E /NFL /NDL /NJH /NJS /NC /NS /NP >nul
if errorlevel 8 (
    echo.
    echo  GAGAL menyalin berkas. Cadangan database Anda ada di
    echo  storage\app\private\backups
    echo.
    pause
    exit /b 1
)

echo  [4/4] Menyiapkan database dan cache...
php\php.exe artisan jpos:prepare
if errorlevel 1 (
    echo.
    echo  Persiapan gagal. Periksa storage\logs\launcher.log
    echo.
    pause
    exit /b 1
)

echo.
echo  Pembaruan selesai. Menjalankan JPOS...
start "" "JPOS.exe"
ping -n 3 127.0.0.1 >nul
endlocal
"@

# Berkas baru diletakkan di subfolder _baru supaya UPDATE.bat bisa menyalinnya
# tanpa pernah menyentuh database/ atau storage/app/.
$UpdatePack = Join-Path $DistDir "_updatepack"
if (Test-Path $UpdatePack) { Remove-Item $UpdatePack -Recurse -Force }
New-Item -ItemType Directory -Path $UpdatePack -Force | Out-Null

# SALIN, bukan Move-Item.
#
# Move-Item pada folder berisi ribuan berkas gagal seketika kalau ada SATU berkas
# yang sedang dipegang proses lain - dan pemindai antivirus memang membuka berkas
# yang baru saja ditulis, tepat di detik-detik ini. Kegagalannya muncul di tahap
# terakhir, setelah sepuluh menit membangun, dan menyebut nama berkas acak yang
# berbeda tiap kali sehingga terlihat seperti kerusakan yang tidak masuk akal.
#
# robocopy menunggu dan mencoba lagi (/R /W), jadi berkas yang sedang dipindai
# cukup ditunggu sebentar alih-alih menggagalkan seluruh build.
robocopy $UpdateDir (Join-Path $UpdatePack "_baru") /E /R:5 /W:2 /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { Gagal "Gagal menyiapkan paket update." }
Remove-Item $UpdateDir -Recurse -Force -ErrorAction SilentlyContinue
Set-Content -Path (Join-Path $UpdatePack "UPDATE.bat") -Value $UpdateBat -Encoding ASCII

$ZipUpdate = Join-Path $DistDir "JPOS_Update_$Version.zip"
if (Test-Path $ZipUpdate) { Remove-Item $ZipUpdate -Force }

# Paket update versi LAMA dibuang dari dist.
#
# Membiarkannya berarti ada dua berkas bernama mirip berdampingan, dan yang salah kirim ke
# client akan memasang aplikasi versi mundur di atas database yang sudah dimigrasikan -
# kesalahan yang mahal dan tidak perlu, cuma karena dua nama berkas terlihat serupa.
Get-ChildItem $DistDir -Filter "JPOS_Update_*.zip" -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne "JPOS_Update_$Version.zip" } |
    ForEach-Object {
        Write-Host "  Paket update versi lama dibuang: $($_.Name)" -ForegroundColor Yellow
        Remove-Item $_.FullName -Force
    }
[System.IO.Compression.ZipFile]::CreateFromDirectory($UpdatePack, $ZipUpdate)

# --- Checksum ---
$Sums = Join-Path $DistDir "SHA256SUMS.txt"
Get-FileHash $ZipFull, $ZipUpdate -Algorithm SHA256 |
    ForEach-Object { "$($_.Hash.ToLower())  $(Split-Path $_.Path -Leaf)" } |
    Set-Content -Path $Sums -Encoding ASCII

# --- Bersih-bersih ---
Remove-Item $StageDir -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item $UpdatePack -Recurse -Force -ErrorAction SilentlyContinue

$UkuranFull   = [math]::Round((Get-Item $ZipFull).Length / 1MB, 1)
$UkuranUpdate = [math]::Round((Get-Item $ZipUpdate).Length / 1MB, 1)

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "  Paket JPOS v$Version berhasil dibuat" -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "  Instalasi baru : $ZipFull ($UkuranFull MB)"
Write-Host "  Paket update   : $ZipUpdate ($UkuranUpdate MB)"
Write-Host "  Checksum       : $Sums"
Write-Host ""
Write-Host "  Paket update TIDAK menyentuh database maupun gambar produk client." -ForegroundColor Cyan
Write-Host ""
