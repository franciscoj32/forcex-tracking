<?php

use App\Http\Controllers\Productivity\AnnouncementController;
use App\Http\Controllers\Productivity\NotificationController;
use App\Http\Controllers\Productivity\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo opcional de Productividad y Cultura
|--------------------------------------------------------------------------
| Se carga dentro del grupo "auth" con el middleware module:productividad:
| si el modulo esta apagado en Configuracion, estas rutas responden 404.
*/

Route::get('/tareas', [TaskController::class, 'index'])->name('tareas.index');
Route::post('/tareas', [TaskController::class, 'store'])->name('tareas.store');
Route::put('/tareas/{task}', [TaskController::class, 'update'])->name('tareas.update');
Route::post('/tareas/{task}/completar', [TaskController::class, 'complete'])->name('tareas.completar');
Route::delete('/tareas/{task}', [TaskController::class, 'destroy'])->name('tareas.destroy');

Route::get('/pizarra', [AnnouncementController::class, 'index'])->name('pizarra.index');
Route::post('/pizarra', [AnnouncementController::class, 'store'])->name('pizarra.store');
Route::post('/pizarra/{announcement}/fijar', [AnnouncementController::class, 'pin'])->name('pizarra.fijar');
Route::delete('/pizarra/{announcement}', [AnnouncementController::class, 'destroy'])->name('pizarra.destroy');

Route::get('/avisos', [NotificationController::class, 'index'])->name('avisos.index');
Route::get('/avisos/nuevos', [NotificationController::class, 'poll'])->name('avisos.nuevos');
Route::get('/avisos/{id}', [NotificationController::class, 'open'])->whereUuid('id')->name('avisos.abrir');
Route::post('/avisos/leidos', [NotificationController::class, 'readAll'])->name('avisos.leidos');
