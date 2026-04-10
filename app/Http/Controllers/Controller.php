<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Guarda un archivo subido en la colección `archivos` de MongoDB.
     * El archivo se almacena en storage/public/archivos/{tipo}/{entidadId}/
     */
    protected function guardarArchivo(Request $request, string $campo, string $tipo, int $entidadId): void
    {
        if (!$request->hasFile($campo)) {
            return;
        }

        $file             = $request->file($campo);
        $nombreOriginal   = $file->getClientOriginalName();
        $nombreAlmacenado = time() . '_' . $nombreOriginal;
        $ruta             = "archivos/{$tipo}/{$entidadId}/{$nombreAlmacenado}";

        $file->storeAs("archivos/{$tipo}/{$entidadId}", $nombreAlmacenado, 'public');

        $mongoDB = DB::connection('mongodb')->getMongoDB();
        $lastDoc = $mongoDB->archivos->find(
            ['id' => ['$type' => 'int']],
            ['sort' => ['id' => -1], 'limit' => 1]
        )->toArray();
        $nextId = !empty($lastDoc) ? ((int) $lastDoc[0]['id'] + 1) : 1;

        $mongoDB->archivos->insertOne([
            'id'                => $nextId,
            'tipo_entidad'      => $tipo,
            'entidad_id'        => $entidadId,
            'nombre_original'   => $nombreOriginal,
            'nombre_almacenado' => $nombreAlmacenado,
            'ruta'              => $ruta,
            'mime_type'         => $file->getMimeType(),
            'tamanio'           => $file->getSize(),
            'fecha_subida'      => new \MongoDB\BSON\UTCDateTime(),
        ]);
    }
}

