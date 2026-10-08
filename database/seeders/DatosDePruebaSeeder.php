<?php

namespace Database\Seeders;

use App\Models\Mision;
use App\Models\Participante;
use Illuminate\Database\Seeder;

/**
 * Datos para recorrer el juego en local: un código por estación y tres
 * participantes sin partida. Solo para pruebas; no se ejecuta con
 * DatabaseSeeder.
 *
 *   php artisan db:seed --class=DatosDePruebaSeeder
 *
 * Es idempotente y no pisa un código de estación que ya se haya fijado.
 */
class DatosDePruebaSeeder extends Seeder
{
    private const CODIGOS = [
        'phishing' => 'CORREO',
        'contrasenas' => 'CLAVE',
        'clasificacion' => 'ARCHIVO',
        'politicas' => 'CAMARA',
        'ia-fuera-de-control' => 'ROBOT',
    ];

    private const PARTICIPANTES = [
        ['Andrea Pardo', 'andrea.pardo@escaperoom.local', '1020345678', 'Gestión Humana'],
        ['Julián Restrepo', 'julian.restrepo@escaperoom.local', '1032998471', 'Tecnología'],
        ['Laura Medina', 'laura.medina@escaperoom.local', '1018776234', 'Operaciones'],
    ];

    public function run(): void
    {
        foreach (self::CODIGOS as $slug => $codigo) {
            Mision::where('slug', $slug)->whereNull('codigo_presencial')->update(['codigo_presencial' => $codigo]);
        }

        foreach (self::PARTICIPANTES as [$nombre, $correo, $documento, $area]) {
            Participante::firstOrCreate(['correo' => $correo], [
                'nombre' => $nombre,
                'documento' => $documento,
                'area' => $area,
                'codigo_acceso' => Participante::generarCodigo(),
            ]);
        }

        $this->command?->info('Datos de prueba cargados.');
    }
}
