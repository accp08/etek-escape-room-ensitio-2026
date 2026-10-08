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

    /** Código final: se ve en el celular y se digita en la consola de la sala. */
    public const CONSOLA = [
        ['final', 'final.ir_consola',
            'Ve a la consola de recuperación de la sala y digita tu código para revertir el ataque.',
            'Aviso bajo el código completo, en el celular', true],
        ['consola', 'consola.titulo',        'Consola de recuperación',                   'Título de la pantalla proyectada', false],
        ['consola', 'consola.instruccion',   'Digita el código de recuperación que aparece en tu celular', 'Instrucción principal', false],
        ['consola', 'consola.exito_titulo',  'Amenaza contenida',                         'Título al acertar el código', false],
        ['consola', 'consola.error_titulo',  'Acceso denegado',                           'Título al fallar el código', false],
        ['consola', 'consola.error_texto',   'El código no es válido. Revisa tu celular e inténtalo de nuevo.', 'Texto al fallar el código', true],
        ['consola', 'consola.top_titulo',    'Top 10',                                    'Título del ranking', false],
        ...self::CONSOLA_DETALLE,
    ];

    /** Resto de los textos de la consola (se agregaron después). */
    public const CONSOLA_DETALLE = [
        ['consola', 'consola.estado_sistema', 'Sistemas cifrados · esperando código',     'Etiqueta roja de la esquina superior', false],
        ['consola', 'consola.terminal',       'recovery@core — /decrypt',                 'Texto de la barra de la ventana', false],
        ['consola', 'consola.ayuda_escribir', 'escribe',                                  'Ayuda junto a las teclas A–Z / 0–9', false],
        ['consola', 'consola.ayuda_borrar',   'borra',                                    'Ayuda junto a la tecla de borrar', false],
        ['consola', 'consola.ayuda_ejecutar', 'ejecuta',                                  'Ayuda junto a la tecla Enter', false],
        ['consola', 'consola.boton',          'Ejecutar recuperación',                    'Botón para enviar el código', false],
        ['consola', 'consola.faltan',         'Faltan {faltan} caracteres.',              'Aviso si el código está incompleto. Comodín: {faltan}', false],
        ['consola', 'consola.vacio',          'Escribe tu código.',                       'Aviso si se envía sin escribir nada', false],
        ['consola', 'consola.verificando',    'Descifrando sistemas',                     'Texto mientras se valida el código', false],
        ['consola', 'consola.log',
            "verificando firma…\nensamblando fragmentos…\nabriendo volúmenes cifrados…",
            'Mensajes que se escriben mientras se valida, uno por línea', true],
        ['consola', 'consola.exito_saludo',   '¡Bien hecho, {nombre}!',                   'Saludo al acertar. Comodín: {nombre}', false],
        ['consola', 'consola.dato_tiempo',    'Tiempo',                                   'Etiqueta del tiempo al acertar', false],
        ['consola', 'consola.dato_puesto',    'Puesto',                                   'Etiqueta del puesto al acertar', false],
        ['consola', 'consola.exito_top',      '¡Entraste al TOP 10 en el puesto #{puesto}!', 'Mensaje si entra al top 10. Comodín: {puesto}', false],
        ['consola', 'consola.exito_puesto',   'Vas en el puesto #{puesto} de {total}.',   'Mensaje si no entra al top 10. Comodines: {puesto}, {total}', false],
        ['consola', 'consola.exito_repetido', 'Tu código ya estaba registrado. Vas en el puesto #{puesto} de {total}.', 'Mensaje si el código ya se había digitado. Comodines: {puesto}, {total}', true],
        ['consola', 'consola.limite',         'Demasiados intentos seguidos. Espera un minuto e inténtalo de nuevo.', 'Error al superar 20 intentos por minuto', true],
        ['consola', 'consola.sin_conexion',   'No hay conexión con el servidor. Inténtalo de nuevo.', 'Error si se cae la red', true],
        ['consola', 'consola.top_rotulo',     'Ranking en vivo',                          'Texto pequeño sobre el título del ranking', false],
        ['consola', 'consola.top_vacio',      'Aún nadie ha recuperado los sistemas. ¡Sé el primero!', 'Ranking sin jugadores', true],
        ['consola', 'consola.top_criterio',   'Más misiones superadas primero; a igualdad, menor tiempo.', 'Nota al pie del ranking', true],
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
        return [...self::LOGIN, ...self::HUB, ...self::MISION, ...self::PRESENCIAL, ...self::CONSOLA, ...self::CERTIFICADO];
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
