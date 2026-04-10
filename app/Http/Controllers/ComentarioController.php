<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComentarioController extends Controller
{
    public function index()
    {
        $comentarios = Comentario::with('usuarioAutor')
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function ($comentario) {
                return [
                    'id' => $comentario->id,
                    'comentario' => $comentario->comentario,
                    'evidencia' => $comentario->evidencia,
                    'ticket_id' => $comentario->ticket_id,
                    'usuario_autor_id' => $comentario->usuario_autor_id,
                    'usuario_autor_nombre' => $comentario->usuarioAutor?->nombre ?? 'Usuario desconocido',
                    'fecha' => $comentario->fecha,
                    'url_evidencia' => $comentario->obtenerUrlEvidencia(),
                ];
            });

        return response()->json(['data' => $comentarios]);
    }

    public function getByTicket($ticketId)
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket no encontrado',
            ], 404);
        }

        $comentarios = Comentario::where('ticket_id', $ticketId)
            ->with('usuarioAutor')
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function ($comentario) {
                return [
                    'id' => $comentario->id,
                    'comentario' => $comentario->comentario,
                    'evidencia' => $comentario->evidencia,
                    'ticket_id' => $comentario->ticket_id,
                    'usuario_autor_id' => $comentario->usuario_autor_id,
                    'usuario_autor_nombre' => $comentario->usuarioAutor?->nombre ?? 'Usuario desconocido',
                    'fecha' => $comentario->fecha,
                    'url_evidencia' => $comentario->obtenerUrlEvidencia(),
                ];
            });

        return response()->json(['data' => $comentarios]);
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

        $comentario = Comentario::create($validated);
        $comentario->load('usuarioAutor');

        return response()->json([
            'data' => [
                'id' => $comentario->id,
                'comentario' => $comentario->comentario,
                'evidencia' => $comentario->evidencia,
                'ticket_id' => $comentario->ticket_id,
                'usuario_autor_id' => $comentario->usuario_autor_id,
                'usuario_autor_nombre' => $comentario->usuarioAutor?->nombre ?? 'Usuario desconocido',
                'fecha' => $comentario->fecha,
                'url_evidencia' => $comentario->obtenerUrlEvidencia(),
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
