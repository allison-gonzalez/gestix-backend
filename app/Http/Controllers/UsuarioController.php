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
                'contrasena'      => 'required|string|min:4',
                'estatus'         => 'required',
                'departamento_id' => 'nullable',
                'permisos'        => 'nullable|array',
            ]);

            $validated['estatus']         = (int) $validated['estatus'];
            $validated['contrasena']      = VigenereHelper::encrypt($validated['contrasena']);
            $validated['departamento_id'] = $validated['departamento_id'] ?? null;
            $validated['permisos']        = $validated['permisos'] ?? [];

            // Calcular siguiente id usando driver nativo para evitar que max() devuelva un ObjectId
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $lastDoc = $mongoDB->usuarios->find(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1], 'limit' => 1]
            )->toArray();
            $nextId  = !empty($lastDoc) ? ((int) $lastDoc[0]['id'] + 1) : 1;

            $usuario = Usuario::create($validated);

            // Asignar id numérico via driver nativo para evitar mapeo id→_id de laravel-mongodb
            $mongoDB->usuarios->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId((string) $usuario->_id)],
                ['$set' => ['id' => $nextId]]
            );

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
            ]);

            if (isset($data['estatus'])) {
                $data['estatus'] = (int) $data['estatus'];
            }

            if (!empty($data['contrasena'])) {
                $data['contrasena'] = VigenereHelper::encrypt($data['contrasena']);
            } else {
                unset($data['contrasena']);
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
            '_id'             => (string) ($u->_id ?? ''),
            'id'              => $numericId,
            'nombre'          => $attrs['nombre']          ?? null,
            'correo'          => $attrs['correo']          ?? null,
            'telefono'        => $attrs['telefono']        ?? null,
            'estatus'         => (int)   ($attrs['estatus']  ?? 0),
            'departamento_id' => $attrs['departamento_id'] ?? null,
            'permisos'        => $attrs['permisos']        ?? [],
        ];
    }
}
