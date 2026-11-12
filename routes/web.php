<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\VehicleLookupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'business'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('vehicles/lookup', VehicleLookupController::class)->name('vehicles.lookup');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
