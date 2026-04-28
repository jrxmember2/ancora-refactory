<?php

namespace App\Support\Signatures;

class DocumentSignatureCatalog
{
    public static function roleOptions(): array
    {
        return [
            'signatario' => 'Signatario',
            'devedor' => 'Devedor',
            'credor' => 'Credor',
            'fiador' => 'Fiador',
            'testemunha' => 'Testemunha',
            'aprovador' => 'Aprovador',
            'endossante' => 'Endossante',
            'endossatario' => 'Endossatario',
        ];
    }

    public static function roleLabels(): array
    {
        return array_values(static::roleOptions());
    }

    public static function messageVariableDefinitions(): array
    {
        return [
            ['key' => 'documento_titulo', 'label' => 'Documento - titulo', 'description' => 'Titulo principal do documento enviado para assinatura.'],
            ['key' => 'tipo_documento', 'label' => 'Documento - tipo', 'description' => 'Tipo do contrato ou termo enviado.'],
            ['key' => 'cliente_nome', 'label' => 'Cliente - nome', 'description' => 'Cliente principal do contrato, quando existir.'],
            ['key' => 'condominio_nome', 'label' => 'Condominio - nome', 'description' => 'Nome do condominio relacionado ao documento.'],
            ['key' => 'condominio_cidade', 'label' => 'Condominio - cidade', 'description' => 'Cidade do condominio vinculado.'],
            ['key' => 'unidade_numero', 'label' => 'Unidade - numero', 'description' => 'Numero da unidade vinculada, quando houver.'],
            ['key' => 'bloco_nome', 'label' => 'Bloco - nome', 'description' => 'Nome do bloco ou torre da unidade vinculada.'],
            ['key' => 'sindico_nome', 'label' => 'Sindico - nome', 'description' => 'Nome simples do sindico vinculado ao documento.'],
            ['key' => 'devedor_nome', 'label' => 'Devedor - nome', 'description' => 'Nome do devedor na OS de cobranca.'],
            ['key' => 'devedor_documento', 'label' => 'Devedor - documento', 'description' => 'CPF ou CNPJ do devedor na OS de cobranca.'],
            ['key' => 'os_numero', 'label' => 'OS - numero', 'description' => 'Numero da OS de cobranca.'],
            ['key' => 'contrato_codigo', 'label' => 'Contrato - codigo', 'description' => 'Codigo interno do contrato, quando houver.'],
            ['key' => 'cidade', 'label' => 'Cidade padrao', 'description' => 'Cidade do documento ou configuracao padrao do modulo.'],
            ['key' => 'data_atual', 'label' => 'Data atual', 'description' => 'Data atual por extenso no sistema.'],
        ];
    }
}
