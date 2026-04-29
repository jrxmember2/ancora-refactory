<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractTemplateRequest;
use App\Http\Requests\UpdateContractTemplateRequest;
use App\Models\ContractCategory;
use App\Models\ContractTemplate;
use App\Support\AncoraAuth;
use App\Support\Contracts\ContractCatalog;
use App\Support\Contracts\ContractVariableCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContractTemplateController extends Controller
{
    public function index(): View
    {
        return view('pages.contratos.templates.index', [
            'title' => 'Templates de contratos',
            'items' => ContractTemplate::query()->with('category')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('pages.contratos.templates.form', [
            'title' => 'Novo template',
            'mode' => 'create',
            'item' => null,
            'categories' => ContractCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'typeOptions' => ContractCatalog::types(),
            'orientationOptions' => ContractCatalog::pageOrientations(),
            'variableDefinitions' => ContractVariableCatalog::definitionsForTemplates(),
        ]);
    }

    public function store(StoreContractTemplateRequest $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        ContractTemplate::query()->create([
            'name' => trim((string) $request->input('name')),
            'document_type' => trim((string) $request->input('document_type')),
            'default_contract_title' => trim((string) $request->input('default_contract_title', '')) ?: trim((string) $request->input('name')),
            'category_id' => $request->input('category_id') ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'content_html' => trim((string) $request->input('content_html', '')) ?: null,
            'header_html' => trim((string) $request->input('header_html', '')) ?: null,
            'footer_html' => trim((string) $request->input('footer_html', '')) ?: null,
            'page_orientation' => $request->input('page_orientation', 'portrait'),
            'margins_json' => $this->marginsFromRequest($request),
            'available_variables_json' => array_values(array_filter((array) $request->input('available_variables', []))),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('contratos.templates.index')->with('success', 'Template salvo com sucesso.');
    }

    public function edit(ContractTemplate $template): View
    {
        return view('pages.contratos.templates.form', [
            'title' => 'Editar template',
            'mode' => 'edit',
            'item' => $template,
            'categories' => ContractCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'typeOptions' => ContractCatalog::types(),
            'orientationOptions' => ContractCatalog::pageOrientations(),
            'variableDefinitions' => ContractVariableCatalog::definitionsForTemplates(),
        ]);
    }

    public function update(UpdateContractTemplateRequest $request, ContractTemplate $template): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $template->update([
            'name' => trim((string) $request->input('name')),
            'document_type' => trim((string) $request->input('document_type')),
            'default_contract_title' => trim((string) $request->input('default_contract_title', '')) ?: trim((string) $request->input('name')),
            'category_id' => $request->input('category_id') ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'content_html' => trim((string) $request->input('content_html', '')) ?: null,
            'header_html' => trim((string) $request->input('header_html', '')) ?: null,
            'footer_html' => trim((string) $request->input('footer_html', '')) ?: null,
            'page_orientation' => $request->input('page_orientation', 'portrait'),
            'margins_json' => $this->marginsFromRequest($request),
            'available_variables_json' => array_values(array_filter((array) $request->input('available_variables', []))),
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => $user->id,
        ]);

        return redirect()->route('contratos.templates.index')->with('success', 'Template atualizado com sucesso.');
    }

    public function destroy(ContractTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('contratos.templates.index')->with('success', 'Template excluído com sucesso.');
    }

    private function marginsFromRequest(StoreContractTemplateRequest|UpdateContractTemplateRequest $request): array
    {
        return [
            'top' => (float) $request->input('margin_top', 3),
            'right' => (float) $request->input('margin_right', 2),
            'bottom' => (float) $request->input('margin_bottom', 2),
            'left' => (float) $request->input('margin_left', 3),
        ];
    }
}
