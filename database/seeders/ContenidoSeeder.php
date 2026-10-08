<?php

namespace Database\Seeders;

use App\Models\Ajuste;
use App\Models\Mision;
use App\Models\PasoBriefing;
use App\Models\Texto;
use App\Support\TextosNuevos;
use Illuminate\Database\Seeder;

/**
 * Lleva a la base de datos el contenido que hasta ahora vivía en
 * config/escape_room.php. A partir de aquí, la fuente de verdad es la BD y
 * todo se edita desde el dashboard.
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar nada.
 */
class ContenidoSeeder extends Seeder
{
    public function run(): void
    {
        $this->misiones();
        $this->briefing();
        $this->ajustes();
        $this->textos();

        $this->command?->info('Contenido cargado.');
    }

    private function misiones(): void
    {
        foreach (config('escape_room.misiones', []) as $i => $m) {
            $mision = Mision::updateOrCreate(
                ['slug' => $m['slug']],
                [
                    'titulo' => $m['titulo'],
                    'etiqueta' => $m['etiqueta'],
                    'icono' => $m['icono'],
                    'brief' => $m['brief'],
                    'instruccion' => $m['instruccion'],
                    'duracion' => $m['duracion'] ?? config('escape_room.duracion_mision', 180),
                    'intentos' => $m['intentos'] ?? config('escape_room.intentos_por_mision', 2),
                    'orden' => $i,
                    'jugable' => true,
                    'visible' => true,
                ],
            );

            // El código de la estación se fija en el dashboard: el seeder no pisa uno ya guardado.
            if (! $mision->codigo_presencial && ! empty($m['codigo'])) {
                $mision->update(['codigo_presencial' => $m['codigo']]);
            }
        }
    }

    private function briefing(): void
    {
        $pasos = [
            ['alert',   'Estamos bajo ataque',            'Un actor malicioso comprometió los sistemas de información de la organización. El cifrado avanza y el tiempo corre en contra.'],
            ['shield',  'Tu rol',                         'Asumes el rol de colaborador del equipo de respuesta. Deberás analizar evidencias, identificar riesgos y decidir bajo presión.'],
            ['lock',    'Una misión, un fragmento',       'Cada misión superada entrega una parte del código de recuperación. Necesitas todas las partes para revertir el ataque.'],
            ['clock',   'Escanea, resuelve y escribe',    'En cada estación escanea el código QR: el cronómetro arranca en ese momento. Resuelve el reto físico y escribe en tu celular el código que obtengas.'],
            ['trophy',  'Tu desempeño queda registrado',  'El ranking general combina tus aciertos y el tiempo empleado. Precisión primero; velocidad después.'],
        ];

        if (PasoBriefing::count() > 0) {
            return;
        }

        foreach ($pasos as $i => [$icono, $titulo, $texto]) {
            PasoBriefing::create(compact('icono', 'titulo', 'texto') + ['orden' => $i]);
        }
    }

    private function ajustes(): void
    {
        $valores = [
            'duracion_mision' => config('escape_room.duracion_mision', 180),
            'intentos_por_mision' => config('escape_room.intentos_por_mision', 2),
            'video_final' => config('escape_room.video_final', 'escape.mp4'),
            'juego_titulo' => config('escape_room.marca.titulo'),
            'juego_subtitulo' => config('escape_room.marca.subtitulo'),
        ];

        foreach ($valores as $clave => $valor) {
            Ajuste::firstOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
        }
    }

