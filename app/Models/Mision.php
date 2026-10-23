<?php

namespace App\Models;

use App\Support\Audio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Misión en sitio: el reto se resuelve físicamente en una estación de la
 * sala. Escanear su QR la activa y arranca el cronómetro; se supera con el
 * código que entrega la estación.
 */
#[Fillable([
    'slug', 'titulo', 'etiqueta', 'icono', 'brief', 'instruccion',
    'codigo_presencial', 'codigos_clasificacion', 'codigo_cualquier_orden', 'token_qr', 'audio_ruta', 'duracion', 'intentos', 'orden',
    'jugable', 'visible',
])]
class Mision extends Model
{
    protected $table = 'misiones';

    /** Cada misión vale un acierto: superarla. */
    public const ACIERTOS_POSIBLES = 1;

    /** Misión de clasificación de la información: pide un código por nivel. */
    public const SLUG_CLASIFICACION = 'clasificacion';

    /** Niveles de clasificación, en el orden en que se piden. */
    public const NIVELES_CLASIFICACION = [
        'publica' => 'Pública',
        'interna' => 'Interna',
        'reservada' => 'Reservada',
        'confidencial' => 'Confidencial',
    ];

    /** Los códigos de la estación y el token del QR nunca se serializan hacia el cliente. */
    protected $hidden = ['codigo_presencial', 'codigos_clasificacion', 'token_qr'];

    protected static function booted(): void
    {
        static::creating(function (Mision $mision) {
            $mision->token_qr ??= Str::random(32);
        });
    }

    protected function casts(): array
    {
        return [
            'codigos_clasificacion' => 'array',
            'codigo_cualquier_orden' => 'boolean',
            'jugable' => 'boolean',
            'visible' => 'boolean',
        ];
    }

    /** Misiones que se muestran en el centro de mando, en orden. */
    public function scopeVisibles($consulta)
    {
        return $consulta->where('visible', true)->orderBy('orden')->orderBy('id');
    }

    /** Misiones que realmente se pueden jugar y puntúan. */
    public function scopeJugables($consulta)
    {
        return $consulta->where('visible', true)->where('jugable', true)->orderBy('orden')->orderBy('id');
    }

    /** `intentos = 0` significa oportunidades ilimitadas. */
    public function intentosIlimitados(): bool
    {
        return (int) $this->intentos === 0;
    }

    /** «3», o «∞» si son ilimitadas. */
    public function intentosLegible(): string
    {
        return $this->intentosIlimitados() ? '∞' : (string) $this->intentos;
    }

    /** «3 min», «4 min 30 s». */
    public function duracionLegible(): string
    {
        $minutos = intdiv($this->duracion, 60);
        $segundos = $this->duracion % 60;

        return trim(($minutos ? "{$minutos} min " : '').($segundos ? "{$segundos} s" : ''));
    }

    /* ---------------- QR de la estación ---------------- */

    /** URL que va dentro del QR de la estación. */
    public function urlActivacion(): string
    {
        if (! $this->token_qr) {
            $this->regenerarTokenQr();
        }

        return route('mision.activar', [$this->slug, $this->token_qr]);
    }

    public function tokenQrCoincide(string $token): bool
    {
        return $this->token_qr !== null && hash_equals($this->token_qr, $token);
    }

    /** Invalida los QR impresos o proyectados hasta ahora. */
    public function regenerarTokenQr(): void
    {
        $this->forceFill(['token_qr' => Str::random(32)])->save();
    }

    /* ---------------- Audio de la estación ---------------- */

    /** MP3 que suena en la pantalla de proyección, o null si no tiene. */
    public function audioUrl(): ?string
    {
        return Audio::url($this->audio_ruta);
    }

    /* ---------------- Código de la estación ---------------- */

    /** La misión de clasificación pide cuatro códigos, uno por nivel, en lugar de uno solo. */
    public function pideCodigosPorNivel(): bool
    {
        return $this->slug === self::SLUG_CLASIFICACION;
    }

    /** Código esperado para un nivel de clasificación, o null si no se ha fijado. */
    public function codigoDeNivel(string $nivel): ?string
    {
        return ($this->codigos_clasificacion ?? [])[$nivel] ?? null;
    }

    /** Sin código (o sin alguno de los cuatro niveles), nadie puede superar la misión. */
    public function tieneCodigo(): bool
    {
        if (! $this->pideCodigosPorNivel()) {
            return self::normalizarCodigo($this->codigo_presencial) !== '';
        }

        foreach (array_keys(self::NIVELES_CLASIFICACION) as $nivel) {
            if (self::normalizarCodigo($this->codigoDeNivel($nivel)) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Compara sin importar mayúsculas, espacios ni guiones (y, si la misión lo
     * permite, sin importar el orden de los caracteres). En la misión de
     * clasificación recibe un arreglo nivel => código y exige los cuatro.
     *
     * @param  string|array<string, string|null>|null  $intento
     */
    public function codigoPresencialCoincide(string|array|null $intento): bool
    {
        if ($this->pideCodigosPorNivel()) {
            if (! is_array($intento) || ! $this->tieneCodigo()) {
                return false;
            }

            foreach (array_keys(self::NIVELES_CLASIFICACION) as $nivel) {
                $escrito = $intento[$nivel] ?? null;

                if (! is_string($escrito) && $escrito !== null) {
                    return false;
                }

                if (! $this->coincide($this->codigoDeNivel($nivel), $escrito)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_string($intento) && $intento !== null) {
            return false;
        }

        return $this->coincide($this->codigo_presencial, $intento);
    }

    private function coincide(?string $esperado, ?string $escrito): bool
    {
        $esperado = $this->formaComparable($esperado);

        return $esperado !== '' && hash_equals($esperado, $this->formaComparable($escrito));
    }

    /**
     * Código normalizado; si se acepta en cualquier orden, con sus caracteres
     * ordenados («321» y «213» quedan ambos como «123»). Se respeta cuántas
     * veces aparece cada carácter: para «112» vale «121», pero no «122».
     */
    private function formaComparable(?string $codigo): string
    {
        $codigo = self::normalizarCodigo($codigo);

        if (! $this->codigo_cualquier_orden) {
            return $codigo;
        }

        $caracteres = mb_str_split($codigo);
        sort($caracteres, SORT_STRING);

        return implode('', $caracteres);
    }

    public static function normalizarCodigo(?string $codigo): string
    {
        return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', (string) $codigo));
    }
}
