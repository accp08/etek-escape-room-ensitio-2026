<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Fragmento del código de recuperación que un participante gana al superar
 * una misión. Es propio de cada participante: no es intercambiable.
 */
#[Fillable(['participante_id', 'mision_id', 'fragmento'])]
class CodigoMision extends Model
{
    protected $table = 'codigos_mision';

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function mision(): BelongsTo
    {
        return $this->belongsTo(Mision::class);
    }

    /** Caracteres que se usan en los fragmentos (sin O/0 ni I/1). */
    public const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function largo(): int
    {
        return (int) config('escape_room.largo_fragmento', 2);
    }

    /**
     * Fragmento aleatorio que ningún otro participante tiene en esa misión.
     * Se omiten caracteres que se confunden al dictarlos (O/0, I/1).
     */
    public static function fragmentoLibre(int $misionId): string
    {
        $largo = static::largo();
        $posibles = strlen(self::ALFABETO) ** $largo;

        // Sin este tope, al agotarse las combinaciones el ciclo no terminaría nunca.
        if (static::where('mision_id', $misionId)->count() >= $posibles) {
            throw new RuntimeException(
                "No quedan fragmentos de {$largo} caracteres libres ({$posibles} en total): "
                .'sube largo_fragmento en config/escape_room.php.'
            );
        }

        do {
            $fragmento = Participante::generarCodigo($largo);
        } while (static::where('mision_id', $misionId)->where('fragmento', $fragmento)->exists());

        return $fragmento;
    }
}
