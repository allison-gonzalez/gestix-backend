<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Models\User;


Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API funcionando',
        'timestamp' => now()
    ]);
});

Route::get('/test-db', function () {
    try {
        $user = User::where('correo', 'juan.perez@innovatechgdl.com')->first();
        $userCount = User::count();
        
        return response()->json([
            'success' => true,
            'table_name' => (new User())->getTable(),
            'total_users' => $userCount,
            'specific_user' => [
                'found' => $user ? true : false,
                'nombre' => $user->nombre ?? 'N/A',
                'correo' => $user->correo ?? 'N/A',
                'password_matches' => $user && $user->contrasena === 'hash' ? 'YES' : 'NO'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('jwt')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

Route::middleware('jwt')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->attributes->get('user_data'),
        ]);
    });
});
