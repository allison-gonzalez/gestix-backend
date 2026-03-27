# Modelos MongoDB - Gestix Backend

Modelos creados para el sistema de gestión de tickets.

## Modelos implementados

1. **Permiso** - Permisos del sistema
2. **Departamento** - Departamentos
3. **Categoria** - Categorías de tickets
4. **Usuario** - Usuarios/trabajadores
5. **Ticket** - Tickets de soporte
6. **Comentario** - Comentarios en tickets

---

## Ejemplos de uso

### PERMISOS

```php
// Crear
Permiso::create([
    'nombre' => 'crear_ticket',
    'descripcion' => 'Permite crear tickets',
    'estatus' => 1
]);

// Obtener todos
$permisos = Permiso::all();

// Obtener activos
$activos = Permiso::where('estatus', 1)->get();

// Actualizar
$permiso = Permiso::find(1);
$permiso->update(['estatus' => 0]);

// Eliminar
$permiso->delete();
```

---

### DEPARTAMENTOS

```php
// Crear
Departamento::create([
    'nombre' => 'Soporte Técnico',
    'estatus' => 1
]);

// Obtener con usuarios
$depto = Departamento::with('usuarios')->find(1);

// Obtener con categorías
$depto = Departamento::with('categorias')->find(1);

// Obtener con ambas relaciones
$depto = Departamento::with('usuarios', 'categorias')->find(1);

// Listar todos los departamentos
$departamentos = Departamento::all();
```

---

### CATEGORIAS

```php
// Crear
Categoria::create([
    'nombre' => 'Hardware',
    'estatus' => 1,
    'departamento_id' => 1
]);

// Obtener con departamento
$categoria = Categoria::with('departamento')->find(1);

// Obtener con tickets
$categoria = Categoria::with('tickets')->find(1);

// Obtener categorías de un departamento
$categorias = Categoria::where('departamento_id', 1)->get();

// Obtener categorías activas
$activas = Categoria::where('estatus', 1)->get();
```

---

### USUARIOS

```php
// Crear usuario
Usuario::create([
    'nombre' => 'Juan Pérez',
    'correo' => 'juan.perez@example.com',
    'telefono' => '3312345678',
    'contrasena' => 'mi_contraseña',  // Se hashea automáticamente
    'estatus' => 1,
    'departamento_id' => 1,
    'permisos' => [1, 2]  // Array de IDs de permisos
]);

// Obtener usuario con departamento
$usuario = Usuario::with('departamento')->find(1);

// Obtener usuario con tickets
$usuario = Usuario::with('tickets')->find(1);

// Obtener usuario con comentarios
$usuario = Usuario::with('comentarios')->find(1);

// Obtener usuario con relaciones
$usuario = Usuario::with('departamento', 'tickets', 'comentarios')->find(1);

// Verificar permiso específico
if ($usuario->tienePermiso(1)) {
    // Hacer algo...
}

// Obtener todos los usuarios de un departamento
$usuarios = Usuario::where('departamento_id', 1)->get();

// Obtener usuarios activos
$activos = Usuario::where('estatus', 1)->get();

// Buscar por correo
$usuario = Usuario::where('correo', 'juan.perez@example.com')->first();
```

---

### TICKETS

```php
// Crear ticket
$ticket = Ticket::create([
    'titulo' => 'Falla en impresora',
    'descripcion' => 'La impresora no responde',
    'prioridad' => 'alta',
    'fecha_creacion' => now(),
    'fecha_asignacion' => now(),
    'usuario_autor_id' => 1,
    'categoria_id' => 1,
    'comentarios' => []
]);

// Obtener ticket con autor
$ticket = Ticket::with('usuarioAutor')->find(1);

// Obtener ticket con categoría
$ticket = Ticket::with('categoria')->find(1);

// Obtener ticket con comentarios
$ticket = Ticket::with('comentariosRelacion')->find(1);

// Obtener ticket con todas las relaciones
$ticket = Ticket::with('usuarioAutor', 'categoria', 'comentariosRelacion')->find(1);

// Obtener tickets sin resolver
$sinResolver = Ticket::sinResolver()->get();

// Obtener tickets resueltos
$resueltos = Ticket::resueltos()->get();

// Obtener tickets por prioridad
$altos = Ticket::porPrioridad('alta')->get();

// Resolver ticket
$ticket->update([
    'fecha_resolucion' => now()
]);

// Obtener tickets de una categoría
$tickets = Ticket::where('categoria_id', 1)->get();

// Obtener tickets de un usuario
$tickets = Ticket::where('usuario_autor_id', 1)->get();

// Contar tickets sin resolver
$count = Ticket::sinResolver()->count();
```

---

### COMENTARIOS

