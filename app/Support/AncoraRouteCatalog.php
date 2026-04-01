<?php

namespace App\Support;

class AncoraRouteCatalog
{
    public static function groups(): array
    {
        return [
            'dashboard' => [
                'label' => 'Painel e busca',
                'routes' => [
                    'dashboard.index' => 'Acessar dashboard executivo',
                    'busca.index' => 'Acessar busca global',
                    'logs.index' => 'Acessar logs e auditoria',
                ],
            ],
            'config' => [
                'label' => 'Configurações',
                'routes' => [
                    'config.index' => 'Acessar configurações',
                    'config.branding.save' => 'Salvar branding',
                    'config.favicon.save' => 'Salvar favicon',
                    'config.modules.save' => 'Salvar módulos',
                    'config.smtp.save' => 'Salvar SMTP do sistema',
                    'config.access-profiles.save' => 'Salvar perfil de acesso',
                    'config.access-profiles.delete' => 'Excluir perfil de acesso',
                    'config.administradoras.store' => 'Cadastrar administradora',
                    'config.administradoras.update' => 'Editar administradora',
                    'config.administradoras.delete' => 'Excluir administradora',
                    'config.servicos.store' => 'Cadastrar serviço',
                    'config.servicos.update' => 'Editar serviço',
                    'config.servicos.delete' => 'Excluir serviço',
                    'config.status.store' => 'Cadastrar status',
                    'config.status.update' => 'Editar status',
                    'config.status.delete' => 'Excluir status',
                    'config.formas.store' => 'Cadastrar forma de envio',
                    'config.formas.update' => 'Editar forma de envio',
                    'config.formas.delete' => 'Excluir forma de envio',
                    'config.users.store' => 'Cadastrar usuário',
                    'config.users.update' => 'Editar usuário',
                    'config.users.delete' => 'Excluir usuário',
                ],
            ],
            'clientes' => [
                'label' => 'Clientes',
                'routes' => [
                    'clientes.index' => 'Acessar módulo clientes',
                    'clientes.avulsos' => 'Listar avulsos',
                    'clientes.avulsos.create' => 'Novo avulso',
                    'clientes.avulsos.store' => 'Salvar avulso',
                    'clientes.avulsos.edit' => 'Editar avulso',
                    'clientes.avulsos.update' => 'Atualizar avulso',
                    'clientes.avulsos.delete' => 'Excluir avulso',
                    'clientes.contatos' => 'Listar contatos',
                    'clientes.contatos.create' => 'Novo contato',
                    'clientes.contatos.store' => 'Salvar contato',
                    'clientes.contatos.edit' => 'Editar contato',
                    'clientes.contatos.update' => 'Atualizar contato',
                    'clientes.contatos.delete' => 'Excluir contato',
                    'clientes.condominios' => 'Listar condomínios',
                    'clientes.condominios.create' => 'Novo condomínio',
                    'clientes.condominios.store' => 'Salvar condomínio',
                    'clientes.condominios.edit' => 'Editar condomínio',
                    'clientes.condominios.update' => 'Atualizar condomínio',
                    'clientes.condominios.delete' => 'Excluir condomínio',
                    'clientes.unidades' => 'Listar unidades',
                    'clientes.unidades.create' => 'Nova unidade',
                    'clientes.unidades.store' => 'Salvar unidade',
                    'clientes.unidades.edit' => 'Editar unidade',
                    'clientes.unidades.update' => 'Atualizar unidade',
                    'clientes.unidades.delete' => 'Excluir unidade',
                    'clientes.config' => 'Configurar tipos de clientes',
                    'clientes.config.types.store' => 'Cadastrar tipo',
                    'clientes.attachments.download' => 'Baixar anexo cliente',
                    'clientes.attachments.delete' => 'Excluir anexo cliente',
                ],
            ],
            'propostas' => [
                'label' => 'Propostas',
                'routes' => [
                    'propostas.dashboard' => 'Acessar dashboard de propostas',
                    'propostas.index' => 'Listar propostas',
                    'propostas.export.csv' => 'Exportar CSV',
                    'propostas.create' => 'Nova proposta',
                    'propostas.store' => 'Salvar proposta',
                    'propostas.show' => 'Visualizar proposta',
                    'propostas.print' => 'Imprimir proposta',
                    'propostas.edit' => 'Editar proposta',
                    'propostas.update' => 'Atualizar proposta',
                    'propostas.delete' => 'Excluir proposta',
                    'propostas.attachments.upload' => 'Enviar anexo de proposta',
                    'propostas.attachments.download' => 'Baixar anexo de proposta',
                    'propostas.attachments.delete' => 'Excluir anexo de proposta',
                    'propostas.document.edit' => 'Editar documento premium',
                    'propostas.document.save' => 'Salvar documento premium',
                    'propostas.document.preview' => 'Visualizar documento premium',
                    'propostas.document.pdf' => 'PDF/print documento premium',
                ],
            ],
        ];
    }

    public static function flat(): array
    {
        $flat = [];
        foreach (self::groups() as $group) {
            foreach ($group['routes'] as $routeName => $label) {
                $flat[$routeName] = $label;
            }
        }
        return $flat;
    }
}
