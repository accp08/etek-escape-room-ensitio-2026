<?php

namespace App\Http\Controllers\Juego;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ParticipanteAutenticado;
use App\Models\Ajuste;
use App\Models\Participante;
use App\Services\Juego;
use App\Support\Bitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccesoController extends Controller
{
    public function formulario(Request $peticion)
    {
        if ($peticion->session()->has(ParticipanteAutenticado::CLAVE_SESION)) {
            return redirect()->route('hub');
        }

        return view('auth.login', [
            'metodoAcceso' => Ajuste::where('clave', 'metodo_acceso')->value('valor') ?? 'correo',
        ]);
    }

    public function iniciarOutlook(Request $peticion): RedirectResponse
    {
        abort_unless((Ajuste::where('clave', 'metodo_acceso')->value('valor') ?? 'correo') === 'outlook', 404);
        abort_unless($this->outlookConfigurado(), 503, 'El acceso con Outlook no está configurado.');

        $estado = Str::random(64);
        $peticion->session()->put('outlook_estado', $estado);
        $tenant = rawurlencode((string) config('services.microsoft.tenant_id'));
        $parametros = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => route('acceso.outlook.retorno'),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $estado,
        ]);

        return redirect()->away("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$parametros}");
    }

    public function retornoOutlook(Request $peticion, Juego $juego): RedirectResponse
    {
        $estadoEsperado = (string) $peticion->session()->pull('outlook_estado', '');
        abort_unless($estadoEsperado !== '' && hash_equals($estadoEsperado, (string) $peticion->query('state')), 403);

        if ($peticion->filled('error') || ! $peticion->filled('code') || ! $this->outlookConfigurado()) {
            return redirect()->route('login')->withErrors(['correo' => 'No fue posible completar el acceso con Microsoft.']);
        }

        try {
            $tenant = rawurlencode((string) config('services.microsoft.tenant_id'));
            $token = Http::asForm()->connectTimeout(3)->timeout(8)->post(
                "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
                [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'grant_type' => 'authorization_code',
                    'code' => $peticion->query('code'),
                    'redirect_uri' => route('acceso.outlook.retorno'),
                    'scope' => 'openid profile email User.Read',
                ]
            )->throw()->json('access_token');

            $perfil = Http::withToken($token)->connectTimeout(3)->timeout(8)
                ->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'displayName,mail,userPrincipalName'])
                ->throw()->json();
        } catch (\Throwable $excepcion) {
            Log::warning('Falló la autenticación del participante con Microsoft.', ['exception' => $excepcion::class]);

            return redirect()->route('login')->withErrors(['correo' => 'No fue posible validar la cuenta con Microsoft. Inténtalo de nuevo.']);
        }

        $correo = mb_strtolower(trim((string) (($perfil['mail'] ?? null) ?: ($perfil['userPrincipalName'] ?? ''))));
        $dominio = mb_strtolower((string) str($correo)->afterLast('@'));
        $dominiosPermitidos = preg_split('/[\s,;]+/', (string) Ajuste::where('clave', 'dominios_outlook')->value('valor'), -1, PREG_SPLIT_NO_EMPTY);
        $dominiosPermitidos = array_map(fn (string $permitido): string => ltrim(mb_strtolower($permitido), '@'), $dominiosPermitidos);

        if (! filter_var($correo, FILTER_VALIDATE_EMAIL) || ! in_array($dominio, $dominiosPermitidos, true)) {
            return redirect()->route('login')->withErrors(['correo' => 'Tu cuenta Microsoft no pertenece a un dominio autorizado.']);
        }

        $participante = Participante::firstOrCreate(
            ['correo' => $correo],
            [
                'nombre' => trim((string) ($perfil['displayName'] ?? '')) ?: str($correo)->before('@')->toString(),
                'documento' => 'OUTLOOK-'.strtoupper(substr(hash('sha256', $correo), 0, 32)),
                'codigo_acceso' => Participante::generarCodigo(),
                'activo' => true,
            ]
        );

        if (! $participante->activo) {
            $participante->update(['activo' => true]);
        }

        return $this->iniciarPartida($peticion, $juego, $participante);
    }

    public function entrar(Request $peticion, Juego $juego): RedirectResponse
    {
        abort_unless((Ajuste::where('clave', 'metodo_acceso')->value('valor') ?? 'correo') === 'correo', 404);

        // Usuario: correo. Contraseña: número de cédula.
        $datos = $peticion->validate([
            'correo' => ['required', 'string', 'max:160'],
            'documento' => ['required', 'string', 'max:40'],
        ], [], [
            'correo' => 'correo',
            'documento' => 'contraseña',
        ]);

        $participante = Participante::where('activo', true)
            ->where('correo', mb_strtolower(trim($datos['correo'])))
            ->first();

        if (! $participante || ! $participante->documentoCoincide($datos['documento'])) {
            Bitacora::juego('acceso.fallido', 'Intento de acceso fallido', [
                'correo' => $datos['correo'],
            ], 'aviso');

            throw ValidationException::withMessages([
                'correo' => 'El correo o la contraseña no son correctos. Recuerda: la contraseña es tu número de cédula.',
            ]);
        }

        // Si ya tenía sesión en otro dispositivo, esa queda cerrada.
        ParticipanteAutenticado::iniciar($peticion, $participante);

        // La partida se crea (o se retoma) al entrar.
        $partida = $juego->partidaDe($participante);

        Bitacora::juego('acceso', "{$participante->nombre} entró al juego", [
            'partida_id' => $partida->id,
        ]);

        // Si ya terminó, solo le queda el certificado, el video y el ranking.
        if ($partida->codigoIngresado()) {
            $peticion->session()->forget('url.intended');

            return redirect()->route('certificado');
        }

        // Si llegó escaneando el QR de una estación, vuelve a esa misión. Solo se
        // honran URLs de misión: la misma clave de sesión la usa el dashboard.
        $pedida = $peticion->session()->pull('url.intended');

        if ($pedida && str_contains((string) parse_url($pedida, PHP_URL_PATH), '/mision/')) {
            return redirect()->to($pedida);
        }

        return redirect()->route(
            $partida->misiones()->where('estado', '!=', 'pendiente')->exists() ? 'hub' : 'instrucciones'
        );
    }

    public function salir(Request $peticion): RedirectResponse
    {
        Bitacora::juego('salida', 'El participante cerró su sesión');

        ParticipanteAutenticado::cerrar($peticion);
        $peticion->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function iniciarPartida(Request $peticion, Juego $juego, Participante $participante): RedirectResponse
    {
        ParticipanteAutenticado::iniciar($peticion, $participante);
        $partida = $juego->partidaDe($participante);

        Bitacora::juego('acceso', "{$participante->nombre} entró al juego", ['partida_id' => $partida->id]);

        if ($partida->codigoIngresado()) {
            $peticion->session()->forget('url.intended');

            return redirect()->route('certificado');
        }

        $pedida = $peticion->session()->pull('url.intended');

        if ($pedida && str_contains((string) parse_url($pedida, PHP_URL_PATH), '/mision/')) {
            return redirect()->to($pedida);
        }

        return redirect()->route(
            $partida->misiones()->where('estado', '!=', 'pendiente')->exists() ? 'hub' : 'instrucciones'
        );
    }

    private function outlookConfigurado(): bool
    {
        return filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled(config('services.microsoft.tenant_id'));
    }
}
