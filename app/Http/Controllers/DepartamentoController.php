<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'nombre'       => 'required|string|max:255|unique:departamentos,nombre',
                'estatus'      => 'required|in:0,1',
                'encargado_id' => 'nullable|integer',
            ]);

            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $lastDoc = $mongoDB->departamentos->findOne(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1]]
            );
            $nextId = $lastDoc ? ((int) $lastDoc['id'] + 1) : 1;

            $now = new \MongoDB\BSON\UTCDateTime();
            $mongoDB->departamentos->insertOne([
                'id'          => (int) $nextId,
                'nombre'      => $validated['nombre'],
                'estatus'     => (int) $validated['estatus'],
                'encargado_id'=> isset($validated['encargado_id']) ? (int) $validated['encargado_id'] : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $departamento = Departamento::where('id', $nextId)->first();

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
                'nombre'       => 'string|max:255|unique:departamentos,nombre,' . $id,
                'estatus'      => 'in:0,1',
                'encargado_id' => 'nullable|integer',
            ]);

            // Usar driver nativo para evitar problemas de dirty-tracking con enteros en laravel-mongodb
            $mongoDB   = DB::connection('mongodb')->getMongoDB();
            $setFields = [];

            if (array_key_exists('nombre', $validated)) {
                $setFields['nombre'] = $validated['nombre'];
            }
            if (array_key_exists('estatus', $validated)) {
                $setFields['estatus'] = (int) $validated['estatus'];
            }
            if (array_key_exists('encargado_id', $validated)) {
                $setFields['encargado_id'] = $validated['encargado_id'] !== null ? (int) $validated['encargado_id'] : null;
            }

            if (!empty($setFields)) {
                $mongoDB->departamentos->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId((string) $departamento->_id)],
                    ['$set' => $setFields]
                );
            }

            $fresh = Departamento::find($id);

            return response()->json([
                'data' => $fresh,
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
