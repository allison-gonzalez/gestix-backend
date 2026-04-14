<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token no proporcionado',
            ], 401);
        }

        $decoded = AuthController::verifyToken($token);

        if (!$decoded) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o expirado',
            ], 401);
        }

        $userId = $decoded->data->id;
        $user = Usuario::where('id', $userId)->orWhere('_id', $userId)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 401);
        }

        Auth::login($user, false);
        $request->attributes->add([
            'user_id' => $userId,
            'user_data' => $decoded->data,
        ]);

        return $next($request);
    }
}
