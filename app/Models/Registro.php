<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['canal', 'tipo', 'nivel', 'descripcion', 'user_id', 'participante_id', 'contexto', 'ip'])]
class Registro extends Model
{
    protected $table = 'registros';

    protected function casts(): array
    {
        return ['contexto' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    /** Quién originó el registro, venga del dashboard o del juego. */
    public function autor(): string
    {
        return $this->usuario?->name
            ?? $this->participante?->nombre
            ?? 'Sistema';
    }
}
