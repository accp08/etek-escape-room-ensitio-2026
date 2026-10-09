<?php

use App\Http\Controllers\Dashboard\ContenidoController;
use App\Http\Controllers\Dashboard\EmpresaController;
use App\Http\Controllers\Dashboard\MisionController;
use App\Http\Controllers\Dashboard\PanelController;
use App\Http\Controllers\Dashboard\ParticipanteController;
use App\Http\Controllers\Dashboard\SesionController;
use App\Http\Controllers\Juego\AccesoController;
use App\Http\Controllers\Juego\ConsolaController;
use App\Http\Controllers\Juego\PartidaController;
use App\Http\Middleware\ParticipanteAutenticado;
/*
|--------------------------------------------------------------------------
| Ciberamenaza Inminente — Juego
|--------------------------------------------------------------------------
| El participante entra con su documento y el código que le entrega el
| administrador. A partir de ahí todo el estado (cronómetro, intentos,
| aciertos) lo lleva el servidor.
*/

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathTraversalDetected;

Route::get('/', [AccesoController::class, 'formulario'])->name('login');
Route::post('/acceso', [AccesoController::class, 'entrar'])->middleware('throttle:10,1')->name('acceso');
Route::get('/acceso/outlook', [AccesoController::class, 'iniciarOutlook'])->middleware('throttle:10,1')->name('acceso.outlook');
Route::get('/acceso/outlook/retorno', [AccesoController::class, 'retornoOutlook'])->name('acceso.outlook.retorno');
Route::post('/salir', [AccesoController::class, 'salir'])->name('salir');

/*
 * Consola de recuperación: pantalla pública que se proyecta en la sala. El
 * jugador digita el código que ve en su celular; el código lo identifica.
 * Sin CSRF (bootstrap/app.php): no hay sesión que proteger y la pantalla
 * queda abierta horas.
 */
Route::get('/consola', [ConsolaController::class, 'index'])->name('consola');
Route::get('/consola/ranking', [ConsolaController::class, 'ranking'])->name('consola.ranking');
Route::post('/consola/verificar', [ConsolaController::class, 'verificar'])
    ->middleware('throttle:20,1')->name('consola.verificar');

