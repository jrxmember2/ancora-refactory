<?php

namespace App\Http\Controllers;

use App\Models\ClientEntity;
use App\Models\Contract;
use App\Models\CobrancaCase;
use App\Models\DocumentSignatureRequest;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\DocumentSignatureDownloadService;
use App\Services\DocumentSignatureMessageService;
use App\Services\DocumentSignatureService;
use App\Services\SignatureSignerService;
use App\Support\AncoraAuth;
use App\Support\ContractSettings;
use App\Support\Signatures\DocumentSignatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentSignatureController extends Controller
{
    public function __construct(
        private readonly DocumentSignatureService $signatureService,
        private readonly AssinafyService $assinafyService,
        private readonly DocumentSignatureMessageService $messageService,
        private readonly DocumentSignatureDownloadService $downloadService,
        private readonly SignatureSignerService $signerService,
    ) {
    }

    public function createContract(Contract $contrato): View
    {
        $contrato->load([
            'client',
            'condominium.syndic',
            'syndic',
            'unit.block',
            'responsible',
        ]);

        return view('pages.signatures.form', [
            'title' => 'Assinatura digital do contrato',
            'subtitle' => 'Configure os signatarios e envie o PDF final para assinatura pela Assinafy.',
            'mode' => 'contract',
            'signable' => $contrato,
            'cancelUrl' => route('contratos.show', ['contrato' => $contrato, 'tab' => 'assinaturas']),
            'submitUrl' => route('contratos.signatures.store', $contrato),
            'submitLabel' => 'Enviar para assinatura',
            'signers' => old('signers', $this->defaultContractSigners($contrato)),
            'signerMessage' => old('signer_message', ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')),
            'defaultSignerOptions' => $this->defaultSignerOptions(),
            'signatureRoleOptions' => DocumentSignatureCatalog::roleOptions(),
            'messageVariableDefinitions' => $this->messageService->definitions(),
            'providerConfigured' => $this->assinafyService->isConfigured(),
            'missingConfig' => $this->assinafyService->missingConfig(),
            'canSubmit' => true,
            'blockingReason' => null,
        ]);
    }

    public function storeContract(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        try {
            $signers = $this->normalizeSigners($request);
            $message = $this->normalizeContractMessage($request, $contrato);

            $this->signatureService->sendContract($contrato, $signers, $user, $message);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Nao foi possivel enviar o contrato para assinatura digital: ' . $e->getMessage());
        }

        return redirect()
            ->route('contratos.show', ['contrato' => $contrato, 'tab' => 'assinaturas'])
            ->with('success', 'Contrato enviado para assinatura digital com sucesso.');
    }

    public function syncContract(Request $request, Contract $contrato, DocumentSignatureRequest $signature): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        $this->ensureOwnership($signature, $contrato);

        try {
            $this->signatureService->syncRequest($signature, null, null, $user);
        } catch (\Throwable $e) {
            return redirect()
                ->route('contratos.show', ['contrato' => $contrato, 'tab' => 'assinaturas'])
                ->with('error', 'Nao foi possivel sincronizar a assinatura digital: ' . $e->getMessage());
        }

        return redirect()
            ->route('contratos.show', ['contrato' => $contrato, 'tab' => 'assinaturas'])
            ->with('success', 'Assinatura digital sincronizada com sucesso.');
    }

    public function downloadContract(Request $request, Contract $contrato, DocumentSignatureRequest $signature, string $artifact): BinaryFileResponse|RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        $this->ensureOwnership($signature, $contrato);

        return $this->downloadArtifact(
            $signature,
            $artifact,
            $user,
            route('contratos.show', ['contrato' => $contrato, 'tab' => 'assinaturas'])
        );
    }

    public function createCobranca(CobrancaCase $cobranca): View
    {
        $relations = [
            'condominium.syndic',
            'block',
            'unit',
            'debtor',
        ];
        if ($this->agreementTermStorageReady()) {
            $relations[] = 'agreementTerm';
        }
        $cobranca->load($relations);

        $blockingReason = null;
        if (!$this->agreementTermStorageReady()) {
            $blockingReason = 'A tabela de termos de acordo ainda nao existe no banco deste ambiente.';
        } elseif (!$cobranca->agreementTerm || trim((string) $cobranca->agreementTerm->body_text) === '') {
            $blockingReason = 'Salve o termo de acordo desta OS antes de enviar para assinatura digital.';
        }

        return view('pages.signatures.form', [
            'title' => 'Assinatura digital do termo',
            'subtitle' => 'Envie o termo de acordo desta OS para assinatura e acompanhe cada envolvido.',
            'mode' => 'cobranca',
            'signable' => $cobranca,
            'cancelUrl' => route('cobrancas.show', $cobranca),
            'submitUrl' => route('cobrancas.signatures.store', $cobranca),
            'submitLabel' => 'Enviar termo para assinatura',
            'signers' => old('signers', $this->defaultCobrancaSigners($cobranca)),
            'signerMessage' => old('signer_message', ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')),
            'defaultSignerOptions' => $this->defaultSignerOptions(),
            'signatureRoleOptions' => DocumentSignatureCatalog::roleOptions(),
            'messageVariableDefinitions' => $this->messageService->definitions(),
            'providerConfigured' => $this->assinafyService->isConfigured(),
            'missingConfig' => $this->assinafyService->missingConfig(),
            'canSubmit' => $blockingReason === null,
            'blockingReason' => $blockingReason,
            'termSaved' => $this->agreementTermStorageReady()
                && $cobranca->agreementTerm
                && trim((string) $cobranca->agreementTerm->body_text) !== '',
        ]);
    }

    public function storeCobranca(Request $request, CobrancaCase $cobranca): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        try {
            $signers = $this->normalizeSigners($request);
            $message = $this->normalizeCobrancaMessage($request, $cobranca);

            $this->signatureService->sendCobrancaAgreement($cobranca, $signers, $user, $message);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Nao foi possivel enviar o termo para assinatura digital: ' . $e->getMessage());
        }

        return redirect()
            ->route('cobrancas.show', $cobranca)
            ->with('success', 'Termo enviado para assinatura digital com sucesso.');
    }

    public function syncCobranca(Request $request, CobrancaCase $cobranca, DocumentSignatureRequest $signature): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        $this->ensureOwnership($signature, $cobranca);

        try {
            $this->signatureService->syncRequest($signature, null, null, $user);
        } catch (\Throwable $e) {
            return redirect()
                ->route('cobrancas.show', $cobranca)
                ->with('error', 'Nao foi possivel sincronizar a assinatura digital da OS: ' . $e->getMessage());
        }

        return redirect()
            ->route('cobrancas.show', $cobranca)
            ->with('success', 'Assinatura digital da OS sincronizada com sucesso.');
    }

    public function downloadCobranca(Request $request, CobrancaCase $cobranca, DocumentSignatureRequest $signature, string $artifact): BinaryFileResponse|RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        $this->ensureOwnership($signature, $cobranca);

        return $this->downloadArtifact(
            $signature,
            $artifact,
            $user,
            route('cobrancas.show', $cobranca)
        );
    }

    private function ensureOwnership(DocumentSignatureRequest $signature, Contract|CobrancaCase $signable): void
    {
        abort_unless(
            $signature->signable_type === $signable::class && (int) $signature->signable_id === (int) $signable->id,
            404
        );
    }

    private function normalizeSigners(Request $request): array
    {
        return $this->signerService->normalizeSigners($request);
    }

    private function normalizeContractMessage(Request $request, Contract $contract): ?string
    {
        $message = trim((string) $request->input(
            'signer_message',
            ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')
        ));

        if ($message === '') {
            return null;
        }

        return Str::limit($this->messageService->renderForContract($message, $contract), 500, '');
    }

    private function normalizeCobrancaMessage(Request $request, CobrancaCase $case): ?string
    {
        $message = trim((string) $request->input(
            'signer_message',
            ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')
        ));

        if ($message === '') {
            return null;
        }

        return Str::limit($this->messageService->renderForCobranca($message, $case), 500, '');
    }

    private function defaultContractSigners(Contract $contract): array
    {
        $rows = collect();

        $syndic = $contract->syndic ?: $contract->condominium?->syndic;
        if ($syndic instanceof ClientEntity) {
            $rows->push($this->signerFromEntity($syndic, 'Signatario'));
        }

        $client = $contract->client;
        if ($client instanceof ClientEntity) {
            $rows->push($this->signerFromEntity($client, 'Signatario'));
        }

        return $this->signerService
            ->uniqueSigners($rows)
            ->values()
            ->whenEmpty(fn (Collection $collection) => $collection->push($this->blankSigner()))
            ->all();
    }

    private function defaultCobrancaSigners(CobrancaCase $case): array
    {
        $name = trim((string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: ''));
        $email = trim((string) ($case->debtor_email_snapshot ?: collect($case->debtor?->emails_json ?? [])->pluck('email')->filter()->first()));
        $phone = trim((string) ($case->debtor_phone_snapshot ?: collect($case->debtor?->phones_json ?? [])->pluck('number')->filter()->first()));
        $document = trim((string) ($case->debtor_document_snapshot ?: $case->debtor?->cpf_cnpj ?: ''));

        return [[
            'name' => $name,
            'email' => $email,
            'phone' => $this->formatPhone($phone),
            'document_number' => $this->formatDocument($document),
            'role_label' => 'Devedor',
            'order_index' => 1,
        ]];
    }

    private function signerFromEntity(ClientEntity $entity, string $roleLabel): array
    {
        return $this->signerService->signerFromEntity($entity, $roleLabel);
    }

    private function blankSigner(): array
    {
        return $this->signerService->blankSigner();
    }

    private function defaultSignerOptions(): array
    {
        return $this->signerService->defaultSignerOptions();
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function formatPhone(string $value): string
    {
        return $this->signerService->formatPhone($value);
    }

    private function formatDocument(string $value): string
    {
        return $this->signerService->formatDocument($value);
    }

    private function downloadArtifact(
        DocumentSignatureRequest $signature,
        string $artifact,
        User $user,
        string $backUrl
    ): BinaryFileResponse|RedirectResponse {
        return $this->downloadService->downloadArtifact($signature, $artifact, $user, $backUrl);
    }

    private function agreementTermStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_agreement_terms');
        } catch (\Throwable) {
            return false;
        }
    }
}
