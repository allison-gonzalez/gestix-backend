<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use App\Models\Departamento;
use App\Models\Categoria;
use App\Models\Usuario;
use App\Models\Ticket;
use App\Models\Comentario;

class DatabaseSeeder extends Seeder
{
    /**
     * Ejecutar los seeds de la base de datos.
     */
    public function run(): void
    {
        // Limpiar colecciones (opcional)
        // Permiso::truncate();
        // Departamento::truncate();
        // Categoria::truncate();
        // Usuario::truncate();
        // Ticket::truncate();
        // Comentario::truncate();

        // Crear permisos
        $permisos = [
            Permiso::create([
                'nombre' => 'crear_ticket',
                'descripcion' => 'Permite crear tickets',
                'estatus' => 1
            ]),
            Permiso::create([
                'nombre' => 'editar_ticket',
                'descripcion' => 'Permite editar tickets',
                'estatus' => 1
            ]),
            Permiso::create([
                'nombre' => 'eliminar_ticket',
                'descripcion' => 'Permite eliminar tickets',
                'estatus' => 1
            ]),
            Permiso::create([
                'nombre' => 'ver_reportes',
                'descripcion' => 'Permite ver reportes',
                'estatus' => 1
            ]),
        ];

        // Crear departamentos
        $depto_soporte = Departamento::create([
            'nombre' => 'Soporte Técnico',
            'estatus' => 1
        ]);

        $depto_admin = Departamento::create([
            'nombre' => 'Administración',
            'estatus' => 1
        ]);

        // Crear categorías
        $categoria_hardware = Categoria::create([
            'nombre' => 'Hardware',
            'estatus' => 1,
            'departamento_id' => $depto_soporte->id ?? 1
        ]);

        $categoria_software = Categoria::create([
            'nombre' => 'Software',
            'estatus' => 1,
            'departamento_id' => $depto_soporte->id ?? 1
        ]);

        $categoria_red = Categoria::create([
            'nombre' => 'Red',
            'estatus' => 1,
            'departamento_id' => $depto_soporte->id ?? 1
        ]);

        // Crear usuarios
        $usuario1 = Usuario::create([
            'nombre' => 'Juan Pérez',
            'correo' => 'juan.perez@example.com',
            'telefono' => '3312345678',
            'contrasena' => 'password123',
            'estatus' => 1,
            'departamento_id' => $depto_soporte->id ?? 1,
            'permisos' => [
                $permisos[0]->id ?? 1,
                $permisos[1]->id ?? 2
            ]
        ]);

        $usuario2 = Usuario::create([
            'nombre' => 'Ana López',
            'correo' => 'ana.lopez@example.com',
            'telefono' => '3387654321',
            'contrasena' => 'password123',
            'estatus' => 1,
            'departamento_id' => $depto_soporte->id ?? 1,
            'permisos' => [
                $permisos[0]->id ?? 1
            ]
        ]);

        $usuario3 = Usuario::create([
            'nombre' => 'Carlos García',
            'correo' => 'carlos.garcia@example.com',
            'telefono' => '3319876543',
            'contrasena' => 'password123',
            'estatus' => 1,
            'departamento_id' => $depto_admin->id ?? 2,
            'permisos' => [
                $permisos[0]->id ?? 1,
                $permisos[1]->id ?? 2,
                $permisos[3]->id ?? 4
            ]
        ]);

        // Crear tickets
        $ticket1 = Ticket::create([
            'titulo' => 'Falla en impresora del piso 2',
            'descripcion' => 'La impresora de la oficina 201 no responde',
            'prioridad' => 'alta',
            'fecha_creacion' => now(),
            'fecha_asignacion' => now(),
            'fecha_resolucion' => null,
            'usuario_autor_id' => $usuario1->id ?? 1,
            'categoria_id' => $categoria_hardware->id ?? 1,
            'comentarios' => []
        ]);

        $ticket2 = Ticket::create([
            'titulo' => 'Problema con conexión a internet',
            'descripcion' => 'La conexión de la oficina 305 está lenta',
            'prioridad' => 'media',
            'fecha_creacion' => now()->subDays(2),
            'fecha_asignacion' => now()->subDays(1),
            'fecha_resolucion' => now(),
            'usuario_autor_id' => $usuario2->id ?? 2,
            'categoria_id' => $categoria_red->id ?? 3,
            'comentarios' => []
        ]);

        $ticket3 = Ticket::create([
            'titulo' => 'Software no funciona correctamente',
            'descripcion' => 'La aplicación de facturación genera errores',
            'prioridad' => 'alta',
            'fecha_creacion' => now()->subDays(1),
            'fecha_asignacion' => now(),
            'fecha_resolucion' => null,
            'usuario_autor_id' => $usuario1->id ?? 1,
            'categoria_id' => $categoria_software->id ?? 2,
            'comentarios' => []
        ]);

        // Crear comentarios
        $comentario1 = Comentario::create([
            'comentario' => 'Se revisó el equipo, aparentemente tiene un problema de conexión',
            'evidencia' => 'foto_impresora.jpg',
            'usuario_autor_id' => $usuario2->id ?? 2,
            'ticket_id' => $ticket1->id ?? 1,
            'fecha' => now()
        ]);

        $comentario2 = Comentario::create([
            'comentario' => 'Se solicitó refacción de la madre de reemplazos',
            'evidencia' => null,
            'usuario_autor_id' => $usuario1->id ?? 1,
            'ticket_id' => $ticket1->id ?? 1,
            'fecha' => now()->addHour()
        ]);

        $comentario3 = Comentario::create([
            'comentario' => 'Se verificó la conexión y todo está funcionando correctamente',
            'evidencia' => 'resultado_prueba_velocidad.png',
            'usuario_autor_id' => $usuario3->id ?? 3,
            'ticket_id' => $ticket2->id ?? 2,
            'fecha' => now()->subDays(1)
        ]);

        // Actualizar referencias de comentarios en tickets
        $ticket1->comentarios = [
            $comentario1->id ?? 1,
            $comentario2->id ?? 2
        ];
        $ticket1->save();

        $ticket2->comentarios = [
            $comentario3->id ?? 3
        ];
        $ticket2->save();

        echo "Seeding completado!\n";
        echo "- Permisos: " . Permiso::count() . "\n";
        echo "- Departamentos: " . Departamento::count() . "\n";
        echo "- Categorías: " . Categoria::count() . "\n";
        echo "- Usuarios: " . Usuario::count() . "\n";
        echo "- Tickets: " . Ticket::count() . "\n";
        echo "- Comentarios: " . Comentario::count() . "\n";
    }
}
