<?php

namespace App\Support\Contracts;

class ContractCatalog
{
    public static function statuses(): array
    {
        return [
            'rascunho' => 'Rascunho',
            'em_revisao' => 'Em revisao',
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
            'Contrato de assessoria juridica condominial',
            'Contrato de cobranca extrajudicial',
            'Contrato de cobranca judicial',
            'Contrato de regularizacao de convencao/regimento',
            'Contrato de participacao em assembleia',
            'Termo de acordo',
            'Confissao de divida',
            'Aditivo contratual',
            'Distrato',
            'Notificacao extrajudicial',
            'Procuracao',
            'Declaracao',
            'Termo de quitacao',
            'Recibo',
            'Outro',
        ];
    }

    public static function billingTypes(): array
    {
        return [
            'mensal' => 'Mensal',
            'unica' => 'Parcela unica',
            'parcelada' => 'Parcelado',
            'honorarios_sobre_exito' => 'Honorarios sobre exito',
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
            'unica' => 'Unica',
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
            'ata_eleicao' => 'Ata de eleicao',
            'procuracao' => 'Procuracao',
            'comprovante' => 'Comprovante',
            'outro' => 'Outro',
        ];
    }

    public static function initialCategories(): array
    {
        return [
            ['name' => 'Assessoria Juridica', 'description' => 'Contratos permanentes de assessoria ao condominio ou cliente.'],
            ['name' => 'Cobranca Condominial', 'description' => 'Instrumentos ligados a cobranca judicial e extrajudicial.'],
            ['name' => 'Acordos', 'description' => 'Termos de acordo, confissao de divida e renegociacao.'],
            ['name' => 'Distratos', 'description' => 'Encerramentos, distratos e rescisoes contratuais.'],
            ['name' => 'Notificacoes', 'description' => 'Notificacoes extrajudiciais e comunicacoes formais.'],
            ['name' => 'Procuracoes', 'description' => 'Procuracoes e documentos de representacao.'],
            ['name' => 'Declaracoes', 'description' => 'Declaracoes, termos de quitacao e recibos.'],
            ['name' => 'Recibos', 'description' => 'Recibos e confirmacoes de pagamento.'],
            ['name' => 'Outros', 'description' => 'Modelos livres e documentos diversos.'],
        ];
    }

    public static function defaultSettings(): array
    {
        return [
            'default_city' => 'Vitoria',
            'default_state' => 'ES',
            'signature_text' => '________________________________________',
            'footer_text' => 'Documento gerado pelo sistema Ancora.',
            'show_logo' => '1',
            'auto_code' => '1',
            'code_prefix' => 'CTR',
            'due_alert_days' => '30',
            'default_status' => 'rascunho',
            'assinafy_environment' => 'production',
            'assinafy_account_id' => '',
            'assinafy_api_key' => '',
            'assinafy_access_token' => '',
            'assinafy_webhook_email' => '',
            'assinafy_webhook_token' => '',
            'assinafy_default_signer_message' => 'Segue o documento para assinatura digital.',
            'assinafy_default_signers_json' => '[]',
        ];
    }

