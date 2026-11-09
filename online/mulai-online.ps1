# Membuka SIM Kinerja ke internet lewat tautan sementara (Cloudflare Quick Tunnel).
# Komputer ini tetap menjadi server: tautan hanya hidup selama skrip ini berjalan.
# Gunakan -UjiLokal untuk menguji tanpa membuka tautan ke internet.
param([switch]$UjiLokal)
$ErrorActionPreference = 'Stop'

$xampp    = 'C:\xampp'
$php      = Join-Path $xampp 'php\php.exe'
$httpd    = Join-Path $xampp 'apache\bin\httpd.exe'
$mysqld   = Join-Path $xampp 'mysql\bin\mysqld.exe'
$myIni    = Join-Path $xampp 'mysql\bin\my.ini'
$conf     = Join-Path $PSScriptRoot 'apache-online.conf'
$pidFile  = Join-Path $PSScriptRoot 'apache-online.pid'
$logCf    = Join-Path $PSScriptRoot 'tunnel.log'
$portApp  = 18081

function Port-Hidup([int]$p) { return [bool](Get-NetTCPConnection -State Listen -LocalPort $p -ErrorAction SilentlyContinue) }
function Kode-Http([string]$url) {
    try { return [int](Invoke-WebRequest $url -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 15 -ErrorAction Stop).StatusCode }
    catch { if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode } else { return 0 } }
}
function Berhenti-Semua {
    if ($script:cfProc -and -not $script:cfProc.HasExited) { & taskkill /PID $script:cfProc.Id /T /F | Out-Null }
    if (Test-Path $pidFile) {
        $p = (Get-Content $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1)
        if ($p -match '^\d+$') { & taskkill /PID $p /T /F | Out-Null }
        Remove-Item $pidFile -ErrorAction SilentlyContinue
    }
}

Write-Host ''
Write-Host '============================================'
Write-Host '  SIM Kinerja - Membuka akses online'
Write-Host '============================================'
Write-Host ''

foreach ($f in @($php, $httpd)) { if (-not (Test-Path $f)) { throw "XAMPP tidak ditemukan: $f" } }

# 1. MySQL
Write-Host '[1/5] Memeriksa MySQL ...'
if (-not (Port-Hidup 3306)) {
    Write-Host '      Menjalankan MySQL ...'
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$myIni`"", '--standalone' -WindowStyle Minimized | Out-Null
    for ($i = 0; $i -lt 30 -and -not (Port-Hidup 3306); $i++) { Start-Sleep 1 }
    if (-not (Port-Hidup 3306)) { throw 'MySQL tidak mau menyala. Buka XAMPP Control Panel untuk melihat penyebabnya.' }
} else { Write-Host '      MySQL sudah berjalan.' }

# 2. Pemeriksaan kata sandi bawaan
Write-Host '[2/5] Memeriksa keamanan akun ...'
$hasil = @(& $php (Join-Path $PSScriptRoot 'cek-keamanan.php'))
$jumlah = [int]$hasil[0]
if ($jumlah -gt 0) {
    Write-Host ''
    Write-Host "  PERINGATAN: $jumlah akun aktif masih memakai kata sandi bawaan yang sama." -ForegroundColor Yellow
    Write-Host '  Jika dibuka ke internet, siapa pun yang tahu nama pengguna bisa masuk ke akun ini:' -ForegroundColor Yellow
    $hasil | Select-Object -Skip 1 -First 25 | ForEach-Object { Write-Host "    - $_" }
    Write-Host ''
    Write-Host '  Sebaiknya BATALKAN, lalu di halaman Pengguna pilih akun dan klik "Cetak akun terpilih"' -ForegroundColor Yellow
    Write-Host '  agar tiap akun mendapat kata sandi acak yang berbeda.' -ForegroundColor Yellow
    Write-Host ''
    if ($UjiLokal) { Write-Host '  (mode uji lokal: dilanjutkan tanpa bertanya)' }
    else {
        $jawab = Read-Host '  Ketik YA (huruf besar) untuk tetap membuka ke internet, atau Enter untuk membatalkan'
        if ($jawab -cne 'YA') { Write-Host 'Dibatalkan. Tidak ada yang dibuka ke internet.'; exit 1 }
    }
} else { Write-Host '      Tidak ada akun yang memakai kata sandi bawaan.' }

# 3. Apache khusus aplikasi
Write-Host "[3/5] Menjalankan Apache khusus aplikasi (port $portApp) ..."
if ((Port-Hidup $portApp) -and -not (Test-Path $pidFile)) {
    throw "Port $portApp sudah dipakai aplikasi lain. Hentikan aplikasi itu atau ubah nilai portApp pada skrip ini dan Listen pada apache-online.conf."
}
if (-not (Port-Hidup $portApp)) {
    Start-Process -FilePath $httpd -ArgumentList '-f', "`"$conf`"" -WindowStyle Hidden | Out-Null
    for ($i = 0; $i -lt 20 -and -not (Port-Hidup $portApp); $i++) { Start-Sleep 1 }
    if (-not (Port-Hidup $portApp)) {
        $galat = Join-Path (Split-Path $PSScriptRoot -Parent) 'logs\apache-online-error.log'
        if (Test-Path $galat) { Get-Content $galat -Tail 5 | ForEach-Object { Write-Host "      $_" } }
        throw "Apache khusus aplikasi tidak mau menyala di port $portApp."
    }
} else { Write-Host '      Sudah berjalan.' }

