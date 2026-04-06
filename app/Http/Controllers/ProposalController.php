<?php

namespace App\Http\Controllers;

use App\Models\Administradora;
use App\Models\AuditLog;
use App\Models\ClientEntity;
use App\Models\FormaEnvio;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\ProposalHistory;
use App\Models\Servico;
use App\Models\StatusRetorno;
use App\Services\ProposalDashboardService;
use App\Services\ProposalService;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProposalController extends Controller
{
    private function formDependencies(): array
    {
        $this->syncLegacyAdministradorasFromClientEntities();

        return [
            'administradoras' => Administradora::query()->active()->get(),
            'servicos' => Servico::query()->active()->get(),
            'formasEnvio' => FormaEnvio::query()->active()->get(),
            'statusRetorno' => StatusRetorno::query()->active()->get(),
        ];
    }

    private function syncLegacyAdministradorasFromClientEntities(): void
    {
        ClientEntity::query()
            ->active()
            ->where('profile_scope', 'contato')
            ->get()
            ->filter(function (ClientEntity $entity) {
                $role = Str::of((string) $entity->role_tag)->lower()->ascii()->value();
                return in_array($role, ['administradora', 'sindico'], true);
            })
            ->each(function (ClientEntity $entity) {
                $role = Str::of((string) $entity->role_tag)->lower()->ascii()->value();
                $type = $role === 'sindico' ? 'sindico' : 'administradora';
                $phone = collect($entity->phones_json ?? [])->pluck('number')->filter()->first();
                $email = collect($entity->emails_json ?? [])->pluck('email')->filter()->first();

                Administradora::query()->updateOrCreate(
                    [
                        'name' => $entity->display_name,
                        'type' => $type,
                    ],
                    [
                        'contact_name' => $entity->legal_representative ?: $entity->display_name,
                        'phone' => $phone,
                        'email' => $email,
                        'is_active' => 1,
                    ]
                );
            });
    }

    public function dashboard(Request $request): View
    {
        $year = max(2020, (int) $request->integer('year', now()->year));
        return view('pages.propostas.dashboard', [
            'title' => 'Dashboard de Propostas',
            'summary' => ProposalDashboardService::summary($year),
        ]);
    }

    public function index(Request $request): View
    {
        $query = Proposal::query()
            ->with(['administradora', 'servico', 'formaEnvio', 'statusRetorno'])
            ->withCount('attachments')
            ->orderByDesc('id');

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($sub) use ($term) {
                $sub->where('proposal_code', 'like', "%{$term}%")
                    ->orWhere('client_name', 'like', "%{$term}%")
                    ->orWhere('requester_name', 'like', "%{$term}%")
                    ->orWhere('contact_email', 'like', "%{$term}%")
                    ->orWhere('referral_name', 'like', "%{$term}%");
            });
        }
        foreach (['administradora_id', 'service_id', 'response_status_id', 'send_method_id'] as $filter) {
            if ((int) $request->integer($filter) > 0) {
                $column = match ($filter) {
                    'service_id' => 'service_id',
                    'response_status_id' => 'response_status_id',
                    'send_method_id' => 'send_method_id',
                    default => 'administradora_id',
                };
                $query->where($column, $request->integer($filter));
            }
        }
        if ((int) $request->integer('year') > 0) $query->whereYear('proposal_date', (int) $request->integer('year'));
        if ($request->filled('date_from')) $query->whereDate('proposal_date', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('proposal_date', '<=', $request->input('date_to'));

        $proposals = $query->paginate(max(5, min(100, (int) $request->integer('per_page', 15))))->withQueryString();
        $totalsQuery = clone $query;
        $totals = [
            'proposal_total' => (float) $totalsQuery->sum('proposal_total'),
            'closed_total' => (float) (clone $query)->sum('closed_total'),
        ];

        return view('pages.propostas.index', [
            'title' => 'Propostas',
            'proposals' => $proposals,
            'filters' => $request->all(),
            'totals' => $totals,
            'filterOptions' => [
                'administradoras' => Administradora::query()->active()->get(),
                'servicos' => Servico::query()->active()->get(),
                'formasEnvio' => FormaEnvio::query()->active()->get(),
                'statusRetorno' => StatusRetorno::query()->active()->get(),
                'years' => DB::table('propostas')->selectRaw('DISTINCT proposal_year')->orderByDesc('proposal_year')->pluck('proposal_year'),
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $items = Proposal::query()
            ->from('propostas as p')
            ->join('administradoras as a', 'a.id', '=', 'p.administradora_id')
            ->join('servicos as s', 's.id', '=', 'p.service_id')
            ->join('formas_envio as f', 'f.id', '=', 'p.send_method_id')
            ->join('status_retorno as st', 'st.id', '=', 'p.response_status_id')
            ->select('p.*', 'a.name as administradora_name', 's.name as service_name', 'f.name as send_method_name', 'st.name as status_name')
            ->orderByDesc('p.id')
            ->get();

        $filename = 'propostas_' . now()->format('Ymd_His') . '.csv';
        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Número', 'Data', 'Cliente', 'Indicação', 'Administradora', 'Serviço', 'Solicitante', 'Telefone', 'Email', 'Forma de envio', 'Status', 'Valor proposta', 'Valor fechado', 'Follow-up', 'Validade em dias'], ';');
            foreach ($items as $item) {
                fputcsv($out, [
                    $item->proposal_code,
                    optional($item->proposal_date)->format('d/m/Y'),
                    $item->client_name,
                    $item->has_referral ? ($item->referral_name ?: 'Sim') : 'Não',
                    $item->administradora_name,
                    $item->service_name,
                    $item->requester_name,
                    $item->requester_phone,
                    $item->contact_email,
                    $item->send_method_name,
                    $item->status_name,
                    number_format((float) $item->proposal_total, 2, ',', '.'),
                    $item->closed_total !== null ? number_format((float) $item->closed_total, 2, ',', '.') : '',
                    optional($item->followup_date)->format('d/m/Y'),
                    (int) $item->validity_days,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        return view('pages.propostas.form', array_merge([
            'title' => 'Nova proposta',
            'proposal' => null,
            'action' => route('propostas.store'),
            'submitLabel' => 'Cadastrar proposta',
        ], $this->formDependencies()));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = ProposalService::payloadFromRequest($request);
        $errors = ProposalService::validate($payload);
        if ($errors !== []) return back()->withInput()->with('errors_list', $errors);
        $user = AncoraAuth::user($request);
        $proposal = ProposalService::create($payload, (int) $user->id);
        $this->logAction($request, 'create_proposta', $proposal->id, 'Cadastro de nova proposta - ' . $proposal->proposal_code);
        $this->recordHistory($proposal->id, $user->id, $user->email, 'create', 'Proposta cadastrada.');
        return redirect()->route('propostas.show', $proposal)->with('success', 'Proposta cadastrada com sucesso. Agora você já pode anexar PDFs.');
    }

    public function show(Proposal $proposta): View
    {
        $proposta->load(['administradora', 'servico', 'formaEnvio', 'statusRetorno', 'attachments', 'history']);
        return view('pages.propostas.show', [
            'title' => 'Proposta ' . $proposta->proposal_code,
            'proposal' => $proposta,
        ]);
    }

    public function printView(Proposal $proposta): View
    {
        $proposta->load(['administradora', 'servico', 'formaEnvio', 'statusRetorno', 'attachments', 'history']);
        return view('pages.propostas.print', ['proposal' => $proposta]);
    }

    public function edit(Proposal $proposta): View
    {
        return view('pages.propostas.form', array_merge([
            'title' => 'Editar proposta',
            'proposal' => $proposta,
            'action' => route('propostas.update', $proposta),
            'submitLabel' => 'Salvar alterações',
        ], $this->formDependencies()));
    }

    public function update(Request $request, Proposal $proposta): RedirectResponse
    {
        $payload = ProposalService::payloadFromRequest($request);
        $errors = ProposalService::validate($payload);
        if ($errors !== []) return back()->withInput()->with('errors_list', $errors);
        $user = AncoraAuth::user($request);
        ProposalService::update($proposta, $payload, (int) $user->id);
        $this->logAction($request, 'update_proposta', $proposta->id, 'Atualização da proposta - ' . $proposta->proposal_code);
        $this->recordHistory($proposta->id, $user->id, $user->email, 'update', 'Proposta atualizada.');
        return redirect()->route('propostas.show', $proposta)->with('success', 'Proposta atualizada.');
    }

    public function destroy(Request $request, Proposal $proposta): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        $this->logAction($request, 'delete_proposta', $proposta->id, 'Exclusão da proposta - ' . $proposta->proposal_code);
        $this->recordHistory($proposta->id, $user->id, $user->email, 'delete', 'Proposta excluída.');
        $proposta->delete();
        return redirect()->route('propostas.index')->with('success', 'Proposta excluída.');
    }

    public function uploadAttachment(Request $request, Proposal $proposta): RedirectResponse
    {
        $errors = ProposalService::attachmentValidation($request->file('attachment_pdf'));
        if ($errors) return back()->with('error', implode(' ', $errors));
        $file = $request->file('attachment_pdf');
        $dir = public_path('uploads/propostas/' . $proposta->id);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $stored = now()->format('Ymd_His') . '_' . Str::random(12) . '.pdf';
        $file->move($dir, $stored);
        $attachment = ProposalAttachment::query()->create([
            'proposta_id' => $proposta->id,
            'original_name' => preg_replace('/[^a-zA-Z0-9._-]+/', '_', $file->getClientOriginalName()) ?: 'arquivo.pdf',
            'stored_name' => $stored,
            'relative_path' => '/uploads/propostas/' . $proposta->id . '/' . $stored,
            'mime_type' => $file->getClientMimeType() ?: 'application/pdf',
            'file_size' => filesize($dir . '/' . $stored) ?: 0,
            'uploaded_by' => AncoraAuth::user($request)?->id,
            'created_at' => now(),
        ]);
        $user = AncoraAuth::user($request);
        $this->logAction($request, 'upload_attachment', $attachment->id, 'Upload de anexo PDF na proposta #' . $proposta->id);
        $this->recordHistory($proposta->id, $user->id, $user->email, 'attachment_upload', 'PDF anexado à proposta.', ['attachment_id' => $attachment->id, 'original_name' => $attachment->original_name]);
        return back()->with('success', 'Anexo enviado com sucesso.');
    }

    public function downloadAttachment(Proposal $proposta, ProposalAttachment $attachment): BinaryFileResponse
    {
        abort_if($attachment->proposta_id !== $proposta->id, 404);
        $path = public_path(ltrim($attachment->relative_path, '/'));
        abort_unless(is_file($path), 404, 'Arquivo não encontrado no servidor.');
        return response()->download($path, $attachment->original_name, ['Content-Type' => 'application/pdf']);
    }

    public function deleteAttachment(Request $request, Proposal $proposta, ProposalAttachment $attachment): RedirectResponse
    {
        abort_if($attachment->proposta_id !== $proposta->id, 404);
        $path = public_path(ltrim($attachment->relative_path, '/'));
        if (is_file($path)) @unlink($path);
        $attachmentId = $attachment->id;
        $originalName = $attachment->original_name;
        $attachment->delete();
        $user = AncoraAuth::user($request);
        $this->logAction($request, 'delete_attachment', $attachmentId, 'Exclusão de anexo PDF da proposta #' . $proposta->id);
        $this->recordHistory($proposta->id, $user->id, $user->email, 'attachment_delete', 'PDF removido da proposta.', ['attachment_id' => $attachmentId, 'original_name' => $originalName]);
        return back()->with('success', 'Anexo removido com sucesso.');
    }

    private function recordHistory(int $proposalId, int $userId, string $email, string $action, string $summary, array $payload = []): void
    {
        ProposalHistory::query()->create([
            'proposta_id' => $proposalId,
            'user_id' => $userId,
            'user_email' => $email,
            'action' => $action,
            'summary' => $summary,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    private function logAction(Request $request, string $action, int $entityId, string $details): void
    {
        $user = AncoraAuth::user($request);
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'user_email' => $user?->email ?? 'desconhecido',
            'action' => $action,
            'entity_type' => 'propostas',
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
