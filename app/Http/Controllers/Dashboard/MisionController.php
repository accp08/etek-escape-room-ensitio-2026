<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Mision;
use App\Support\Bitacora;
use App\Support\HtmlSeguro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MisionController extends Controller
{
    public function index()
    {
        return view('dashboard.misiones.index', [
            'misiones' => Mision::orderBy('orden')->orderBy('id')->get(),
        ]);
    }

    public function editar(Mision $mision)
    {
        return view('dashboard.misiones.editar', ['mision' => $mision]);
    }

    public function actualizar(Request $peticion, Mision $mision): RedirectResponse
    {
        $datos = $peticion->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'etiqueta' => ['nullable', 'string', 'max:60'],   // opcional: vacía no se muestra
            'icono' => ['required', 'string', 'max:30'],
            'brief' => ['nullable', 'string', 'max:800'],
            'instruccion' => ['nullable', 'string', 'max:800'],
            'duracion' => ['required', 'integer', 'min:30', 'max:3600'],
            'intentos' => ['required', 'integer', 'min:0', 'max:99'],   // 0 = ilimitadas
            'orden' => ['required', 'integer', 'min:0', 'max:99'],
            'codigo_presencial' => ['nullable', 'string', 'max:40'],   // lo entrega la estación física
        ]);

        $datos['codigo_presencial'] = trim((string) ($datos['codigo_presencial'] ?? '')) ?: null;

        // La instrucción se imprime con {!! !!} en el juego: se sanea aquí.
        $datos['instruccion'] = HtmlSeguro::limpiar($datos['instruccion'] ?? '');
        $datos['etiqueta'] = trim((string) ($datos['etiqueta'] ?? ''));
        $datos['jugable'] = $peticion->boolean('jugable');
        $datos['visible'] = $peticion->boolean('visible');

        $ordenSolicitado = (int) $datos['orden'];

        DB::transaction(function () use ($mision, $datos, $ordenSolicitado): void {
            $misiones = Mision::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->reject(fn (Mision $ordenada): bool => $ordenada->id === $mision->id)
                ->values()
                ->all();

            $indice = min($ordenSolicitado, count($misiones));
            array_splice($misiones, $indice, 0, [$mision]);

            foreach ($misiones as $orden => $ordenada) {
                if ($ordenada->id === $mision->id) {
                    $datos['orden'] = $orden;
                    $ordenada->update($datos);
                } elseif ((int) $ordenada->orden !== $orden) {
                    $ordenada->update(['orden' => $orden]);
                }
            }
        });

        $mision->refresh();

        Bitacora::dashboard('mision.editada', "Se editó la misión «{$mision->titulo}»", [
            'mision' => $mision->slug,
            'jugable' => $mision->jugable,
        ]);

        return back()->with('exito', 'Misión actualizada.');
    }

    /* ---------------- QR de las estaciones ---------------- */

    /** Hoja imprimible con el QR de cada estación. */
    public function qr()
    {
        return view('dashboard.misiones.qr', [
            'misiones' => Mision::jugables()->get(),
        ]);
    }

    /** Invalida el QR impreso de la misión (por ejemplo, si se filtró una foto). */
    public function regenerarQr(Mision $mision): RedirectResponse
    {
        $mision->regenerarTokenQr();

        Bitacora::dashboard('mision.qr_regenerado', "Se regeneró el QR de «{$mision->titulo}»", [
            'mision' => $mision->slug,
        ]);

        return back()->with('exito', 'QR regenerado. El anterior ya no activa la misión: imprime o proyecta el nuevo.');
    }

    /**
     * Pantalla para proyectar en un televisor junto a la estación: el QR en
     * grande y las instrucciones de la misión. Se navega entre misiones con
     * las flechas del teclado.
     *
     * @return View|JsonResponse
     */
    public function proyectar(Request $peticion, Mision $mision)
    {
        // La pantalla consulta esto cada cierto tiempo: si el QR se regeneró,
        // lo repinta sin recargar (recargar sacaría al televisor de pantalla completa).
        if ($peticion->wantsJson()) {
            return response()->json(['qr' => $mision->urlActivacion()]);
        }

        $misiones = Mision::jugables()->get();
        $posicion = $misiones->search(fn (Mision $m) => $m->id === $mision->id);

        return view('dashboard.misiones.proyectar', [
            'mision' => $mision,
            'numero' => $posicion === false ? null : $posicion + 1,
            'total' => $misiones->count(),
            'anterior' => $posicion === false ? null : $misiones->get($posicion - 1),
            'siguiente' => $posicion === false ? null : $misiones->get($posicion + 1),
        ]);
    }
}
