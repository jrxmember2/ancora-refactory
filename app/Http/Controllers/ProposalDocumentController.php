<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Models\ProposalDocument;
use App\Models\ProposalDocumentOption;
use App\Models\ProposalTemplate;
use App\Services\ProposalRenderService;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProposalDocumentController extends Controller
{
    public function edit(Proposal $proposta): View|RedirectResponse
    {
        $document = ProposalDocument::query()->where('proposta_id', $proposta->id)->first();
        return view('pages.propostas.documentos.edit', [
            'title' => 'Documento Premium',
            'proposta' => $proposta,
            'document' => $document,
            'options' => $document ? ProposalDocumentOption::query()->where('proposal_document_id', $document->id)->orderBy('sort_order')->get() : collect(),
            'templates' => ProposalTemplate::active()->get(),
        ]);
    }

    public function save(Request $request, Proposal $proposta): RedirectResponse
    {
        $request->validate([
            'template_id' => ['required', 'integer'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'client_display_name' => ['nullable', 'string', 'max:255'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $userId = AncoraAuth::user($request)?->id ?? 1;
        $existing = ProposalDocument::query()->where('proposta_id', $proposta->id)->first();

        $payload = [
            'proposta_id' => $proposta->id,
            'template_id' => (int) $request->integer('template_id'),
            'document_title' => trim((string) $request->input('document_title', 'Proposta de Honorários')),
            'proposal_kind' => trim((string) $request->input('proposal_kind', '')) ?: null,
            'client_display_name' => trim((string) $request->input('client_display_name', $proposta->client_name)),
            'attention_to' => trim((string) $request->input('attention_to', $proposta->requester_name)) ?: null,
            'attention_role' => trim((string) $request->input('attention_role', '')) ?: null,
            'cover_subtitle' => trim((string) $request->input('cover_subtitle', '')) ?: null,
            'intro_context' => null,
            'scope_intro' => trim((string) $request->input('scope_intro', '')) ?: null,
            'closing_message' => trim((string) $request->input('closing_message', '')) ?: null,
            'validity_days' => min(365, max(1, (int) $request->integer('validity_days', 30))),
            'show_institutional' => $request->boolean('show_institutional'),
            'show_services' => $request->boolean('show_services'),
            'show_extra_services' => $request->boolean('show_extra_services'),
            'show_contacts_page' => $request->boolean('show_contacts_page'),
            'cover_image_path' => trim((string) $request->input('cover_image_path', '')) ?: null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        DB::transaction(function () use ($existing, $payload, $request) {
            if ($existing) {
                $existing->update($payload);
                $document = $existing;
            } else {
                $document = ProposalDocument::query()->create($payload);
            }

            ProposalDocumentOption::query()->where('proposal_document_id', $document->id)->delete();
            foreach ((array) $request->input('options', []) as $index => $option) {
                if (trim((string) ($option['title'] ?? '')) === '') {
                    continue;
                }
                ProposalDocumentOption::query()->create([
                    'proposal_document_id' => $document->id,
                    'sort_order' => (int) $index,
                    'title' => trim((string) ($option['title'] ?? '')),
                    'scope_title' => trim((string) ($option['scope_title'] ?? '')) ?: null,
                    'scope_html' => trim((string) ($option['scope_html'] ?? '')) ?: null,
                    'fee_label' => trim((string) ($option['fee_label'] ?? '')) ?: null,
                    'amount_value' => \App\Services\ProposalService::moneyToDb($option['amount_value'] ?? null),
                    'amount_text' => trim((string) ($option['amount_text'] ?? '')) ?: null,
                    'payment_terms' => trim((string) ($option['payment_terms'] ?? '')) ?: null,
                    'is_recommended' => !empty($option['is_recommended']),
                ]);
            }
        });

        return redirect()->route('propostas.document.edit', $proposta)->with('success', 'Documento premium salvo com sucesso.');
    }

    public function preview(Proposal $proposta): View|RedirectResponse
    {
        $render = ProposalRenderService::buildByPropostaId((int) $proposta->id);
        if (!$render) {
            return redirect()->route('propostas.document.edit', $proposta)->with('error', 'Salve o Documento Premium antes de visualizar o preview.');
        }
        return view('pages.propostas.documentos.preview', ['title' => 'Preview da Proposta Premium', 'render' => $render, 'proposta' => $proposta]);
    }

    public function print(Proposal $proposta): View|RedirectResponse
    {
        $render = ProposalRenderService::buildByPropostaId((int) $proposta->id);
        if (!$render) {
            return redirect()->route('propostas.document.edit', $proposta)->with('error', 'Salve o Documento Premium antes de gerar o PDF.');
        }
        return view('pages.propostas.documentos.print', ['render' => $render, 'proposta' => $proposta]);
    }
}
