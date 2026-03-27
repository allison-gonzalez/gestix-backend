<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        try {
            $rango = $request->get('rango', 'mes');

            $fechaInicio = match ($rango) {
                'semana'    => Carbon::now()->subWeek(),
                'mes'       => Carbon::now()->subMonth(),
                'trimestre' => Carbon::now()->subMonths(3),
                'anio'      => Carbon::now()->subYear(),
                default     => Carbon::now()->subMonth(),
            };

            $todosTickets = Ticket::all();

            $tickets = $todosTickets->filter(function ($t) use ($fechaInicio) {
                if (!$t->fecha_creacion) return false;
                $fecha = is_string($t->fecha_creacion)
                    ? Carbon::parse($t->fecha_creacion)
                    : $t->fecha_creacion;
                return $fecha >= $fechaInicio;
            });

            // Por estado
            $porEstado = ['abierto' => 0, 'pendiente' => 0, 'resuelto' => 0];
            foreach ($tickets as $t) {
                $estado = $this->determinarEstado($t);
                $porEstado[$estado]++;
            }

            // Por prioridad
            $porPrioridad = ['baja' => 0, 'media' => 0, 'alta' => 0, 'critica' => 0];
            foreach ($tickets as $t) {
                $p = $t->prioridad ?? 'media';
                if (isset($porPrioridad[$p])) $porPrioridad[$p]++;
            }

            // Por categoría (top 6)
            $porCategoria = [];
            $categorias = Categoria::all()->keyBy('id');
            foreach ($tickets as $t) {
                $catNombre = $categorias->get($t->categoria_id)?->nombre ?? 'Sin categoría';
                $porCategoria[$catNombre] = ($porCategoria[$catNombre] ?? 0) + 1;
            }
            arsort($porCategoria);
            $porCategoria = array_slice($porCategoria, 0, 6, true);

            // Por mes (últimos 6 meses, sobre todos los tickets)
            $porMes = [];
            for ($i = 5; $i >= 0; $i--) {
                $mes = Carbon::now()->subMonths($i)->locale('es')->isoFormat('MMM YYYY');
                $porMes[$mes] = 0;
            }
            foreach ($todosTickets as $t) {
                if (!$t->fecha_creacion) continue;
                $fecha = is_string($t->fecha_creacion)
                    ? Carbon::parse($t->fecha_creacion)
                    : $t->fecha_creacion;
                $mes = $fecha->locale('es')->isoFormat('MMM YYYY');
                if (isset($porMes[$mes])) $porMes[$mes]++;
            }

            // KPIs
            $total = $tickets->count();
            $resueltos = $tickets->filter(fn($t) => $this->determinarEstado($t) === 'resuelto');

            $tiemposResolucion = $resueltos
                ->filter(fn($t) => $t->fecha_creacion && $t->fecha_resolucion)
                ->map(function ($t) {
                    $inicio = is_string($t->fecha_creacion) ? Carbon::parse($t->fecha_creacion) : $t->fecha_creacion;
                    $fin    = is_string($t->fecha_resolucion) ? Carbon::parse($t->fecha_resolucion) : $t->fecha_resolucion;
                    return $inicio->diffInHours($fin);
                });

            $tiempoPromedio = $tiemposResolucion->count() > 0
                ? round($tiemposResolucion->avg())
                : 0;

            return response()->json([
                'data' => [
                    'kpis' => [
                        'total'                  => $total,
                        'abiertos'               => $porEstado['abierto'],
                        'pendientes'             => $porEstado['pendiente'],
                        'resueltos'              => $porEstado['resuelto'],
                        'tasa_resolucion'        => $total > 0 ? round($porEstado['resuelto'] / $total * 100) : 0,
                        'tiempo_promedio_horas'  => $tiempoPromedio,
                    ],
                    'por_estado'    => $porEstado,
                    'por_prioridad' => $porPrioridad,
                    'por_categoria' => $porCategoria,
                    'por_mes'       => $porMes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al generar reporte: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function determinarEstado($ticket): string
    {
        if ($ticket->fecha_resolucion) return 'resuelto';
        if ($ticket->fecha_asignacion) return 'pendiente';
        return 'abierto';
    }
}
