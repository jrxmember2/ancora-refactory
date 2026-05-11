<?php

namespace App\Support;

use Illuminate\Support\Str;

class AiDocumentCatalog
{
    public const SOURCE_CONDOMINIUM_ATTACHMENT = 'condominium_attachment';
    public const SOURCE_GLOBAL_DOCUMENT = 'global_document';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_ERROR = 'error';

    /** @return array<string,string> */
    public static function condominiumDocumentKinds(): array
    {
        return [
            'convention' => 'Convenção condominial',
            'regiment' => 'Regimento interno',
            'ata' => 'ATA',
        ];
    }

    /** @return array<string,string> */
    public static function condominiumDocumentPrefixes(): array
    {
        return [
            'convention' => 'Convenção condominial -',
            'regiment' => 'Regimento interno -',
            'ata' => 'ATA -',
        ];
    }

    /** @return array<string,string> */
    public static function processingStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_PROCESSED => 'Processado',
            self::STATUS_ERROR => 'Erro',
        ];
    }

    public static function classifyCondominiumAttachmentName(?string $originalName): ?string
    {
        $candidate = self::normalize((string) $originalName);
        if ($candidate === '') {
            return null;
        }

        foreach (self::condominiumDocumentPrefixes() as $kind => $prefix) {
            if (str_starts_with($candidate, self::normalize($prefix))) {
                return $kind;
            }
        }

        return null;
    }

    public static function documentKindLabel(?string $value): string
    {
        $key = trim((string) $value);

        if (isset(self::condominiumDocumentKinds()[$key])) {
            return self::condominiumDocumentKinds()[$key];
        }

        return AiLegalDocumentCatalog::documentTypeLabel($key);
    }

    public static function processingStatusLabel(?string $value): string
    {
        $key = trim((string) $value);

        return self::processingStatuses()[$key] ?? self::processingStatuses()[self::STATUS_PENDING];
    }

    public static function documentPriority(?string $sourceType, ?string $documentKind): int
    {
        $sourceType = trim((string) $sourceType);
        $documentKind = trim((string) $documentKind);

        if ($sourceType === self::SOURCE_CONDOMINIUM_ATTACHMENT) {
            return match ($documentKind) {
                'convention' => 420,
                'regiment' => 400,
                'ata' => 340,
                default => 300,
            };
        }

        return 180;
    }

    public static function searchableText(string $value): string
    {
        return Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private static function normalize(string $value): string
    {
        return self::searchableText($value);
    }
}