```php
// Crear comentario
Comentario::create([
    'comentario' => 'Se revisó el equipo',
    'evidencia' => 'imagen1.png',  // Opcional
    'usuario_autor_id' => 2,
    'ticket_id' => 1,
    'fecha' => now()
]);

// Obtener comentario con usuario autor
$comentario = Comentario::with('usuarioAutor')->find(1);

// Obtener comentario con ticket
$comentario = Comentario::with('ticket')->find(1);

// Obtener comentario con ambas relaciones
$comentario = Comentario::with('usuarioAutor', 'ticket')->find(1);

// Obtener comentarios de un ticket
$comentarios = Comentario::where('ticket_id', 1)->get();

// Obtener comentarios de un usuario
$comentarios = Comentario::where('usuario_autor_id', 2)->get();

// Verificar si tiene evidencia
if ($comentario->tieneEvidencia()) {
    $url = $comentario->obtenerUrlEvidencia();
}

// Obtener comentarios con evidencia
$conEvidencia = Comentario::where('evidencia', '!=', null)
    ->where('ticket_id', 1)
    ->get();
```

---

## Consultas avanzadas

### Obtener tickets de un usuario con sus comentarios

```php
$tickets = Ticket::where('usuario_autor_id', 1)
    ->with('comentariosRelacion')
    ->get();

foreach ($tickets as $ticket) {
    echo $ticket->titulo . "\n";
    foreach ($ticket->comentariosRelacion as $comentario) {
        echo "  - " . $comentario->comentario . "\n";
    }
}
```

### Obtener usuarios de un departamento con sus tickets

```php
$usuarios = Usuario::where('departamento_id', 1)
    ->with('tickets')
    ->get();

foreach ($usuarios as $usuario) {
    echo $usuario->nombre . " - " . $usuario->tickets->count() . " tickets\n";
}
```

### Obtener tickets de alta prioridad sin resolver

```php
$criticos = Ticket::where('prioridad', 'alta')
    ->whereNull('fecha_resolucion')
    ->with('usuarioAutor', 'categoria')
    ->get();
```

### Obtener tickets resueltos en el últimas 7 días

```php
$resueltos = Ticket::where('fecha_resolucion', '>=', now()->subDays(7))
    ->with('usuarioAutor', 'comentariosRelacion')
    ->orderBy('fecha_resolucion', 'desc')
    ->get();
```

### Contar tickets por prioridad

```php
$porPrioridad = [
    'baja' => Ticket::where('prioridad', 'baja')->count(),
    'media' => Ticket::where('prioridad', 'media')->count(),
    'alta' => Ticket::where('prioridad', 'alta')->count(),
];
```

---

## Scopes personalizados

Todos los modelos incluyen scopes útiles:

```php
// Tickets
Ticket::sinResolver()->get();  // Tickets sin resolución
Ticket::resueltos()->get();    // Tickets resueltos
Ticket::porPrioridad('alta')->get();  // Tickets por prioridad

// Usuarios
Usuario::donde('estatus', 1)->get();  // Usuarios activos
```

---

## Relaciones

### Diagrama de relaciones

```
Departamento (1) ---> (N) Usuario
          |
          +---> (N) Tabla Categoria

Categoria (N) ---> (1) Departamento
     |
     +---> (N) Ticket

Ticket (N) ---> (1) Usuario (autor)
   |
   +---> (1) Categoria
   |
   +---> (N) Comentario

Comentario (N) ---> (1) Usuario (autor)
       |
       +---> (1) Ticket

Usuario (N) ---> (1) Departamento
   |
   +---> (N) Ticket (como autor)
   |
   +---> (N) Comentario (como autor)
```

---

## Métodos personalizados

### Usuario

```php
$usuario->tienePermiso($permiso_id)  // Verifica si tiene un permiso específico
```

### Comentario

```php
$comentario->tieneEvidencia()        // Verifica si tiene evidencia
$comentario->obtenerUrlEvidencia()   // Obtiene URL de la evidencia
```

### Ticket

```php
$ticket->sinResolver()   // Scope para tickets sin resolver
$ticket->resueltos()     // Scope para tickets resueltos
$ticket->porPrioridad()  // Scope por prioridad
```

---

## Operaciones CRUD completas

### Crear y relacionar

```php
// Crear ticket con comentario
$ticket = Ticket::create([
    'titulo' => 'Nueva falla',
    'descripcion' => 'Descripción',
    'prioridad' => 'media',
    'fecha_creacion' => now(),
    'usuario_autor_id' => 1,
    'categoria_id' => 1,
    'comentarios' => [],
]);

// Agregar comentario
$ticket->comentarios[] = Comentario::create([
    'comentario' => 'Primer comentario',
    'usuario_autor_id' => 1,
    'ticket_id' => $ticket->id,
    'fecha' => now()
])->id;

$ticket->save();
```

### Actualizar relaciones

```php
// Agregar permiso a usuario
$usuario = Usuario::find(1);
$usuario->permisos = array_merge($usuario->permisos ?? [], [3]);
$usuario->save();

// Remover permiso
$usuario->permisos = array_diff($usuario->permisos ?? [], [2]);
$usuario->save();
```

---

## Notas importantes

- Los modelos estánames en MongoDB, no SQL
- Las fechas se almacenan como `UTCDateTime` en MongoDB
- Las relaciones son "falsas" (las referencias se almacenan como IDs)
- Se recomienda usar `with()` para eager loading de relaciones
- El campo `permisos` en Usuario es un array de IDs
- El campo `comentarios` en Ticket es un array de IDs
