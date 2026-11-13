<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleLookupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Signed-in users always start at Inicio; the landing page is for visitors.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'business'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('vehicles/lookup', VehicleLookupController::class)->name('vehicles.lookup');

    Route::middleware('can:manage-customers')->group(function () {
        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->whereNumber('vehicle')->name('vehicles.show');
        Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->whereNumber('vehicle')->name('vehicles.update');
    });

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
