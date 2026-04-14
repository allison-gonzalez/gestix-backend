<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermisoController extends Controller
{
    /**
     * Obtener todos los permisos
     */
    public function index()
    {
        try {
            $permisos = Permiso::all();
            return response()->json([
                'data' => $permisos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener permisos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un permiso específico
     */
    public function show($id)
    {
        try {
            $permiso = Permiso::find($id);
            
            if (!$permiso) {
                return response()->json([
                    'error' => 'Permiso no encontrado',
                ], 404);
            }

            return response()->json([
                'data' => $permiso,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener permiso: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear un nuevo permiso
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255|unique:permisos,nombre',
                'descripcion' => 'nullable|string',
                'estatus' => 'required|in:0,1',
            ]);

            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $lastDoc = $mongoDB->permisos->findOne(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1]]
            );
            $nextId = $lastDoc ? ((int) $lastDoc['id'] + 1) : 1;

            $now = new \MongoDB\BSON\UTCDateTime();
            $mongoDB->permisos->insertOne([
                'id'          => (int) $nextId,
                'nombre'      => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'estatus'     => (int) $validated['estatus'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $permiso = Permiso::where('id', $nextId)->first();

            return response()->json([
                'data' => $permiso,
                'message' => 'Permiso creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear permiso: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Actualizar un permiso
     */
    public function update(Request $request, $id)
    {
        try {
            $permiso = Permiso::find($id);
            
            if (!$permiso) {
                return response()->json([
                    'error' => 'Permiso no encontrado',
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'string|max:255|unique:permisos,nombre,' . $id,
                'descripcion' => 'nullable|string',
                'estatus' => 'in:0,1',
            ]);

            $permiso->fill($validated);
            $saved = $permiso->save();
            
            if ($saved) {
                // Hacer un query fresco a BD para confirmar persistencia
                $fresh = Permiso::find($id);
                $permiso = $fresh;
            }

            return response()->json([
                'data' => $permiso,
                'message' => 'Permiso actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar permiso: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Eliminar un permiso
     */
    public function destroy($id)
    {
        try {
            $permiso = Permiso::find($id);
            
            if (!$permiso) {
                return response()->json([
                    'error' => 'Permiso no encontrado',
                ], 404);
            }

            $permiso->delete();

            return response()->json([
                'message' => 'Permiso eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar permiso: ' . $e->getMessage(),
            ], 400);
        }
    }
}
