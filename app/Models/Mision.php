<?php

namespace App\Models;

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
    'codigo_presencial', 'token_qr', 'duracion', 'intentos', 'orden',
    'jugable', 'visible',
])]
class Mision extends Model
{
    protected $table = 'misiones';

    /** Cada misión vale un acierto: superarla. */
    public const ACIERTOS_POSIBLES = 1;

    /** El código de la estación y el token del QR nunca se serializan hacia el cliente. */
    protected $hidden = ['codigo_presencial', 'token_qr'];

    protected static function booted(): void
    {
        static::creating(function (Mision $mision) {
            $mision->token_qr ??= Str::random(32);
        });
    }

    protected function casts(): array
    {
        return [
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

    /* ---------------- Código de la estación ---------------- */

    /** Compara sin importar mayúsculas, espacios ni guiones. */
    public function codigoPresencialCoincide(?string $intento): bool
    {
        $esperado = self::normalizarCodigo($this->codigo_presencial);

        return $esperado !== '' && hash_equals($esperado, self::normalizarCodigo($intento));
    }

    public static function normalizarCodigo(?string $codigo): string
    {
        return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', (string) $codigo));
    }
}
