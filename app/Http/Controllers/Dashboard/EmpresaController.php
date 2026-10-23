<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Bitacora;
use App\Support\Marca;
use App\Support\RecorteLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    public function editar()
    {
        return view('dashboard.empresa', [
            'empresa' => Marca::empresaActiva(),
        ]);
    }

    public function actualizar(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'logo' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:2048', // 2 MB
            ],
        ], [
            'logo.mimes' => 'El logo debe ser PNG, JPG, WEBP o SVG.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
        ], [
            'nombre' => 'nombre de la empresa',
        ]);

        $empresa = Marca::empresaActiva() ?? new Empresa(['activa' => true]);
        $empresa->nombre = $datos['nombre'];

        if ($peticion->hasFile('logo')) {
            $anterior = $empresa->logo_ruta;

            $extension = $peticion->file('logo')->getClientOriginalExtension();
            $nombreArchivo = Str::slug($datos['nombre']).'-'.Str::random(8).'.'.$extension;

            $empresa->logo_ruta = $peticion->file('logo')
                ->storeAs('marca', $nombreArchivo, 'public');

            // Sin el margen vacío, el logo llena la placa blanca.
            RecorteLogo::recortar($empresa->logo_ruta);

            // Se borra el archivo reemplazado para no dejar basura en el disco.
            if ($anterior && $anterior !== $empresa->logo_ruta) {
                Storage::disk('public')->delete($anterior);
            }
        }

        $empresa->activa = true;
        $empresa->save();

        Marca::olvidar();
        Bitacora::dashboard('empresa.actualizada', "Marca actualizada a «{$empresa->nombre}»", [
            'logo' => $empresa->logo_ruta,
        ]);

        return redirect()
            ->route('dashboard.empresa')
            ->with('exito', 'La marca de la empresa quedó actualizada en todo el juego.');
    }

    public function quitarLogo(): RedirectResponse
    {
        $empresa = Marca::empresaActiva();

        if ($empresa?->logo_ruta) {
            Storage::disk('public')->delete($empresa->logo_ruta);
            $empresa->logo_ruta = null;
            $empresa->save();
            Marca::olvidar();
        }

        return redirect()
            ->route('dashboard.empresa')
            ->with('exito', 'Se quitó el logo. El juego vuelve al logo por defecto.');
    }
}
