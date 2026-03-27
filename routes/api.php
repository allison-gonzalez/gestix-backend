<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\CategoriaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas de Tickets
Route::prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'index']);
    Route::get('/stats', [TicketController::class, 'stats']);
    Route::post('/', [TicketController::class, 'store']);
    Route::get('/{id}', [TicketController::class, 'show']);
    Route::put('/{id}', [TicketController::class, 'update']);
    Route::delete('/{id}', [TicketController::class, 'destroy']);
    Route::post('/{id}/resolve', [TicketController::class, 'resolve']);
});

// Rutas de Backups
Route::prefix('backup')->group(function () {
    Route::get('/info',       [BackupController::class, 'info']);
    Route::post('/create',    [BackupController::class, 'create']);
    Route::get('/list',       [BackupController::class, 'list']);
    Route::get('/schedule',    [BackupController::class, 'schedule']);
    Route::put('/schedule',    [BackupController::class, 'updateSchedule']);
    Route::get('/download/{filename}', [BackupController::class, 'download']);
    Route::delete('/{filename}', [BackupController::class, 'delete']);
    Route::post('/restore/{filename}', [BackupController::class, 'restore']);
});

// Rutas de Departamentos
Route::apiResource('departamentos', DepartamentoController::class);

// Rutas de Permisos
Route::apiResource('permisos', PermisoController::class);

// Rutas de Categorías
Route::apiResource('categorias', CategoriaController::class);
