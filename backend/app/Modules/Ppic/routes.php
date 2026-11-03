<?php
use App\Modules\Ppic\Livewire\DashboardPage;
use App\Modules\Ppic\Livewire\ProductionPlanPage;
use App\Modules\Ppic\Livewire\InventoryControlPage;
use Illuminate\Support\Facades\Route;
Route::middleware('can:access-ppic')->group(function () {
    Route::get('/ppic', DashboardPage::class)->name('ppic.dashboard');
    Route::get('/ppic/production-plan', ProductionPlanPage::class)->name('ppic.production-plan');
    Route::get('/ppic/production-calendar', \App\Modules\Ppic\Livewire\ProductionCalendarPage::class)->name('ppic.production-calendar');
});
Route::get('/ppic/inventory-control', InventoryControlPage::class)->middleware('can:access-ic')->name('ppic.inventory-control');
Route::get('/ppic/inventory-control/sales', InventoryControlPage::class)->middleware('can:ppic.ic.sales.view')->name('ppic.ic.sales');
Route::get('/ppic/inventory-control/requests', InventoryControlPage::class)->middleware('can:ppic.ic.requests.view')->name('ppic.ic.requests');
Route::get('/ppic/inventory-control/receipts', InventoryControlPage::class)->middleware('can:ppic.ic.receipts.view')->name('ppic.ic.receipts');
Route::get('/ppic/inventory-control/stock', InventoryControlPage::class)->middleware('can:ppic.ic.stock.view')->name('ppic.ic.stock');

Route::get('/ppic/inventory-control/issues', \App\Modules\Ppic\Livewire\GoodsIssuePage::class)->middleware('can:ppic.ic.issues.view')->name('ppic.ic.issues');
