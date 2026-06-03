<?php

namespace App\Http\Controllers;

use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarProviders;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarConnectionController extends Controller
{
    public function __construct(private readonly CalendarProviders $providers)
    {
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $driver = $this->providers->get($provider);
        if (!$driver || !$driver->isConfigured()) {
            return redirect()->route('agenda.calendar')->with('error', 'Integracao de calendario nao configurada.');
        }

        $state = Str::random(40);
        $request->session()->put('calendar_oauth_state', $state);

        return redirect()->away($driver->authorizationUrl($state, $this->redirectUri($provider)));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $driver = $this->providers->get($provider);
        if (!$driver || !$driver->isConfigured()) {
            return redirect()->route('agenda.calendar')->with('error', 'Integracao de calendario nao configurada.');
        }

        if ($request->filled('error')) {
            return redirect()->route('agenda.calendar')->with('error', 'Conexao cancelada ou negada pelo provedor.');
        }

        $state = (string) $request->query('state', '');
        $expected = (string) $request->session()->pull('calendar_oauth_state', '');
        if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
            return redirect()->route('agenda.calendar')->with('error', 'Falha de seguranca na conexao (state invalido). Tente novamente.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('agenda.calendar')->with('error', 'Codigo de autorizacao ausente.');
        }

        try {
            $tokens = $driver->exchangeCode($code, $this->redirectUri($provider));
        } catch (\Throwable $e) {
            return redirect()->route('agenda.calendar')->with('error', 'Nao foi possivel concluir a conexao: ' . $e->getMessage());
        }

        $attributes = [
            'account_email' => $tokens['account_email'] ?? null,
            'access_token' => (string) ($tokens['access_token'] ?? ''),
            'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600) - 60),
            'scopes' => $tokens['scope'] ?? null,
            'is_active' => true,
        ];

        // Preserva o refresh token anterior quando o provedor nao devolve um novo.
        if (!empty($tokens['refresh_token'])) {
            $attributes['refresh_token'] = (string) $tokens['refresh_token'];
        }

        CalendarConnection::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            $attributes
        );

        return redirect()->route('agenda.calendar')->with('success', $driver->label() . ' conectado com sucesso.');
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        CalendarConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();

        return redirect()->route('agenda.calendar')->with('success', 'Integracao removida.');
    }

    private function redirectUri(string $provider): string
    {
        return route('agenda.calendar.callback', ['provider' => $provider]);
    }
}
