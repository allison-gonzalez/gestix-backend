<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * GET /api/notificaciones
     * Devuelve las notificaciones del usuario autenticado (más recientes primero).
     */
    public function index(Request $request)
    {
        $userId = (int) $request->attributes->get('user_id');

        $notificaciones = Notificacion::where('receptor_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($n) => $this->format($n));

        $noLeidas = Notificacion::where('receptor_id', $userId)
            ->where('leida', false)
            ->count();

        return response()->json([
            'data'      => $notificaciones,
            'no_leidas' => $noLeidas,
        ]);
    }

    /**
     * GET /api/notificaciones/no-leidas
     * Devuelve sólo el conteo de no leídas.
     */
    public function unreadCount(Request $request)
    {
        $userId = (int) $request->attributes->get('user_id');

        $count = Notificacion::where('receptor_id', $userId)
            ->where('leida', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * PUT /api/notificaciones/{id}/leer
     * Marca una notificación como leída.
     */
    public function markRead(Request $request, $id)
    {
        $userId = (int) $request->attributes->get('user_id');

        $notif = Notificacion::where('_id', $id)
            ->where('receptor_id', $userId)
            ->first();

        if ($notif) {
            $notif->leida = true;
            $notif->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * PUT /api/notificaciones/leer-todas
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function markAllRead(Request $request)
    {
        $userId = (int) $request->attributes->get('user_id');

        Notificacion::where('receptor_id', $userId)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------

    private function format(Notificacion $n): array
    {
        return [
            'id'          => (string) $n->_id,
            'tipo'        => $n->tipo,
            'titulo'      => $n->titulo,
            'mensaje'     => $n->mensaje,
            'receptor_id' => $n->receptor_id,
            'emisor_id'   => $n->emisor_id,
            'ticket_id'   => $n->ticket_id,
            'leida'       => (bool) $n->leida,
            'created_at'  => $n->created_at?->toISOString(),
        ];
    }
}
