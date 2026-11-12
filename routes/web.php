<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VehicleLookupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'business'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('vehicles/lookup', VehicleLookupController::class)->name('vehicles.lookup');

    Route::get('reports', [ReportController::class, 'index'])->middleware('can:view-reports')->name('reports.index');

    Route::middleware('can:manage-catalog')->prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])->name('index');
        Route::post('vehicle-types', [CatalogController::class, 'storeVehicleType'])->name('vehicle-types.store');
        Route::post('services', [CatalogController::class, 'storeService'])->name('services.store');
        Route::patch('services/{service}', [CatalogController::class, 'updateService'])->name('services.update');
        Route::put('services/{service}/prices', [CatalogController::class, 'updatePrices'])->name('services.prices');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