    private function textos(): void
    {
        $textos = [
            // grupo, clave, valor, descripción, multilínea
            ['login', 'login.alerta',        'Alerta nivel crítico',                    'Etiqueta roja sobre el título', false],
            ['login', 'login.intro',         'Los sistemas de información de la organización han sido comprometidos. Identifícate para unirte al equipo de respuesta.', 'Párrafo de entrada', true],
            ...TextosNuevos::LOGIN,
            ['login', 'login.boton',         'Iniciar protocolo',                       'Botón de entrada', false],

            ['briefing', 'briefing.titulo',  'Briefing de misión',                      'Encabezado del briefing', false],
            ['briefing', 'briefing.saltar',  'Saltar',                                  'Enlace para omitir el briefing', false],
            ['briefing', 'briefing.comenzar', 'Comenzar',                                'Botón final del briefing', false],

            ['hub', 'hub.titulo',            'Centro de mando',                         'Antetítulo del hub', false],
            ['hub', 'hub.saludo',            'Hola',                                    'Saludo antes del nombre', false],
            ['hub', 'hub.estado_ataque',     'Ataque en curso',                         'Etiqueta del panel superior', false],
            ['hub', 'hub.descripcion',       'Sistemas comprometidos. Recupera los fragmentos del código de recuperación para revertir el cifrado.', 'Texto del panel superior', true],
            ['hub', 'hub.misiones',          'Misiones disponibles',                    'Título de la lista', false],
            ['hub', 'hub.boton_codigo',      'Ingresar código de recuperación',         'Botón inferior', false],
            ['hub', 'hub.proximamente',      'Próximamente',                            'Etiqueta de misiones no jugables', false],
            ...TextosNuevos::HUB,

            ['mision', 'mision.objetivo',    'Tu objetivo',                             'Título del recuadro de instrucciones', false],
            ...TextosNuevos::MISION,
            ...TextosNuevos::PRESENCIAL,

            ['resultado', 'resultado.exito_titulo',     'Fragmento recuperado',         'Título al superar', false],
            ['resultado', 'resultado.exito_texto',      'Descifraste una parte del código de recuperación. Consérvala: la necesitarás al final.', 'Texto al superar', true],
            ['resultado', 'resultado.reintento_titulo', 'Tienes otra oportunidad',      'Título del reintento', false],
            ['resultado', 'resultado.reintento_texto',  'Tu análisis no fue correcto. En la siguiente oportunidad el cronómetro vuelve a empezar y el tiempo se suma a tu total.', 'Texto del reintento', true],
            ['resultado', 'resultado.fallo_titulo',     'Sin intentos disponibles',     'Título al fallar', false],
            ['resultado', 'resultado.fallo_texto',      'Agotaste los intentos de esta misión. Podrás volver a intentarla al terminar las demás.', 'Texto al fallar', true],
            ['resultado', 'resultado.tiempo_titulo',    'El cronómetro llegó a cero',   'Título al agotarse el tiempo', false],
            ['resultado', 'resultado.tiempo_texto',     'No alcanzaste a completar la misión dentro del tiempo asignado.', 'Texto al agotarse el tiempo', true],

            ['final', 'final.titulo',        'Revierte el ataque',                      'Título de la consola final', false],
            ['final', 'final.descripcion',   'Ensambla los fragmentos en el orden de las misiones para liberar los sistemas cifrados.', 'Texto de la consola', true],
            ['final', 'final.boton',         'Ejecutar recuperación',                   'Botón de la consola', false],
            ['final', 'final.error',         'El código no es correcto. Revisa los fragmentos obtenidos.', 'Mensaje de código inválido', true],

            ['victoria', 'victoria.titulo',  'Amenaza contenida',                       'Título de cierre', false],
            ['victoria', 'victoria.texto',   'Los sistemas de información fueron recuperados. El ciberataque no se materializó gracias a tu criterio y tu velocidad de respuesta.', 'Texto de cierre', true],
            ['victoria', 'victoria.certificado', 'Generar mi certificado',              'Botón del certificado', false],

            ['ranking', 'ranking.titulo',    'Ranking general',                         'Título del ranking', false],
            ['ranking', 'ranking.criterio',  'El orden se define por aciertos y, en caso de empate, por el menor tiempo de resolución.', 'Explicación del criterio', true],

            ['certificado', 'certificado.encabezado', 'Certificado de participación',   'Línea superior del certificado', false],
            ['certificado', 'certificado.otorga',     'Se otorga el presente reconocimiento a', 'Frase antes del nombre', false],
            ['certificado', 'certificado.firma',      'Comité de Seguridad de la Información',  'Firma del certificado', false],
            ['certificado', 'certificado.video',      'Ver el video de la experiencia', 'Botón del video', false],
            ...TextosNuevos::CERTIFICADO,
        ];

        foreach ($textos as $i => [$grupo, $clave, $valor, $descripcion, $multilinea]) {
            Texto::firstOrCreate(
                ['clave' => $clave],
                compact('grupo', 'valor', 'descripcion', 'multilinea') + ['orden' => $i],
            );
        }
    }
}
