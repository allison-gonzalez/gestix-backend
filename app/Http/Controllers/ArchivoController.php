<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchivoController extends Controller
{
    private function formatArchivo(array $doc): array
    {
        return [
            'id'              => isset($doc['id']) ? (int) $doc['id'] : null,
            'tipo_entidad'    => $doc['tipo_entidad'] ?? null,
            'entidad_id'      => isset($doc['entidad_id']) ? (int) $doc['entidad_id'] : null,
            'nombre_original' => $doc['nombre_original'] ?? null,
            'mime_type'       => $doc['mime_type'] ?? null,
            'tamanio'         => isset($doc['tamanio']) ? (int) $doc['tamanio'] : null,
            'fecha_subida'    => isset($doc['fecha_subida'])
                ? ($doc['fecha_subida'] instanceof \MongoDB\BSON\UTCDateTime
                    ? $doc['fecha_subida']->toDateTime()->format('c')
                    : (string) $doc['fecha_subida'])
                : null,
            'url' => asset('storage/' . ($doc['ruta'] ?? '')),
        ];
    }

    public function getByEntidad(string $tipo, int $id)
    {
        $mongoDB = DB::connection('mongodb')->getMongoDB();
        $docs    = $mongoDB->archivos->find(
            [
                'tipo_entidad' => $tipo,
                '$or' => [
                    ['entidad_id' => (int) $id],
                    ['entidad_id' => (float) $id],
                    ['entidad_id' => (string) $id],
                ],
            ],
            ['sort' => ['fecha_subida' => 1]]
        )->toArray();

        return response()->json([
            'data' => array_map(fn($d) => $this->formatArchivo($d), $docs),
        ]);
    }

    public function destroy(int $id)
    {
        $mongoDB = DB::connection('mongodb')->getMongoDB();
        $doc     = $mongoDB->archivos->findOne(['id' => $id]);

        if (!$doc) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        Storage::disk('public')->delete($doc['ruta'] ?? '');
        $mongoDB->archivos->deleteOne(['id' => $id]);

        return response()->json(['message' => 'Archivo eliminado']);
    }
}
