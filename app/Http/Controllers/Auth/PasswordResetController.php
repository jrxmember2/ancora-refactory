<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AncoraMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('pages.auth.forgot-password', ['title' => 'Recuperar senha']);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()->active()->where('email', $email)->first();

        if ($user && AncoraMail::applySmtpSettings()) {
            $token = Str::random(64);
            Cache::put('password-reset:' . $email, password_hash($token, PASSWORD_DEFAULT), now()->addMinutes(60));
            $url = route('password.reset.form', ['token' => $token, 'email' => $email]);

            try {
                Mail::raw("Olá, {$user->name}.

Recebemos uma solicitação para redefinir sua senha no Âncora.

Acesse o link abaixo para criar uma nova senha:
{$url}

Se você não solicitou essa alteração, ignore esta mensagem.", function ($message) use ($email, $user) {
                    $message->to($email, $user->name)->subject('Redefinição de senha - Âncora');
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Se existir uma conta vinculada a esse e-mail, enviaremos as instruções de redefinição.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('pages.auth.reset-password', [
            'title' => 'Nova senha',
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $hash = Cache::get('password-reset:' . $email);

        if (!$hash || !password_verify($validated['token'], $hash)) {
            return back()->withInput($request->except('password', 'password_confirmation'))->with('error', 'O link de redefinição é inválido ou expirou.');
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return redirect()->route('login')->with('success', 'Senha redefinida com sucesso.');
        }

        $user->update([
            'password_hash' => password_hash($validated['password'], PASSWORD_DEFAULT),
        ]);

        Cache::forget('password-reset:' . $email);

        return redirect()->route('login')->with('success', 'Senha redefinida com sucesso. Faça o login com a nova senha.');
    }
}
