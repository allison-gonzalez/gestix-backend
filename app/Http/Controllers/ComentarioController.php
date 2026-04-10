<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ComentarioController extends Controller
{
    private function getNombresMap(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $mongoDB = DB::connection('mongodb')->getMongoDB();
        $docs    = $mongoDB->usuarios->find(['id' => ['$in' => $ids]])->toArray();

        $map = [];
        foreach ($docs as $doc) {
            if (isset($doc['id'])) {
                $map[(int) $doc['id']] = (string) $doc['nombre'];
            }
        }
        return $map;
    }

    private function formatComentario(Comentario $comentario, array $nombresMap = []): array
    {
        $attrs   = $comentario->getAttributes();
        $autorId = (int) $comentario->usuario_autor_id;

        $fecha = $comentario->fecha;
        $fechaStr = $fecha instanceof \MongoDB\BSON\UTCDateTime
            ? $fecha->toDateTime()->format('c')
            : ($fecha ? (string) $fecha : null);

        return [
            'id'                   => $attrs['id'] ?? (string) $comentario->_id,
            'comentario'           => $comentario->comentario,
            'evidencia'            => $comentario->evidencia,
            'ticket_id'            => $comentario->ticket_id,
            'usuario_autor_id'     => $autorId,
            'usuario_autor_nombre' => $nombresMap[$autorId] ?? 'Usuario desconocido',
            'fecha'                => $fechaStr,
            'url_evidencia'        => $comentario->obtenerUrlEvidencia(),
        ];
    }

    public function index()
    {
        $comentarios = Comentario::orderBy('fecha', 'asc')->get();

        $ids        = $comentarios->pluck('usuario_autor_id')->map(fn($id) => (int) $id)->unique()->values()->toArray();
        $nombresMap = $this->getNombresMap($ids);

        return response()->json([
            'data' => $comentarios->map(fn($c) => $this->formatComentario($c, $nombresMap)),
        ]);
    }

    public function getByTicket($ticketId)
    {
        $ticketId = (int) $ticketId;

        // Verificar que el ticket existe usando driver nativo (evita traducción id→_id de laravel-mongodb)
        $mongoDB   = DB::connection('mongodb')->getMongoDB();
        $ticketDoc = $mongoDB->tickets->findOne(['id' => $ticketId]);

        if (!$ticketDoc) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        // Buscar comentarios por ticket_id numérico usando driver nativo
        $docs = $mongoDB->comentarios->find(
            ['ticket_id' => $ticketId],
            ['sort' => ['fecha' => 1]]
        )->toArray();

        $autorIds   = array_unique(array_map(fn($d) => (int) ($d['usuario_autor_id'] ?? 0), $docs));
        $nombresMap = $this->getNombresMap(array_values($autorIds));

        $data = array_map(function ($doc) use ($nombresMap) {
            $id      = isset($doc['id']) ? (int) $doc['id'] : (string) $doc['_id'];
            $autorId = (int) ($doc['usuario_autor_id'] ?? 0);
            $evidencia = $doc['evidencia'] ?? null;
            $urlEvidencia = $evidencia
                ? asset('storage/tickets/' . ($doc['ticket_id'] ?? '') . '/comentarios/' . $evidencia)
                : null;

            return [
                'id'                   => $id,
                'comentario'           => $doc['comentario'] ?? '',
                'evidencia'            => $evidencia,
                'ticket_id'            => (int) ($doc['ticket_id'] ?? 0),
                'usuario_autor_id'     => $autorId,
                'usuario_autor_nombre' => $nombresMap[$autorId] ?? 'Usuario desconocido',
                'fecha'                => isset($doc['fecha'])
                    ? ($doc['fecha'] instanceof \MongoDB\BSON\UTCDateTime
                        ? $doc['fecha']->toDateTime()->format('c')
                        : (string) $doc['fecha'])
                    : null,
                'url_evidencia'        => $urlEvidencia,
            ];
        }, $docs);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticket_id' => 'required',
            'comentario' => 'required|string',
            'usuario_autor_id' => 'required',
            'evidencia' => 'nullable|file|max:20480',
        ]);

        $ticketId = (int) $validated['ticket_id'];

        // Buscar ticket por id numérico via driver nativo (laravel-mongodb traduce where('id') → _id)
        $mongoDB   = DB::connection('mongodb')->getMongoDB();
        $ticketDoc = $mongoDB->tickets->findOne(['id' => $ticketId]);

        if (!$ticketDoc) {
            return response()->json([
                'error' => 'Ticket no encontrado',
            ], 404);
        }

        if ($request->hasFile('evidencia')) {
            $file = $request->file('evidencia');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('tickets/' . $ticketId . '/comentarios', $filename, 'public');
            $validated['evidencia'] = $filename;
        }

        $validated['fecha'] = now();
        $validated['ticket_id'] = $ticketId;
        $validated['usuario_autor_id'] = (int) $validated['usuario_autor_id'];

        // Calcular siguiente id usando driver nativo para evitar que max() devuelva un ObjectId
        $lastDoc = $mongoDB->comentarios->find(
            ['id' => ['$type' => 'int']],
            ['sort' => ['id' => -1], 'limit' => 1]
        )->toArray();
        $nextId = !empty($lastDoc) ? ((int) $lastDoc[0]['id'] + 1) : 1;

        // Crear sin `id` (MongoDB genera ObjectId como _id automáticamente)
        $comentario = new Comentario($validated);
        $comentario->save();

        // Setear el campo `id` via driver MongoDB nativo para evitar el mapeo id→_id de laravel-mongodb
        $mongoDB->comentarios->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId((string) $comentario->_id)],
            ['$set' => ['id' => $nextId]]
        );
        return response()->json([
            'data' => [
                'id'                   => $nextId,
                'comentario'           => $comentario->comentario,
                'evidencia'            => $comentario->evidencia,
                'ticket_id'            => $comentario->ticket_id,
                'usuario_autor_id'     => $comentario->usuario_autor_id,
                'usuario_autor_nombre' => $this->getNombresMap([(int) $validated['usuario_autor_id']])[(int) $validated['usuario_autor_id']] ?? 'Usuario desconocido',
                'fecha'                => $comentario->fecha instanceof \MongoDB\BSON\UTCDateTime
                    ? $comentario->fecha->toDateTime()->format('c')
                    : ($comentario->fecha ? (string) $comentario->fecha : null),
                'url_evidencia'        => $comentario->obtenerUrlEvidencia(),
            ],
            'message' => 'Comentario creado exitosamente',
        ], 201);
    }

    public function destroy($id)
    {
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json([
                'error' => 'Comentario no encontrado',
            ], 404);
        }

        if ($comentario->evidencia) {
            Storage::disk('public')->delete('tickets/' . $comentario->ticket_id . '/comentarios/' . $comentario->evidencia);
        }

        $comentario->delete();

        return response()->json([
            'message' => 'Comentario eliminado exitosamente',
        ]);
    }
}
