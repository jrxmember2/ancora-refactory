<?php

namespace App\Http\Controllers;

use App\Models\ClientEntity;
use App\Models\Contract;
use App\Models\CobrancaCase;
use App\Models\DocumentSignatureRequest;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\DocumentSignatureService;
use App\Support\AncoraAuth;
use App\Support\ContractSettings;
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
    ) {
    }

    public function createContract(Contract $contrato): View
    {
        $contrato->load([
            'client',
            'condominium.syndic',
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
            $message = $this->normalizeMessage($request);

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
            'condominium',
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
            $message = $this->normalizeMessage($request);

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
        $rows = collect($request->input('signers', []))
            ->map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'email' => trim((string) ($row['email'] ?? '')),
                    'phone' => trim((string) ($row['phone'] ?? '')),
                    'document_number' => trim((string) ($row['document_number'] ?? '')),
                    'role_label' => trim((string) ($row['role_label'] ?? '')),
                ];
            })
            ->filter(fn (array $row) => collect($row)->filter(fn ($value) => $value !== '')->isNotEmpty())
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'signers' => 'Informe ao menos um signatario.',
            ]);
        }

        $errors = [];
        foreach ($rows as $index => $row) {
            $line = $index + 1;

            if ($row['name'] === '') {
                $errors['signers.' . $index . '.name'] = 'Informe o nome do signatario na linha ' . $line . '.';
            }

            if ($row['email'] === '') {
                $errors['signers.' . $index . '.email'] = 'Informe o e-mail do signatario na linha ' . $line . '.';
            } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['signers.' . $index . '.email'] = 'Informe um e-mail valido na linha ' . $line . '.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows->all();
    }

    private function normalizeMessage(Request $request): ?string
    {
        $message = trim((string) $request->input(
            'signer_message',
            ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')
        ));

        return $message !== '' ? Str::limit($message, 500, '') : null;
    }

    private function defaultContractSigners(Contract $contract): array
    {
        $rows = collect();

        $syndic = $contract->condominium?->syndic;
        if ($syndic instanceof ClientEntity) {
            $rows->push($this->signerFromEntity($syndic, 'Representante do condominio'));
        }

        $client = $contract->client;
        if ($client instanceof ClientEntity) {
            $rows->push($this->signerFromEntity($client, 'Cliente'));
        }

        return $rows
            ->filter()
            ->unique(fn (array $row) => Str::lower($row['email']) . '|' . Str::lower($row['name']))
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
            'phone' => $phone,
            'document_number' => $document,
            'role_label' => 'Devedor(a)',
        ]];
    }

    private function signerFromEntity(ClientEntity $entity, string $roleLabel): array
    {
        return [
            'name' => trim((string) ($entity->display_name ?: $entity->legal_name ?: '')),
            'email' => trim((string) collect($entity->emails_json ?? [])->pluck('email')->filter()->first()),
            'phone' => trim((string) collect($entity->phones_json ?? [])->pluck('number')->filter()->first()),
            'document_number' => trim((string) ($entity->cpf_cnpj ?: '')),
            'role_label' => $roleLabel,
        ];
    }

    private function blankSigner(): array
    {
        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'document_number' => '',
            'role_label' => '',
        ];
    }

    private function downloadArtifact(
        DocumentSignatureRequest $signature,
        string $artifact,
        User $user,
        string $backUrl
    ): BinaryFileResponse|RedirectResponse {
        $meta = match ($artifact) {
            'original' => ['field' => 'local_pdf_path', 'filename' => trim((string) $signature->document_name) ?: 'documento-original.pdf'],
            'signed' => ['field' => 'signed_pdf_path', 'filename' => 'documento-assinado.pdf'],
            'certificate' => ['field' => 'certificate_pdf_path', 'filename' => 'certificado-assinatura.pdf'],
            'bundle' => ['field' => 'bundle_pdf_path', 'filename' => 'pacote-assinatura.pdf'],
            default => null,
        };

        if (!$meta) {
            abort(404);
        }

        if (in_array($artifact, ['signed', 'certificate', 'bundle'], true) && trim((string) $signature->{$meta['field']}) === '') {
            try {
                $signature = $this->signatureService->syncRequest($signature, null, null, $user);
            } catch (\Throwable $e) {
                return redirect($backUrl)->with('error', 'Nao foi possivel preparar o download do documento assinado: ' . $e->getMessage());
            }
        }

        $relativePath = trim((string) $signature->{$meta['field']});
        if ($relativePath === '') {
            return redirect($backUrl)->with('error', 'Este arquivo ainda nao esta disponivel para download.');
        }

        $path = storage_path('app/public/' . ltrim($relativePath, '/'));
        if (!is_file($path)) {
            return redirect($backUrl)->with('error', 'O arquivo solicitado nao foi localizado no servidor.');
        }

        return response()->download($path, $meta['filename']);
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
