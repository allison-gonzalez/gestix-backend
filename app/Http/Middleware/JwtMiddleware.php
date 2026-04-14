<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Models\Usuario;

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

        $tokenUserId = $decoded->data->id;

        // Con $primaryKey='id', laravel-mongodb traduce where('id', x) → _id = x.
        // Eso encuentra usuarios NUEVOS (donde _id es Int32).
        // Para usuarios LEGACY (_id es ObjectId + campo id separado), hay que usar driver nativo.
        $user = null;

        // 1) Intento Eloquent — funciona para usuarios nuevos con _id Int32
        if (is_numeric($tokenUserId)) {
            $user = Usuario::where('id', (int) $tokenUserId)->first();
        }

        // 2) Fallback nativo — funciona para usuarios legacy con id como campo separado
        if (!$user && is_numeric($tokenUserId)) {
            try {
                $mongoDB = DB::connection('mongodb')->getMongoDB();
                $doc = $mongoDB->usuarios->findOne(['id' => (int) $tokenUserId]);
                if ($doc) {
                    $user = Usuario::where('_id', new \MongoDB\BSON\ObjectId((string) $doc['_id']))->first();
                }
            } catch (\Exception $e) {
                \Log::warning('JwtMiddleware fallback lookup failed: ' . $e->getMessage());
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 401);
        }

        Auth::login($user, false);

        // Resolve the actual numeric id from the database (never trust the JWT value
        // which may be a MongoDB ObjectId string from old accounts).
        $numericUserId = $this->resolveNumericUserId($user);

        $request->attributes->add([
            'user_id'   => $numericUserId,
            'user_data' => $decoded->data,
        ]);

        return $next($request);
    }

    /**
     * Returns the integer sequential id of the user.
     * With $primaryKey='id', MongoDB stores the pk as _id.
     * - Properly created users: _id is Int32  → return it directly.
     * - Legacy users:           _id is ObjectId → check for a separate 'id' field
     *   added by a previous auto-heal, or auto-assign a new one and save it.
     */
    private function resolveNumericUserId(Usuario $user): ?int
    {
        $pk = $user->getAttributes()['id'] ?? null;

        // Case 1: primary key is already an integer (normal case)
        if (!($pk instanceof \MongoDB\BSON\ObjectId) && is_numeric($pk)) {
            return (int) $pk;
        }

        // Case 2: primary key is an ObjectId — legacy user without numeric id
        try {
            $mongoDB = DB::connection('mongodb')->getMongoDB();

            // Check for a separate numeric 'id' field already saved
            if ($pk instanceof \MongoDB\BSON\ObjectId) {
                $doc = $mongoDB->usuarios->findOne(
                    ['_id' => $pk],
                    ['projection' => ['id' => 1]]
                );
                if ($doc && isset($doc['id']) && is_numeric($doc['id'])) {
                    return (int) $doc['id'];
                }
            }

            // Auto-assign: find the max integer across both _id (Int32) and id fields
            $max = 0;
            $d1 = $mongoDB->usuarios->findOne(
                ['_id' => ['$type' => 'int']],
                ['sort' => ['_id' => -1], 'projection' => ['_id' => 1]]
            );
            $d2 = $mongoDB->usuarios->findOne(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1], 'projection' => ['id' => 1]]
            );
            if ($d1) $max = max($max, (int) $d1['_id']);
            if ($d2) $max = max($max, (int) $d2['id']);

            $newId = $max + 1;
            $mongoDB->usuarios->updateOne(
                ['_id' => $pk],
                ['$set' => ['id' => $newId]]
            );

            return $newId;
        } catch (\Exception $e) {
            \Log::warning('resolveNumericUserId failed: ' . $e->getMessage());
            return null;
        }
    }
}
