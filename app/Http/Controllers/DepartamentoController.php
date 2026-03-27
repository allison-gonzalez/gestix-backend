<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    /**
     * Obtener todos los departamentos
     */
    public function index()
    {
        try {
            $departamentos = Departamento::all();
            return response()->json([
                'data' => $departamentos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener departamentos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un departamento específico
     */
    public function show($id)
    {
        try {
            $departamento = Departamento::find($id);

            if (!$departamento) {
                return response()->json([
                    'error' => 'Departamento no encontrado',
                ], 404);
            }

            return response()->json([
                'data' => $departamento,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener departamento: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear un nuevo departamento
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255|unique:departamentos,nombre',
                'estatus' => 'required|in:0,1',
            ]);

            $departamento = Departamento::create($validated);

            return response()->json([
                'data' => $departamento,
                'message' => 'Departamento creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear departamento: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Actualizar un departamento
     */
    public function update(Request $request, $id)
    {
        try {
            $departamento = Departamento::find($id);

            if (!$departamento) {
                return response()->json([
                    'error' => 'Departamento no encontrado',
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'string|max:255|unique:departamentos,nombre,' . $id,
                'estatus' => 'in:0,1',
            ]);

            $departamento->update($validated);

            return response()->json([
                'data' => $departamento,
                'message' => 'Departamento actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar departamento: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Eliminar un departamento
     */
    public function destroy($id)
    {
        try {
            $departamento = Departamento::find($id);

            if (!$departamento) {
                return response()->json([
                    'error' => 'Departamento no encontrado',
                ], 404);
            }

            $departamento->delete();

            return response()->json([
                'message' => 'Departamento eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar departamento: ' . $e->getMessage(),
            ], 400);
        }
    }
}
