<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Departamento extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'departamentos';

    protected $fillable = [
        'nombre',
        'estatus',
    ];

    protected $casts = [
        'estatus' => 'integer',
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
