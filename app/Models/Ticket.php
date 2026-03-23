<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Ticket extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'tickets';

    protected $fillable = [
        'titulo',
        'descripcion',
        'prioridad',
        'fecha_creacion',
        'fecha_asignacion',
        'fecha_resolucion',
        'usuario_autor_id',
        'categoria_id',
        'comentarios',
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'fecha_asignacion' => 'datetime',
        'fecha_resolucion' => 'datetime',
        'usuario_autor_id' => 'integer',
        'categoria_id' => 'integer',
        'comentarios' => 'array',
    ];

    /**
     * Obtener el usuario que creó el ticket
     */
    public function usuarioAutor()
    {
        return $this->belongsTo(Usuario::class, 'usuario_autor_id', 'id');
    }

    /**
     * Obtener la categoría del ticket
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id', 'id');
    }

    /**
     * Obtener los comentarios del ticket
     */
    public function comentariosRelacion()
    {
        return $this->hasMany(Comentario::class, 'ticket_id', 'id');
    }

    /**
     * Scope: Obtener tickets por prioridad
     */
    public function scopePorPrioridad($query, $prioridad)
    {
        return $query->where('prioridad', $prioridad);
    }

    /**
     * Scope: Obtener tickets sin resolver
     */
    public function scopeSinResolver($query)
    {
        return $query->whereNull('fecha_resolucion');
    }

    /**
     * Scope: Obtener tickets resueltos
     */
    public function scopeResueltos($query)
    {
        return $query->whereNotNull('fecha_resolucion');
    }
}
