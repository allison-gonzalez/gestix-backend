<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Helpers\VigenereHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $validated = $request->validate([
                'nombre'          => 'required|string|max:255',
                'correo'          => 'required|string',
                'telefono'        => 'nullable|string|max:20',
                'contrasena'      => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/'],
                'estatus'         => 'required',
                'departamento_id' => 'nullable|integer',
                'permisos'        => 'nullable|array',
            ]);

            $validated['estatus']         = (int) $validated['estatus'];
            $validated['contrasena']      = VigenereHelper::encrypt($validated['contrasena']);
            $validated['departamento_id'] = $validated['departamento_id'] ?? null;
            $validated['permisos']        = $validated['permisos'] ?? [];

            // Calcular siguiente id usando driver nativo para evitar que max() devuelva un ObjectId
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $lastDoc = $mongoDB->usuarios->findOne(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1]]
            );
            $nextId  = $lastDoc ? ((int) $lastDoc['id'] + 1) : 1;

            // Asignar id numérico antes de crear
            $validated['id'] = $nextId;

            $usuario = Usuario::create($validated);

            return response()->json([
                'data'    => $this->formatUsuario($usuario, $nextId),
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

            $data = $request->only([
                'nombre', 'correo', 'telefono',
                'contrasena', 'estatus', 'departamento_id', 'permisos',
                'categorias_asignables',
            ]);

            if (isset($data['estatus'])) {
                $data['estatus'] = (int) $data['estatus'];
            }

            // Update via native driver to avoid BSON dirty-tracking issues with laravel-mongodb
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $setFields = [];

            if (!empty($data['contrasena'])) {
                $setFields['contrasena'] = VigenereHelper::encrypt($data['contrasena']);
            }
            unset($data['contrasena']);

            if (array_key_exists('permisos', $data)) {
                $setFields['permisos'] = array_values(array_map('intval', $data['permisos']));
                unset($data['permisos']);
            }
            if (array_key_exists('estatus', $data)) {
                $setFields['estatus'] = (int) $data['estatus'];
                unset($data['estatus']);
            }
            if (array_key_exists('departamento_id', $data)) {
                $setFields['departamento_id'] = $data['departamento_id'] !== null ? (int) $data['departamento_id'] : null;
                unset($data['departamento_id']);
            }
            if (array_key_exists('categorias_asignables', $data)) {
                $setFields['categorias_asignables'] = array_values(array_map('intval', $data['categorias_asignables'] ?? []));
                unset($data['categorias_asignables']);
            }

            if (!empty($setFields)) {
                $mongoDB->usuarios->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId((string) $usuario->_id)],
                    ['$set' => $setFields]
                );
            }

            $usuario->fill($data);
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

    private function formatUsuario(Usuario $u, ?int $forceId = null): array
    {
        $attrs = $u->getAttributes();
        $rawId = $attrs['id'] ?? null;

        // Documentos viejos pueden tener id como ObjectId — descartar en ese caso
        if ($rawId instanceof \MongoDB\BSON\ObjectId) {
            $rawId = null;
        }

        $numericId = $forceId ?? (is_numeric($rawId) ? (int) $rawId : null);

        return [
            '_id'                   => (string) ($u->_id ?? ''),
            'id'                    => $numericId,
            'nombre'                => $attrs['nombre']               ?? null,
            'correo'                => $attrs['correo']               ?? null,
            'telefono'              => $attrs['telefono']             ?? null,
            'estatus'               => (int)   ($attrs['estatus']     ?? 0),
            'departamento_id'       => $attrs['departamento_id']      ?? null,
            'permisos'              => $attrs['permisos']             ?? [],
            'categorias_asignables' => $attrs['categorias_asignables'] ?? [],
        ];
    }
}
