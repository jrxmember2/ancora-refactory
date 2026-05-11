<?php

namespace App\Support;

class AiLegalDocumentCatalog
{
    /** @return array<string,string> */
    public static function documentTypes(): array
    {
        return [
            'civil_code' => 'Codigo Civil',
            'law' => 'Lei',
            'norm' => 'Norma',
            'other' => 'Outro',
        ];
    }

    /** @return array<string,string> */
    public static function processingStatuses(): array
    {
        return [
            'pending' => 'Pendente',
            'processed' => 'Processado',
            'error' => 'Erro',
        ];
    }

    /** @return list<string> */
    public static function documentTypeKeys(): array
    {
        return array_keys(self::documentTypes());
    }

    /** @return list<string> */
    public static function processingStatusKeys(): array
    {
        return array_keys(self::processingStatuses());
    }

    public static function processableExtension(): string
    {
        return 'docx';
    }

    /** @return list<string> */
    public static function acceptedExtensions(): array
    {
        return ['docx', 'pdf'];
    }

    public static function documentTypeLabel(?string $value): string
    {
        $key = strtolower(trim((string) $value));

        return self::documentTypes()[$key] ?? 'Outro';
    }

    public static function processingStatusLabel(?string $value): string
    {
        $key = strtolower(trim((string) $value));

        return self::processingStatuses()[$key] ?? 'Pendente';
    }
}
