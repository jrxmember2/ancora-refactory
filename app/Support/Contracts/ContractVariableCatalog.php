<?php

namespace App\Support\Contracts;

class ContractVariableCatalog
{
    public static function definitions(): array
    {
        return [
            ['key' => 'contrato_codigo', 'label' => 'Contrato - código', 'description' => 'Código interno do contrato.', 'source' => 'contracts.code'],
            ['key' => 'contrato_titulo', 'label' => 'Contrato - título', 'description' => 'Título do contrato.', 'source' => 'contracts.title'],
            ['key' => 'cliente_nome', 'label' => 'Cliente - nome', 'description' => 'Nome principal da entidade vinculada.', 'source' => 'client_entities.display_name'],
            ['key' => 'cliente_documento', 'label' => 'Cliente - documento', 'description' => 'CPF ou CNPJ do cliente vinculado.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'cliente_endereco', 'label' => 'Cliente - endereco', 'description' => 'Endereco principal do cliente vinculado.', 'source' => 'client_entities.primary_address_json'],
            ['key' => 'condominio_nome', 'label' => 'Condominio - nome', 'description' => 'Nome do condominio vinculado.', 'source' => 'client_condominiums.name'],
            ['key' => 'condominio_cnpj', 'label' => 'Condominio - CNPJ', 'description' => 'CNPJ do condominio vinculado.', 'source' => 'client_condominiums.cnpj'],
            ['key' => 'condominio_endereco', 'label' => 'Condominio - endereco', 'description' => 'Endereco do condominio vinculado.', 'source' => 'client_condominiums.address_json'],
            ['key' => 'sindico_nome', 'label' => 'Sindico - nome', 'description' => 'Nome do sindico vinculado ao condominio.', 'source' => 'client_entities.display_name'],
            ['key' => 'sindico_cpf', 'label' => 'Sindico - CPF', 'description' => 'Documento do sindico vinculado ao condominio.', 'source' => 'client_entities.cpf_cnpj'],
            ['key' => 'unidade_numero', 'label' => 'Unidade - numero', 'description' => 'Numero da unidade vinculada.', 'source' => 'client_units.unit_number'],
            ['key' => 'bloco_nome', 'label' => 'Bloco - nome', 'description' => 'Nome do bloco ou torre da unidade vinculada.', 'source' => 'client_condominium_blocks.name'],
            ['key' => 'contrato_valor', 'label' => 'Contrato - valor', 'description' => 'Valor principal do contrato formatado em BRL.', 'source' => 'contracts.contract_value'],
            ['key' => 'contrato_valor_extenso', 'label' => 'Contrato - valor por extenso', 'description' => 'Valor principal do contrato escrito por extenso.', 'source' => 'contracts.contract_value'],
            ['key' => 'contrato_data_inicio', 'label' => 'Contrato - data de inicio', 'description' => 'Data inicial da vigencia do contrato.', 'source' => 'contracts.start_date'],
            ['key' => 'contrato_data_fim', 'label' => 'Contrato - data de termino', 'description' => 'Data final da vigencia do contrato.', 'source' => 'contracts.end_date'],
            ['key' => 'contrato_dia_vencimento', 'label' => 'Contrato - dia de vencimento', 'description' => 'Dia de vencimento configurado no contrato.', 'source' => 'contracts.due_day'],
            ['key' => 'contrato_reajuste_indice', 'label' => 'Contrato - indice de reajuste', 'description' => 'Indice de reajuste definido no contrato.', 'source' => 'contracts.adjustment_index'],
            ['key' => 'data_atual', 'label' => 'Data atual', 'description' => 'Data atual do sistema formatada em portugues.', 'source' => 'system.now'],
            ['key' => 'cidade', 'label' => 'Cidade padrao', 'description' => 'Cidade padrao das configuracoes do modulo.', 'source' => 'contract_settings.default_city'],
            ['key' => 'responsavel_nome', 'label' => 'Responsavel - nome', 'description' => 'Nome do usuario responsavel pelo contrato.', 'source' => 'users.name'],
        ];
    }

    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
