<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AncoraAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        return view('pages.profile.edit', [
            'title' => 'Meus dados',
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (!empty($validated['password'] ?? '')) {
            if (empty($validated['current_password'] ?? '') || !password_verify((string) $validated['current_password'], (string) $user->password_hash)) {
                return back()->withInput()->with('error', 'Informe a senha atual corretamente para alterar sua senha.');
            }
        }

        $avatarPath = $user->avatar_path;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->storeAvatar($request->file('avatar'), (string) $user->avatar_path);
        }

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => Str::lower(trim((string) $validated['email'])),
            'avatar_path' => $avatarPath,
        ];

        if (!empty($validated['password'] ?? '')) {
            $payload['password_hash'] = password_hash((string) $validated['password'], PASSWORD_DEFAULT);
        }

        $user->update($payload);
        AncoraAuth::cacheSessionUser($request, $user->fresh(), AncoraAuth::sessionMinutes($request));

        return back()->with('success', 'Seus dados foram atualizados com sucesso.');
    }

    public function updateTheme(Request $request): JsonResponse|RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'theme_preference' => ['required', Rule::in(['light', 'dark'])],
        ]);

        $user->forceFill([
            'theme_preference' => (string) $validated['theme_preference'],
        ])->save();

        AncoraAuth::cacheSessionUser($request, $user->fresh(), AncoraAuth::sessionMinutes($request));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Tema atualizado com sucesso.');
    }

    private function storeAvatar(UploadedFile $file, string $currentPath): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'bin'));
        $name = 'avatar-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.' . $extension;
        $path = 'avatars/users/' . $name;

        Storage::disk('public')->putFileAs('avatars/users', $file, $name);

        if ($currentPath !== '' && !str_starts_with($currentPath, '/')) {
            Storage::disk('public')->delete($currentPath);
        }

        if (str_starts_with($currentPath, '/assets/uploads/users/')) {
            $old = public_path(ltrim($currentPath, '/'));
            if (is_file($old)) {
                @unlink($old);
            }
        }

        return $path;
    }
}
