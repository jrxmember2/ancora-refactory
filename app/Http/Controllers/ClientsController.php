<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientsController extends Controller
{
    public function index(): View
    {
        return view('pages.clientes.index', [
            'title' => 'Clientes',
            'entityCounts' => [
                'total' => ClientEntity::query()->count(),
                'avulsos_total' => ClientEntity::query()->where('profile_scope', 'avulso')->count(),
            ],
            'condominiumCounts' => [
                'total' => ClientCondominium::query()->count(),
                'with_blocks_total' => ClientCondominium::query()->where('has_blocks', 1)->count(),
            ],
            'unitCounts' => [
                'total' => ClientUnit::query()->count(),
                'rented_total' => ClientUnit::query()->whereNotNull('tenant_entity_id')->count(),
            ],
            'recentEntities' => ClientEntity::query()->latest('id')->limit(5)->get(),
            'recentCondominiums' => ClientCondominium::query()->latest('id')->limit(5)->get(),
        ]);
    }

    public function avulsos(Request $request): View
    {
        $query = ClientEntity::query()->where('profile_scope', 'avulso')->orderByDesc('id');
        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($sub) use ($term) {
                $sub->where('display_name', 'like', "%{$term}%")
                    ->orWhere('legal_name', 'like', "%{$term}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$term}%");
            });
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type')->toString());
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }

        return view('pages.clientes.avulsos', [
            'title' => 'Clientes avulsos',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
        ]);
    }

    public function contatos(Request $request): View
    {
        $items = ClientEntity::query()->where('profile_scope', 'contato')->orderByDesc('id')->paginate(15)->withQueryString();
        return view('pages.clientes.contatos', ['title' => 'Contatos', 'items' => $items, 'filters' => $request->all()]);
    }

    public function condominios(Request $request): View
    {
        $items = ClientCondominium::query()->orderByDesc('id')->paginate(15)->withQueryString();
        return view('pages.clientes.condominios', ['title' => 'Condomínios', 'items' => $items, 'filters' => $request->all()]);
    }

    public function unidades(Request $request): View
    {
        $items = DB::table('client_units as u')
            ->leftJoin('client_condominiums as c', 'c.id', '=', 'u.condominium_id')
            ->leftJoin('client_entities as owner', 'owner.id', '=', 'u.owner_entity_id')
            ->leftJoin('client_entities as tenant', 'tenant.id', '=', 'u.tenant_entity_id')
            ->select('u.*', 'c.name as condominium_name', 'owner.display_name as owner_name', 'tenant.display_name as tenant_name')
            ->orderByDesc('u.id')
            ->paginate(15)
            ->withQueryString();

        return view('pages.clientes.unidades', ['title' => 'Unidades', 'items' => $items, 'filters' => $request->all()]);
    }
}
