<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\ClientAttachment;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends HubApiController
{
    public function download(Request $request, ClientAttachment $document): JsonResponse|BinaryFileResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.avulsos', 'clientes.contatos', 'clientes.condominos', 'clientes.condominios', 'clientes.unidades', 'clientes.attachments.download'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->canDownloadDocument($user, $document)) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        $path = $document->absolutePath();
        if (!is_string($path) || !is_file($path)) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        return response()->download($path, $document->original_name);
    }

    private function canDownloadDocument(User $user, ClientAttachment $document): bool
    {
        return match ((string) $document->related_type) {
            'entity' => $this->canAccessEntityDocument($user, (int) $document->related_id),
            'condominium' => $this->userCanAnyRoute($user, ['clientes.index', 'clientes.condominios', 'clientes.attachments.download']),
            'unit' => $this->canAccessUnitDocument($user, (int) $document->related_id),
            default => false,
        };
    }

    private function canAccessEntityDocument(User $user, int $entityId): bool
    {
        $entity = ClientEntity::query()->find($entityId);
        if (!$entity) {
            return false;
        }

        if ($entity->profile_scope === 'avulso') {
            return $this->userCanAnyRoute($user, ['clientes.index', 'clientes.avulsos', 'clientes.attachments.download']);
        }

        return $this->userCanAnyRoute($user, ['clientes.index', 'clientes.contatos', 'clientes.condominos', 'clientes.attachments.download']);
    }

    private function canAccessUnitDocument(User $user, int $unitId): bool
    {
        return ClientUnit::query()->whereKey($unitId)->exists()
            && $this->userCanAnyRoute($user, ['clientes.index', 'clientes.condominios', 'clientes.unidades', 'clientes.attachments.download']);
    }
}
