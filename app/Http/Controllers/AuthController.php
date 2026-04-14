<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Helpers\VigenereHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends Controller
{
    private $secret_key = null;

    public function __construct()
    {
        $this->secret_key = env('JWT_SECRET', config('app.key'));
    }




    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            \Log::info('Buscando usuario con correo', ['correo' => $request->email]);
            $user = Usuario::where('correo', $request->email)->first();
            \Log::info('Resultado de búsqueda', ['user_found' => $user ? 'Sí' : 'No', 'user_id' => $user ? $user->_id : null]);

            if (!$user) {
                \Log::warning('Usuario no encontrado', ['correo' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'El correo no está registrado',
                ], 401);
            }

            if ($user->estatus !== 1) {
                \Log::warning('Usuario inactivo intentando hacer login', ['user_id' => $user->_id, 'estatus' => $user->estatus]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta está desactivada. Contacta al administrador.',
                ], 401);
            }

            $passwordValid = false;



            try {
                $decryptedPassword = VigenereHelper::decrypt($user->contrasena);
                if ($decryptedPassword === $request->password) {
                    $passwordValid = true;
                }
            } catch (\Exception $e) {
                \Log::warning('Error al descifrar contraseña', ['user_id' => $user->_id, 'error' => $e->getMessage()]);
            }

            if (!$passwordValid) {
                \Log::warning('Contraseña incorrecta', [
                    'user_id' => $user->_id,
                    'stored_password' => $user->contrasena,
                    'provided_password' => $request->password
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Contraseña incorrecta',
                ], 401);
            }

            $token = $this->generateToken($user);

            // Extraer id de forma segura (puede ser int o ObjectId)
            $userId = $user->getAttributes()['id'] ?? $user->_id;
            $userId = is_numeric($userId) ? (int) $userId : (string) $userId;

            \Log::info('Login response user:', [
                'id' => $userId,
                'must_change_password_raw' => $user->must_change_password,
                'must_change_password_cast' => (bool) ($user->must_change_password ?? false),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $userId,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                    'telefono' => $user->telefono,
                    'estatus' => $user->estatus,
                    'departamento_id' => is_numeric($user->departamento_id) ? (int) $user->departamento_id : null,
                    'permisos' => $user->permisos,
                    'must_change_password' => (bool) ($user->must_change_password ?? false),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'telefono' => 'nullable|string',
                'departamento_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $existingUser = Usuario::where('correo', $request->email)->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo ya está registrado',
                ], 422);
            }

            $user = Usuario::create([
                'nombre' => $request->name,
                'correo' => $request->email,
                'contrasena' => VigenereHelper::encrypt($request->password),
                'telefono' => $request->telefono ?? '',
                'estatus' => 'activo',
                'departamento_id' => $request->departamento_id ?? null,
                'permisos' => [],
            ]);

            // Asignar id numérico via driver nativo (laravel-mongodb mapea id→_id en queries)
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $lastDoc = $mongoDB->usuarios->find(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1], 'limit' => 1]
            )->toArray();
            $nextId  = !empty($lastDoc) ? ((int) $lastDoc[0]['id'] + 1) : 1;
            $mongoDB->usuarios->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId((string) $user->_id)],
                ['$set' => ['id' => $nextId]]
            );

            $token = $this->generateToken($user);

            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $nextId,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                    'telefono' => $user->telefono,
                    'estatus' => $user->estatus,
                    'departamento_id' => $user->departamento_id,
                    'permisos' => $user->permisos,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Correo electrónico inválido',
                ], 422);
            }

            $user = Usuario::where('correo', $request->email)->first();

            // Always return success to avoid email enumeration
            if (!$user) {
                return response()->json([
                    'success' => true,
                    'message' => 'Si el correo está registrado, recibirás una contraseña temporal.',
                ]);
            }

            if ($user->estatus !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta está desactivada. Contacta al administrador.',
                ], 403);
            }

            // Generate temporary password: Temporal@XXXX
            $tempPassword = 'Temporal@' . rand(1000, 9999);
            $encrypted = VigenereHelper::encrypt($tempPassword);

            $user->contrasena = $encrypted;
            $user->must_change_password = true;
            $user->save();

            \Log::info('Forgot password - User updated', [
                'user_id' => $user->id,
                'must_change_password' => $user->must_change_password,
            ]);

            // Send email with temp password
            $userName = $user->nombre;
            $toEmail  = $user->correo;

            Mail::send([], [], function ($message) use ($toEmail, $userName, $tempPassword) {
                $message->to($toEmail)
                    ->subject('Gestix - Contraseña temporal')
                    ->html(
                        '<div style="font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:32px;border:1px solid #e0e0e0;border-radius:12px;">'
                        . '<h2 style="color:#1B3A5C;">Recuperación de contraseña</h2>'
                        . '<p>Hola <strong>' . htmlspecialchars($userName) . '</strong>,</p>'
                        . '<p>Tu contraseña temporal para acceder a <strong>Gestix</strong> es:</p>'
                        . '<div style="background:#f0f7ff;border:2px dashed #1B3A5C;border-radius:8px;padding:16px 24px;text-align:center;font-size:22px;font-weight:bold;color:#1B3A5C;letter-spacing:1px;margin:20px 0;">'
                        . htmlspecialchars($tempPassword)
                        . '</div>'
                        . '<p style="color:#e67e22;font-size:13px;">Por seguridad, cambia esta contraseña desde tu perfil después de iniciar sesión.</p>'
                        . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">'
                        . '<p style="color:#999;font-size:12px;">Si no solicitaste este correo, ignóralo.</p>'
                        . '</div>'
                    );
            });

            return response()->json([
                'success' => true,
                'message' => 'Se ha enviado una contraseña temporal a tu correo electrónico.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor',
            ], 500);
        }
    }


    public function me(Request $request)
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function logout()
    {
        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso',
        ], 200);
    }


    public function refresh(Request $request)
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o expirado',
                ], 401);
            }

            $token = $this->generateToken($user);

            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    private function generateToken(Usuario $user)
    {
        $issuedAt = time();
        $expire = $issuedAt + (60 * 60 * 24); // 24 horas

        // With $primaryKey='id', getAttributes()['id'] equals MongoDB's _id.
        // For properly created users _id is Int32; for legacy users it's an ObjectId.
        $rawId = $user->getAttributes()['id'] ?? null;
        $userId = (!($rawId instanceof \MongoDB\BSON\ObjectId) && is_numeric($rawId))
            ? (int) $rawId
            : (string) ($rawId ?? $user->_id);

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'iss' => config('app.url'),
            'sub' => $user->_id,
            'data' => [
                'id' => $userId,
                'nombre' => $user->nombre,
                'correo' => $user->correo,
            ],
        ];

        return JWT::encode($payload, $this->secret_key, 'HS256');
    }


    public static function verifyToken($token)
    {
        try {
            $secret_key = env('JWT_SECRET', config('app.key'));
            $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
