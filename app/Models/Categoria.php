<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Categoria extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'categorias';

    protected $fillable = [
        'nombre',
        'estatus',
        'departamento_id',
    ];

    protected $casts = [
        'estatus' => 'integer',
        'departamento_id' => 'integer',
    ];

    /**
     * Obtener el departamento de la categoría
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id', 'id');
    }

    /**
     * Obtener los tickets de esta categoría
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'categoria_id', 'id');
    }
}
