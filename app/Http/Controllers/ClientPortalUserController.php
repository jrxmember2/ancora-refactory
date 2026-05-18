<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientPortalUser;
use App\Services\Ai\AiUsageLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientPortalUserController extends Controller
{
    public function index(Request $request, AiUsageLimiter $aiUsageLimiter): View
    {
        $query = ClientPortalUser::query()->with(['entity', 'condominium', 'condominiums']);

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('login_key', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->input('active') === '1');
        }

        $items = $query->latest('id')->paginate(15)->withQueryString();
        $items->getCollection()->transform(function (ClientPortalUser $portalUser) use ($aiUsageLimiter) {
            $portalUser->ai_usage_status = $aiUsageLimiter->statusForUser($portalUser, false);

            return $portalUser;
        });

        return view('pages.clientes.portal.users', [
            'title' => 'Usuarios do portal',
            'items' => $items,
            'filters' => $request->all(),
            'entities' => ClientEntity::query()->active()->get(),
            'condominiums' => ClientCondominium::query()->where('is_active', 1)->orderBy('name')->get(),
            'roles' => $this->roleLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$payload, $condominiumIds] = $this->validatedPayload($request);
        $payload['password_hash'] = Hash::make($request->input('password'));
        $payload['must_change_password'] = $request->boolean('must_change_password', true);

        DB::transaction(function () use ($payload, $condominiumIds) {
            $portalUser = ClientPortalUser::query()->create($payload);
            $portalUser->condominiums()->sync($condominiumIds);
        });

        return back()->with('success', 'Usuario do portal cadastrado.');
    }

    public function update(Request $request, ClientPortalUser $portalUser): RedirectResponse
    {
        [$payload, $condominiumIds] = $this->validatedPayload($request, $portalUser);
        if (trim((string) $request->input('password')) !== '') {
            $payload['password_hash'] = Hash::make($request->input('password'));
            $payload['must_change_password'] = $request->boolean('must_change_password', true);
        } else {
            $payload['must_change_password'] = $request->boolean('must_change_password');
        }

        DB::transaction(function () use ($portalUser, $payload, $condominiumIds) {
            $portalUser->update($payload);
            $portalUser->condominiums()->sync($condominiumIds);
        });

        return back()->with('success', 'Usuario do portal atualizado.');
    }

    public function destroy(ClientPortalUser $portalUser): RedirectResponse
    {
        $portalUser->delete();

        return back()->with('success', 'Usuario do portal removido.');
    }

    private function validatedPayload(Request $request, ?ClientPortalUser $current = null): array
    {
        $id = $current?->id ?: 0;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'login_key' => ['required', 'string', 'max:80', 'unique:client_portal_users,login_key,' . $id],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'portal_role' => ['required', 'string', 'max:40'],
            'client_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'client_condominium_ids' => ['nullable', 'array'],
            'client_condominium_ids.*' => ['integer', 'distinct', 'exists:client_condominiums,id'],
            'password' => [$current ? 'nullable' : 'required', 'string', 'min:8'],
            'ai_monthly_question_limit' => ['nullable', 'integer', 'min:0'],
            'ai_questions_used_current_month' => ['nullable', 'integer', 'min:0'],
            'ai_usage_reset_at' => ['nullable', 'date'],
            'ai_internal_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $condominiumIds = collect($validated['client_condominium_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($condominiumIds === [] && !empty($validated['client_condominium_id'])) {
            $condominiumIds[] = (int) $validated['client_condominium_id'];
        }

        $usedQuestions = max(0, (int) ($validated['ai_questions_used_current_month'] ?? $current?->ai_questions_used_current_month ?? 0));
        $limitInput = $validated['ai_monthly_question_limit'] ?? null;
        $resetAt = $validated['ai_usage_reset_at'] ?? null;

        if ($resetAt === null && $usedQuestions > 0) {
            $resetAt = now()->toDateString();
        }

        $avatarPath = $current?->avatar_path;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->storeAvatar($request->file('avatar'), (string) $current?->avatar_path);
        }

        return [
            [
                'name' => $validated['name'],
                'login_key' => trim($validated['login_key']),
                'email' => $this->nullableTrim($validated['email'] ?? null),
                'phone' => $this->nullableTrim($validated['phone'] ?? null),
                'birth_date' => $validated['birth_date'] ?? null,
                'avatar_path' => $avatarPath,
                'portal_role' => $validated['portal_role'],
                'client_entity_id' => $validated['client_entity_id'] ?? null,
                'client_condominium_id' => $condominiumIds[0] ?? null,
                'is_active' => $request->boolean('is_active'),
                'can_view_processes' => $request->boolean('can_view_processes'),
                'can_view_cobrancas' => $request->boolean('can_view_cobrancas'),
                'can_open_demands' => $request->boolean('can_open_demands'),
                'can_view_demands' => $request->boolean('can_view_demands'),
                'can_view_documents' => $request->boolean('can_view_documents'),
                'can_view_financial_summary' => $request->boolean('can_view_financial_summary'),
                'ai_enabled' => $request->boolean('ai_enabled'),
                'ai_monthly_question_limit' => $limitInput === null || $limitInput === '' ? null : max(0, (int) $limitInput),
                'ai_questions_used_current_month' => $usedQuestions,
                'ai_usage_reset_at' => $resetAt,
                'ai_internal_note' => filled($validated['ai_internal_note'] ?? null) ? trim((string) $validated['ai_internal_note']) : null,
            ],
            $condominiumIds,
        ];
    }

    private function storeAvatar($file, string $currentPath = ''): string
    {
        $currentPath = trim($currentPath);
        if ($currentPath !== '' && !preg_match('#^https?://#i', $currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $name = 'avatar-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.' . $extension;
        $path = 'avatars/client-portal-users/' . $name;

        Storage::disk('public')->putFileAs('avatars/client-portal-users', $file, $name);

        return $path;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function roleLabels(): array
    {
        return [
            'sindico' => 'Sindico',
            'administradora' => 'Administradora',
            'cliente_avulso' => 'Cliente avulso',
            'representante' => 'Representante',
            'somente_leitura' => 'Somente leitura',
        ];
    }
}
