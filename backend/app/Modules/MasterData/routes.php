<?php

use App\Modules\MasterData\Livewire\BomPage;
use App\Modules\MasterData\Livewire\CustomerPage;
use App\Modules\MasterData\Livewire\MasterDataPage;
use App\Modules\MasterData\Livewire\SupplierPage;
use Illuminate\Support\Facades\Route;

Route::get('/master-data/access-profiles', \App\Modules\MasterData\Livewire\AccessProfilePage::class)->name('master-data.access-profiles');

Route::redirect('/master-data', '/master-data/items')->name('master-data.index');
Route::get('/master-data/items', MasterDataPage::class)->defaults('section', 'items')->name('master-data.items');
Route::get('/master-data/categories', MasterDataPage::class)->defaults('section', 'categories')->name('master-data.categories');
Route::get('/master-data/uoms', MasterDataPage::class)->defaults('section', 'units')->name('master-data.units');
Route::get('/master-data/suppliers', SupplierPage::class)->name('master-data.suppliers');
Route::get('/master-data/customers', CustomerPage::class)->name('master-data.customers');
Route::get('/master-data/boms', BomPage::class)->name('master-data.boms');

foreach (['job-titles', 'divisions', 'employees'] as $section) {
    Route::get('/master-data/'.$section, \App\Modules\MasterData\Livewire\PersonnelPage::class)->defaults('section', $section)->name('master-data.'.$section);
}

foreach (array_keys(\App\Modules\MasterData\Models\ProductionReference::SECTIONS) as $section) {
    Route::get('/master-data/'.$section, \App\Modules\MasterData\Livewire\ProductionReferencePage::class)
        ->defaults('section', $section)->name('master-data.'.$section);
}
