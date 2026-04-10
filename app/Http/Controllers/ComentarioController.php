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

        return [
            'id'                   => $attrs['id'] ?? (string) $comentario->_id,
            'comentario'           => $comentario->comentario,
            'evidencia'            => $comentario->evidencia,
            'ticket_id'            => $comentario->ticket_id,
            'usuario_autor_id'     => $autorId,
            'usuario_autor_nombre' => $nombresMap[$autorId] ?? 'Usuario desconocido',
            'fecha'                => $comentario->fecha,
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
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        $comentarios = Comentario::where('ticket_id', $ticketId)
            ->orderBy('fecha', 'asc')
            ->get();

        $ids        = $comentarios->pluck('usuario_autor_id')->map(fn($id) => (int) $id)->unique()->values()->toArray();
        $nombresMap = $this->getNombresMap($ids);

        return response()->json([
            'data' => $comentarios->map(fn($c) => $this->formatComentario($c, $nombresMap)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticket_id' => 'required',
            'comentario' => 'required|string',
            'usuario_autor_id' => 'required',
            'evidencia' => 'nullable|file|max:20480',
        ]);

        $ticketId = $validated['ticket_id'];
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            $ticket = Ticket::where('_id', $ticketId)
                ->orWhere('id', $ticketId)
                ->first();
        }

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket no encontrado',
            ], 404);
        }

        if ($request->hasFile('evidencia')) {
            $file = $request->file('evidencia');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('tickets/' . $validated['ticket_id'] . '/comentarios', $filename, 'public');
            $validated['evidencia'] = $filename;
        }

        $validated['fecha'] = now();
        $validated['usuario_autor_id'] = (int) $validated['usuario_autor_id'];

        // Calcular siguiente ID
        $nextId = (Comentario::max('id') ?? 0) + 1;

        // Crear sin `id` (MongoDB genera ObjectId como _id automáticamente)
        $comentario = new Comentario($validated);
        $comentario->save();

        // Setear el campo `id` via driver MongoDB nativo para evitar el mapeo id→_id de laravel-mongodb
        $mongoDB = DB::connection('mongodb')->getMongoDB();
        $mongoDB->comentarios->updateOne(
            ['_id' => $comentario->_id],
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
                'fecha'                => $comentario->fecha,
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
