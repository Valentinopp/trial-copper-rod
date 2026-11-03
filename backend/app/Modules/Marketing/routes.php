<?php

use App\Modules\Marketing\Livewire\SalesPage;
use Illuminate\Support\Facades\Route;

Route::redirect('/marketing', '/marketing/sales')->name('marketing.index');
// Keep old bookmarks working after retiring the catalog page.
Route::redirect('/marketing/products', '/marketing/sales');
Route::get('/marketing/sales', SalesPage::class)->name('marketing.sales');
