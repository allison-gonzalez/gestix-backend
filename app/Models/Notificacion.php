<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Notificacion extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notificaciones';

    protected $fillable = [
        'tipo',        // ticket_creado | ticket_asignado | ticket_actualizado | comentario_nuevo
        'titulo',
        'mensaje',
        'receptor_id', // int — ID del usuario que recibe la notificación
        'emisor_id',   // int — ID del usuario que la generó
        'ticket_id',   // int — ticket relacionado (opcional)
        'leida',       // bool
    ];

    protected $casts = [
        'receptor_id' => 'integer',
        'emisor_id'   => 'integer',
        'ticket_id'   => 'integer',
        'leida'       => 'boolean',
    ];
}
