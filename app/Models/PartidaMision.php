<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'partida_id', 'mision_id', 'estado', 'intentos_usados', 'reactivada',
    'aciertos', 'aciertos_posibles', 'segundos', 'iniciada_en', 'finalizada_en', 'respuestas',
])]
class PartidaMision extends Model
{
    protected $table = 'partida_misiones';

    protected function casts(): array
    {
        return [
            'respuestas' => 'array',
            'reactivada' => 'boolean',
            'iniciada_en' => 'datetime',
            'finalizada_en' => 'datetime',
        ];
    }

    public function partida(): BelongsTo
    {
        return $this->belongsTo(Partida::class);
    }

    public function mision(): BelongsTo
    {
        return $this->belongsTo(Mision::class);
    }

    public function superada(): bool
    {
        return $this->estado === 'superada'
            && $this->aciertos_posibles > 0
            && $this->aciertos === $this->aciertos_posibles;
    }

    public function fallada(): bool
    {
        return $this->estado === 'fallada';
    }

    public function abierta(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_curso'], true)
            || ($this->estado === 'superada' && ! $this->superada());
    }

    /**
     * Segundos que quedan del cronómetro de la oportunidad actual. Cada
     * oportunidad arranca con la duración completa de la misión. El tiempo lo
     * manda el servidor: el cliente solo lo pinta, así recargar no regala tiempo.
     */
    public function segundosRestantes(): int
    {
        if (! $this->iniciada_en) {
            return $this->mision->duracion;
        }

        return max(0, $this->mision->duracion - $this->transcurridos());
    }

    public function sinTiempo(): bool
    {
        return $this->iniciada_en !== null && $this->segundosRestantes() <= 0;
    }

    /** Segundos consumidos por la oportunidad en curso (nunca más que la duración). */
    public function segundosDelIntento(): int
    {
        if (! $this->iniciada_en) {
            return 0;
        }

        return min($this->mision->duracion, $this->transcurridos());
    }

    /** Segundos enteros desde que arrancó el cronómetro (Carbon los da con decimales). */
    private function transcurridos(): int
    {
        return (int) floor(now()->diffInSeconds($this->iniciada_en, absolute: true));
    }

    /** ¿Le queda alguna oportunidad dentro de esta ronda? */
    public function leQuedanIntentos(): bool
    {
        return $this->mision->intentosIlimitados()
            || $this->intentos_usados < $this->mision->intentos;
    }

    /** Oportunidades por usar en esta ronda: «2», o «∞» si son ilimitadas. */
    public function intentosRestantesLegible(): string
    {
        return $this->mision->intentosIlimitados()
            ? '∞'
            : (string) max(0, $this->mision->intentos - $this->intentos_usados);
    }
}
