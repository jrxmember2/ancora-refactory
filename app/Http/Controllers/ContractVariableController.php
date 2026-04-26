<?php

namespace App\Http\Controllers;

use App\Models\ContractVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractVariableController extends Controller
{
    public function index(): View
    {
        return view('pages.contratos.variables.index', [
            'title' => 'Variáveis disponíveis',
            'items' => ContractVariable::query()->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function update(Request $request, ContractVariable $variable): RedirectResponse
    {
        $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variable->update([
            'label' => trim((string) $request->input('label')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'source' => trim((string) $request->input('source', '')) ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('contratos.variables.index')->with('success', 'Variável atualizada com sucesso.');
    }
}
