<?php

use App\Http\Controllers\AirlineController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AwbController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShipmentChargeController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentDocumentController;
use App\Http\Controllers\ShipmentStatusController;
use App\Http\Controllers\ShipmentTrackingController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', [TrackingController::class, 'show'])->name('tracking');
Route::get('/track', [TrackingController::class, 'show'])->name('tracking.show');
Route::get('/api/track/{awb}', [TrackingController::class, 'api'])
    ->middleware('throttle:60,1')
    ->name('tracking.api');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Signed in: staff and customers
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Customers reach index and show only; the policy blocks the rest.
    Route::resource('shipments', ShipmentController::class);

    Route::prefix('shipments/{shipment}')->name('shipments.')->group(function () {
        Route::post('status', [ShipmentStatusController::class, 'store'])->name('status.store');
        Route::delete('status/{event}', [ShipmentStatusController::class, 'destroy'])->name('status.destroy');

        Route::post('documents', [ShipmentDocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [ShipmentDocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [ShipmentDocumentController::class, 'destroy'])->name('documents.destroy');

        Route::post('charges', [ShipmentChargeController::class, 'store'])->name('charges.store');
        Route::delete('charges/{charge}', [ShipmentChargeController::class, 'destroy'])->name('charges.destroy');
        Route::post('charges/reprice', [ShipmentChargeController::class, 'reprice'])->name('charges.reprice');

        Route::post('tracking/sync', [ShipmentTrackingController::class, 'sync'])->name('tracking.sync');
    });

    /*
    |----------------------------------------------------------------------
    | Operations staff
    |----------------------------------------------------------------------
    */

    Route::middleware('can:operate')->group(function () {
        Route::resource('customers', CustomerController::class);
    });

    /*
    |----------------------------------------------------------------------
    | Administrators
    |----------------------------------------------------------------------
    */

    Route::middleware('can:administer')->group(function () {
        Route::resource('airlines', AirlineController::class)->except('show');

        Route::get('awbs', [AwbController::class, 'index'])->name('awbs.index');
        Route::post('awbs', [AwbController::class, 'store'])->name('awbs.store');
        Route::post('awbs/generate', [AwbController::class, 'generate'])->name('awbs.generate');
        Route::patch('awbs/{awb}', [AwbController::class, 'update'])->name('awbs.update');
        Route::delete('awbs/{awb}', [AwbController::class, 'destroy'])->name('awbs.destroy');

        Route::resource('users', UserController::class)->except('show');
    });
});
