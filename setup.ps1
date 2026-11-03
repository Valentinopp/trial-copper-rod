$ErrorActionPreference = 'Stop'

function Require-Command([string]$Name, [string]$Help) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name belum dikenali oleh terminal. $Help"
    }
}

Push-Location (Join-Path $PSScriptRoot 'backend')
try {
    Write-Host '=== Setup irris.next - PostgreSQL ===' -ForegroundColor Cyan
    Require-Command 'php' 'Pastikan PHP 8.4.1+ tersedia pada PATH. Periksa dengan php -v.'
    Require-Command 'composer' 'Pasang Composer 2 dan buka ulang terminal.'
    Require-Command 'node' 'Pasang Node.js LTS yang kompatibel dengan Vite 6.'
    Require-Command 'npm.cmd' 'Pastikan npm dari instalasi Node.js tersedia pada PATH.'

    $preflight = @'
<?php
if (PHP_VERSION_ID < 80401) {
    file_put_contents('php://stderr', "Dependency composer.lock memerlukan PHP minimal 8.4.1.\n");
    exit(1);
}
if (!extension_loaded('pdo_pgsql')) {
    file_put_contents('php://stderr', "Ekstensi pdo_pgsql belum aktif. Periksa php --ini dan php -m.\n");
    exit(1);
}
'@
    $preflight | php
    if ($LASTEXITCODE -ne 0) { throw 'Pemeriksaan PHP gagal. Lengkapi kebutuhan di README.md.' }
    $nodeVersion = (& node -p 'process.versions.node').Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membaca versi Node.js.' }
    $nodeMajor = ([version]$nodeVersion).Major
    if (-not ($nodeMajor -eq 18 -or $nodeMajor -eq 20 -or $nodeMajor -ge 22)) {
        throw 'Versi Node.js tidak kompatibel dengan Vite 6. Gunakan Node.js 22 atau 24 LTS.'
    }

    if (-not (Test-Path -LiteralPath '.env')) {
        Copy-Item -LiteralPath '.env.example' -Destination '.env'
        throw 'backend/.env sudah dibuat. Isi koneksi PostgreSQL dan buat database sesuai README.md, lalu jalankan setup lagi.'
    }

    Write-Host '[1/6] Memasang dependency PHP sesuai lockfile...' -ForegroundColor Yellow
    composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'composer install gagal. Periksa versi PHP dan ekstensi yang disebut pada pesan Composer.' }
    composer check-platform-reqs
    if ($LASTEXITCODE -ne 0) { throw 'Kebutuhan PHP atau ekstensi dependency belum terpenuhi.' }

    php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membersihkan cache konfigurasi.' }
    $configurationCheck = @'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'pgsql') {
    file_put_contents('php://stderr', "Gunakan DB_CONNECTION=pgsql di backend/.env.\n");
    exit(1);
}
try {
    $app->make('db')->connection()->getPdo();
} catch (Throwable $exception) {
    file_put_contents('php://stderr', "Koneksi PostgreSQL gagal. Pastikan server berjalan, database sudah dibuat, serta host/port/user/password sesuai backend/.env.\n");
    exit(1);
}
echo json_encode(['has_key' => !empty(config('app.key'))]);
'@
    $configurationJson = $configurationCheck | php
    if ($LASTEXITCODE -ne 0) { throw 'Konfigurasi database belum siap. Ikuti langkah PostgreSQL di README.md.' }
    $configuration = $configurationJson | ConvertFrom-Json

    Write-Host '[2/6] Memeriksa APP_KEY...' -ForegroundColor Yellow
    if (-not $configuration.has_key) {
        php artisan key:generate --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Pembuatan APP_KEY gagal.' }
    } else {
        Write-Host 'APP_KEY sudah tersedia; tetap dipertahankan.'
    }

    Write-Host '[3/6] Menjalankan migration PostgreSQL...' -ForegroundColor Yellow
    php artisan migrate --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Migration gagal. Periksa pesan error di atas.' }

    Set-Location (Join-Path $PSScriptRoot 'frontend')
    Write-Host '[4/6] Memasang dependency JavaScript sesuai lockfile...' -ForegroundColor Yellow
    npm.cmd ci
    if ($LASTEXITCODE -ne 0) { throw 'npm ci gagal. Periksa versi Node.js dan pesan error di atas.' }

    Write-Host '[5/6] Build aset frontend...' -ForegroundColor Yellow
    npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Build aset frontend gagal.' }

    Write-Host '[6/6] Setup selesai.' -ForegroundColor Green
    Write-Host 'Jalankan dari backend: php artisan serve' -ForegroundColor Cyan
    Write-Host 'Kemudian buka: http://127.0.0.1:8000' -ForegroundColor Cyan
} finally {
    Pop-Location
}
