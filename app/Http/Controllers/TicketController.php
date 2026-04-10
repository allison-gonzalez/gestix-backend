<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    private function findByNumericId(int $id): ?Ticket
    {
        $mongoDB = DB::connection('mongodb')->getMongoDB();
        // Buscar con $or para tolerar id guardado como int, double o string
        $doc = $mongoDB->tickets->findOne([
            '$or' => [
                ['id' => $id],
                ['id' => (float) $id],
                ['id' => (string) $id],
            ]
        ]);
        if (!$doc) return null;
        // Pasar el ObjectId directamente para evitar problemas de cast string→ObjectId
        return Ticket::where('_id', $doc['_id'])->first();
    }

    private function formatTicket(Ticket $ticket, ?int $forceId = null): array
    {
        $attrs = $ticket->getAttributes();
        $rawId = $attrs['id'] ?? null;

        // Documentos viejos pueden tener id como ObjectId — descartar en ese caso
        if ($rawId instanceof \MongoDB\BSON\ObjectId) {
            $rawId = null;
        }

        $id = $forceId ?? (is_numeric($rawId) ? (int) $rawId : null);

        return [
            'id'               => $id,
            'titulo'           => $ticket->titulo,
            'descripcion'      => $ticket->descripcion,
            'prioridad'        => $ticket->prioridad,
            'fecha_creacion'   => $ticket->fecha_creacion,
            'fecha_asignacion' => $ticket->fecha_asignacion,
            'fecha_resolucion' => $ticket->fecha_resolucion,
            'usuario_autor_id' => (int) $ticket->usuario_autor_id,
            'categoria_id'     => $ticket->categoria_id,
            'departamento_id'  => (int) $ticket->departamento_id,
            'asignado_a_id'    => $ticket->asignado_a_id ? (int) $ticket->asignado_a_id : null,
            'comentarios'      => $ticket->comentarios ?? [],
            'archivo_path'     => $ticket->archivo_path ?? null,
            'estado'           => $this->determinarEstado($ticket),
        ];
    }

    /**
     * Obtener todos los tickets
     */
    public function index()
    {
        try {
            $tickets = Ticket::all();

            // Agregar el campo estado calculado y formatear datos
            $tickets = $tickets->map(fn($ticket) => $this->formatTicket($ticket));

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
            $ticket = $this->findByNumericId((int) $id);

            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }

            return response()->json(['data' => $this->formatTicket($ticket)]);
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
                'categoria_id' => 'required|string',
                'departamento_id' => 'required|numeric',
                'usuario_autor_id' => 'required|integer',
                'archivo' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx|max:10240', // 10MB max
            ]);

            // Asegurar que los IDs sean integers
            $validated['departamento_id'] = (int) $validated['departamento_id'];
            $validated['usuario_autor_id'] = (int) $validated['usuario_autor_id'];

            // El archivo no debe ir al documento del ticket
            unset($validated['archivo']);

            $validated['fecha_creacion'] = now();

            // Calcular siguiente id usando driver nativo para evitar que max() devuelva un ObjectId
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $lastDoc = $mongoDB->tickets->find(
                ['id' => ['$type' => 'int']],
                ['sort' => ['id' => -1], 'limit' => 1]
            )->toArray();
            $nextId = !empty($lastDoc) ? ((int) $lastDoc[0]['id'] + 1) : 1;

            $ticket = Ticket::create($validated);

            // Asignar id numérico via driver nativo para evitar mapeo id→_id de laravel-mongodb
            $mongoDB->tickets->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId((string) $ticket->_id)],
                ['$set' => ['id' => $nextId]]
            );

            // Guardar archivo en colección archivos
            $this->guardarArchivo($request, 'archivo', 'ticket', $nextId);

            return response()->json([
                'data'    => $this->formatTicket($ticket, $nextId),
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
            $ticket = $this->findByNumericId((int) $id);

            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }

            $validated = $request->validate([
                'titulo' => 'string|max:255',
                'descripcion' => 'string',
                'prioridad' => 'in:baja,media,alta,critica',
                'estado' => 'in:abierto,en_progreso,resuelto,cerrado',
                'departamento_id' => 'nullable|numeric',
                'categoria_id' => 'string',
                'asignado_a_id' => 'nullable|integer',
                'fecha_asignacion' => 'nullable|date',
                'fecha_resolucion' => 'nullable|date',
                'archivo' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx|max:10240',
            ]);

            // Asegurar que los IDs sean integers
            if (isset($validated['departamento_id'])) {
                $validated['departamento_id'] = (int) $validated['departamento_id'];
            }
            if (array_key_exists('asignado_a_id', $validated)) {
                $validated['asignado_a_id'] = $validated['asignado_a_id'] !== null ? (int) $validated['asignado_a_id'] : null;
            }

            // El archivo no debe ir al $set de MongoDB
            unset($validated['archivo']);

            // Guardar directamente con driver nativo para evitar problemas de dirty-tracking de Eloquent con MongoDB
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $mongoDB->tickets->updateOne(
                ['$or' => [
                    ['id' => (int) $id],
                    ['id' => (float) $id],
                    ['id' => (string) $id],
                ]],
                ['$set' => $validated]
            );
            // Recargar usando findByNumericId para evitar que refresh() use Eloquent find
            $ticket = $this->findByNumericId((int) $id);

            // Guardar nuevo archivo en colección archivos si fue enviado
            $this->guardarArchivo($request, 'archivo', 'ticket', (int) $id);

            return response()->json([
                'data'    => $this->formatTicket($ticket),
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
            $ticket = $this->findByNumericId((int) $id);

            if (!$ticket) {
                return response()->json([
                    'error' => 'Ticket no encontrado',
                ], 404);
            }

            $ticket->delete();

            // Eliminar archivos asociados de la colección archivos
            $mongoDB = DB::connection('mongodb')->getMongoDB();
            $archivosToDelete = $mongoDB->archivos->find(
                ['tipo_entidad' => 'ticket', 'entidad_id' => (int) $id]
            )->toArray();
            foreach ($archivosToDelete as $archivo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($archivo['ruta'] ?? '');
            }
            $mongoDB->archivos->deleteMany(['tipo_entidad' => 'ticket', 'entidad_id' => (int) $id]);

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

            return response()->json([
                'data'    => $this->formatTicket($ticket),
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
