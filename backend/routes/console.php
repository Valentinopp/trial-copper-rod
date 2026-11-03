<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about-wip', function () {
    $this->info('WIP Board Laravel + Livewire scaffold aktif.');
})->purpose('Menampilkan status singkat aplikasi WIP Board');

Artisan::command('demo:users {--reset-passwords : Samakan username dan password akun dummy yang sudah ada}', function () {
    $rows = app(\Database\Seeders\DummyUserSeeder::class)->sync((bool) $this->option('reset-passwords'));
    $this->table(['Role', 'Username', 'Password dummy'], $rows);
})->purpose('Menyiapkan akun dummy lokal; password sama dengan username');
