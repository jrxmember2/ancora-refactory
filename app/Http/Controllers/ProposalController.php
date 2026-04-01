<?php

namespace App\Http\Controllers;

use App\Models\Administradora;
use App\Models\AuditLog;
use App\Models\FormaEnvio;
use App\Models\Proposal;
use App\Models\ProposalHistory;
use App\Models\Servico;
use App\Models\StatusRetorno;
use App\Services\ProposalDashboardService;
use App\Services\ProposalService;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProposalController extends Controller
{
    private function formDependencies(): array
    {
        return [
            'administradoras' => Administradora::query()->active()->get(),
            'servicos' => Servico::query()->active()->get(),
            'formasEnvio' => FormaEnvio::query()->active()->get(),
            'statusRetorno' => StatusRetorno::query()->active()->get(),
        ];
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

        if ((int) $request->integer('year') > 0) {
            $query->whereYear('proposal_date', (int) $request->integer('year'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('proposal_date', '>=', $request->string('date_from')->toString());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('proposal_date', '<=', $request->string('date_to')->toString());
        }

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
        if ($errors !== []) {
            return back()->withInput()->with('errors_list', $errors);
        }

        $user = AncoraAuth::user($request);
        $proposal = ProposalService::create($payload, (int) $user->id);
        $this->logAction($request, 'create_proposta', $proposal->id, 'Cadastro de nova proposta - ' . $proposal->proposal_code);
        $this->recordHistory($proposal->id, $user->id, $user->email, 'create', 'Proposta cadastrada.');

        return redirect()->route('propostas.show', $proposal)->with('success', 'Proposta cadastrada com sucesso.');
    }

    public function show(Proposal $proposta): View
    {
        $proposta->load(['administradora', 'servico', 'formaEnvio', 'statusRetorno', 'attachments', 'history']);

        return view('pages.propostas.show', [
            'title' => 'Proposta ' . $proposta->proposal_code,
            'proposal' => $proposta,
        ]);
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
        if ($errors !== []) {
            return back()->withInput()->with('errors_list', $errors);
        }

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

    private function recordHistory(int $proposalId, int $userId, string $email, string $action, string $summary): void
    {
        ProposalHistory::query()->create([
            'proposta_id' => $proposalId,
            'user_id' => $userId,
            'user_email' => $email,
            'action' => $action,
            'summary' => $summary,
            'payload_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
