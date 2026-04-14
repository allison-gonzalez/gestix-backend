<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Departamento extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'departamentos';
    protected $primaryKey = '_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre',
        'estatus',
        'encargado_id',
    ];

    protected $casts = [
        'id'           => 'integer',
        'estatus'      => 'integer',
        'encargado_id' => 'integer',
    ];

    /**
     * Obtener los usuarios del departamento
     */
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'departamento_id', 'id');
    }

    /**
     * Obtener las categorías del departamento
     */
    public function categorias()
    {
        return $this->hasMany(Categoria::class, 'departamento_id', 'id');
    }
}
