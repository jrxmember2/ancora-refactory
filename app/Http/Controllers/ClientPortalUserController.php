<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientPortalUserController extends Controller
{
    public function index(Request $request): View
    {
        $query = ClientPortalUser::query()->with(['entity', 'condominium']);

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
            'title' => 'Usuários do portal',
            'items' => $query->latest('id')->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'entities' => ClientEntity::query()->active()->get(),
            'condominiums' => ClientCondominium::query()->where('is_active', 1)->orderBy('name')->get(),
            'roles' => $this->roleLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $payload['password_hash'] = Hash::make($request->input('password'));
        $payload['must_change_password'] = $request->boolean('must_change_password', true);

        ClientPortalUser::query()->create($payload);

        return back()->with('success', 'Usuário do portal cadastrado.');
    }

    public function update(Request $request, ClientPortalUser $portalUser): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $portalUser);
        if (trim((string) $request->input('password')) !== '') {
            $payload['password_hash'] = Hash::make($request->input('password'));
            $payload['must_change_password'] = $request->boolean('must_change_password', true);
        } else {
            $payload['must_change_password'] = $request->boolean('must_change_password');
        }

        $portalUser->update($payload);

        return back()->with('success', 'Usuário do portal atualizado.');
    }

    public function destroy(ClientPortalUser $portalUser): RedirectResponse
    {
        $portalUser->delete();

        return back()->with('success', 'Usuário do portal removido.');
    }

    private function validatedPayload(Request $request, ?ClientPortalUser $current = null): array
    {
        $id = $current?->id ?: 0;
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'login_key' => ['required', 'string', 'max:80', 'unique:client_portal_users,login_key,' . $id],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'portal_role' => ['required', 'string', 'max:40'],
            'client_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'password' => [$current ? 'nullable' : 'required', 'string', 'min:8'],
        ];

        $validated = $request->validate($rules);

        return [
            'name' => $validated['name'],
            'login_key' => trim($validated['login_key']),
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'portal_role' => $validated['portal_role'],
            'client_entity_id' => $validated['client_entity_id'] ?? null,
            'client_condominium_id' => $validated['client_condominium_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'can_view_processes' => $request->boolean('can_view_processes'),
            'can_view_cobrancas' => $request->boolean('can_view_cobrancas'),
            'can_open_demands' => $request->boolean('can_open_demands'),
            'can_view_demands' => $request->boolean('can_view_demands'),
            'can_view_documents' => $request->boolean('can_view_documents'),
            'can_view_financial_summary' => $request->boolean('can_view_financial_summary'),
        ];
    }

    private function roleLabels(): array
    {
        return [
            'sindico' => 'Síndico',
            'administradora' => 'Administradora',
            'cliente_avulso' => 'Cliente avulso',
            'representante' => 'Representante',
            'somente_leitura' => 'Somente leitura',
        ];
    }
}
