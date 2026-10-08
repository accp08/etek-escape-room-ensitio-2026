<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'participante_id', 'estado', 'iniciada_en', 'finalizada_en',
    'segundos_jugados', 'aciertos', 'aciertos_posibles',
    'certificado_visto_en', 'certificado_descargado_en', 'certificado_descargas',
])]
class Partida extends Model
{
    protected $table = 'partidas';

    protected function casts(): array
    {
        return [
            'iniciada_en' => 'datetime',
            'finalizada_en' => 'datetime',
            'certificado_visto_en' => 'datetime',
            'certificado_descargado_en' => 'datetime',
        ];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function misiones(): HasMany
    {
        return $this->hasMany(PartidaMision::class);
    }

    public function completada(): bool
    {
        return $this->estado === 'completada';
    }

    /**
     * Estado del certificado: descargado | visto | pendiente.
     *
     * «Descargado» significa que el participante pulsó el botón de descarga.
     * Si canceló el diálogo de impresión del navegador, eso no es observable
     * desde el servidor.
     */
    public function estadoCertificado(): string
    {
        return match (true) {
            $this->certificado_descargado_en !== null => 'descargado',
            $this->certificado_visto_en !== null => 'visto',
            default => 'pendiente',
        };
    }

    /** Tiempo jugado en mm:ss. */
    public function tiempoLegible(): string
    {
        $s = $this->segundos_jugados;

        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }
}
