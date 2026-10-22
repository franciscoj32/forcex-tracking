<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DocumentValidationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProvisionController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\RateConceptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ShipmentAlertController;
use App\Http\Controllers\ShipmentBulkController;
use App\Http\Controllers\ShipmentClientNoteController;
use App\Http\Controllers\ShipmentContainerController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentCutOffController;
use App\Http\Controllers\ShipmentDateChangeController;
use App\Http\Controllers\ShipmentEventController;
use App\Http\Controllers\ShipmentPreAlertController;
use App\Http\Controllers\ShipmentWhatsAppController;
use App\Http\Controllers\SupplierController;
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
    Route::post('/clientes/{client}/aprobar', [ClientController::class, 'approve'])->name('clientes.approve');
    Route::post('/clientes/{client}/rechazar', [ClientController::class, 'reject'])->name('clientes.reject');
    Route::post('/clientes/{client}/contactos', [ClientContactController::class, 'store'])->name('clientes.contactos.store');
    Route::put('/contactos/{contact}', [ClientContactController::class, 'update'])->name('contactos.update');
    Route::delete('/contactos/{contact}', [ClientContactController::class, 'destroy'])->name('contactos.destroy');

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->middleware('role:admin');

    Route::resource('embarques', ShipmentController::class)
        ->parameters(['embarques' => 'shipment']);
    Route::get('/embarques-exportar', [ShipmentController::class, 'export'])->name('embarques.export');
    Route::post('/embarques/{shipment}/pin', [ShipmentController::class, 'togglePin'])->name('embarques.pin');

    Route::post('/alertas', [ShipmentAlertController::class, 'store'])->name('alertas.store');
    Route::delete('/alertas/{state}', [ShipmentAlertController::class, 'destroy'])->name('alertas.destroy');

    Route::post('/embarques/{shipment}/cut-off-cumplido', [ShipmentCutOffController::class, 'store'])->name('embarques.cut-off.store');
    Route::delete('/embarques/{shipment}/cut-off-cumplido', [ShipmentCutOffController::class, 'destroy'])->name('embarques.cut-off.destroy');

    Route::post('/embarques/{shipment}/contenedores', [ShipmentContainerController::class, 'store'])->name('embarques.contenedores.store');
    Route::put('/embarques/{shipment}/contenedores-fechas', [ShipmentContainerController::class, 'applyDates'])->name('embarques.contenedores.fechas');
    Route::put('/contenedores/{container}', [ShipmentContainerController::class, 'update'])->name('contenedores.update');
    Route::delete('/contenedores/{container}', [ShipmentContainerController::class, 'destroy'])->name('contenedores.destroy');

    Route::get('/embarques-masivo', [ShipmentBulkController::class, 'index'])->name('embarques.masivo');
    Route::post('/embarques-masivo/fechas', [ShipmentBulkController::class, 'updateDates'])->name('embarques.masivo.fechas');
    Route::post('/embarques-masivo/hitos', [ShipmentBulkController::class, 'registerMilestone'])->name('embarques.masivo.hitos');

    Route::get('/cambios-fecha/lote/{lote}', [ShipmentDateChangeController::class, 'batchShow'])->whereUuid('lote')->name('cambios-fecha.lote');
    Route::post('/cambios-fecha/lote/{lote}', [ShipmentDateChangeController::class, 'batchReview'])->whereUuid('lote')->name('cambios-fecha.lote.review');
    Route::post('/cambios-fecha/{change}/aprobar', [ShipmentDateChangeController::class, 'approve'])->name('cambios-fecha.approve');
    Route::post('/cambios-fecha/{change}/descartar', [ShipmentDateChangeController::class, 'discard'])->name('cambios-fecha.discard');

    Route::get('/tracking', [ShipmentEventController::class, 'overview'])->name('tracking.index');
    Route::get('/embarques/{shipment}/tracking', [ShipmentEventController::class, 'index'])->name('embarques.tracking.index');
    Route::post('/embarques/{shipment}/tracking', [ShipmentEventController::class, 'store'])->name('embarques.tracking.store');
    Route::post('/embarques/{shipment}/notas-cliente', [ShipmentClientNoteController::class, 'store'])->name('embarques.notas-cliente.store');
    Route::post('/embarques/{shipment}/whatsapp', [ShipmentWhatsAppController::class, 'store'])->name('embarques.whatsapp');
    Route::get('/embarques/{shipment}/pre-alerta', [ShipmentPreAlertController::class, 'create'])->name('embarques.pre-alerta');
    Route::post('/embarques/{shipment}/pre-alerta', [ShipmentPreAlertController::class, 'store'])->name('embarques.pre-alerta.store');
    Route::post('/embarques/{shipment}/pre-alerta/retener', [ShipmentPreAlertController::class, 'hold'])->name('embarques.pre-alerta.retener');

    Route::get('/documentos', [DocumentController::class, 'overview'])->name('documentos.index');
    Route::get('/embarques/{shipment}/documentos', [DocumentController::class, 'index'])->name('embarques.documentos.index');
    Route::post('/embarques/{shipment}/documentos', [DocumentController::class, 'store'])->name('embarques.documentos.store');
    Route::get('/documentos/{document}/descargar', [DocumentController::class, 'download'])->name('documentos.download');
    Route::delete('/documentos/{document}', [DocumentController::class, 'destroy'])->name('documentos.destroy');
    Route::post('/documentos/{document}/aprobar', [DocumentValidationController::class, 'approve'])->name('documentos.approve');
    Route::post('/documentos/{document}/observar', [DocumentValidationController::class, 'observe'])->name('documentos.observe');

    Route::resource('provisiones', ProvisionController::class)
        ->parameters(['provisiones' => 'provision']);

    Route::get('/proveedores', [SupplierController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [SupplierController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{supplier}', [SupplierController::class, 'update'])->name('proveedores.update');

    Route::get('/conceptos', [RateConceptController::class, 'index'])->name('conceptos.index');
    Route::post('/conceptos', [RateConceptController::class, 'store'])->name('conceptos.store');
    Route::put('/conceptos/{concept}', [RateConceptController::class, 'update'])->name('conceptos.update');

    Route::resource('facturacion', InvoiceController::class)
        ->parameters(['facturacion' => 'invoice']);

    Route::get('/reportes', [ReportController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/operaciones.xlsx', [ReportExportController::class, 'operations'])->name('reportes.operaciones');
    Route::get('/reportes/financiero.xlsx', [ReportExportController::class, 'financial'])->name('reportes.financiero');
    Route::get('/reportes/gerencial.pdf', [ReportExportController::class, 'management'])->name('reportes.gerencial');
    Route::get('/embarques/{shipment}/estado.pdf', [ReportExportController::class, 'shipmentStatus'])->name('embarques.estado-pdf');
    Route::view('/roles', 'roles.index')->middleware('role:admin')->name('roles.index');
    Route::get('/configuracion', [AppSettingController::class, 'edit'])->middleware('role:admin')->name('configuracion.edit');
    Route::put('/configuracion', [AppSettingController::class, 'update'])->middleware('role:admin')->name('configuracion.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