    public static function initialTemplates(): array
    {
        $variables = ContractVariableCatalog::keys();

        return [
            [
                'name' => 'Contrato de assessoria juridica condominial',
                'document_type' => 'Contrato de assessoria juridica condominial',
                'default_contract_title' => 'Contrato de assessoria juridica condominial',
                'category_name' => 'Assessoria Juridica',
                'description' => 'Modelo base para prestacao continua de assessoria juridica ao condominio.',
                'available_variables' => $variables,
                'content_html' => '<p><strong>CONTRATANTE:</strong> {{condominio_nome}}, inscrito no CNPJ {{condominio_cnpj}}, neste ato representado por {{sindico_qualificacao}}.</p><p><strong>CONTRATADO:</strong> Rebeca Medina Sociedade Individual de Advocacia.</p><p>O presente instrumento tem por objeto a prestacao de assessoria juridica condominial, com inicio em {{contrato_data_inicio}} e vigencia ate {{contrato_data_fim}}.</p><p>O valor mensal contratado e de {{contrato_valor}}, vencendo todo dia {{contrato_dia_vencimento}}, com reajuste pelo indice {{contrato_reajuste_indice}}.</p><p>As partes elegem {{cidade}}/{{data_atual}} para os fins deste ajuste.</p>',
            ],
            [
                'name' => 'Termo de acordo',
                'document_type' => 'Termo de acordo',
                'default_contract_title' => 'Termo de acordo',
                'category_name' => 'Acordos',
                'description' => 'Modelo para composicao amigavel de debitos e obrigacoes.',
                'available_variables' => $variables,
                'content_html' => '<p><strong>CREDOR:</strong> {{condominio_nome}}, representado por {{sindico_qualificacao}}.</p><p><strong>DEVEDOR:</strong> {{cliente_nome}}, documento {{cliente_documento}}, residente em {{cliente_endereco}}.</p><p>As partes firmam o presente termo para quitacao do debito referente a unidade {{unidade_numero}}, bloco {{bloco_nome}}, pelo valor de {{contrato_valor}} ({{contrato_valor_extenso}}).</p><p>O pagamento seguira as condicoes definidas neste instrumento, com vencimento no dia {{contrato_dia_vencimento}}.</p>',
            ],
            [
                'name' => 'Confissao de divida',
                'document_type' => 'Confissao de divida',
                'default_contract_title' => 'Confissao de divida',
                'category_name' => 'Acordos',
                'description' => 'Modelo para reconhecimento formal da obrigacao pelo devedor.',
                'available_variables' => $variables,
                'content_html' => '<p>{{cliente_nome}} reconhece e confessa ser devedor do valor de {{contrato_valor}} ({{contrato_valor_extenso}}), devido em favor de {{condominio_nome}}.</p><p>O debito sera satisfeito conforme as condicoes pactuadas pelas partes, observando-se a data de inicio {{contrato_data_inicio}} e vencimento definido no instrumento.</p>',
            ],
            [
                'name' => 'Aditivo contratual',
                'document_type' => 'Aditivo contratual',
                'default_contract_title' => 'Aditivo contratual',
                'category_name' => 'Assessoria Juridica',
                'description' => 'Modelo para alteracao pontual de clausulas do contrato principal.',
                'available_variables' => $variables,
                'content_html' => '<p>As partes resolvem aditar o contrato intitulado <strong>{{contrato_titulo}}</strong>, firmado entre {{cliente_nome}} e {{condominio_nome}}, para ajustar prazo, valores ou obrigacoes especificas.</p><p>Ficam mantidas as demais clausulas nao alteradas por este aditivo.</p>',
            ],
            [
                'name' => 'Distrato',
                'document_type' => 'Distrato',
                'default_contract_title' => 'Distrato',
                'category_name' => 'Distratos',
                'description' => 'Modelo para encerramento consensual do vinculo contratual.',
                'available_variables' => $variables,
                'content_html' => '<p>As partes, de comum acordo, resolvem extinguir o contrato <strong>{{contrato_titulo}}</strong>, celebrado em {{contrato_data_inicio}}, dando-se plena e geral quitacao, ressalvadas as obrigacoes expressamente mantidas.</p>',
            ],
            [
                'name' => 'Notificacao extrajudicial',
                'document_type' => 'Notificacao extrajudicial',
                'default_contract_title' => 'Notificacao extrajudicial',
                'category_name' => 'Notificacoes',
                'description' => 'Modelo base de notificacao extrajudicial.',
                'available_variables' => $variables,
                'content_html' => '<p>Sirva a presente para NOTIFICAR {{cliente_nome}}, documento {{cliente_documento}}, para que adote as providencias necessarias no prazo assinalado, sob pena das medidas cabiveis.</p><p>Fica consignado que o presente documento e emitido em {{cidade}}, na data de {{data_atual}}.</p>',
            ],
            [
                'name' => 'Termo de quitacao',
                'document_type' => 'Termo de quitacao',
                'default_contract_title' => 'Termo de quitacao',
                'category_name' => 'Declaracoes',
                'description' => 'Modelo para declaracao de adimplemento integral.',
                'available_variables' => $variables,
                'content_html' => '<p>{{condominio_nome}} declara, para os devidos fins, que {{cliente_nome}} quitou integralmente a obrigacao tratada no instrumento <strong>{{contrato_titulo}}</strong>, no valor de {{contrato_valor}}.</p>',
            ],
        ];
    }
}
