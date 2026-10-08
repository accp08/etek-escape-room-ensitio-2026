<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['clave', 'grupo', 'valor', 'descripcion', 'multilinea', 'orden'])]
class Texto extends Model
{
    protected $table = 'textos';

    protected function casts(): array
    {
        return ['multilinea' => 'boolean'];
    }
}
