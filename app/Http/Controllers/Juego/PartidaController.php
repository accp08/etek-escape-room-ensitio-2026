<?php

namespace App\Http\Controllers\Juego;

use App\Http\Controllers\Controller;
use App\Models\Mision;
use App\Models\PartidaMision;
use App\Models\PasoBriefing;
use App\Services\Juego;
use App\Support\Audio;
use App\Support\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PartidaController extends Controller
{
    public function __construct(private Juego $juego) {}

    private function participante(Request $p)
    {
        return $p->attributes->get('participante');
    }

    private function partida(Request $p)
    {
        return $this->juego->partidaDe($this->participante($p));
    }

    /** Avance de la misión si se puede jugar ahora; null si no (se vuelve al hub). */
    private function avanceAbierto(Request $peticion, Mision $mision): ?PartidaMision
    {
        $avance = $this->juego->avanceDe($this->partida($peticion), $mision);

        if (! $mision->jugable || ! $avance) {
            return null;
        }

        $this->juego->cerrarVencidas($avance->partida);
        $avance->refresh();

        return $avance->abierta() ? $avance : null;
    }

    /* ---------------------------------------------------------------- */

    public function instrucciones()
    {
        return view('game.instrucciones', [
            'pasos' => PasoBriefing::orderBy('orden')->get(),
        ]);
    }

    public function hub(Request $peticion)
    {
        $partida = $this->partida($peticion);
        $tablero = $this->juego->tablero($partida);

        return view('game.hub', [
            'participante' => $this->participante($peticion),
            'partida' => $partida->refresh(),
            'tablero' => $tablero,
            'jugables' => $tablero->filter(fn ($f) => $f['mision']->jugable),
            'codigo' => $this->juego->codigoObtenido($partida),
            'pasos' => PasoBriefing::orderBy('orden')->get(),   // ventana de instrucciones
            'audio' => Audio::url(ajuste('audio_hub')),   // se carga en Parámetros
        ]);
    }

    /**
     * Antesala de la misión: pide escanear el QR de la estación. Si ya lo
     * escaneó, el cronómetro corre y va directo a la ventana del código.
     */
    public function mision(Request $peticion, string $slug)
    {
        $mision = Mision::where('slug', $slug)->firstOrFail();
        $avance = $this->avanceAbierto($peticion, $mision);

        if (! $avance) {
            return redirect()->route('hub');
        }

        return $avance->iniciada_en
            ? redirect()->route('mision.jugar', $mision->slug)
            : view('missions.escanear', compact('mision', 'avance'));
    }

    /**
     * Destino del QR de la estación. Leerlo —con el lector de la app o con la
     * cámara del celular— activa la misión y arranca su cronómetro.
     */
    public function activar(Request $peticion, string $slug, string $token): RedirectResponse
    {
        $mision = Mision::where('slug', $slug)->firstOrFail();

        if (! $mision->tokenQrCoincide($token)) {
            Bitacora::juego('mision.qr_invalido', "QR no válido para «{$mision->titulo}»", ['mision' => $mision->slug], 'aviso');

            return redirect()->route('mision', $mision->slug)->withErrors([
                'qr' => 'Este código QR no es válido para la misión. Pide ayuda al equipo organizador.',
            ]);
        }

        $avance = $this->avanceAbierto($peticion, $mision);

        if (! $avance) {
            return redirect()->route('hub');
        }

        if (! $avance->iniciada_en) {
            $avance = $this->juego->comenzar($avance);

            Bitacora::juego('mision.activada', "Escaneó el QR de «{$mision->titulo}»", [
                'mision' => $mision->slug,
                'intento' => $avance->intentos_usados + 1,
            ]);
        }

        return redirect()->route('mision.jugar', $mision->slug);
    }

    /**
     * Ventana del código. Solo se llega tras escanear el QR: el cronómetro
     * nunca arranca aquí.
     */
    public function jugar(Request $peticion, string $slug)
    {
        $mision = Mision::where('slug', $slug)->firstOrFail();
        $avance = $this->avanceAbierto($peticion, $mision);

        if (! $avance) {
            return redirect()->route('hub');
        }

        if (! $avance->iniciada_en) {
            return redirect()->route('mision', $mision->slug);
        }

        return view('missions.codigo', compact('mision', 'avance'));
    }

    public function responder(Request $peticion, string $slug): RedirectResponse
    {
        $mision = Mision::where('slug', $slug)->firstOrFail();
        $avance = $this->juego->avanceDe($this->partida($peticion), $mision);

        if (! $mision->jugable || ! $avance || ! $avance->abierta()) {
            return redirect()->route('hub');
        }

        // Sin escanear el QR no hay cronómetro y no se acepta ningún código.
        if (! $avance->iniciada_en) {
            return redirect()->route('mision', $mision->slug);
        }

        $peticion->validate(['codigo' => ['nullable', 'string', 'max:60']]);

        // Un envío vacío con tiempo disponible no consume la oportunidad.
        // Si el tiempo venció, el cronómetro envía el formulario vacío y se cierra.
        if (blank($peticion->input('codigo')) && ! $avance->sinTiempo()) {
            return back()->withErrors(['codigo' => 'Escribe el código que obtuviste en la estación.']);
        }

        $juicio = $this->juego->responder($avance, $peticion->input('codigo'));

        Bitacora::juego(
            'mision.'.$juicio['resultado'],
            "{$mision->titulo}: {$juicio['resultado']} ({$juicio['aciertos']}/{$juicio['posibles']})",
            ['mision' => $mision->slug] + $juicio,
            $juicio['resultado'] === 'superada' ? 'info' : 'aviso',
        );

        return redirect()
            ->route('mision.resultado', $mision->slug)
            ->with('juicio', $juicio);
    }

    public function resultado(Request $peticion, string $slug)
    {
        $mision = Mision::where('slug', $slug)->firstOrFail();
        $avance = $this->juego->avanceDe($this->partida($peticion), $mision);

        if (! $avance) {
            return redirect()->route('hub');
        }

        $juicio = session('juicio');

        // Sin resultado en sesión (por ejemplo al recargar) se deduce del avance.
        if (! $juicio) {
            $juicio = [
                'resultado' => $avance->superada() ? 'superada' : ($avance->fallada() ? 'fallada' : 'reintento'),
                'aciertos' => $avance->aciertos,
                'posibles' => $avance->aciertos_posibles,
                'porcentaje' => $avance->aciertos_posibles > 0
                    ? round($avance->aciertos * 100 / $avance->aciertos_posibles, 2)
                    : 0,
            ];
        }

        $fragmento = $avance->superada()
            ? $this->participante($peticion)->asegurarCodigos()->get($mision->id)
            : null;

        return view('missions.resultado', compact('mision', 'avance', 'juicio', 'fragmento'));
    }

    /* ---------------------------------------------------------------- */

    /**
     * Código de recuperación en el celular. Aquí solo se muestra: se digita en
     * la consola que se proyecta en la sala (ConsolaController).
     */
    public function final(Request $peticion)
    {
        $partida = $this->partida($peticion);

        if ($partida->codigoIngresado()) {
            return redirect()->route('victoria');
        }

        $completo = $this->juego->todasLasMisionesSuperadas($partida);

        return view('game.final', [
            'partida' => $partida,
            'tablero' => $this->juego->tablero($partida),
            'obtenido' => $this->juego->codigoObtenido($partida),
            'completo' => $completo,
        ]);
    }

    /** Latido del celular: mantiene al participante «en línea» mientras la pantalla está abierta. */
    public function presencia(): Response
    {
        return response()->noContent();
    }

    /** El celular pregunta si ya se digitó el código en la consola, para pasar a la victoria. */
    public function estadoFinal(Request $peticion): JsonResponse
    {
        return response()->json([
            'ingresado' => $this->partida($peticion)->refresh()->codigoIngresado(),
        ]);
    }

    public function victoria(Request $peticion)
    {
        $partida = $this->partida($peticion);

        // Con todas las misiones superadas, la victoria llega al digitar el código en la consola.
        if (! $partida->codigoIngresado() && $this->juego->todasLasMisionesSuperadas($partida)) {
            return redirect()->route('final');
        }

        return view('game.victoria', [
            'partida' => $partida,
            'tablero' => $this->juego->tablero($partida),
            'codigo' => $this->juego->codigoObtenido($partida),
        ]);
    }

    public function ranking(Request $peticion)
    {
        return view('game.ranking', [
            'ranking' => $this->juego->ranking(),
            'partida' => $this->partida($peticion),
        ]);
    }

    public function certificado(Request $peticion)
    {
        $partida = $this->partida($peticion);

        if (! $partida->certificado_visto_en) {
            $partida->update(['certificado_visto_en' => now()]);
            Bitacora::juego('certificado.visto', 'Abrió su certificado');
        }

        $puesto = $this->juego->puestoDe($partida);

        return view('game.certificado', [
            'participante' => $this->participante($peticion),
            'partida' => $partida,
            'tablero' => $this->juego->tablero($partida),
            'codigo' => $this->juego->codigoObtenido($partida),
            'puesto' => $puesto,
        ]);
    }

    /**
     * El participante pulsó «Descargar certificado». La descarga en sí la hace
     * el navegador (window.print), así que esto es lo último que el servidor
     * alcanza a ver: si luego cancela el diálogo, ya no es observable.
     */
    public function certificadoDescargado(Request $peticion)
    {
        $partida = $this->partida($peticion);

        $partida->update([
            'certificado_descargado_en' => now(),
            'certificado_descargas' => $partida->certificado_descargas + 1,
        ]);

        Bitacora::juego('certificado.descargado', 'Descargó su certificado', [
            'descargas' => $partida->certificado_descargas,
        ]);

        return response()->noContent();
    }
}
