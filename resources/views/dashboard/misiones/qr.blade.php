{{-- Hoja imprimible con el QR de cada estación. Página propia, sin el
     layout del dashboard, para que se imprima limpia en blanco. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR de las estaciones — {{ ajuste('juego_titulo', 'Ciberamenaza Inminente') }}</title>
    @vite(['resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #fff; color: #0b1220; font-family: system-ui, sans-serif; }
        .barra { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
        .barra h1 { margin: 0; font-size: 20px; }
        .barra p { margin: 4px 0 0; font-size: 13px; color: #4b5563; }
        .barra button { padding: 10px 18px; border: 0; border-radius: 10px; background: #0b1220; color: #fff; font-weight: 700; cursor: pointer; }
        .rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
        .tarjeta { border: 2px dashed #94a3b8; border-radius: 16px; padding: 20px; text-align: center; break-inside: avoid; }
        .tarjeta canvas { width: 220px; height: 220px; }
        .orden { font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #64748b; }
        .titulo { margin: 6px 0 12px; font-size: 18px; font-weight: 900; }
        .pie { margin-top: 10px; font-size: 11px; color: #64748b; }
        .vacio { padding: 40px; text-align: center; color: #64748b; }
        @media print {
            body { padding: 0; }
            .barra { display: none; }
            .rejilla { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="barra">
        <div>
            <h1>QR de las estaciones</h1>
            <p>Ubica cada QR en la estación de su misión. Escanearlo activa la misión y arranca el cronómetro.</p>
        </div>
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    @if ($misiones->isEmpty())
        <p class="vacio">No hay misiones jugables.</p>
    @else
        <div class="rejilla">
            @foreach ($misiones as $mision)
                <div class="tarjeta">
                    <p class="orden">Misión {{ $loop->iteration }}</p>
                    <p class="titulo">{{ $mision->titulo }}</p>
                    <canvas data-qr="{{ $mision->urlActivacion() }}" width="220" height="220"></canvas>
                    <p class="pie">Escanéalo con la app del juego o con la cámara de tu celular</p>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
