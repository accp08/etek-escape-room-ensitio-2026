<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Textos de interfaz agregados después de la primera versión. Viven aquí para
 * que el seeder (instalaciones nuevas) y la migración (instalaciones
 * existentes) usen exactamente los mismos valores.
 *
 * Formato: [grupo, clave, valor, descripción, multilínea]
 */
final class TextosNuevos
{
    /** Acceso con correo (usuario) y cédula (contraseña). */
    public const LOGIN = [
        ['login', 'login.campo_correo', 'Correo electrónico',                        'Etiqueta del campo de usuario', false],
        ['login', 'login.campo_clave',  'Contraseña',                                'Etiqueta del campo de contraseña', false],
        ['login', 'login.ayuda_clave',  'Tu contraseña es tu número de cédula, sin puntos.', 'Ayuda bajo la contraseña', false],
    ];

    /** Textos del acceso anterior (documento + código) que ya no se usan. */
    public const RETIRADOS = ['login.campo_documento', 'login.campo_codigo'];

    public const HUB = [
        ['hub', 'hub.instrucciones', 'Instrucciones del juego', 'Botón y título de la ventana de instrucciones del centro de mando', false],
    ];

    public const MISION = [
        ['mision', 'mision.ver_instrucciones',  'Ver instrucciones',                    'Botón de la cabecera durante la misión', false],
        ['mision', 'mision.instrucciones_nota', 'El cronómetro sigue corriendo mientras lees las instrucciones.', 'Aviso en la hoja de instrucciones', true],
        ['mision', 'mision.volver_a_mision',    'Volver a la misión',                   'Botón para cerrar las instrucciones', false],
    ];

    /** Misiones en sitio: escanear el QR de la estación e ingresar su código. */
    public const PRESENCIAL = [
        ['mision', 'mision.escanear_titulo', 'Escanea el código QR de la estación', 'Título de la pantalla de escaneo', false],
        ['mision', 'mision.escanear_texto',
            'Ubica el código QR de esta misión en la sala y escanéalo. El cronómetro arranca en cuanto lo leas.',
            'Explicación bajo el título de la pantalla de escaneo', true],
        ['mision', 'mision.escanear_boton',  'Escanear código QR',                   'Botón que abre la cámara', false],
        ['mision', 'mision.escanear_ayuda',
            '¿La cámara no abre? Escanea el QR con la cámara de tu celular: te traerá de vuelta aquí.',
            'Ayuda bajo el lector de QR', true],
        ['mision', 'mision.codigo_titulo',   'Misión activa',                        'Título de la ventana del código', false],
        ['mision', 'mision.codigo_texto',
            'Resuelve el reto en la estación. Cuando obtengas el código de la misión, escríbelo aquí antes de que el cronómetro llegue a cero.',
            'Texto de la ventana del código', true],
        ['mision', 'mision.codigo_campo',    'Código de la misión',                  'Etiqueta del campo del código', false],
        ['mision', 'mision.validar',         'Validar código',                       'Botón que envía el código', false],
    ];

    public const CERTIFICADO = [
        ['certificado', 'certificado.texto_completo',
            'Por superar {misiones} del escape room de ciberseguridad, analizar las evidencias del incidente y recuperar el código {codigo} que permitió revertir el ataque antes de que se materializara.',
            'Texto del certificado cuando superó todas las misiones. Comodines: {misiones} (p. ej. «5 de 5 misiones»), {codigo}, {nombre}', true],
        ['certificado', 'certificado.texto_parcial',
            'Por superar {misiones} del escape room de ciberseguridad, analizar las evidencias del incidente y responder ante la amenaza bajo presión.',
            'Texto del certificado cuando no superó todas las misiones. Comodines: {misiones}, {nombre}', true],
        ['certificado', 'certificado.pantalla_titulo', 'Tu certificado está listo',      'Título de la pantalla del certificado', false],
        ['certificado', 'certificado.pantalla_texto',
            'Descárgalo en PDF y consérvalo como constancia de tu participación en el protocolo de contención.',
            'Texto bajo el título de la pantalla', true],
        ['certificado', 'certificado.descargar',       'Descargar certificado en PDF',   'Botón de descarga', false],
    ];

    /** @return array<int, array{0:string,1:string,2:string,3:string,4:bool}> */
    public static function todos(): array
    {
        return [...self::LOGIN, ...self::HUB, ...self::MISION, ...self::PRESENCIAL, ...self::CERTIFICADO];
    }

    /**
     * Crea en una instalación existente los textos que aún no tiene, al final
     * de su grupo. Con la tabla vacía no hace nada: ahí los crea el seeder.
     */
    public static function instalar(): void
    {
        if (! DB::table('textos')->exists()) {
            return;
        }

        foreach (self::todos() as [$grupo, $clave, $valor, $descripcion, $multilinea]) {
            if (DB::table('textos')->where('clave', $clave)->exists()) {
                continue;
            }

            DB::table('textos')->insert([
                'clave' => $clave,
                'grupo' => $grupo,
                'valor' => $valor,
                'descripcion' => $descripcion,
                'multilinea' => $multilinea,
                'orden' => (int) DB::table('textos')->where('grupo', $grupo)->max('orden') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
