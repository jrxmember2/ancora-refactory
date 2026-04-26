<?php

namespace App\Http\Controllers;

use App\Models\ContractCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractCategoryController extends Controller
{
    public function index(): View
    {
        return view('pages.contratos.categories.index', [
            'title' => 'Categorias de contratos',
            'items' => ContractCategory::query()->withCount(['templates', 'contracts'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:contract_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ContractCategory::query()->create([
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('contratos.categories.index')->with('success', 'Categoria criada com sucesso.');
    }

    public function update(Request $request, ContractCategory $category): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('contract_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return redirect()->route('contratos.categories.index')->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(ContractCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('contratos.categories.index')->with('success', 'Categoria excluída com sucesso.');
    }
}
