<?php

namespace App\Support\Agenda;

class AgendaCatalog
{
    public static function types(): array
    {
        return [
            'prazo' => 'Prazo processual',
            'audiencia' => 'Audiencia',
            'reuniao' => 'Reuniao',
            'tarefa' => 'Tarefa',
            'compromisso' => 'Compromisso',
            'diligencia' => 'Diligencia',
            'pericia' => 'Pericia',
            'outro' => 'Outro',
        ];
    }

    public static function statuses(): array
    {
        return [
            'aberto' => 'Aberto',
            'concluido' => 'Concluido',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function priorities(): array
    {
        return [
            'baixa' => 'Baixa',
            'media' => 'Media',
            'alta' => 'Alta',
        ];
    }

    public static function reminderOptions(): array
    {
        return [
            '' => 'Sem lembrete',
            '30' => '30 minutos antes',
            '60' => '1 hora antes',
            '120' => '2 horas antes',
            '1440' => '1 dia antes',
            '2880' => '2 dias antes',
            '10080' => '1 semana antes',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::types()[$type] ?? ($type ?: 'Compromisso');
    }

    public static function statusLabel(?string $status): string
    {
        return self::statuses()[$status] ?? ($status ?: 'Aberto');
    }
}
