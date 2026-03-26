<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;

class TicketController extends Controller
{
    /**
     * Obtener todos los tickets
     */
    public function index()
    {
        try {
            $tickets = Ticket::all();
            
            // Agregar el campo estado calculado y formatear datos
            $tickets = $tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'titulo' => $ticket->titulo,
                    'descripcion' => $ticket->descripcion,
                    'prioridad' => $ticket->prioridad,
                    'fecha_creacion' => $ticket->fecha_creacion,
                    'fecha_asignacion' => $ticket->fecha_asignacion,
                    'fecha_resolucion' => $ticket->fecha_resolucion,
                    'usuario_autor_id' => $ticket->usuario_autor_id,
                    'categoria_id' => $ticket->categoria_id,
                    'comentarios' => $ticket->comentarios ?? [],
                    'estado' => $this->determinarEstado($ticket),
                ];
            });

            return response()->json([
                'data' => $tickets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener tickets: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un ticket específico
     */
    public function show($id)
    {
        try {
            $ticket = Ticket::find($id);
            
            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }

            $data = [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'prioridad' => $ticket->prioridad,
                'fecha_creacion' => $ticket->fecha_creacion,
                'fecha_asignacion' => $ticket->fecha_asignacion,
                'fecha_resolucion' => $ticket->fecha_resolucion,
                'usuario_autor_id' => $ticket->usuario_autor_id,
                'categoria_id' => $ticket->categoria_id,
                'comentarios' => $ticket->comentarios ?? [],
                'estado' => $this->determinarEstado($ticket),
            ];

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ticket no encontrado: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear un nuevo ticket
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'prioridad' => 'required|in:baja,media,alta,critica',
                'categoria_id' => 'required|integer',
                'usuario_autor_id' => 'required|integer',
            ]);

            $validated['fecha_creacion'] = now();
            $ticket = Ticket::create($validated);

            $data = [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'prioridad' => $ticket->prioridad,
                'fecha_creacion' => $ticket->fecha_creacion,
                'fecha_asignacion' => $ticket->fecha_asignacion,
                'fecha_resolucion' => $ticket->fecha_resolucion,
                'usuario_autor_id' => $ticket->usuario_autor_id,
                'categoria_id' => $ticket->categoria_id,
                'comentarios' => $ticket->comentarios ?? [],
                'estado' => $this->determinarEstado($ticket),
            ];

            return response()->json([
                'data' => $data,
                'message' => 'Ticket creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear ticket: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Actualizar un ticket
     */
    public function update(Request $request, $id)
    {
        try {
            $ticket = Ticket::find($id);
            
            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }

            $validated = $request->validate([
                'titulo' => 'string|max:255',
                'descripcion' => 'string',
                'prioridad' => 'in:baja,media,alta,critica',
                'categoria_id' => 'integer',
                'fecha_asignacion' => 'nullable|date',
                'fecha_resolucion' => 'nullable|date',
            ]);

            $ticket->update($validated);

            $data = [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'prioridad' => $ticket->prioridad,
                'fecha_creacion' => $ticket->fecha_creacion,
                'fecha_asignacion' => $ticket->fecha_asignacion,
                'fecha_resolucion' => $ticket->fecha_resolucion,
                'usuario_autor_id' => $ticket->usuario_autor_id,
                'categoria_id' => $ticket->categoria_id,
                'comentarios' => $ticket->comentarios ?? [],
                'estado' => $this->determinarEstado($ticket),
            ];

            return response()->json([
                'data' => $data,
                'message' => 'Ticket actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar ticket: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Eliminar un ticket
     */
    public function destroy($id)
    {
        try {
            $ticket = Ticket::find($id);
            
            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }
            
            $ticket->delete();

            return response()->json([
                'message' => 'Ticket eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar ticket: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Determinar el estado del ticket basado en sus fechas
     */
    private function determinarEstado($ticket)
    {
        if ($ticket->fecha_resolucion) {
            return 'resuelto';
        } elseif ($ticket->fecha_asignacion) {
            return 'pendiente';
        } else {
            return 'abierto';
        }
    }

    /**
     * Resolver un ticket
     */
    public function resolve(Request $request, $id)
    {
        try {
            $ticket = Ticket::find($id);
            
            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }
            
            $ticket->fecha_resolucion = now();
            $ticket->save();

            $data = [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'prioridad' => $ticket->prioridad,
                'fecha_creacion' => $ticket->fecha_creacion,
                'fecha_asignacion' => $ticket->fecha_asignacion,
                'fecha_resolucion' => $ticket->fecha_resolucion,
                'usuario_autor_id' => $ticket->usuario_autor_id,
                'categoria_id' => $ticket->categoria_id,
                'comentarios' => $ticket->comentarios ?? [],
                'estado' => $this->determinarEstado($ticket),
            ];

            return response()->json([
                'data' => $data,
                'message' => 'Ticket marcado como resuelto',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al resolver ticket: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obtener estadísticas de tickets
     */
    public function stats()
    {
        try {
            $tickets = Ticket::all();
            
            $stats = [
                'total' => $tickets->count(),
                'abiertos' => 0,
                'pendientes' => 0,
                'resueltos' => 0,
                'por_prioridad' => [
                    'baja' => 0,
                    'media' => 0,
                    'alta' => 0,
                    'critica' => 0,
                ]
            ];

            foreach ($tickets as $ticket) {
                $estado = $this->determinarEstado($ticket);
                if (isset($stats[$estado])) {
                    $stats[$estado]++;
                }
                
                if (isset($ticket->prioridad) && isset($stats['por_prioridad'][$ticket->prioridad])) {
                    $stats['por_prioridad'][$ticket->prioridad]++;
                }
            }

            return response()->json([
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }
}
