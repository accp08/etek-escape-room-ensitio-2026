<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['nombre', 'documento', 'area', 'correo', 'codigo_acceso', 'activo'])]
class Participante extends Model
{
    protected $table = 'participantes';

    /** El token de la sesión activa nunca se serializa hacia el cliente. */
    protected $hidden = ['sesion_token'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'visto_en' => 'datetime'];
    }

    /** Cada participante nace con su propio fragmento para cada misión. */
    protected static function booted(): void
    {
        static::created(fn (Participante $participante) => $participante->asegurarCodigos());
    }

    /** El correo es el usuario del juego: se guarda en minúsculas y sin espacios. */
    protected function correo(): Attribute
    {
        return Attribute::set(fn (?string $valor) => ($valor = mb_strtolower(trim((string) $valor))) !== '' ? $valor : null);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(Partida::class);
    }

    public function codigos(): HasMany
    {
        return $this->hasMany(CodigoMision::class);
    }

    /**
     * Fragmentos del participante indexados por misión. Si alguna misión no
     * tiene fragmento todavía (se creó después que el participante), se le
     * genera en este momento.
     *
     * @return Collection<int, string> mision_id => fragmento
     */
    public function asegurarCodigos(): Collection
    {
        $existentes = $this->codigos()->pluck('fragmento', 'mision_id');

        Mision::whereNotIn('id', $existentes->keys())->pluck('id')
            ->each(function (int $misionId) use ($existentes) {
                $codigo = $this->codigos()->create([
                    'mision_id' => $misionId,
                    'fragmento' => CodigoMision::fragmentoLibre($misionId),
                ]);

                $existentes->put($misionId, $codigo->fragmento);
            });

        return $existentes;
    }

    public function partidaActual(): ?Partida
    {
        return $this->partidas()->latest('id')->first();
    }

    /**
     * Código interno del participante. Ya no se usa para entrar al juego
     * (el acceso es solo con el correo), pero la columna sigue siendo obligatoria.
     * Se omiten caracteres que se confunden al dictarlos (O/0, I/1).
     */
    public static function generarCodigo(int $largo = 6): string
    {
        $alfabeto = CodigoMision::ALFABETO;

        return collect(range(1, $largo))
            ->map(fn () => $alfabeto[random_int(0, strlen($alfabeto) - 1)])
            ->implode('');
    }
}
