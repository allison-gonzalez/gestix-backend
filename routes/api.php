<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ProfileController; // Importado correctamente
use App\Http\Controllers\ArchivoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Ruta de prueba
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API funcionando',
        'timestamp' => now(),
        'server' => 'Laravel Backend'
    ]);
});

// Rutas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('jwt')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/user/update-password', [ProfileController::class, 'updatePassword']);
        Route::put('/user/update-profile', [ProfileController::class, 'updateProfile']);
    });
});

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

// Rutas de Comentarios
Route::get('/tickets/{id}/comentarios', [ComentarioController::class, 'getByTicket']);
Route::prefix('comentarios')->group(function () {
    Route::get('/', [ComentarioController::class, 'index']);
    Route::post('/', [ComentarioController::class, 'store']);
    Route::delete('/{id}', [ComentarioController::class, 'destroy']);
});

// Rutas de Archivos
Route::get('/archivos/{tipo}/{id}', [ArchivoController::class, 'getByEntidad']);
Route::delete('/archivos/{id}', [ArchivoController::class, 'destroy']);

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

// Rutas de Reportes
Route::get('/reportes', [ReporteController::class, 'index']);

// Rutas de Usuarios
Route::prefix('usuarios')->group(function () {
    Route::get('/',              [UsuarioController::class, 'index']);
    Route::post('/',             [UsuarioController::class, 'store']);
    Route::get('/{id}',          [UsuarioController::class, 'show']);
    Route::put('/{id}',          [UsuarioController::class, 'update']);
    Route::delete('/{id}',       [UsuarioController::class, 'destroy']);
    Route::post('/{id}/verify-password', [UsuarioController::class, 'verifyPassword']);
});
