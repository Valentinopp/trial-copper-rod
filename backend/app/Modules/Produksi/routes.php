<?php

use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\CopperBreakPage;
use App\Modules\Produksi\Livewire\CrystallizerPage;
use App\Modules\Produksi\Livewire\OutputPage;
use Illuminate\Support\Facades\Route;

Route::get('/', BoardPage::class)->name('board');
Route::get('/crystallizer', CrystallizerPage::class)->name('crystallizer');
Route::get('/copper-break', CopperBreakPage::class)->name('copper-break');
Route::get('/output', OutputPage::class)->name('output');
