<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['icono', 'titulo', 'texto', 'orden'])]
class PasoBriefing extends Model
{
    protected $table = 'pasos_briefing';
}
