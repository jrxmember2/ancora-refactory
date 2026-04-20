<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientPortalUserController extends Controller
{
    public function index(Request $request): View
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

        return view('pages.clientes.portal.users', [
            'title' => 'Usuarios do portal',
            'items' => $query->latest('id')->paginate(15)->withQueryString(),
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
            'portal_role' => ['required', 'string', 'max:40'],
            'client_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'client_condominium_ids' => ['nullable', 'array'],
            'client_condominium_ids.*' => ['integer', 'distinct', 'exists:client_condominiums,id'],
            'password' => [$current ? 'nullable' : 'required', 'string', 'min:8'],
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

        return [
            [
                'name' => $validated['name'],
                'login_key' => trim($validated['login_key']),
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
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
            ],
            $condominiumIds,
        ];
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
