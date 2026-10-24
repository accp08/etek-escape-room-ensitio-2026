<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Niveles fijos con los que se guardaban los códigos hasta ahora. */
    private const NIVELES = [
        'publica' => 'Pública',
        'interna' => 'Interna',
        'reservada' => 'Reservada',
        'confidencial' => 'Confidencial',
    ];

    /**
     * Los códigos de la misión de clasificación dejan de ser cuatro niveles
     * fijos y pasan a ser una lista de {nombre, codigo} que se puede alargar
     * o acortar. Los niveles sin código se descartan: ya no se piden.
     */
    public function up(): void
    {
        foreach (DB::table('misiones')->whereNotNull('codigos_clasificacion')->get(['id', 'codigos_clasificacion']) as $mision) {
            $anteriores = json_decode($mision->codigos_clasificacion, true);

            if (! is_array($anteriores) || array_is_list($anteriores)) {
                continue;
            }

            $codigos = [];
            foreach (self::NIVELES as $nivel => $nombre) {
                $codigo = trim((string) ($anteriores[$nivel] ?? ''));

                if ($codigo !== '') {
                    $codigos[] = ['nombre' => $nombre, 'codigo' => $codigo];
                }
            }

            DB::table('misiones')->where('id', $mision->id)->update(['codigos_clasificacion' => json_encode($codigos)]);
        }
    }

    public function down(): void
    {
        $niveles = array_flip(self::NIVELES);

        foreach (DB::table('misiones')->whereNotNull('codigos_clasificacion')->get(['id', 'codigos_clasificacion']) as $mision) {
            $codigos = json_decode($mision->codigos_clasificacion, true);

            if (! is_array($codigos) || ! array_is_list($codigos)) {
                continue;
            }

            $anteriores = array_fill_keys(array_keys(self::NIVELES), null);
            foreach ($codigos as $codigo) {
                if (isset($niveles[$codigo['nombre'] ?? ''])) {
                    $anteriores[$niveles[$codigo['nombre']]] = $codigo['codigo'];
                }
            }

            DB::table('misiones')->where('id', $mision->id)->update(['codigos_clasificacion' => json_encode($anteriores)]);
        }
    }
};
