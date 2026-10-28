<?php

use App\Models\CodigoMision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los fragmentos pasan de 2 a 3 caracteres: con 2 solo cabían 1.024
     * participantes y la lista del evento tiene 2.000. Se regeneran los que
     * tenían otro largo, así todos los participantes quedan iguales.
     *
     * Ojo: si alguien ya había anotado un fragmento, el nuevo es distinto.
     */
    public function up(): void
    {
        $largo = CodigoMision::largo();

        DB::table('codigos_mision')
            ->orderBy('id')
            ->get()
            ->reject(fn ($fila) => strlen($fila->fragmento) === $largo)
            ->each(function ($fila) use ($largo) {
                do {
                    $fragmento = '';
                    for ($i = 0; $i < $largo; $i++) {
                        $fragmento .= CodigoMision::ALFABETO[random_int(0, strlen(CodigoMision::ALFABETO) - 1)];
                    }
                } while (DB::table('codigos_mision')->where('mision_id', $fila->mision_id)->where('fragmento', $fragmento)->exists());

                DB::table('codigos_mision')->where('id', $fila->id)->update(['fragmento' => $fragmento, 'updated_at' => now()]);
            });
    }

    /** Los fragmentos anteriores no se guardaron: no hay a qué volver. */
    public function down(): void {}
};
