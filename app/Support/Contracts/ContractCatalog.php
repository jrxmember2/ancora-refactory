<?php

namespace App\Support\Contracts;

class ContractCatalog
{
    public static function statuses(): array
    {
        return [
            'rascunho' => 'Rascunho',
            'em_revisao' => 'Em revisão',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'assinado' => 'Assinado',
            'ativo' => 'Ativo',
            'vencido' => 'Vencido',
            'rescindido' => 'Rescindido',
            'cancelado' => 'Cancelado',
            'arquivado' => 'Arquivado',
        ];
    }

    public static function types(): array
    {
        return [
            'Contrato de assessoria jurídica condominial',
            'Contrato de cobrança extrajudicial',
            'Contrato de cobrança judicial',
            'Contrato de regularização de convenção/regimento',
            'Contrato de participação em assembleia',
            'Termo de acordo',
            'Confissão de dívida',
            'Aditivo contratual',
            'Distrato',
            'Notificação extrajudicial',
            'Procuração',
            'Declaração',
            'Termo de quitação',
            'Recibo',
            'Outro',
        ];
    }

    public static function billingTypes(): array
    {
        return [
            'mensal' => 'Mensal',
            'unica' => 'Parcela única',
            'parcelada' => 'Parcelado',
            'honorarios_sobre_exito' => 'Honorários sobre êxito',
            'sob_demanda' => 'Sob demanda',
            'outro' => 'Outro',
        ];
    }

    public static function recurrences(): array
    {
        return [
            'mensal' => 'Mensal',
            'bimestral' => 'Bimestral',
            'trimestral' => 'Trimestral',
            'semestral' => 'Semestral',
            'anual' => 'Anual',
            'unica' => 'Única',
        ];
    }

    public static function adjustmentPeriodicities(): array
    {
        return [
            'mensal' => 'Mensal',
            'trimestral' => 'Trimestral',
            'semestral' => 'Semestral',
            'anual' => 'Anual',
            'bienal' => 'Bienal',
        ];
    }

    public static function pageOrientations(): array
    {
        return [
            'portrait' => 'Retrato',
            'landscape' => 'Paisagem',
        ];
    }

    public static function fileTypes(): array
    {
        return [
            'contrato_assinado' => 'Contrato assinado',
            'documento_pessoal' => 'Documento pessoal',
            'ata_eleicao' => 'ATA de eleição',
            'procuracao' => 'Procuração',
            'comprovante' => 'Comprovante',
            'outro' => 'Outro',
        ];
    }

    public static function initialCategories(): array
    {
        return [
            ['name' => 'Assessoria Jurídica', 'description' => 'Contratos permanentes de assessoria ao condomínio ou cliente.'],
            ['name' => 'Cobrança Condominial', 'description' => 'Instrumentos ligados a cobrança judicial e extrajudicial.'],
            ['name' => 'Acordos', 'description' => 'Termos de acordo, confissão de dívida e renegociação.'],
            ['name' => 'Distratos', 'description' => 'Encerramentos, distratos e rescisões contratuais.'],
            ['name' => 'Notificações', 'description' => 'Notificações extrajudiciais e comunicações formais.'],
            ['name' => 'Procurações', 'description' => 'Procurações e documentos de representação.'],
            ['name' => 'Declarações', 'description' => 'Declarações, termos de quitação e recibos.'],
            ['name' => 'Recibos', 'description' => 'Recibos e confirmações de pagamento.'],
            ['name' => 'Outros', 'description' => 'Modelos livres e documentos diversos.'],
        ];
    }

    public static function defaultSettings(): array
    {
        return [
            'default_city' => 'Vitória',
            'default_state' => 'ES',
            'signature_text' => '________________________________________',
            'footer_text' => 'Documento gerado pelo sistema Âncora.',
            'show_logo' => '1',
            'auto_code' => '1',
            'code_prefix' => 'CTR',
            'due_alert_days' => '30',
            'default_status' => 'rascunho',
        ];
    }

