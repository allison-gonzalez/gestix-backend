<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Events\NuevaNotificacion;
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

    private function formatTicket(Ticket $ticket, ?int $forceId = null, array $archivos = []): array
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
            'archivos'         => $archivos,
            'estado'           => $this->determinarEstado($ticket),
        ];
    }

    private function getArchivosForTicket(int $ticketId): array
    {
        $mongoDB = DB::connection('mongodb')->getMongoDB();
        $docs    = $mongoDB->archivos->find(
            [
                'tipo_entidad' => 'ticket',
                '$or' => [
                    ['entidad_id' => $ticketId],
                    ['entidad_id' => (float) $ticketId],
                    ['entidad_id' => (string) $ticketId],
                ],
            ],
            ['sort' => ['fecha_subida' => 1]]
        )->toArray();

        return array_map(fn($doc) => [
            'id'              => isset($doc['id']) ? (int) $doc['id'] : null,
            'nombre_original' => $doc['nombre_original'] ?? null,
            'url'             => asset('storage/' . ($doc['ruta'] ?? '')),
        ], $docs);
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

            $archivos = $this->getArchivosForTicket((int) $id);

            return response()->json(['data' => $this->formatTicket($ticket, null, $archivos)]);
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
                'titulo'          => 'required|string|max:255',
                'descripcion'     => 'required|string',
                'prioridad'       => 'required|in:baja,media,alta,critica',
                'categoria_id'    => 'required|string',
                'departamento_id' => 'required|numeric',
                'archivo'         => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx|max:10240',
            ]);

            // Usar el id autenticado del JWT (evita confiar en datos del cliente
            // y soluciona el caso donde authUser.id sea un ObjectId en el frontend)
            $autorId = (int) $request->attributes->get('user_id');
            if (!$autorId) {
                return response()->json(['error' => 'No se pudo determinar el usuario autenticado.'], 401);
            }

            $validated['departamento_id']  = (int) $validated['departamento_id'];
            $validated['usuario_autor_id'] = $autorId;
            $validated['estado']           = 'abierto';

            // Autoasignación: buscar gestor con menor carga en este departamento y categoría
            $autoAsignadoId = $this->autoAsignar(
                (int) $validated['departamento_id'],
                (int) $validated['categoria_id'],
                $autorId
            );
            if ($autoAsignadoId) {
                $validated['asignado_a_id'] = $autoAsignadoId;
            }

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

            // Incluir archivos en la respuesta del store
            $archivos = $this->getArchivosForTicket($nextId);

            // Notificar a usuarios con permiso asignar_ticket (id=4) del mismo departamento
            $this->notificarAsignadores(
                $nextId,
                (int) $validated['usuario_autor_id'],
                $validated['titulo'],
                (int) $validated['departamento_id'],
                $autoAsignadoId
            );

            return response()->json([
                'data'    => $this->formatTicket($ticket, $nextId, $archivos),
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

            // Notificar asignación si cambió asignado_a_id
            if (array_key_exists('asignado_a_id', $validated) && $validated['asignado_a_id'] !== null) {
                $nuevoAsignado = (int) $validated['asignado_a_id'];
                $autorId = (int) ($ticket->getAttributes()['usuario_autor_id'] ?? 0);
                $emisorId = (int) ($request->attributes->get('user_id') ?? 0);
                $this->crearYBroadcast([
                    'tipo'        => 'ticket_asignado',
                    'titulo'      => 'Ticket asignado',
                    'mensaje'     => "Se te ha asignado el ticket #{$id}: {$ticket->titulo}",
                    'receptor_id' => $nuevoAsignado,
                    'emisor_id'   => $emisorId,
                    'ticket_id'   => (int) $id,
                    'leida'       => false,
                ]);
            }

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

            // Notificar al autor del ticket
            $autorId = (int) ($ticket->getAttributes()['usuario_autor_id'] ?? 0);
            $emisorId = (int) ($request->attributes->get('user_id') ?? 0);
            if ($autorId) {
                $this->crearYBroadcast([
                    'tipo'        => 'ticket_resuelto',
                    'titulo'      => 'Ticket resuelto',
                    'mensaje'     => "Tu ticket #{$id}: {$ticket->titulo} ha sido marcado como resuelto.",
                    'receptor_id' => $autorId,
                    'emisor_id'   => $emisorId,
                    'ticket_id'   => (int) $id,
                    'leida'       => false,
                ]);
            }

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

    // -------------------------------------------------------------------------
    // Helpers de notificaciones
    // -------------------------------------------------------------------------

    /**
     * Crea un registro en notificaciones y dispara el evento WebSocket.
     */
    private function crearYBroadcast(array $data): void
    {
        try {
            $notif = Notificacion::create($data);
            broadcast(new NuevaNotificacion(array_merge($data, [
                'id'         => (string) $notif->_id,
                'created_at' => $notif->created_at?->toISOString(),
            ])));
        } catch (\Exception $e) {
            \Log::warning('Error al crear/broadcast notificación: ' . $e->getMessage());
        }
    }

    /**
     * Autoasignación por menor carga:
     * Busca gestores del departamento con permiso 4 y que tengan la categoría en su lista.
     * Si ninguno tiene la categoría configurada, toma cualquier gestor del departamento.
     * Retorna el id numérico del gestor elegido, o null si no hay candidatos.
     */
    private function autoAsignar(int $departamentoId, int $categoriaId, int $autorId): ?int
    {
        try {
            $mongoDB = DB::connection('mongodb')->getMongoDB();

            // Candidatos: usuarios activos del departamento (cualquiera puede atender)
            $cursor = $mongoDB->usuarios->find([
                'estatus'        => ['$in' => [1, true]],
                'departamento_id'=> $departamentoId,
            ]);

            $candidatos = iterator_to_array($cursor);

            \Log::info('autoAsignar candidatos: ' . count($candidatos) . ' dept=' . $departamentoId . ' cat=' . $categoriaId . ' autor=' . $autorId);

            if (empty($candidatos)) return null;

            // Filtrar por categorías atendibles
            $conCategoria = array_filter($candidatos, function ($u) use ($categoriaId) {
                $cats = isset($u['categorias_asignables']) ? (array) $u['categorias_asignables'] : [];
                if (empty($cats)) return false;
                return in_array($categoriaId, array_map('intval', $cats));
            });

            $pool = !empty($conCategoria) ? $conCategoria : $candidatos;

            // Helper: obtener id numérico del usuario
            $resolveId = function ($doc): ?int {
                if (isset($doc['id']) && is_numeric($doc['id'])) {
                    return (int) $doc['id'];
                }
                return null;
            };

            // Elegir gestor con menor carga
            $elegido    = null;
            $menorCarga = PHP_INT_MAX;

            foreach ($pool as $candidato) {
                $cid = $resolveId($candidato);
                if ($cid === null || $cid === $autorId) continue;

                $carga = $mongoDB->tickets->countDocuments([
                    'asignado_a_id' => $cid,
                    'estado'        => ['$in' => ['abierto', 'en_progreso']],
                ]);

                if ($carga < $menorCarga) {
                    $menorCarga = $carga;
                    $elegido    = $cid;
                }
            }

            \Log::info('autoAsignar elegido: ' . ($elegido ?? 'null'));

            return $elegido;
        } catch (\Exception $e) {
            \Log::warning('autoAsignar error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Notifica a usuarios con permiso asignar_ticket (id=4)
     * que pertenezcan al mismo departamento del ticket, excluyendo al autor.
     * Si el ticket fue autoasignado, notifica al asignado con tipo ticket_asignado.
     */
    private function notificarAsignadores(int $ticketId, int $autorId, string $titulo, int $departamentoId, ?int $autoAsignadoId = null): void
    {
        try {
            $gestores = Usuario::where('estatus', 1)
                ->where('permisos', 4)
                ->where('departamento_id', $departamentoId)
                ->get();

            foreach ($gestores as $gestor) {
                $rawId    = $gestor->getAttributes()['id'] ?? null;
                $gestorId = (is_numeric($rawId) && !($rawId instanceof \MongoDB\BSON\ObjectId))
                    ? (int) $rawId
                    : null;

                if ($gestorId === null) {
                    try {
                        $mongoDB = DB::connection('mongodb')->getMongoDB();
                        $doc = $mongoDB->usuarios->findOne(
                            ['_id' => new \MongoDB\BSON\ObjectId((string) $gestor->_id)],
                            ['projection' => ['id' => 1]]
                        );
                        if ($doc && isset($doc['id']) && is_numeric($doc['id'])) {
                            $gestorId = (int) $doc['id'];
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error obteniendo id numérico de gestor: ' . $e->getMessage());
                    }
                }

                if ($gestorId === null || $gestorId === $autorId) continue;

                // Si fue autoasignado a este gestor, notificación diferente
                if ($gestorId === $autoAsignadoId) {
                    $this->crearYBroadcast([
                        'tipo'        => 'ticket_asignado',
                        'titulo'      => 'Ticket asignado automáticamente',
                        'mensaje'     => "Se te asignó el ticket #{$ticketId}: {$titulo}",
                        'receptor_id' => $gestorId,
                        'emisor_id'   => $autorId,
                        'ticket_id'   => $ticketId,
                        'leida'       => false,
                    ]);
                } else {
                    $this->crearYBroadcast([
                        'tipo'        => 'ticket_creado',
                        'titulo'      => 'Nuevo ticket',
                        'mensaje'     => "Se creó el ticket #{$ticketId}: {$titulo}",
                        'receptor_id' => $gestorId,
                        'emisor_id'   => $autorId,
                        'ticket_id'   => $ticketId,
                        'leida'       => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error al notificar asignadores: ' . $e->getMessage());
        }
    }
}
