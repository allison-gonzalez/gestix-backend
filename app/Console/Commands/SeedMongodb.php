<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Permiso;
use App\Models\Departamento;
use App\Models\Categoria;
use App\Models\Usuario;
use App\Models\Ticket;
use App\Models\Comentario;

class SeedMongodb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mongodb:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed MongoDB con datos iniciales';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Iniciando seed de MongoDB...');

            // Crear permisos
            $this->info('Creando permisos...');
            $permisos = [
                Permiso::create(['nombre' => 'crear_ticket', 'descripcion' => 'Permite crear tickets', 'estatus' => 1]),
                Permiso::create(['nombre' => 'editar_ticket', 'descripcion' => 'Permite editar tickets', 'estatus' => 1]),
                Permiso::create(['nombre' => 'eliminar_ticket', 'descripcion' => 'Permite eliminar tickets', 'estatus' => 1]),
                Permiso::create(['nombre' => 'ver_reportes', 'descripcion' => 'Permite ver reportes', 'estatus' => 1]),
            ];
            $this->info('✓ ' . count($permisos) . ' permisos creados');

            // Crear departamentos
            $this->info('Creando departamentos...');
            $depto_soporte = Departamento::create(['nombre' => 'Soporte Técnico', 'estatus' => 1]);
            $depto_admin = Departamento::create(['nombre' => 'Administración', 'estatus' => 1]);
            $this->info('✓ 2 departamentos creados');

            // Crear categorías
            $this->info('Creando categorías...');
            $categorias = [
                Categoria::create(['nombre' => 'Hardware', 'estatus' => 1, 'departamento_id' => 1]),
                Categoria::create(['nombre' => 'Software', 'estatus' => 1, 'departamento_id' => 1]),
                Categoria::create(['nombre' => 'Red', 'estatus' => 1, 'departamento_id' => 1]),
            ];
            $this->info('✓ ' . count($categorias) . ' categorías creadas');

            // Crear usuarios
            $this->info('Creando usuarios...');
            $usuario1 = Usuario::create([
                'nombre' => 'Juan Pérez',
                'correo' => 'juan.perez@example.com',
                'telefono' => '3312345678',
                'contrasena' => 'password123',
                'estatus' => 1,
                'departamento_id' => 1,
                'permisos' => [1, 2]
            ]);

            $usuario2 = Usuario::create([
                'nombre' => 'Ana López',
                'correo' => 'ana.lopez@example.com',
                'telefono' => '3387654321',
                'contrasena' => 'password123',
                'estatus' => 1,
                'departamento_id' => 1,
                'permisos' => [1]
            ]);

            $usuario3 = Usuario::create([
                'nombre' => 'Carlos García',
                'correo' => 'carlos.garcia@example.com',
                'telefono' => '3319876543',
                'contrasena' => 'password123',
                'estatus' => 1,
                'departamento_id' => 2,
                'permisos' => [1, 2, 4]
            ]);
            $this->info('✓ 3 usuarios creados');

            // Crear tickets
            $this->info('Creando tickets...');
            $ticket1 = Ticket::create([
                'titulo' => 'Falla en impresora del piso 2',
                'descripcion' => 'La impresora de la oficina 201 no responde',
                'prioridad' => 'alta',
                'fecha_creacion' => now(),
                'fecha_asignacion' => now(),
                'usuario_autor_id' => 1,
                'categoria_id' => 1,
                'comentarios' => []
            ]);

            $ticket2 = Ticket::create([
                'titulo' => 'Problema con conexión a internet',
                'descripcion' => 'La conexión de la oficina 305 está lenta',
                'prioridad' => 'media',
                'fecha_creacion' => now()->subDays(2),
                'fecha_asignacion' => now()->subDays(1),
                'fecha_resolucion' => now(),
                'usuario_autor_id' => 2,
                'categoria_id' => 3,
                'comentarios' => []
            ]);

            $ticket3 = Ticket::create([
                'titulo' => 'Software no funciona correctamente',
                'descripcion' => 'La aplicación de facturación genera errores',
                'prioridad' => 'alta',
                'fecha_creacion' => now()->subDays(1),
                'fecha_asignacion' => now(),
                'usuario_autor_id' => 1,
                'categoria_id' => 2,
                'comentarios' => []
            ]);
            $this->info('✓ 3 tickets creados');

            // Crear comentarios
            $this->info('Creando comentarios...');
            $comentario1 = Comentario::create([
                'comentario' => 'Se revisó el equipo, aparentemente tiene un problema de conexión',
                'evidencia' => 'foto_impresora.jpg',
                'usuario_autor_id' => 2,
                'ticket_id' => 1,
                'fecha' => now()
            ]);

            $comentario2 = Comentario::create([
                'comentario' => 'Se solicitó refacción de la placa madre',
                'usuario_autor_id' => 1,
                'ticket_id' => 1,
                'fecha' => now()->addHour()
            ]);

            $comentario3 = Comentario::create([
                'comentario' => 'Se verificó la conexión y todo está funcionando correctamente',
                'evidencia' => 'resultado_prueba_velocidad.png',
                'usuario_autor_id' => 3,
                'ticket_id' => 2,
                'fecha' => now()->subDays(1)
            ]);
            $this->info('✓ 3 comentarios creados');

            $this->info("\n✅ Seed completado exitosamente!");
            $this->info("Resumen:");
            $this->info("  - Permisos: " . Permiso::count());
            $this->info("  - Departamentos: " . Departamento::count());
            $this->info("  - Categorías: " . Categoria::count());
            $this->info("  - Usuarios: " . Usuario::count());
            $this->info("  - Tickets: " . Ticket::count());
            $this->info("  - Comentarios: " . Comentario::count());

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error durante el seeding: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
