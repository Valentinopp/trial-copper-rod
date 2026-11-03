<?php

// Inisialisasi direktori storage di /tmp untuk lingkungan serverless Vercel
$storageDirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Teruskan request ke entrypoint Laravel di backend/public/index.php
require __DIR__ . '/../backend/public/index.php';
