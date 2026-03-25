<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('backup')->group(function () {
    Route::get('/info',       [BackupController::class, 'info']);
    Route::post('/create',    [BackupController::class, 'create']);
    Route::get('/list',       [BackupController::class, 'list']);
    Route::get('/download/{filename}', [BackupController::class, 'download']);
    Route::delete('/{filename}', [BackupController::class, 'delete']);
    Route::post('/restore/{filename}', [BackupController::class, 'restore']);
});
