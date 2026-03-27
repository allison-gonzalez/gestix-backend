<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Permiso extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'permisos';
    protected $primaryKey = '_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estatus',
    ];

    protected $casts = [
        'estatus' => 'integer',
    ];

    /**
     * Obtener los usuarios que tienen este permiso
     */
    public function usuarios()
    {
        return $this->hasManyThrough(
            Usuario::class,
            'usuarios',
            'permisos',
            'id',
            'id'
        );
    }
}
