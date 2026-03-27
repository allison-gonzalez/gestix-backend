<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Comentario extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'comentarios';

    protected $fillable = [
        'comentario',
        'evidencia',
        'usuario_autor_id',
        'ticket_id',
        'fecha',
    ];

    protected $casts = [
        'usuario_autor_id' => 'integer',
        'ticket_id' => 'integer',
        'fecha' => 'datetime',
    ];

    /**
     * Obtener el usuario que hizo el comentario
     */
    public function usuarioAutor()
    {
        return $this->belongsTo(Usuario::class, 'usuario_autor_id', 'id');
    }

    /**
     * Obtener el ticket del comentario
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    /**
     * Obtener la ruta de evidencia si existe
     */
    public function tieneEvidencia()
    {
        return !is_null($this->evidencia);
    }

    /**
     * Obtener la URL completa de la evidencia
     */
    public function obtenerUrlEvidencia()
    {
        if ($this->tieneEvidencia()) {
            return asset('storage/tickets/' . $this->ticket_id . '/' . $this->evidencia);
        }
        return null;
    }
}
