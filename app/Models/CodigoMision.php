<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Fragmento aleatorio que ningún otro participante tiene en esa misión.
     * Se omiten caracteres que se confunden al dictarlos (O/0, I/1).
     */
    public static function fragmentoLibre(int $misionId): string
    {
        $largo = (int) config('escape_room.largo_fragmento', 3);

        do {
            $fragmento = Participante::generarCodigo($largo);
        } while (static::where('mision_id', $misionId)->where('fragmento', $fragmento)->exists());

        return $fragmento;
    }
}
