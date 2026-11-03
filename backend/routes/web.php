<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'can:access-erp'])->group(function () {
    Route::view('/access-unavailable', 'auth.access-unavailable')->name('access-unavailable');
    Route::middleware('can:access-master-data')->group(function () {
        require app_path('Modules/MasterData/routes.php');
    });
    require app_path('Modules/Ppic/routes.php');
    foreach (['Produksi', 'Marketing', 'Purchasing'] as $module) {
        Route::middleware('can:access-'.strtolower($module))->group(function () use ($module) {
            require app_path("Modules/{$module}/routes.php");
        });
    }
});