    public static function initialTemplates(): array
    {
        $variables = ContractVariableCatalog::keys();

        return [
            [
                'name' => 'Contrato de assessoria jurídica condominial',
                'document_type' => 'Contrato de assessoria jurídica condominial',
                'category_name' => 'Assessoria Jurídica',
                'description' => 'Modelo base para prestação contínua de assessoria jurídica ao condomínio.',
                'available_variables' => $variables,
                'content_html' => '<p><strong>CONTRATANTE:</strong> {{condominio_nome}}, inscrito no CNPJ {{condominio_cnpj}}, neste ato representado por {{sindico_nome}}.</p><p><strong>CONTRATADO:</strong> Rebeca Medina Sociedade Individual de Advocacia.</p><p>O presente instrumento tem por objeto a prestação de assessoria jurídica condominial, com início em {{contrato_data_inicio}} e vigência até {{contrato_data_fim}}.</p><p>O valor mensal contratado é de {{contrato_valor}}, vencendo todo dia {{contrato_dia_vencimento}}, com reajuste pelo índice {{contrato_reajuste_indice}}.</p><p>As partes elegem {{cidade}}/{{data_atual}} para os fins deste ajuste.</p>',
            ],
            [
                'name' => 'Termo de acordo',
                'document_type' => 'Termo de acordo',
                'category_name' => 'Acordos',
                'description' => 'Modelo para composição amigável de débitos e obrigações.',
                'available_variables' => $variables,
                'content_html' => '<p><strong>CREDOR:</strong> {{condominio_nome}}, representado por {{sindico_nome}}.</p><p><strong>DEVEDOR:</strong> {{cliente_nome}}, documento {{cliente_documento}}, residente em {{cliente_endereco}}.</p><p>As partes firmam o presente termo para quitação do débito referente à unidade {{unidade_numero}}, bloco {{bloco_nome}}, pelo valor de {{contrato_valor}} ({{contrato_valor_extenso}}).</p><p>O pagamento seguirá as condições definidas neste instrumento, com vencimento no dia {{contrato_dia_vencimento}}.</p>',
            ],
            [
                'name' => 'Confissão de dívida',
                'document_type' => 'Confissão de dívida',
                'category_name' => 'Acordos',
                'description' => 'Modelo para reconhecimento formal da obrigação pelo devedor.',
                'available_variables' => $variables,
                'content_html' => '<p>{{cliente_nome}} reconhece e confessa ser devedor do valor de {{contrato_valor}} ({{contrato_valor_extenso}}), devido em favor de {{condominio_nome}}.</p><p>O débito será satisfeito conforme as condições pactuadas pelas partes, observando-se a data de início {{contrato_data_inicio}} e vencimento definido no instrumento.</p>',
            ],
            [
                'name' => 'Aditivo contratual',
                'document_type' => 'Aditivo contratual',
                'category_name' => 'Assessoria Jurídica',
                'description' => 'Modelo para alteração pontual de cláusulas do contrato principal.',
                'available_variables' => $variables,
                'content_html' => '<p>As partes resolvem aditar o contrato intitulado <strong>{{contrato_titulo}}</strong>, firmado entre {{cliente_nome}} e {{condominio_nome}}, para ajustar prazo, valores ou obrigações específicas.</p><p>Ficam mantidas as demais cláusulas não alteradas por este aditivo.</p>',
            ],
            [
                'name' => 'Distrato',
                'document_type' => 'Distrato',
                'category_name' => 'Distratos',
                'description' => 'Modelo para encerramento consensual do vínculo contratual.',
                'available_variables' => $variables,
                'content_html' => '<p>As partes, de comum acordo, resolvem extinguir o contrato <strong>{{contrato_titulo}}</strong>, celebrado em {{contrato_data_inicio}}, dando-se plena e geral quitação, ressalvadas as obrigações expressamente mantidas.</p>',
            ],
            [
                'name' => 'Notificação extrajudicial',
                'document_type' => 'Notificação extrajudicial',
                'category_name' => 'Notificações',
                'description' => 'Modelo base de notificação extrajudicial.',
                'available_variables' => $variables,
                'content_html' => '<p>Sirva a presente para NOTIFICAR {{cliente_nome}}, documento {{cliente_documento}}, para que adote as providências necessárias no prazo assinalado, sob pena das medidas cabíveis.</p><p>Fica consignado que o presente documento é emitido em {{cidade}}, na data de {{data_atual}}.</p>',
            ],
            [
                'name' => 'Termo de quitação',
                'document_type' => 'Termo de quitação',
                'category_name' => 'Declarações',
                'description' => 'Modelo para declaração de adimplemento integral.',
                'available_variables' => $variables,
                'content_html' => '<p>{{condominio_nome}} declara, para os devidos fins, que {{cliente_nome}} quitou integralmente a obrigação tratada no instrumento <strong>{{contrato_titulo}}</strong>, no valor de {{contrato_valor}}.</p>',
            ],
        ];
    }
}
