<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use Illuminate\Http\Request;

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

            $permiso = Permiso::create($validated);

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

            $permiso->update($validated);

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
