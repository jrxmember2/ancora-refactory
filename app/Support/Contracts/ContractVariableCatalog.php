<?php

namespace App\Support\Contracts;

use App\Support\ContractSettings;
use Illuminate\Support\Str;

class ContractVariableCatalog
{
    public static function definitions(): array
    {
        return self::annotate([
            ['key' => 'contrato_codigo', 'label' => 'Contrato - codigo', 'description' => 'Codigo interno do contrato.', 'source' => 'contracts.code'],
            ['key' => 'contrato_titulo', 'label' => 'Contrato - titulo', 'description' => 'Titulo do contrato.', 'source' => 'contracts.title'],

            ['key' => 'cliente_nome', 'label' => 'Cliente - nome', 'description' => 'Nome principal da entidade cliente vinculada.', 'source' => 'client_entities.display_name'],
            ['key' => 'cliente_documento', 'label' => 'Cliente - documento', 'description' => 'CPF ou CNPJ do cliente vinculado.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'cliente_endereco', 'label' => 'Cliente - endereco completo', 'description' => 'Endereco completo do cliente vinculado.', 'source' => 'client_entities.primary_address_json'],
            ['key' => 'cliente_logradouro', 'label' => 'Cliente - logradouro', 'description' => 'Logradouro do cliente vinculado.', 'source' => 'client_entities.primary_address_json.street'],
            ['key' => 'cliente_numero', 'label' => 'Cliente - numero', 'description' => 'Numero do endereco do cliente vinculado.', 'source' => 'client_entities.primary_address_json.number'],
            ['key' => 'cliente_complemento', 'label' => 'Cliente - complemento', 'description' => 'Complemento do endereco do cliente vinculado.', 'source' => 'client_entities.primary_address_json.complement'],
            ['key' => 'cliente_bairro', 'label' => 'Cliente - bairro', 'description' => 'Bairro do cliente vinculado.', 'source' => 'client_entities.primary_address_json.neighborhood'],
            ['key' => 'cliente_cidade', 'label' => 'Cliente - cidade', 'description' => 'Cidade do cliente vinculado.', 'source' => 'client_entities.primary_address_json.city'],
            ['key' => 'cliente_estado', 'label' => 'Cliente - estado', 'description' => 'Estado do cliente vinculado.', 'source' => 'client_entities.primary_address_json.state'],
            ['key' => 'cliente_cep', 'label' => 'Cliente - CEP', 'description' => 'CEP do cliente vinculado.', 'source' => 'client_entities.primary_address_json.zip'],

            ['key' => 'condominio_nome', 'label' => 'Condominio - nome', 'description' => 'Nome do condominio vinculado.', 'source' => 'client_condominiums.name'],
            ['key' => 'condominio_cnpj', 'label' => 'Condominio - CNPJ', 'description' => 'CNPJ do condominio vinculado.', 'source' => 'client_condominiums.cnpj'],
            ['key' => 'condominio_endereco', 'label' => 'Condominio - endereco completo', 'description' => 'Endereco completo do condominio vinculado.', 'source' => 'client_condominiums.address_json'],
            ['key' => 'condominio_logradouro', 'label' => 'Condominio - logradouro', 'description' => 'Logradouro do condominio vinculado.', 'source' => 'client_condominiums.address_json.street'],
            ['key' => 'condominio_numero', 'label' => 'Condominio - numero', 'description' => 'Numero do endereco do condominio vinculado.', 'source' => 'client_condominiums.address_json.number'],
            ['key' => 'condominio_complemento', 'label' => 'Condominio - complemento', 'description' => 'Complemento do endereco do condominio vinculado.', 'source' => 'client_condominiums.address_json.complement'],
            ['key' => 'condominio_bairro', 'label' => 'Condominio - bairro', 'description' => 'Bairro do condominio vinculado.', 'source' => 'client_condominiums.address_json.neighborhood'],
            ['key' => 'condominio_cidade', 'label' => 'Condominio - cidade', 'description' => 'Cidade do condominio vinculado.', 'source' => 'client_condominiums.address_json.city'],
            ['key' => 'condominio_estado', 'label' => 'Condominio - estado', 'description' => 'Estado do condominio vinculado.', 'source' => 'client_condominiums.address_json.state'],
            ['key' => 'condominio_cep', 'label' => 'Condominio - CEP', 'description' => 'CEP do condominio vinculado.', 'source' => 'client_condominiums.address_json.zip'],

            ['key' => 'sindico_nome', 'label' => 'Sindico - qualificacao principal', 'description' => 'Nome simples quando PF ou qualificacao composta quando o sindico for PJ.', 'source' => 'client_entities.display_name'],
            ['key' => 'sindico_nome_simples', 'label' => 'Sindico - nome simples', 'description' => 'Nome do sindico PF ou nome fantasia/empresa principal do sindico PJ.', 'source' => 'client_entities.display_name'],
            ['key' => 'sindico_tipo_pessoa', 'label' => 'Sindico - tipo de pessoa', 'description' => 'Retorna PF ou PJ conforme o cadastro selecionado.', 'source' => 'client_entities.entity_type'],
            ['key' => 'sindico_documento', 'label' => 'Sindico - documento principal', 'description' => 'Documento principal do cadastro do sindico: CPF se PF ou CNPJ se PJ.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'sindico_cpf', 'label' => 'Sindico - CPF', 'description' => 'CPF do sindico quando o cadastro for pessoa fisica.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'sindico_cnpj', 'label' => 'Sindico - CNPJ', 'description' => 'CNPJ do sindico quando o cadastro for pessoa juridica.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'sindico_endereco', 'label' => 'Sindico - endereco completo', 'description' => 'Endereco completo do cadastro principal do sindico.', 'source' => 'client_entities.primary_address_json'],
            ['key' => 'sindico_email', 'label' => 'Sindico - e-mail', 'description' => 'E-mail principal do cadastro do sindico.', 'source' => 'client_entities.emails_json'],
            ['key' => 'sindico_telefone', 'label' => 'Sindico - telefone', 'description' => 'Telefone principal do cadastro do sindico.', 'source' => 'client_entities.phones_json'],
            ['key' => 'sindico_logradouro', 'label' => 'Sindico - logradouro', 'description' => 'Logradouro do cadastro principal do sindico.', 'source' => 'client_entities.primary_address_json.street'],
            ['key' => 'sindico_numero', 'label' => 'Sindico - numero', 'description' => 'Numero do endereco do sindico.', 'source' => 'client_entities.primary_address_json.number'],
            ['key' => 'sindico_complemento', 'label' => 'Sindico - complemento', 'description' => 'Complemento do endereco do sindico.', 'source' => 'client_entities.primary_address_json.complement'],
            ['key' => 'sindico_bairro', 'label' => 'Sindico - bairro', 'description' => 'Bairro do sindico.', 'source' => 'client_entities.primary_address_json.neighborhood'],
            ['key' => 'sindico_cidade', 'label' => 'Sindico - cidade', 'description' => 'Cidade do sindico.', 'source' => 'client_entities.primary_address_json.city'],
            ['key' => 'sindico_estado', 'label' => 'Sindico - estado', 'description' => 'Estado do sindico.', 'source' => 'client_entities.primary_address_json.state'],
            ['key' => 'sindico_cep', 'label' => 'Sindico - CEP', 'description' => 'CEP do sindico.', 'source' => 'client_entities.primary_address_json.zip'],
            ['key' => 'sindico_empresa_nome', 'label' => 'Sindico PJ - empresa', 'description' => 'Razao social/nome da empresa quando o sindico for pessoa juridica.', 'source' => 'client_entities.legal_name'],
            ['key' => 'sindico_empresa_cnpj', 'label' => 'Sindico PJ - CNPJ', 'description' => 'CNPJ da empresa quando o sindico for pessoa juridica.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'sindico_qualificacao', 'label' => 'Sindico - qualificacao completa', 'description' => 'Texto completo com empresa/CNPJ e representante PF, quando aplicavel.', 'source' => 'client_entities.shareholders_json'],
            ['key' => 'sindico_representante_nome', 'label' => 'Sindico PJ - representante', 'description' => 'Nome da pessoa fisica representante da PJ.', 'source' => 'client_entities.shareholders_json'],
            ['key' => 'sindico_representante_documento', 'label' => 'Sindico PJ - documento do representante', 'description' => 'Documento do representante principal da pessoa juridica.', 'source' => 'client_entities.shareholders_json'],
            ['key' => 'sindico_representante_cpf', 'label' => 'Sindico PJ - CPF do representante', 'description' => 'CPF do representante principal da pessoa juridica.', 'source' => 'client_entities.shareholders_json'],

            ['key' => 'unidade_numero', 'label' => 'Unidade - numero', 'description' => 'Numero da unidade vinculada.', 'source' => 'client_units.unit_number'],
            ['key' => 'bloco_nome', 'label' => 'Bloco - nome', 'description' => 'Nome do bloco ou torre da unidade vinculada.', 'source' => 'client_condominium_blocks.name'],

            ['key' => 'contrato_valor', 'label' => 'Contrato - valor', 'description' => 'Valor principal do contrato formatado em BRL.', 'source' => 'contracts.contract_value'],
            ['key' => 'contrato_valor_extenso', 'label' => 'Contrato - valor por extenso', 'description' => 'Valor principal do contrato escrito por extenso.', 'source' => 'contracts.contract_value'],
            ['key' => 'contrato_data_inicio', 'label' => 'Contrato - data de inicio', 'description' => 'Data inicial da vigencia do contrato.', 'source' => 'contracts.start_date'],
            ['key' => 'contrato_data_fim', 'label' => 'Contrato - data de termino', 'description' => 'Data final da vigencia do contrato.', 'source' => 'contracts.end_date'],
            ['key' => 'contrato_dia_vencimento', 'label' => 'Contrato - dia de vencimento', 'description' => 'Dia de vencimento configurado no contrato.', 'source' => 'contracts.due_day'],
            ['key' => 'contrato_reajuste_indice', 'label' => 'Contrato - indice de reajuste', 'description' => 'Indice de reajuste definido no contrato.', 'source' => 'contracts.adjustment_index'],

            ['key' => 'numero_pagina', 'label' => 'Sistema - numero da pagina', 'description' => 'Numero da pagina atual no PDF gerado.', 'source' => 'pdf.counter.page'],
            ['key' => 'total_paginas', 'label' => 'Sistema - total de paginas', 'description' => 'Quantidade total de paginas no PDF gerado.', 'source' => 'pdf.counter.pages'],
            ['key' => 'data_atual', 'label' => 'Data atual', 'description' => 'Data atual do sistema formatada em portugues.', 'source' => 'system.now'],
            ['key' => 'cidade', 'label' => 'Cidade padrao', 'description' => 'Cidade padrao das configuracoes do modulo.', 'source' => 'contract_settings.default_city'],
            ['key' => 'responsavel_nome', 'label' => 'Responsavel - nome', 'description' => 'Nome do usuario responsavel pelo contrato.', 'source' => 'users.name'],
        ]);
    }

    public static function definitionsForTemplates(): array
    {
        return self::annotate(array_merge(
            self::definitions(),
            self::signaturePresetDefinitions()
        ));
    }

    public static function keys(): array
    {
        return collect(self::definitionsForTemplates())
            ->pluck('key')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function groupLabels(): array
    {
        return [
            'all' => 'Todos',
            'cliente' => 'Cliente',
            'condominio' => 'Condominio',
            'sindico' => 'Sindico',
            'unidade' => 'Unidade',
            'contrato' => 'Contrato',
            'assinaturas' => 'Assinaturas',
            'sistema' => 'Sistema',
        ];
    }

    public static function signaturePresetDefinitions(): array
    {
        $rows = collect(ContractSettings::jsonArray('assinafy_default_signers_json'));

        return collect([
            'signatario' => 'Signatario',
            'testemunha' => 'Testemunha',
        ])->flatMap(function (string $label, string $slug) use ($rows) {
            return $rows
                ->filter(fn ($row) => mb_strtolower(trim((string) ($row['role_label'] ?? '')), 'UTF-8') === $slug)
                ->values()
                ->flatMap(function (array $row, int $index) use ($slug, $label) {
                    $position = $index + 1;
                    $base = $slug . '_' . $position;

                    return [
                        [
                            'key' => $base . '_nome',
                            'label' => $label . ' ' . $position . ' - nome',
                            'description' => 'Nome do ' . mb_strtolower($label, 'UTF-8') . ' pre-cadastrado na posicao ' . $position . '.',
                            'source' => 'contract_settings.assinafy_default_signers_json',
                        ],
                        [
                            'key' => $base . '_email',
                            'label' => $label . ' ' . $position . ' - e-mail',
                            'description' => 'E-mail do ' . mb_strtolower($label, 'UTF-8') . ' pre-cadastrado na posicao ' . $position . '.',
                            'source' => 'contract_settings.assinafy_default_signers_json',
                        ],
                        [
                            'key' => $base . '_telefone',
                            'label' => $label . ' ' . $position . ' - telefone',
                            'description' => 'Telefone do ' . mb_strtolower($label, 'UTF-8') . ' pre-cadastrado na posicao ' . $position . '.',
                            'source' => 'contract_settings.assinafy_default_signers_json',
                        ],
                        [
                            'key' => $base . '_documento',
                            'label' => $label . ' ' . $position . ' - documento',
                            'description' => 'Documento do ' . mb_strtolower($label, 'UTF-8') . ' pre-cadastrado na posicao ' . $position . '.',
                            'source' => 'contract_settings.assinafy_default_signers_json',
                        ],
                    ];
                });
        })->values()->all();
    }

    public static function groupKeyFor(string $key): string
    {
        return match (true) {
            Str::startsWith($key, 'cliente_') => 'cliente',
            Str::startsWith($key, 'condominio_') => 'condominio',
            Str::startsWith($key, 'sindico_') => 'sindico',
            Str::startsWith($key, ['unidade_', 'bloco_']) => 'unidade',
            Str::startsWith($key, 'contrato_') => 'contrato',
            Str::startsWith($key, ['signatario_', 'testemunha_']) => 'assinaturas',
            default => 'sistema',
        };
    }

    private static function annotate(array $definitions): array
    {
        return collect($definitions)
            ->map(function ($definition) {
                $definition = is_array($definition) ? $definition : [];
                $group = self::groupKeyFor((string) ($definition['key'] ?? ''));
                $definition['group'] = $group;
                $definition['group_label'] = self::groupLabels()[$group] ?? Str::headline($group);

                return $definition;
            })
            ->values()
            ->all();
    }
}
