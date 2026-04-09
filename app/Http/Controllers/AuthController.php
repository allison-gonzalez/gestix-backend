<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Helpers\VigenereHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->_id,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                    'telefono' => $user->telefono,
                    'estatus' => $user->estatus,
                    'departamento_id' => $user->departamento_id,
                    'permisos' => $user->permisos,
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

            $token = $this->generateToken($user);

            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->_id,
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

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'iss' => config('app.url'),
            'sub' => $user->_id,
            'data' => [
                'id' => $user->_id,
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
