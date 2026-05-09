<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProvisionController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentEventController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/tracking-publico', PublicTrackingController::class)->name('tracking.public');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('clientes', ClientController::class)
        ->parameters(['clientes' => 'client']);

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->middleware('role:admin');

    Route::resource('embarques', ShipmentController::class)
        ->parameters(['embarques' => 'shipment']);
    Route::get('/embarques-exportar', [ShipmentController::class, 'export'])->name('embarques.export');
    Route::post('/embarques/{shipment}/pin', [ShipmentController::class, 'togglePin'])->name('embarques.pin');

    Route::get('/tracking', [ShipmentEventController::class, 'overview'])->name('tracking.index');
    Route::get('/embarques/{shipment}/tracking', [ShipmentEventController::class, 'index'])->name('embarques.tracking.index');
    Route::post('/embarques/{shipment}/tracking', [ShipmentEventController::class, 'store'])->name('embarques.tracking.store');

    Route::get('/documentos', [DocumentController::class, 'overview'])->name('documentos.index');
    Route::get('/embarques/{shipment}/documentos', [DocumentController::class, 'index'])->name('embarques.documentos.index');
    Route::post('/embarques/{shipment}/documentos', [DocumentController::class, 'store'])->name('embarques.documentos.store');
    Route::get('/documentos/{document}/descargar', [DocumentController::class, 'download'])->name('documentos.download');
    Route::delete('/documentos/{document}', [DocumentController::class, 'destroy'])->name('documentos.destroy');

    Route::resource('provisiones', ProvisionController::class)
        ->parameters(['provisiones' => 'provision']);

    Route::resource('facturacion', InvoiceController::class)
        ->parameters(['facturacion' => 'invoice']);

    Route::get('/reportes', [ReportController::class, 'index'])->name('reportes.index');
    Route::view('/roles', 'roles.index')->middleware('role:admin')->name('roles.index');
    Route::get('/configuracion', [AppSettingController::class, 'edit'])->middleware('role:admin')->name('configuracion.edit');
    Route::put('/configuracion', [AppSettingController::class, 'update'])->middleware('role:admin')->name('configuracion.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
