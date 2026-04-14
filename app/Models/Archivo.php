<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Archivo extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'archivos';
    public $timestamps    = false;

    protected $fillable = [
        'tipo_entidad',
        'entidad_id',
        'nombre_original',
        'nombre_almacenado',
        'ruta',
        'mime_type',
        'tamanio',
        'fecha_subida',
    ];

    protected $casts = [
        'id'          => 'integer',
        'entidad_id'  => 'integer',
        'tamanio'     => 'integer',
        'fecha_subida'=> 'datetime',
    ];
}
