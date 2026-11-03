<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Inisialisasi direktori storage di /tmp untuk lingkungan serverless Vercel
$storageDirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/storage/bootstrap/cache',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Redirect cache paths ke /tmp agar dapat ditulis di lingkungan serverless
$cacheEnv = [
    'APP_PACKAGES_CACHE' => '/tmp/storage/bootstrap/cache/packages.php',
    'APP_SERVICES_CACHE' => '/tmp/storage/bootstrap/cache/services.php',
    'APP_CONFIG_CACHE' => '/tmp/storage/bootstrap/cache/config.php',
    'APP_ROUTES_CACHE' => '/tmp/storage/bootstrap/cache/routes.php',
    'APP_EVENTS_CACHE' => '/tmp/storage/bootstrap/cache/events.php',
];

foreach ($cacheEnv as $k => $v) {
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
    putenv("{$k}={$v}");
}

// Copy pre-built bootstrap cache ke /tmp bila tersedia
$bootstrapSource = __DIR__ . '/../backend/bootstrap/cache';
if (is_dir($bootstrapSource)) {
    foreach (['packages.php', 'services.php'] as $file) {
        $src = $bootstrapSource . '/' . $file;
        $dst = '/tmp/storage/bootstrap/cache/' . $file;
        if (file_exists($src) && !file_exists($dst)) {
            @copy($src, $dst);
        }
    }
}

if (!extension_loaded('pdo_pgsql')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "MISSING EXTENSION: pdo_pgsql is NOT loaded in PHP " . PHP_VERSION . "!\n";
    echo "Loaded extensions:\n" . implode(", ", get_loaded_extensions()) . "\n";
    exit;
}

$vendorPaths = [
    __DIR__ . '/../backend/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
];
$foundVendor = null;
foreach ($vendorPaths as $path) {
    if (file_exists($path)) {
        $foundVendor = $path;
        break;
    }
}
if (!$foundVendor) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: vendor/autoload.php not found!\nChecked paths:\n" . implode("\n", $vendorPaths) . "\n\nFiles in __DIR__:\n";
    print_r(scandir(__DIR__));
    echo "\nFiles in __DIR__/..:\n";
    print_r(scandir(__DIR__ . '/..'));
    if (is_dir(__DIR__ . '/../backend')) {
        echo "\nFiles in __DIR__/../backend:\n";
        print_r(scandir(__DIR__ . '/../backend'));
    }
    exit;
}

try {
    // Teruskan request ke entrypoint Laravel di backend/public/index.php
    require __DIR__ . '/../backend/public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "SERVERLESS EXECUTION ERROR:\n";
    echo $e->getMessage() . "\n\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
