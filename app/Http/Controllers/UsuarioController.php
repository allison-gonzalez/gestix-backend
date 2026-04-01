<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Helpers\VigenereHelper;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function index()
    {
        try {
            $usuarios = Usuario::all();
            $result   = $usuarios->map(fn($u) => $this->formatUsuario($u))->values();
            return response()->json(['data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $usuario = Usuario::find($id);
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
            return response()->json(['data' => $this->formatUsuario($usuario)]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->all();

            if (empty($data['nombre']) || empty($data['correo']) || empty($data['contrasena'])) {
                return response()->json(['error' => 'Nombre, correo y contraseña son obligatorios'], 422);
            }

            $usuario                  = new Usuario();
            $usuario->nombre          = $data['nombre'];
            $usuario->correo          = $data['correo'];
            $usuario->telefono        = $data['telefono']        ?? null;
            $usuario->estatus         = (int) ($data['estatus']  ?? 1);
            $usuario->departamento_id = $data['departamento_id'] ?? null;
            $usuario->permisos        = $this->normalizarPermisos($data['permisos'] ?? []);
            $usuario->contrasena      = VigenereHelper::encrypt($data['contrasena']);
            $usuario->save();

            return response()->json([
                'data'    => $this->formatUsuario($usuario),
                'message' => 'Usuario creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $usuario = Usuario::find($id);
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            $data = $request->all();

            // Actualizar campo a campo — evita problemas con $hidden y MongoDB
            if (isset($data['nombre']))                     $usuario->nombre          = $data['nombre'];
            if (isset($data['correo']))                     $usuario->correo          = $data['correo'];
            if (array_key_exists('telefono', $data))        $usuario->telefono        = $data['telefono'];
            if (isset($data['estatus']))                    $usuario->estatus         = (int) $data['estatus'];
            if (array_key_exists('departamento_id', $data)) $usuario->departamento_id = $data['departamento_id'];
            if (isset($data['permisos']))                   $usuario->permisos        = $this->normalizarPermisos($data['permisos']);

            // Solo encriptar si se proporciona una nueva contraseña no vacía
            if (!empty($data['contrasena'])) {
                $usuario->contrasena = VigenereHelper::encrypt($data['contrasena']);
            }

            $usuario->save();

            $fresh = Usuario::find($id);

            return response()->json([
                'data'    => $this->formatUsuario($fresh),
                'message' => 'Usuario actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        try {
            $usuario = Usuario::find($id);
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
            $usuario->delete();
            return response()->json(['message' => 'Usuario eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function verifyPassword(Request $request, $id)
    {
        try {
            $usuario = Usuario::find($id);
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
            $contrasena = $request->input('contrasena', '');
            $stored     = $usuario->getAttributes()['contrasena'] ?? null;
            $valid      = $stored ? VigenereHelper::verify($contrasena, $stored) : false;
            return response()->json(['valid' => $valid]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Normaliza permisos: convierte BSONArray, objeto o cualquier cosa a array PHP limpio.
     */
    private function normalizarPermisos($val): array
    {
        if (is_null($val)) return [];
        if (is_array($val)) return array_values($val);
        // BSONArray u objeto iterable
        if (is_object($val) && method_exists($val, 'getIterator')) {
            return array_values(iterator_to_array($val));
        }
        // Fallback: cast
        return array_values((array) $val);
    }

    /**
     * Formatea un usuario para la respuesta JSON.
     * Siempre devuelve permisos como array JSON limpio (nunca BSONArray).
     */
    private function formatUsuario(Usuario $u): array
    {
        $attrs = $u->getAttributes();

        return [
            '_id'             => (string) ($u->_id ?? ''),
            'id'              => (string) ($u->_id ?? ''),
            'nombre'          => $attrs['nombre']          ?? null,
            'correo'          => $attrs['correo']          ?? null,
            'telefono'        => $attrs['telefono']        ?? null,
            'estatus'         => (int)   ($attrs['estatus']  ?? 0),
            'departamento_id' => $attrs['departamento_id'] ?? null,
            'permisos'        => $this->normalizarPermisos($attrs['permisos'] ?? []),
        ];
    }
}