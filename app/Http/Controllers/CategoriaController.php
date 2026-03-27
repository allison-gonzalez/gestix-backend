<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Obtener todas las categorías
     */
    public function index()
    {
        try {
            $categorias = Categoria::all();
            return response()->json([
                'data' => $categorias,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener categorías: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener una categoría específica
     */
    public function show($id)
    {
        try {
            $categoria = Categoria::find($id);

            if (!$categoria) {
                return response()->json([
                    'error' => 'Categoría no encontrada',
                ], 404);
            }

            return response()->json([
                'data' => $categoria,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener categoría: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear una nueva categoría
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255|unique:categorias,nombre',
                'departamento_id' => 'required|integer',
                'estatus' => 'required|in:0,1',
            ]);

            $categoria = Categoria::create($validated);

            return response()->json([
                'data' => $categoria,
                'message' => 'Categoría creada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear categoría: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Actualizar una categoría
     */
    public function update(Request $request, $id)
    {
        try {
            $categoria = Categoria::find($id);

            if (!$categoria) {
                return response()->json([
                    'error' => 'Categoría no encontrada',
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'string|max:255|unique:categorias,nombre,' . $id,
                'departamento_id' => 'integer',
                'estatus' => 'in:0,1',
            ]);

            $categoria->update($validated);

            return response()->json([
                'data' => $categoria,
                'message' => 'Categoría actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar categoría: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Eliminar una categoría
     */
    public function destroy($id)
    {
        try {
            $categoria = Categoria::find($id);

            if (!$categoria) {
                return response()->json([
                    'error' => 'Categoría no encontrada',
                ], 404);
            }

            $categoria->delete();

            return response()->json([
                'message' => 'Categoría eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar categoría: ' . $e->getMessage(),
            ], 400);
        }
    }
}
