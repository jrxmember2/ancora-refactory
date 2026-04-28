<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractVersion;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseAttachment;
use App\Models\CobrancaCaseTimeline;
use App\Models\DocumentSignatureEvent;
use App\Models\DocumentSignatureRequest;
use App\Models\DocumentSignatureSigner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentSignatureService
{
    public function __construct(
        private readonly AssinafyService $assinafy,
        private readonly ContractPdfService $contractPdfService,
        private readonly CobrancaAgreementPdfService $agreementPdfService,
    ) {
    }

    public static function requestStatusLabels(): array
    {
        return [
            'draft' => 'Rascunho',
            'uploaded' => 'Documento enviado',
            'metadata_ready' => 'Documento preparado',
            'pending_signatures' => 'Aguardando assinaturas',
            'partially_signed' => 'Parcialmente assinado',
            'certificating' => 'Certificando',
            'certificated' => 'Assinado e certificado',
            'rejected_by_signer' => 'Recusado',
            'rejected_by_user' => 'Cancelado',
            'expired' => 'Expirado',
            'failed' => 'Falhou',
        ];
    }

    public static function signerStatusLabels(): array
    {
        return [
            'pending' => 'Pendente',
            'requested' => 'Convite enviado',
            'viewed' => 'Visualizado',
            'signed' => 'Assinado',
            'rejected' => 'Recusado',
        ];
    }

    public function sendContract(Contract $contract, array $signers, User $user, ?string $message = null): DocumentSignatureRequest
    {
        if (!$this->assinafy->isConfigured()) {
            throw new \RuntimeException('Configure a Assinafy antes de enviar: ' . implode(', ', $this->assinafy->missingConfig()) . '.');
        }

        $contract->loadMissing(['template', 'client', 'condominium', 'unit', 'responsible', 'versions']);

        $version = $contract->versions()->orderByDesc('version_number')->first();
        if (!$contract->final_pdf_path) {
            $version = $this->contractPdfService->generate(
                $contract->fresh(['template', 'client', 'condominium', 'unit', 'responsible']),
                $user->id,
                'PDF gerado para envio à assinatura digital.'
            );
            $contract->refresh();
        }

        $absolutePath = $this->contractPdfService->absolutePath($contract->final_pdf_path);
        if (!$absolutePath) {
            throw new \RuntimeException('O PDF final do contrato nao foi localizado para envio à assinatura digital.');
        }

        $documentName = ($contract->code ?: Str::slug($contract->title ?: 'contrato')) . '-assinatura.pdf';

        $request = $this->sendDocument(
            $contract,
            $absolutePath,
            $contract->final_pdf_path,
            $documentName,
            $version,
            $signers,
            $user,
            $message
        );

        if (!in_array($contract->status, ['assinado', 'ativo', 'rescindido', 'cancelado', 'arquivado'], true)) {
            $contract->forceFill([
                'status' => 'aguardando_assinatura',
                'updated_by' => $user->id,
            ])->save();
        }

        return $request->fresh(['signers', 'events', 'creator', 'updater']);
    }

    public function sendCobrancaAgreement(CobrancaCase $case, array $signers, User $user, ?string $message = null): DocumentSignatureRequest
    {
        if (!$this->assinafy->isConfigured()) {
            throw new \RuntimeException('Configure a Assinafy antes de enviar: ' . implode(', ', $this->assinafy->missingConfig()) . '.');
        }

        if (!$case->agreementTerm || trim((string) $case->agreementTerm->body_text) === '') {
            throw new \RuntimeException('Salve o termo de acordo na OS antes de enviar para assinatura digital.');
        }

        $generated = $this->agreementPdfService->generatePersistent($case->fresh());

        $request = $this->sendDocument(
            $case,
            $generated['absolute_path'],
            $generated['relative_path'],
            $generated['filename'],
            null,
            $signers,
            $user,
            $message
        );

        if ($case->workflow_stage !== 'aguardando_assinatura') {
            $case->forceFill([
                'workflow_stage' => 'aguardando_assinatura',
                'updated_by' => $user->id,
                'last_progress_at' => now(),
            ])->save();
        }

        $this->recordCaseTimeline($case, 'termo', 'Termo enviado para assinatura digital via Assinafy.', $user, now());

        return $request->fresh(['signers', 'events', 'creator', 'updater']);
    }

    public function syncRequest(DocumentSignatureRequest $request, ?array $documentData = null, ?array $webhookPayload = null, ?User $actor = null): DocumentSignatureRequest
    {
        $request->loadMissing(['signers', 'events', 'signable']);

        if (!$request->provider_document_id) {
            return $request;
        }

        $documentData ??= $this->assinafy->getDocument((string) $request->provider_document_id);

        $eventType = trim((string) ($webhookPayload['event'] ?? ''));
        $eventAt = $this->eventTime($webhookPayload);
        $previousStatus = (string) $request->status;

        if ($webhookPayload) {
            $this->recordWebhookEvent($request, $webhookPayload, $eventType, $eventAt);
        }

        $status = $this->normalizeStatus((string) ($documentData['status'] ?? $request->status));
        $summary = (array) data_get($documentData, 'assignment.summary', []);

        if ($summary === [] && data_get($documentData, 'assignment.signers')) {
            $summary = [
                'signer_count' => count((array) data_get($documentData, 'assignment.signers', [])),
                'completed_count' => 0,
                'signers' => [],
            ];
        }

        $request->fill([
            'provider_account_id' => (string) ($documentData['account_id'] ?? $request->provider_account_id),
            'provider_assignment_id' => (string) (data_get($documentData, 'assignment.id') ?: $request->provider_assignment_id),
            'document_name' => (string) ($documentData['name'] ?? $request->document_name),
            'status' => $status,
            'signing_url' => (string) ($documentData['signing_url'] ?? $request->signing_url),
            'requested_at' => $status !== 'draft' && $status !== 'uploaded'
                ? ($request->requested_at ?: now())
                : $request->requested_at,
            'summary_json' => $summary,
            'provider_payload_json' => $documentData,
            'last_synced_at' => now(),
            'completed_at' => $status === 'certificated' ? ($request->completed_at ?: now()) : $request->completed_at,
            'updated_by' => $actor?->id ?: $request->updated_by,
        ])->save();

        $this->syncSigners($request, $documentData, $eventType, $eventAt, $webhookPayload);
        $this->downloadArtifacts($request);
        $this->mirrorArtifactsToSource($request, $actor);
        $this->syncSourceStatus($request, $previousStatus, $actor);

        return $request->fresh(['signers', 'events', 'creator', 'updater']);
    }

    public function processWebhook(array $payload): ?DocumentSignatureRequest
    {
        $documentId = trim((string) data_get($payload, 'object.id', ''));
        if ($documentId === '') {
            return null;
        }

        $request = DocumentSignatureRequest::query()
            ->where('provider', 'assinafy')
            ->where('provider_document_id', $documentId)
            ->first();

        if (!$request) {
            return null;
        }

        $documentData = (array) data_get($payload, 'object', []);
        try {
            $documentData = $this->assinafy->getDocument($documentId);
        } catch (\Throwable) {
            // Usa o payload bruto do webhook como fallback quando a consulta imediata falhar.
        }

        return $this->syncRequest($request, $documentData, $payload, null);
    }

    private function sendDocument(
        Model $signable,
        string $absolutePath,
        ?string $relativePath,
        string $documentName,
        ?ContractVersion $version,
        array $signers,
        User $user,
        ?string $message = null
    ): DocumentSignatureRequest {
        $upload = $this->assinafy->uploadDocument($documentName, $absolutePath);

        /** @var DocumentSignatureRequest $request */
        $request = DB::transaction(function () use ($signable, $upload, $documentName, $relativePath, $version, $user, $message) {
            return $signable->signatureRequests()->create([
                'provider' => 'assinafy',
                'provider_account_id' => (string) ($upload['account_id'] ?? $this->assinafy->accountId()),
                'provider_document_id' => (string) ($upload['id'] ?? ''),
                'document_version_id' => $version?->id,
                'document_name' => $documentName,
                'status' => $this->normalizeStatus((string) ($upload['status'] ?? 'uploaded')),
                'local_pdf_path' => $relativePath,
                'signing_url' => (string) ($upload['signing_url'] ?? ''),
                'signer_message' => $message,
                'provider_payload_json' => $upload,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        try {
            $preparedSigners = [];
            $providerSignerIds = [];

            foreach (array_values($signers) as $index => $signer) {
                $providerSigner = $this->assinafy->findOrCreateSigner([
                    'full_name' => $signer['name'],
                    'email' => $signer['email'],
                    'whatsapp_phone_number' => $signer['phone'] ?? null,
                    'government_id' => $signer['document_number'] ?? null,
                ]);

                $preparedSigners[] = [
                    'provider_signer_id' => (string) ($providerSigner['id'] ?? ''),
                    'name' => (string) ($providerSigner['full_name'] ?? $signer['name']),
                    'email' => (string) ($providerSigner['email'] ?? $signer['email']),
                    'phone' => (string) ($providerSigner['whatsapp_phone_number'] ?? ($signer['phone'] ?? '')),
                    'document_number' => (string) ($signer['document_number'] ?? ''),
                    'role_label' => (string) ($signer['role_label'] ?? ''),
                    'order_index' => $index + 1,
                    'status' => 'pending',
                ];
                $providerSignerIds[] = (string) ($providerSigner['id'] ?? '');
            }

            DB::transaction(function () use ($request, $preparedSigners) {
                foreach ($preparedSigners as $payload) {
                    $request->signers()->create($payload);
                }
            });

            $assignment = $this->assinafy->createAssignment((string) $request->provider_document_id, $providerSignerIds, $message);
            if (!empty($assignment['id'])) {
                $request->forceFill([
                    'provider_assignment_id' => (string) $assignment['id'],
                ])->save();
            }

            $documentData = $this->assinafy->getDocument((string) $request->provider_document_id);

            return $this->syncRequest($request, $documentData, null, $user);
        } catch (\Throwable $e) {
            $request->forceFill([
                'status' => 'failed',
                'updated_by' => $user->id,
            ])->save();

            throw $e;
        }
    }

    private function syncSigners(
        DocumentSignatureRequest $request,
        array $documentData,
        string $eventType,
        Carbon $eventAt,
        ?array $webhookPayload = null
    ): void {
        $assignmentSigners = collect((array) data_get($documentData, 'assignment.signers', []));
        $summarySigners = collect((array) data_get($documentData, 'assignment.summary.signers', []));
        $signingUrls = collect((array) data_get($documentData, 'assignment.signing_urls', []));
        $documentStatus = $this->normalizeStatus((string) ($documentData['status'] ?? $request->status));

        foreach ($request->signers as $signer) {
            $remoteSigner = $assignmentSigners->first(fn ($row) => $this->signerMatches($signer, (array) $row));
            $summarySigner = $summarySigners->first(fn ($row) => $this->signerMatches($signer, (array) $row));
            $signingUrl = $signingUrls->first(fn ($row) => (string) ($row['signer_id'] ?? '') === (string) $signer->provider_signer_id);

            $status = (string) $signer->status;
            $completed = (bool) $signer->completed;

            if ($summarySigner && (bool) ($summarySigner['completed'] ?? false)) {
                $status = 'signed';
                $completed = true;
            }

            if ($documentStatus === 'certificated' && $status !== 'rejected') {
                $status = 'signed';
                $completed = true;
            }

            if ($this->webhookMatchesSigner($signer, $webhookPayload)) {
                if ($eventType === 'signature_requested') {
                    $status = 'requested';
                } elseif ($eventType === 'signer_viewed_document') {
                    $status = 'viewed';
                } elseif ($eventType === 'signer_signed_document') {
                    $status = 'signed';
                    $completed = true;
                } elseif ($eventType === 'signer_rejected_document') {
                    $status = 'rejected';
                    $completed = false;
                }
            }

            $signer->fill([
                'name' => (string) ($remoteSigner['full_name'] ?? $signer->name),
                'email' => (string) ($remoteSigner['email'] ?? $signer->email),
                'phone' => (string) ($remoteSigner['whatsapp_phone_number'] ?? $signer->phone),
                'status' => $status,
                'completed' => $completed,
                'signing_url' => (string) (($signingUrl['url'] ?? '') ?: $signer->signing_url),
                'requested_at' => $status !== 'pending' ? ($signer->requested_at ?: $request->requested_at ?: now()) : $signer->requested_at,
                'viewed_at' => $status === 'viewed' ? ($signer->viewed_at ?: $eventAt) : $signer->viewed_at,
                'signed_at' => $status === 'signed' ? ($signer->signed_at ?: $eventAt) : $signer->signed_at,
                'rejected_at' => $status === 'rejected' ? ($signer->rejected_at ?: $eventAt) : $signer->rejected_at,
                'last_event_at' => $eventType !== '' && $this->webhookMatchesSigner($signer, $webhookPayload) ? $eventAt : $signer->last_event_at,
                'provider_payload_json' => array_filter([
                    'signer' => $remoteSigner,
                    'summary' => $summarySigner,
                    'signing_url' => $signingUrl,
                ]),
            ])->save();
        }
    }

    private function downloadArtifacts(DocumentSignatureRequest $request): void
    {
        if ($request->status !== 'certificated' || !$request->provider_document_id) {
            return;
        }

        $dir = storage_path('app/public/signatures/' . $request->id);
        File::ensureDirectoryExists($dir);

        $artifacts = [
            'certificated' => ['field' => 'signed_pdf_path', 'filename' => 'documento-assinado.pdf'],
            'certificate-page' => ['field' => 'certificate_pdf_path', 'filename' => 'certificado-assinatura.pdf'],
            'bundle' => ['field' => 'bundle_pdf_path', 'filename' => 'pacote-assinatura.pdf'],
        ];

        $changed = false;

        foreach ($artifacts as $artifact => $meta) {
            $current = trim((string) $request->{$meta['field']});
            $absolute = $current !== '' ? storage_path('app/public/' . ltrim($current, '/')) : null;
            if ($absolute && is_file($absolute)) {
                continue;
            }

            $targetPath = $dir . DIRECTORY_SEPARATOR . $meta['filename'];
            if ($this->assinafy->downloadArtifact((string) $request->provider_document_id, $artifact, $targetPath)) {
                $request->{$meta['field']} = 'signatures/' . $request->id . '/' . $meta['filename'];
                $changed = true;
            }
        }

        if ($changed) {
            $request->save();
        }
    }

    private function mirrorArtifactsToSource(DocumentSignatureRequest $request, ?User $actor = null): void
    {
        $request->loadMissing('signable');

        if ($request->signable instanceof Contract) {
            $this->mirrorContractArtifact($request, $actor);
            return;
        }

        if ($request->signable instanceof CobrancaCase) {
            $this->mirrorCobrancaArtifact($request, $actor);
        }
    }

    private function mirrorContractArtifact(DocumentSignatureRequest $request, ?User $actor = null): void
    {
        $contract = $request->signable;
        if (!$contract instanceof Contract) {
            return;
        }

        $artifacts = [
            'signed_pdf_path' => ['file_type' => 'contrato_assinado', 'description' => 'Documento assinado digitalmente via Assinafy.'],
            'bundle_pdf_path' => ['file_type' => 'comprovante', 'description' => 'Pacote/certificado da assinatura digital via Assinafy.'],
        ];

        foreach ($artifacts as $field => $meta) {
            $relativePath = trim((string) $request->{$field});
            if ($relativePath === '') {
                continue;
            }

            if (ContractAttachment::query()->where('contract_id', $contract->id)->where('relative_path', $relativePath)->exists()) {
                continue;
            }

            $absolute = storage_path('app/public/' . ltrim($relativePath, '/'));
            if (!is_file($absolute)) {
                continue;
            }

            ContractAttachment::query()->create([
                'contract_id' => $contract->id,
                'original_name' => basename($absolute),
                'stored_name' => basename($absolute),
                'relative_path' => $relativePath,
                'file_type' => $meta['file_type'],
                'mime_type' => 'application/pdf',
                'file_size' => (int) (@filesize($absolute) ?: 0),
                'description' => $meta['description'],
                'uploaded_by' => $actor?->id ?: $request->updated_by ?: $request->created_by,
            ]);
        }
    }

    private function mirrorCobrancaArtifact(DocumentSignatureRequest $request, ?User $actor = null): void
    {
        $case = $request->signable;
        if (!$case instanceof CobrancaCase) {
            return;
        }

        $artifacts = [
            'signed_pdf_path' => ['file_role' => 'termo', 'description' => 'Termo assinado digitalmente via Assinafy.'],
            'bundle_pdf_path' => ['file_role' => 'comprovante', 'description' => 'Pacote/certificado da assinatura digital via Assinafy.'],
        ];

        foreach ($artifacts as $field => $meta) {
            $relativePath = trim((string) $request->{$field});
            if ($relativePath === '') {
                continue;
            }

            if (CobrancaCaseAttachment::query()->where('cobranca_case_id', $case->id)->where('relative_path', $relativePath)->exists()) {
                continue;
            }

            $absolute = storage_path('app/public/' . ltrim($relativePath, '/'));
            if (!is_file($absolute)) {
                continue;
            }

            CobrancaCaseAttachment::query()->create([
                'cobranca_case_id' => $case->id,
                'original_name' => basename($absolute),
                'stored_name' => basename($absolute),
                'relative_path' => $relativePath,
                'file_role' => $meta['file_role'],
                'mime_type' => 'application/pdf',
                'file_size' => (int) (@filesize($absolute) ?: 0),
                'uploaded_by' => $actor?->id ?: $request->updated_by ?: $request->created_by,
                'created_at' => now(),
            ]);
        }
    }

    private function syncSourceStatus(DocumentSignatureRequest $request, string $previousStatus, ?User $actor = null): void
    {
        $request->loadMissing('signable');

        if ($request->signable instanceof Contract) {
            $contract = $request->signable;
            if ($request->status === 'certificated' && !in_array($contract->status, ['ativo', 'rescindido', 'cancelado', 'arquivado'], true)) {
                $contract->forceFill([
                    'status' => 'assinado',
                    'updated_by' => $actor?->id ?: $request->updated_by,
                ])->save();
            }

            return;
        }

        if (!$request->signable instanceof CobrancaCase) {
            return;
        }

        $case = $request->signable;

        if ($request->status === 'certificated' && $previousStatus !== 'certificated') {
            $case->forceFill([
                'workflow_stage' => 'acordo_ativo',
                'updated_by' => $actor?->id ?: $request->updated_by,
                'last_progress_at' => now(),
            ])->save();

            $this->recordCaseTimeline($case, 'termo', 'Todas as assinaturas do termo foram concluídas via Assinafy.', $actor, now());
        }
    }

    private function recordWebhookEvent(DocumentSignatureRequest $request, array $payload, string $eventType, Carbon $eventAt): void
    {
        $providerEventId = trim((string) ($payload['id'] ?? ''));
        $signer = $request->signers->first(fn (DocumentSignatureSigner $item) => $this->webhookMatchesSigner($item, $payload));

        DocumentSignatureEvent::query()->updateOrCreate(
            [
                'signature_request_id' => $request->id,
                'provider_event_id' => $providerEventId !== '' ? $providerEventId : null,
                'event_type' => $eventType !== '' ? $eventType : 'provider_event',
            ],
            [
                'signature_signer_id' => $signer?->id,
                'message' => trim((string) ($payload['message'] ?? '')) ?: null,
                'payload_json' => $payload,
                'received_at' => $eventAt,
            ]
        );
    }

    private function recordCaseTimeline(CobrancaCase $case, string $eventType, string $description, ?User $user, Carbon $when): void
    {
        CobrancaCaseTimeline::query()->create([
            'cobranca_case_id' => $case->id,
            'event_type' => Str::limit($eventType, 40, ''),
            'description' => $description,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'created_at' => $when,
        ]);
    }

    private function normalizeStatus(string $status): string
    {
        return match (trim($status)) {
            'ready', 'document_ready' => 'metadata_ready',
            'pending_signature' => 'pending_signatures',
            'completed' => 'certificated',
            'document_processing_failed' => 'failed',
            default => trim($status) !== '' ? trim($status) : 'draft',
        };
    }

    private function signerMatches(DocumentSignatureSigner $signer, array $payload): bool
    {
        $payloadId = trim((string) ($payload['id'] ?? ''));
        $payloadEmail = Str::lower(trim((string) ($payload['email'] ?? '')));

        if ($payloadId !== '' && $payloadId === (string) $signer->provider_signer_id) {
            return true;
        }

        return $payloadEmail !== '' && $payloadEmail === Str::lower((string) $signer->email);
    }

    private function webhookMatchesSigner(DocumentSignatureSigner $signer, ?array $payload): bool
    {
        if (!$payload) {
            return false;
        }

        $subjectId = trim((string) data_get($payload, 'subject.id', ''));
        $subjectEmail = Str::lower(trim((string) data_get($payload, 'subject.email', '')));
        $payloadEmail = Str::lower(trim((string) data_get($payload, 'payload.signer_email', '')));

        if ($subjectId !== '' && $subjectId === (string) $signer->provider_signer_id) {
            return true;
        }

        return in_array(Str::lower((string) $signer->email), array_filter([$subjectEmail, $payloadEmail]), true);
    }

    private function eventTime(?array $payload): Carbon
    {
        $raw = $payload['created_at'] ?? null;
        if (is_numeric($raw)) {
            return Carbon::createFromTimestamp((int) $raw);
        }

        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // Segue para fallback.
            }
        }

        return now();
    }
}
