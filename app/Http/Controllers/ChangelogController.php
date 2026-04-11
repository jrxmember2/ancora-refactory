<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(): View
    {
        $currentVersion = config('ancora_version.current', [
            'version' => 'v1.1',
            'date' => '11/04/2026',
            'label' => 'v1.1 • 11/04/2026',
        ]);

        $releases = [
            [
                'version' => $currentVersion['version'],
                'date' => $currentVersion['date'],
                'title' => 'Termo de acordo automático nas OS de cobrança',
                'items' => [
                    'Geração automática de termo de confissão de dívida a partir dos dados da OS.',
                    'Modelos distintos para unidade não ajuizada e unidade ajuizada com número de processo.',
                    'Tela de revisão e customização integral do texto antes da emissão.',
                    'Página A4 de PDF/print para salvar o termo final pelo navegador.',
                    'Nova tabela de termos vinculada à OS e permissões específicas de acesso.',
                ],
            ],
            [
                'version' => 'v1.0',
                'date' => '11/04/2026',
                'title' => 'Estabilização pré-deploy e versionamento v1.x',
                'items' => [
                    'Versionamento reajustado para a série v1.x com casas decimais.',
                    'Importação de inadimplência aceita datas seriais do Excel e exibe datas normalizadas na prévia.',
                    'Lotes sem linhas prontas não são mais marcados como processados em silêncio.',
                    'Reprocessamento seguro para lotes antigos que foram processados sem criar OS, atualizar OS ou gerar duplicidade.',
                    'Correções de estabilidade em sessão, formulários administrativos e feedback de importação antes do deploy.',
                ],
            ],
            [
                'version' => 'v0.10',
                'date' => '10/04/2026',
                'title' => 'Importação de inadimplência por planilha no módulo Cobrança',
                'items' => [
                    'Novo submódulo para importar inadimplência por .xls e .xlsx dentro de Cobrança.',
                    'Prévia do lote com conferência de unidade, pendências, duplicidades e linhas prontas.',
                    'Criação automática de OS apta para notificar quando não houver cobrança aberta.',
                    'Complemento automático de OS existente com andamento gerado pelo sistema.',
                    'Botões para baixar planilha modelo e exemplo preenchido diretamente na tela de importação.',
                ],
            ],
            [
                'version' => 'v0.9',
                'date' => '02/04/2026',
                'title' => 'Endereços vinculados, changelog e refinamentos de UX',
                'items' => [
                    'Endereço principal igual ao de cobrança com desativação automática do card de cobrança.',
                    'Modais de visualização de clientes centralizados em tela.',
                    'Página de versionamento e changelog integrada ao sistema.',
                    'Login com Powered by Serratech alinhado à esquerda.',
                    'Formulário de condomínio com máscara de CNPJ e melhor feedback de anexos.',
                ],
            ],
            [
                'version' => 'v0.8',
                'date' => '02/04/2026',
                'title' => 'Correções de documentos de condomínio e ajustes de apresentação',
                'items' => [
                    'Correção do fluxo de upload de documentos do condomínio.',
                    'Texto da experiência inicial reescrito com foco comercial.',
                    'Ajustes visuais do Powered by Serratech.',
                ],
            ],
            [
                'version' => 'v0.7',
                'date' => '02/04/2026',
                'title' => 'Clientes com ações rápidas e importação de unidades',
                'items' => [
                    'Botões de visualizar e excluir diretamente nas listagens do módulo Clientes.',
                    'Importação em massa de unidades por CSV.',
                    'Saudação dinâmica por horário no hub.',
                    'Home mais enxuta e objetiva.',
                ],
            ],
            [
                'version' => 'v0.6',
                'date' => '02/04/2026',
                'title' => 'Clientes e condomínios fortalecidos',
                'items' => [
                    'Fluxos de cadastro de avulsos, parceiros, condomínios e unidades endurecidos.',
                    'Melhoria no tratamento de erros para evitar 500 bruto no módulo Clientes.',
                    'Cards e formulários preparados para evolução do cadastro condominial.',
                ],
            ],
            [
                'version' => 'v0.5',
                'date' => '02/04/2026',
                'title' => 'Endereços nacionais com CEP, UF e município',
                'items' => [
                    'UF em dropdown com estados do Brasil.',
                    'Município em dropdown dinâmico por UF.',
                    'Busca por CEP com preenchimento automático de endereço.',
                ],
            ],
            [
                'version' => 'v0.4',
                'date' => '02/04/2026',
                'title' => 'Clientes com regras PF/PJ e anexos mais claros',
                'items' => [
                    'Perfil/papel vindo de configuração.',
                    'Regras de PF/PJ aplicadas nos formulários.',
                    'Status Inativo com campos condicionais.',
                    'Estrutura de proprietário e locatário em cards dedicados.',
                ],
            ],
            [
                'version' => 'v0.3',
                'date' => '02/04/2026',
                'title' => 'Configurações, branding e autenticação refinados',
                'items' => [
                    'Fallback de logo e favicon para evitar imagens quebradas no redeploy.',
                    'Configuração de SMTP e recuperação de senha com resposta genérica.',
                    'Melhorias no login, rodapé e perfis de acesso.',
                ],
            ],
            [
                'version' => 'v0.2',
                'date' => '01/04/2026',
                'title' => 'Configurações administrativas mais robustas',
                'items' => [
                    'Branding com slogans, thumbs e uploads mais claros.',
                    'Catálogos de serviços, status e formas de envio reorganizados.',
                    'Permissões por rota e perfis de acesso evoluídos.',
                ],
            ],
            [
                'version' => 'v0.1',
                'date' => '01/04/2026',
                'title' => 'Base Laravel/TailAdmin integrada ao Âncora',
                'items' => [
                    'Branding dinâmico no layout.',
                    'Documento premium reimplantado com template Aquarela.',
                    'Anexos, rotas e SQL corrigido para a nova base.',
                ],
            ],
            [
                'version' => 'v0.0',
                'date' => '01/04/2026',
                'title' => 'Reescrita Big Bang da nova base',
                'items' => [
                    'Nova estrutura Laravel em cima do TailAdmin.',
                    'Módulos iniciais de Hub, Propostas, Clientes, Configurações e Logs.',
                    'Deploy preparado para EasyPanel com Dockerfile compatível.',
                ],
            ],
        ];

        return view('pages.changelog.index', [
            'title' => 'Versionamento e changelog',
            'releases' => $releases,
        ]);
    }
}
