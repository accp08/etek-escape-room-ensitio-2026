<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Registro;
use App\Services\Estadisticas;
use App\Services\Juego;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    /** Dashboard general. */
    public function index(Estadisticas $estadisticas, Juego $juego)
    {
        return view('dashboard.panel', [
            'resumen' => $estadisticas->resumen(),
            'porMision' => $estadisticas->desempenoPorMision(),
            'porDia' => $estadisticas->partidasPorDia(),
            'actividad' => $estadisticas->actividadReciente(),
            'pulso' => $estadisticas->pulsoBitacora(),
            'ranking' => $juego->ranking(5),
            'enLinea' => $estadisticas->jugadoresEnLinea(),
        ]);
    }

    /** Fragmento «Jugadores en línea», que el dashboard vuelve a pedir cada pocos segundos. */
    public function enLinea(Estadisticas $estadisticas)
    {
        return view('dashboard.en-linea', ['enLinea' => $estadisticas->jugadoresEnLinea()]);
    }

    /** Bitácora completa, con filtros. */
    public function registros(Request $peticion)
    {
        $consulta = Registro::with(['usuario', 'participante'])->latest('id');

        if ($canal = $peticion->query('canal')) {
            $consulta->where('canal', $canal);
        }

        if ($nivel = $peticion->query('nivel')) {
            $consulta->where('nivel', $nivel);
        }

        if ($busqueda = trim((string) $peticion->query('q'))) {
            $consulta->where(function ($c) use ($busqueda) {
                $c->where('descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('tipo', 'like', "%{$busqueda}%");
            });
        }

        return view('dashboard.registros', [
            'registros' => $consulta->paginate(40)->withQueryString(),
            'canal' => $canal,
            'nivel' => $nivel,
            'busqueda' => $busqueda,
            'totales' => [
                'todos' => Registro::count(),
                'juego' => Registro::where('canal', 'juego')->count(),
                'panel' => Registro::where('canal', 'dashboard')->count(),
                'alerta' => Registro::where('nivel', 'alerta')->count(),
            ],
        ]);
    }
}