Route::middleware(ParticipanteAutenticado::class)->group(function () {

    Route::get('/instrucciones', [PartidaController::class, 'instrucciones'])->name('instrucciones');
    Route::get('/presencia', [PartidaController::class, 'presencia'])->name('presencia');
    Route::get('/centro-de-mando', [PartidaController::class, 'hub'])->name('hub');

    Route::get('/mision/{slug}', [PartidaController::class, 'mision'])->name('mision');
    // Destino del QR de la estación (misiones en sitio): activa la misión y arranca el cronómetro.
    Route::get('/mision/{slug}/activar/{token}', [PartidaController::class, 'activar'])
        ->middleware('throttle:30,1')->name('mision.activar');
    Route::get('/mision/{slug}/jugar', [PartidaController::class, 'jugar'])->name('mision.jugar');
    Route::post('/mision/{slug}/responder', [PartidaController::class, 'responder'])->name('mision.responder');
    Route::get('/mision/{slug}/resultado', [PartidaController::class, 'resultado'])->name('mision.resultado');

    Route::get('/codigo-final', [PartidaController::class, 'final'])->name('final');
    Route::get('/codigo-final/estado', [PartidaController::class, 'estadoFinal'])->name('final.estado');

    Route::get('/amenaza-contenida', [PartidaController::class, 'victoria'])->name('victoria');
    Route::get('/ranking', [PartidaController::class, 'ranking'])->name('ranking');
    Route::get('/certificado', [PartidaController::class, 'certificado'])->name('certificado');
    Route::post('/certificado/descargado', [PartidaController::class, 'certificadoDescargado'])
        ->name('certificado.descargado');

    /*
     * El video de cierre se sirve por aquí y no como archivo estático porque el
     * servidor embebido de PHP (`artisan serve`) no responde peticiones por rangos,
     * y sin eso Safari en iOS no reproduce video ni se puede adelantar.
     * BinaryFileResponse sí las maneja.
     */
    Route::get('/video-final', function () {
        $ruta = ajuste('video_final', config('escape_room.video_final'));

        abort_if(! $ruta, 404);

        $archivo = public_path($ruta);

        abort_unless(is_file($archivo), 404);

        return response()->file($archivo, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    })->name('video.final');
});

/*
 * Archivos subidos al disco "public" (logo de la empresa, etc.). Se sirven por
 * Laravel en vez de depender del symlink public/storage, que en el hosting de
 * producción no siempre existe o no se puede seguir (PUBLIC_PATH distinto).
 */
Route::get('/archivos/{ruta}', function (string $ruta) {
    $disco = Storage::disk('public');

    try {
        abort_unless($disco->exists($ruta), 404);
    } catch (PathTraversalDetected) {
        abort(404);
    }

    return $disco->response($ruta, headers: [
        'Cache-Control' => 'public, max-age=604800',
        // Un SVG subido no puede ejecutar scripts si se abre directamente.
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('ruta', '.*')->name('archivos.publicos');

/*
|--------------------------------------------------------------------------
| Dashboard de administración
|--------------------------------------------------------------------------
| A diferencia del juego, esta parte SÍ tiene lógica: autenticación real y
| persistencia de la marca de la empresa (nombre y logo), que se refleja
| automáticamente en el login, el ranking y el certificado.
*/

Route::prefix('dashboard')->group(function () {

    // Pantalla de proyección de cada estación: pública, para dejarla abierta en
    // el televisor de la sala sin sesión. Solo muestra el QR y las instrucciones;
    // el código de la estación nunca sale en ella.
    Route::get('/misiones/{mision}/proyectar', [MisionController::class, 'proyectar'])->name('dashboard.misiones.proyectar');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [SesionController::class, 'formulario'])->name('dashboard.login');
        Route::post('/login', [SesionController::class, 'entrar'])->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function () {

        // Panel general y bitácora
        Route::get('/', [PanelController::class, 'index'])->name('dashboard');
        Route::get('/registros', [PanelController::class, 'registros'])->name('dashboard.registros');
        Route::get('/en-linea', [PanelController::class, 'enLinea'])->name('dashboard.en-linea');

        // Empresa (marca)
        Route::get('/empresa', [EmpresaController::class, 'editar'])->name('dashboard.empresa');
        Route::put('/empresa', [EmpresaController::class, 'actualizar'])->name('dashboard.empresa.actualizar');
        Route::delete('/empresa/logo', [EmpresaController::class, 'quitarLogo'])->name('dashboard.empresa.logo.quitar');

        // Misiones, sus QR y la pantalla de proyección de cada estación
        Route::get('/misiones', [MisionController::class, 'index'])->name('dashboard.misiones');
        Route::get('/misiones/qr', [MisionController::class, 'qr'])->name('dashboard.misiones.qr');
        Route::get('/misiones/{mision}', [MisionController::class, 'editar'])->name('dashboard.misiones.editar');
        Route::put('/misiones/{mision}', [MisionController::class, 'actualizar'])->name('dashboard.misiones.actualizar');
        Route::post('/misiones/{mision}/qr', [MisionController::class, 'regenerarQr'])->name('dashboard.misiones.qr.regenerar');

        // Instrucciones (briefing)
        Route::get('/briefing', [ContenidoController::class, 'briefing'])->name('dashboard.briefing');
        Route::post('/briefing', [ContenidoController::class, 'guardarPaso'])->name('dashboard.briefing.crear');
        Route::put('/briefing/{paso}', [ContenidoController::class, 'guardarPaso'])->name('dashboard.briefing.actualizar');
        Route::delete('/briefing/{paso}', [ContenidoController::class, 'borrarPaso'])->name('dashboard.briefing.borrar');

        // Textos de interfaz
        Route::get('/textos', [ContenidoController::class, 'textos'])->name('dashboard.textos');
        Route::put('/textos', [ContenidoController::class, 'guardarTextos'])->name('dashboard.textos.guardar');

        // Parámetros del juego
        Route::get('/ajustes', [ContenidoController::class, 'ajustes'])->name('dashboard.ajustes');
        Route::put('/ajustes', [ContenidoController::class, 'guardarAjustes'])->name('dashboard.ajustes.guardar');

        // Participantes
        Route::get('/participantes', [ParticipanteController::class, 'index'])->name('dashboard.participantes');
        Route::post('/participantes', [ParticipanteController::class, 'guardar'])->name('dashboard.participantes.crear');
        Route::post('/participantes/importar', [ParticipanteController::class, 'importar'])->name('dashboard.participantes.importar');
        Route::get('/participantes/plantilla', [ParticipanteController::class, 'plantilla'])->name('dashboard.participantes.plantilla');
        Route::get('/participantes/csv', [ParticipanteController::class, 'exportarParticipantes'])->name('dashboard.participantes.csv');
        Route::put('/participantes/{participante}', [ParticipanteController::class, 'guardar'])->name('dashboard.participantes.actualizar');
        Route::post('/participantes/{participante}/codigo', [ParticipanteController::class, 'nuevoCodigo'])->name('dashboard.participantes.codigo');
        Route::post('/participantes/{participante}/reiniciar', [ParticipanteController::class, 'reiniciar'])->name('dashboard.participantes.reiniciar');
        Route::delete('/participantes/{participante}', [ParticipanteController::class, 'borrar'])->name('dashboard.participantes.borrar');

        // Resultados
        Route::get('/resultados', [ParticipanteController::class, 'resultados'])->name('dashboard.resultados');
        Route::get('/resultados/csv', [ParticipanteController::class, 'exportar'])->name('dashboard.resultados.csv');

        Route::post('/salir', [SesionController::class, 'salir'])->name('dashboard.salir');
    });
});
