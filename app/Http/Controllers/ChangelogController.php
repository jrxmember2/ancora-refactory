<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(): View
    {
        $currentVersion = config('ancora_version.current', [
            'version' => 'v1.16',
            'date' => '16/04/2026',
            'label' => 'v1.16 • 16/04/2026',
        ]);

        $releases = [
            [
                'version' => $currentVersion['version'],
                'date' => $currentVersion['date'],
                'title' => 'Separação rígida entre parceiros e condôminos',
                'items' => [
                    'Parceiros/fornecedores passam a listar somente Síndico, Administradora e Imobiliária/Corretor.',
                    'Proprietários, locatários e inquilinos passam a ficar concentrados na tela de Condôminos, inclusive quando ainda não estão vinculados a uma unidade.',
                    'Formulários e filtros de parceiros deixam de oferecer papéis de proprietário ou locatário e o backend bloqueia envio manual desses papéis nessa seção.',
                ],
            ],
            [
                'version' => 'v1.15',
                'date' => '16/04/2026',
                'title' => 'Importação segura de unidades e auditoria',
                'items' => [
                    'Importação em massa de unidades passa a abrir prévia em modal antes de criar registros.',
                    'Duplicidade por condomínio, bloco e unidade é bloqueada no cadastro manual e na importação.',
                    'Unidades podem ser selecionadas e excluídas em massa com modal de confirmação e bloqueio quando houver OS vinculada.',
                    'Logs passam a registrar requisições de alteração dos usuários e ganham filtros por usuário, ação, módulo e período.',
                ],
            ],
            [
                'version' => 'v1.14',
                'date' => '16/04/2026',
                'title' => 'Hotfix da tela de condôminos',
                'items' => [
                    'Corrigida a montagem dos vínculos de proprietário e locatário na nova tela de condôminos.',
                    'A listagem deixa de tentar tratar arrays como models Eloquent, eliminando o erro getKey() ao acessar o menu.',
                ],
            ],
            [
                'version' => 'v1.13',
                'date' => '15/04/2026',
                'title' => 'Clientes mais seguros e listas ordenáveis',
                'items' => [
                    'Exclusão de condomínio passa a ser bloqueada quando ainda houver OS, unidades ou blocos vinculados.',
                    'Exclusão de unidade passa a ser bloqueada quando existir OS de cobrança relacionada.',
                    'Condôminos ganharam seção própria, separando proprietários e locatários da lista de parceiros e fornecedores.',
                    'Listas principais passam a ter cabeçalhos clicáveis para alternar ordenação crescente e decrescente mantendo filtros e paginação.',
                ],
            ],
            [
                'version' => 'v1.12',
                'date' => '15/04/2026',
                'title' => 'Atualização monetária TJES na cobrança',
                'items' => [
                    'Nova base de fatores mensais do índice TJES/ATM importada do relatório oficial salvo nos documentos de referência, cobrindo competências de 1969 a 2026.',
                    'OS de cobrança ganha modal para simular atualização monetária com juros legais ou contratuais, multa, custas, abatimento e honorários em percentual ou valor fixo.',
                    'Memórias de cálculo podem ser salvas no histórico da OS, aplicadas ao valor do acordo e exportadas em PDF.',
                    'Aplicação do cálculo atualiza valores das quotas, valor total do acordo, honorários e data-base da OS de forma auditável.',
                ],
            ],
            [
                'version' => 'v1.11',
                'date' => '14/04/2026',
                'title' => 'Uploads maiores em condomínios',
                'items' => [
                    'Limites PHP do container ajustados para 64 MB por arquivo e 72 MB por envio.',
                    'Validação de anexos de clientes alinhada para aceitar documentos de até 20 MB por arquivo.',
                    'Tela de cadastro de condomínio passa a informar o limite recomendado para convenção, regimento e ATAs.',
                ],
            ],
            [
                'version' => 'v1.10',
                'date' => '14/04/2026',
                'title' => 'Documento do proprietário no termo',
                'items' => [
                    'Geração do termo passa a buscar o documento cadastrado na unidade e, como fallback, no proprietário vinculado.',
                    'Documento em imagem é renderizado como última página do termo.',
                    'Documento em PDF é anexado ao final do termo por mesclagem com pdfunite no container.',
                    'Tela de revisão do termo informa qual documento será anexado ou avisa quando nenhum documento foi encontrado.',
                ],
            ],
            [
                'version' => 'v1.9',
                'date' => '14/04/2026',
                'title' => 'Composer fixado em PHP 8.3 no build',
                'items' => [
                    'Stage vendor do Dockerfile passa a executar o Composer dentro de PHP 8.3, igual ao runtime.',
                    'Imagem composer:2 fica apenas como fonte do binário Composer, evitando conflito com PHP 8.5 no composer.lock.',
                ],
            ],
            [
                'version' => 'v1.8',
                'date' => '14/04/2026',
                'title' => 'Hotfix do build Docker',
                'items' => [
                    'Etapa de dependências PHP simplificada para usar a imagem composer:2 diretamente.',
                    'Dependência da imagem php:8.3-cli removida do Dockerfile para reduzir falhas de metadata no Docker Hub durante o deploy.',
                ],
            ],
            [
                'version' => 'v1.7',
                'date' => '14/04/2026',
                'title' => 'Hub mais direto e legível',
                'items' => [
                    'Bloco institucional Ecossistema Âncora removido da tela principal do hub.',
                    'Cards dos módulos redesenhados com mais contraste, área útil e destaque visual.',
                ],
            ],
            [
                'version' => 'v1.6',
                'date' => '14/04/2026',
                'title' => 'Margens do termo de acordo',
                'items' => [
                    'PDF do termo ajustado para margens de 3 cm no topo/esquerda e 2 cm no rodapé/direita.',
                    'Altura útil da folha recalculada para manter o rodapé no final da área imprimível quando o termo não ocupa a página inteira.',
                ],
            ],
            [
                'version' => 'v1.5',
                'date' => '12/04/2026',
                'title' => 'Correção do salvamento da OS',
                'items' => [
                    'Modal de divisão automática movido para fora do formulário principal da OS, removendo formulário aninhado inválido.',
                    'Botão Salvar alterações passa a apontar explicitamente para o formulário da OS.',
                ],
            ],
            [
                'version' => 'v1.4',
                'date' => '12/04/2026',
                'title' => 'PDF compatível com EasyPanel',
                'items' => [
                    'Correção do build Docker no Debian Trixie, removendo a dependência do pacote obsoleto wkhtmltopdf.',
                    'Geração de PDF real do termo de acordo migrada para Chromium headless no container.',
                    'Fallback mantido para wkhtmltopdf quando o binário existir em algum ambiente legado.',
                ],
            ],
            [
                'version' => 'v1.3',
                'date' => '12/04/2026',
                'title' => 'PDF e layout jurídico do termo de acordo',
                'items' => [
                    'Termo de acordo com logomarca do branding claro no cabeçalho e contatos no rodapé.',
                    'Separadores do cabeçalho e rodapé na cor institucional #941415.',
                    'Assinaturas reorganizadas em duas colunas para credor/devedor e testemunhas.',
                    'Comarca e local da assinatura calculados pela cidade/UF do condomínio.',
                    'Geração de PDF real pelo container, com contingência para HTML quando nenhum motor PDF estiver disponível.',
                    'Salvar OS volta a aceitar rascunho de pagamento; a trava de plano fechado passa a ocorrer ao gerar o termo.',
                ],
            ],
            [
                'version' => 'v1.2',
                'date' => '12/04/2026',
                'title' => 'Automação e validação do plano de pagamento',
                'items' => [
                    'Botão de divisão automática para gerar entrada e parcelas mensais a partir do valor total do acordo.',
                    'Botão de parcela única preenchendo descrição, número e valor total do acordo.',
                    'Indicador dinâmico de valor restante em Parcelas / vencimentos.',
                    'Bloqueio de salvamento quando entrada e parcelas não fecham exatamente o valor do acordo.',
                    'Geração de termo blindada contra banco sem a tabela de termos aplicada, evitando erro 500 antes da migration.',
                ],
            ],
            [
                'version' => 'v1.1',
                'date' => '11/04/2026',
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
