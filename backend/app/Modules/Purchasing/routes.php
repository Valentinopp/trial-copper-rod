<?php

use App\Modules\Purchasing\Livewire\DashboardPage;
use App\Modules\Purchasing\Livewire\PengajuanPage;
use App\Modules\Purchasing\Livewire\PembelianPage;
use Illuminate\Support\Facades\Route;

Route::get('/purchasing', DashboardPage::class)->name('purchasing.dashboard');
Route::get('/purchasing/requests', PengajuanPage::class)->name('purchasing.requests');
Route::get('/purchasing/orders', PembelianPage::class)->name('purchasing.orders');