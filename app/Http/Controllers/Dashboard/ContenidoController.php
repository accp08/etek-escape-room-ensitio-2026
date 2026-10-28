<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Participante;
use App\Models\PasoBriefing;
use App\Models\Texto;
use App\Support\Audio;
use App\Support\Bitacora;
use App\Support\Contenido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Briefing, textos de interfaz y parámetros del juego. */
class ContenidoController extends Controller
{
    /* ---------------- Briefing ---------------- */

    public function briefing()
    {
        return view('dashboard.briefing', [
            'pasos' => PasoBriefing::orderBy('orden')->get(),
        ]);
    }

    public function guardarPaso(Request $peticion, ?PasoBriefing $paso = null): RedirectResponse
    {
        $datos = $peticion->validate([
            'icono' => ['required', 'string', 'max:30'],
            'titulo' => ['required', 'string', 'max:120'],
            'texto' => ['required', 'string', 'max:600'],
            'orden' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $paso?->exists ? $paso->update($datos) : PasoBriefing::create($datos);

        Bitacora::dashboard('briefing.guardado', "Se guardó el paso «{$datos['titulo']}»");

        return back()->with('exito', 'Instrucciones actualizadas.');
    }

    public function borrarPaso(PasoBriefing $paso): RedirectResponse
    {
        $titulo = $paso->titulo;
        $paso->delete();

        Bitacora::dashboard('briefing.eliminado', "Se eliminó el paso «{$titulo}»", [], 'aviso');

        return back()->with('exito', 'Paso eliminado.');
    }

    /* ---------------- Textos de interfaz ---------------- */

    /**
     * Pestañas y pantallas de la página de textos, en el orden en que el
     * participante recorre el juego. Cada pantalla toma los textos de su
     * grupo, salvo los que otra pantalla reclama por clave.
     */
    private const PESTANAS_TEXTOS = [
        'acceso' => ['titulo' => 'Acceso', 'icono' => 'user', 'pantallas' => [
            ['titulo' => 'Inicio de sesión', 'ruta' => '/', 'grupo' => 'login',
                'ayuda' => 'Primera pantalla: el participante entra con su correo.'],
            ['titulo' => 'Instrucciones', 'ruta' => '/instrucciones', 'grupo' => 'briefing',
                'ayuda' => 'Briefing paso a paso. Los pasos se editan en Contenido › Instrucciones.'],
        ]],
        'mando' => ['titulo' => 'Centro de mando', 'icono' => 'shield', 'pantallas' => [
            ['titulo' => 'Centro de mando', 'ruta' => '/centro-de-mando', 'grupo' => 'hub',
                'ayuda' => 'Estado del ataque, fragmentos obtenidos y lista de misiones.'],
        ]],
        'misiones' => ['titulo' => 'Misiones', 'icono' => 'folder', 'pantallas' => [
            ['titulo' => 'Escanear el QR', 'ruta' => null, 'grupo' => 'mision',
                'ayuda' => 'Se ve al entrar a una misión: pide escanear el QR de la estación. El título, la introducción y las instrucciones de cada una se editan en Misiones.'],
            ['titulo' => 'Ventana del código', 'ruta' => null, 'claves' => [
                'mision.codigo_titulo', 'mision.codigo_texto', 'mision.codigo_campo', 'mision.validar',
                'mision.ver_instrucciones', 'mision.instrucciones_nota', 'mision.volver_a_mision',
            ], 'ayuda' => 'Se abre al escanear el QR: el cronómetro corre y el participante escribe el código de la estación.'],
            ['titulo' => 'Resultado', 'ruta' => null, 'grupo' => 'resultado',
                'ayuda' => 'Aparece al enviar el código: éxito, reintento, fallo o tiempo agotado.'],
            ['titulo' => 'Pantalla de la estación (proyectada)', 'ruta' => null, 'grupo' => 'proyeccion',
                'ayuda' => 'Se proyecta en el televisor de cada estación junto al QR (Misiones › Proyectar). Los textos son los mismos para todas las estaciones.'],
        ]],
        'cierre' => ['titulo' => 'Cierre', 'icono' => 'trophy', 'pantallas' => [
            ['titulo' => 'Consola de recuperación (proyectada)', 'ruta' => '/consola', 'grupo' => 'consola',
                'ayuda' => 'Pantalla pública de la sala donde cada jugador digita su código. Muestra el top 10.'],
            ['titulo' => 'Consola final', 'ruta' => '/codigo-final', 'grupo' => 'final',
                'ayuda' => 'Donde se ingresa el código de recuperación completo.'],
            ['titulo' => 'Victoria', 'ruta' => '/amenaza-contenida', 'grupo' => 'victoria',
                'ayuda' => 'Cierre de la experiencia tras ingresar el código correcto.'],
            ['titulo' => 'Certificado', 'ruta' => '/certificado', 'grupo' => 'certificado',
                'ayuda' => 'Certificado de participación exportable a PDF.'],
            ['titulo' => 'Ranking', 'ruta' => '/ranking', 'grupo' => 'ranking',
                'ayuda' => 'Tabla general de posiciones.'],
        ]],
    ];

    public function textos()
    {
        $textos = Texto::orderBy('orden')->get();
        $reclamadas = collect(self::PESTANAS_TEXTOS)
            ->flatMap(fn ($p) => $p['pantallas'])
            ->flatMap(fn ($s) => $s['claves'] ?? []);

        $pestanas = collect(self::PESTANAS_TEXTOS)->map(function (array $pestana) use ($textos, $reclamadas) {
            $pestana['pantallas'] = collect($pestana['pantallas'])->map(function (array $pantalla) use ($textos, $reclamadas) {
                $pantalla['textos'] = isset($pantalla['claves'])
                    ? $textos->whereIn('clave', $pantalla['claves'])->values()
                    : $textos->where('grupo', $pantalla['grupo'])->whereNotIn('clave', $reclamadas)->values();

                return $pantalla;
            })->filter(fn ($p) => $p['textos']->isNotEmpty())->values();

            return $pestana;
        });

        // Cualquier texto que no encaje en una pantalla conocida queda a la vista.
        $ubicados = $pestanas->flatMap(fn ($p) => $p['pantallas'])->flatMap(fn ($s) => $s['textos'])->pluck('id');
        $sueltos = $textos->whereNotIn('id', $ubicados)->values();

        if ($sueltos->isNotEmpty()) {
            $pestanas->put('otros', ['titulo' => 'Otros', 'icono' => 'file', 'pantallas' => collect([
                ['titulo' => 'Sin pantalla asignada', 'ruta' => null, 'ayuda' => 'Textos que aún no se ubican en una pantalla.', 'textos' => $sueltos],
            ])]);
        }

        return view('dashboard.textos', [
            'pestanas' => $pestanas->filter(fn ($p) => $p['pantallas']->isNotEmpty()),
        ]);
    }

    public function guardarTextos(Request $peticion): RedirectResponse
    {
        $valores = (array) $peticion->input('textos', []);

        foreach ($valores as $id => $valor) {
            if (! is_scalar($valor)) {
                continue;
            }

            Texto::whereKey((int) $id)->update(['valor' => Str::limit((string) $valor, 2000, '')]);
        }

        Contenido::olvidar();
        Bitacora::dashboard('textos.guardados', 'Se actualizaron los textos de la interfaz', [
            'cantidad' => count($valores),
        ]);

        // Vuelve a la pestaña en la que se estaba trabajando.
        $pestana = (string) $peticion->input('pestana');
        $ancla = preg_match('/^[a-z]+$/', $pestana) ? '#'.$pestana : '';

        return redirect()
            ->to(route('dashboard.textos').$ancla)
            ->with('exito', 'Textos actualizados en todo el juego.');
    }

    /* ---------------- Parámetros ---------------- */

    /** Rótulo del panel superior del centro de mando: es un texto, pero se edita también en Parámetros. */
    private const TEXTO_ESTADO_ATAQUE = 'hub.estado_ataque';

    public function ajustes()
    {
        return view('dashboard.ajustes', [
            'ajustes' => Ajuste::orderBy('clave')->get()->keyBy('clave'),
            'estadoAtaque' => Texto::where('clave', self::TEXTO_ESTADO_ATAQUE)->value('valor') ?? 'Ataque en curso',
            'participantes' => [
                'total' => Participante::count(),
                'activos' => Participante::where('activo', true)->count(),
            ],
        ]);
    }

    public function guardarAjustes(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'duracion_mision' => ['required', 'integer', 'min:30', 'max:3600'],
            'intentos_por_mision' => ['required', 'integer', 'min:0', 'max:99'],   // 0 = ilimitadas
            'ranking_minutos_minimos' => ['required', 'integer', 'min:0', 'max:600'], // 0 = sin mínimo
            'juego_titulo' => ['required', 'string', 'max:80'],
            'juego_subtitulo' => ['nullable', 'string', 'max:120'],
            'video_final' => ['nullable', 'string', 'max:160'],
            'metodo_acceso' => ['required', 'in:correo,outlook'],
            'dominios_outlook' => ['nullable', 'string', 'max:2000'],
            'estado_ataque' => ['required', 'string', 'max:60'],
        ], [], ['estado_ataque' => 'rótulo del centro de mando']);

        Texto::updateOrCreate(['clave' => self::TEXTO_ESTADO_ATAQUE], [
            'grupo' => 'hub',
            'valor' => trim($datos['estado_ataque']),
            'descripcion' => 'Etiqueta del panel superior',
        ]);
        unset($datos['estado_ataque']);

        if ($datos['metodo_acceso'] === 'outlook') {
            $dominios = preg_split('/[\s,;]+/', mb_strtolower(trim($datos['dominios_outlook'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            $dominios = array_values(array_unique(array_map(fn (string $dominio): string => ltrim($dominio, '@'), $dominios)));

            if ($dominios === [] || collect($dominios)->contains(fn (string $dominio): bool => ! filter_var('usuario@'.$dominio, FILTER_VALIDATE_EMAIL))) {
                return back()->withErrors(['dominios_outlook' => 'Indica uno o más dominios válidos, separados por coma o salto de línea.'])->withInput();
            }

            $datos['dominios_outlook'] = implode(',', $dominios);
        } else {
            unset($datos['dominios_outlook']);
        }

        foreach ($datos as $clave => $valor) {
            Ajuste::updateOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
        }

        Contenido::olvidar();
        Bitacora::dashboard('ajustes.guardados', 'Se actualizaron los parámetros del juego', $datos);

        return back()->with('exito', 'Parámetros actualizados.');
    }

    /* ---------------- Audio de las pantallas ---------------- */

    /** Pantallas con audio propio, cargado en Parámetros: clave del ajuste => nombre. */
    public const PANTALLAS_CON_AUDIO = [
        'consola' => 'consola de recuperación',
        'hub' => 'centro de mando',
    ];

    /** MP3 que suena en bucle en la pantalla (consola o centro de mando). */
    public function guardarAudio(Request $peticion, string $pantalla): RedirectResponse
    {
        abort_unless(isset(self::PANTALLAS_CON_AUDIO[$pantalla]), 404);

        $campo = "audio_{$pantalla}";
        $peticion->validate([$campo => [...Audio::reglas(), 'required']], Audio::mensajes($campo));

        $ruta = Audio::guardar($peticion->file($campo), $pantalla, ajuste($campo));
        Ajuste::updateOrCreate(['clave' => $campo], ['valor' => $ruta]);

        Contenido::olvidar();
        Bitacora::dashboard('ajustes.audio', 'Se cargó el audio del '.self::PANTALLAS_CON_AUDIO[$pantalla], ['audio' => $ruta]);

        return back()->with('exito', 'Audio del '.self::PANTALLAS_CON_AUDIO[$pantalla].' actualizado.');
    }

    public function quitarAudio(string $pantalla): RedirectResponse
    {
        abort_unless(isset(self::PANTALLAS_CON_AUDIO[$pantalla]), 404);

        Audio::borrar(ajuste("audio_{$pantalla}"));
        Ajuste::where('clave', "audio_{$pantalla}")->delete();

        Contenido::olvidar();
        Bitacora::dashboard('ajustes.audio', 'Se quitó el audio del '.self::PANTALLAS_CON_AUDIO[$pantalla]);

        return back()->with('exito', 'Se quitó el audio del '.self::PANTALLAS_CON_AUDIO[$pantalla].'.');
    }
}
