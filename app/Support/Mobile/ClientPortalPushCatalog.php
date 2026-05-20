<?php

namespace App\Support\Mobile;

class ClientPortalPushCatalog
{
    public static function typeOptions(): array
    {
        return [
            'general' => 'Aviso geral',
            'assembly' => 'Assembleia',
            'financial' => 'Financeiro',
            'maintenance' => 'Manutencao',
            'document' => 'Documento',
            'occurrence' => 'Ocorrencia',
            'emergency' => 'Emergencia',
            'other' => 'Outro',
        ];
    }

    public static function typeKeys(): array
    {
        return array_keys(self::typeOptions());
    }

    public static function typeLabel(?string $type): string
    {
        $normalized = trim((string) $type);

        return self::typeOptions()[$normalized] ?? 'Outro';
    }

    public static function defaultDeepLink(?string $type = null): string
    {
        return 'app://notifications';
    }

    public static function defaultScreen(?string $type = null): string
    {
        return 'notifications';
    }

    public static function statusLabels(): array
    {
        return [
            'queued' => 'Na fila',
            'processing' => 'Processando',
            'completed' => 'Concluido',
            'completed_with_errors' => 'Concluido com erros',
            'failed' => 'Falhou',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        $normalized = trim((string) $status);

        return self::statusLabels()[$normalized] ?? 'Desconhecido';
    }

    public static function recipientModeLabels(): array
    {
        return [
            'global' => 'Envio global',
            'specific' => 'Usuarios especificos',
        ];
    }

    public static function recipientModeLabel(?string $mode): string
    {
        $normalized = trim((string) $mode);

        return self::recipientModeLabels()[$normalized] ?? 'Envio global';
    }

    public static function isFinished(?string $status): bool
    {
        return in_array(trim((string) $status), ['completed', 'completed_with_errors', 'failed'], true);
    }
}