# 4. Uji pagar keamanan sebelum dibuka
Write-Host '[4/5] Menguji pagar keamanan ...'
$dasar = "http://127.0.0.1:$portApp"
$cek = @(
    @{ u = '/index.php?h=login';            ok = @(200) },
    @{ u = '/config.php';                   ok = @(403, 404) },
    @{ u = '/lib/db.php';                   ok = @(403, 404) },
    @{ u = '/database/simkinerja.sql';      ok = @(403, 404) },
    @{ u = '/logs/galat.log';               ok = @(403, 404) },
    @{ u = '/online/apache-online.conf';    ok = @(403, 404) },
    @{ u = '/phpmyadmin/';                  ok = @(403, 404) },
    @{ u = '/uploads/.htaccess';            ok = @(403, 404) }
)
$lolos = $true
foreach ($c in $cek) {
    $k = Kode-Http ($dasar + $c.u)
    $baik = $c.ok -contains $k
    if (-not $baik) { $lolos = $false }
    Write-Host ('      {0,-34} -> {1}  {2}' -f $c.u, $k, $(if ($baik) { 'aman' } else { 'GAGAL' }))
}
if (-not $lolos) { Berhenti-Semua; throw 'Pagar keamanan tidak lolos. Tidak ada yang dibuka ke internet.' }

if ($UjiLokal) {
    Write-Host ''
    Write-Host 'Uji lokal selesai: semua pagar keamanan lolos. Menghentikan Apache khusus aplikasi ...'
    Berhenti-Semua
    exit 0
}

# 5. Terowongan
Write-Host '[5/5] Membuat tautan online (Cloudflare Tunnel) ...'
$cf = (Get-Command cloudflared -ErrorAction SilentlyContinue).Source
if (-not $cf) {
    $cf = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter cloudflared.exe -Recurse -ErrorAction SilentlyContinue |
          Select-Object -First 1 -ExpandProperty FullName
}
if (-not $cf) { Berhenti-Semua; throw 'cloudflared tidak ditemukan. Pasang dengan: winget install Cloudflare.cloudflared' }

Remove-Item $logCf, "$logCf.out" -ErrorAction SilentlyContinue
$script:cfProc = Start-Process -FilePath $cf -ArgumentList 'tunnel', '--no-autoupdate', '--url', "http://127.0.0.1:$portApp" `
    -RedirectStandardError $logCf -RedirectStandardOutput "$logCf.out" -WindowStyle Hidden -PassThru
$alamat = $null
for ($i = 0; $i -lt 45 -and -not $alamat; $i++) {
    Start-Sleep 1
    if (Test-Path $logCf) {
        $m = Select-String -Path $logCf -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($m) { $alamat = $m.Matches[0].Value }
    }
}
if (-not $alamat) { Berhenti-Semua; throw 'Tautan online belum terbentuk. Periksa koneksi internet lalu coba lagi.' }

Set-Clipboard -Value $alamat
Write-Host ''
Write-Host '============================================' -ForegroundColor Green
Write-Host '  SISTEM ONLINE' -ForegroundColor Green
Write-Host "  $alamat" -ForegroundColor Green
Write-Host '  (tautan sudah disalin ke clipboard)' -ForegroundColor Green
Write-Host '============================================' -ForegroundColor Green
Write-Host ''
Write-Host '  - Bagikan tautan ini kepada pengguna. Tautan berubah setiap kali skrip dijalankan ulang.'
Write-Host '  - Komputer ini harus tetap menyala dan terhubung ke internet.'
Write-Host '  - Catatan akses ada di folder logs\ (apache-online-akses.log).'
Write-Host ''
Start-Process $alamat
[void](Read-Host '  Tekan Enter untuk MENGHENTIKAN akses online')
Berhenti-Semua
Write-Host 'Akses online dihentikan. XAMPP utama Anda tidak diubah.'
