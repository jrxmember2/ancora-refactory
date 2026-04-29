<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(): View
    {
        $currentVersion = config('ancora_version.current', [
            'version' => 'v1.75',
            'date' => '29/04/2026',
            'label' => 'v1.75 - 29/04/2026',
        ]);

        $releases = [
            [
                'version' => $currentVersion['version'],
                'date' => $currentVersion['date'],
                'title' => 'Contratos passam a priorizar mPDF para rodape estavel e paginacao real',
                'items' => [
                    'A geracao do PDF do modulo Contratos passa a priorizar mPDF, que trata melhor documentos paginados com rodape repetido, margem inferior real e numeracao honesta por pagina.',
                    'O container de deploy passa a instalar as extensoes e bibliotecas necessarias para o mPDF, incluindo gd e mbstring, sem remover os renderizadores antigos que ficam como fallback.',
                    'Os anexos selecionados continuam entrando ao final do contrato e cada pagina anexada segue em quebra propria, enquanto o corpo principal deixa de depender do print-to-pdf simples do Chromium como caminho principal.',
                ],
            ],
            [
                'version' => 'v1.74',
                'date' => '29/04/2026',
                'title' => 'PDF de contratos com Chromium DevTools para rodape real e anexos mais estaveis',
                'items' => [
                    'A geracao do PDF do contrato passa a priorizar um renderer via Chromium DevTools, em vez do print-to-pdf simples do CLI, permitindo reservar area real de rodape e destravar a paginacao correta com numero da pagina e total.',
                    'O rodape customizado deixa de disputar espaco dentro do corpo do HTML no caminho principal do contrato, reduzindo o risco de sobreposicao sobre o texto do documento.',
                    'Anexos selecionados para o contrato passam a abrir em pagina propria no final do PDF e imagens do cadastro agora preferem URI de arquivo local, o que melhora a renderizacao de JPEG e PNG no Chromium.',
                ],
            ],
            [
                'version' => 'v1.73',
                'date' => '29/04/2026',
                'title' => 'Refino do rodape do PDF no fallback por Chromium',
                'items' => [
                    'O rodape do contrato deixa de usar deslocamento negativo e passa a ser posicionado de forma mais simples no fallback por Chromium, com a reserva de espaco concentrada na margem inferior dinamica do PDF.',
                    'A margem inferior reservada para o rodape foi ampliada e o texto do fechamento ficou mais compacto, reduzindo a chance de o corpo do contrato atropelar o rodape.',
                ],
            ],
            [
                'version' => 'v1.72',
                'date' => '29/04/2026',
                'title' => 'Refino do contrato em PDF com reserva dinamica de rodape e anexos ampliados',
                'items' => [
                    'O template do PDF passa a estimar o espaco necessario do rodape customizado e amplia a margem inferior do documento conforme o volume real do conteudo, reduzindo o risco de o corpo atropelar o fechamento.',
                    'A qualificacao automatica em quadrados deixa de ser renderizada por padrao, evitando poluicao visual quando o proprio corpo do contrato ja traz a qualificacao textual das partes.',
                    'A selecao de anexos para o PDF agora considera tambem documentos vinculados a unidade, proprietario e locatario, alem de cliente, sindico e condominio.',
                ],
            ],
            [
                'version' => 'v1.71',
                'date' => '29/04/2026',
                'title' => 'Refino do PDF de contratos apos ajuste de deploy',
                'items' => [
                    'Rodape do fallback em Chromium passa a ser empurrado para dentro da margem inferior real da pagina, em vez de ficar aparente no meio da area util do documento.',
                    'Quando o PDF roda em Chromium e nao ha motor com paginacao nativa, os marcadores de pagina deixam de exibir 0 de 0 para evitar numeracao enganosa no rodape customizado.',
                    'Resolucao de anexos do cadastro ganha normalizacao extra de caminho e o PDF volta a embutir imagens selecionadas como JPEG, PNG e WEBP ao final do contrato.',
                ],
            ],
            [
                'version' => 'v1.70',
                'date' => '29/04/2026',
                'title' => 'Hotfix de deploy do PDF de contratos',
                'items' => [
                    'O Dockerfile deixa de tentar instalar wkhtmltopdf no Debian trixie do EasyPanel, evitando falha de build por pacote sem candidato disponivel.',
                    'A geracao do PDF continua preparada para usar wkhtmltopdf apenas se o binario ja existir no ambiente; caso contrario, o sistema segue no fallback por Chromium sem travar o deploy.',
                ],
            ],
            [
                'version' => 'v1.69',
                'date' => '29/04/2026',
                'title' => 'PDF de contratos com rodape mais estavel, melhor qualificacao e anexo visual',
                'items' => [
                    'A geracao do PDF passa a priorizar wkhtmltopdf quando disponivel no container, o que melhora repeticao do rodape em todas as paginas e destrava a numeracao correta usando pagina atual e total de paginas.',
                    'O fallback em Chromium ganha reserva maior de espaco para o rodape, reduzindo o risco de o corpo do contrato correr por cima da area final da pagina.',
                    'Os quadros de qualificacao foram refinados com borda em #941415, fundo mais neutro, texto mais escuro, espacamento lateral melhor e quebra de linha para conteudos longos.',
                    'Anexos visuais do cadastro voltam a ser embutidos como imagem no PDF e cada anexo passa a abrir sua propria pagina ao final do contrato, com altura controlada para nao estourar o layout.',
                ],
            ],
            [
                'version' => 'v1.68',
                'date' => '29/04/2026',
                'title' => 'Hotfix no editor e na geracao do PDF de contratos',
                'items' => [
                    'Insercao de linha horizontal no editor de contratos deixa de depender de execCommand e passa a usar insercao direta no range selecionado, estabilizando o recurso apos o uso do modal.',
                    'Geracao de PDF passa a preparar o rodape de forma especifica para wkhtmltopdf, repetindo corretamente em todas as paginas quando esse motor for o usado no servidor.',
                    'Anexos selecionados no PDF passam a usar resolucao de caminho e renderizacao de imagem mais robustas, cobrindo melhor arquivos JPEG e outros documentos visuais vindos do cadastro vinculado.',
                ],
            ],
            [
                'version' => 'v1.67',
                'date' => '29/04/2026',
                'title' => 'Hotfix estrutural do editor de templates de contratos',
                'items' => [
                    'Editor rico do modulo Contratos passa a preservar a selecao do texto ao abrir color picker, linha horizontal e tabela, permitindo aplicar cor e inserir elementos no ponto certo do documento.',
                    'Os dialogs internos do editor deixam de usar form aninhado dentro do formulario principal do template, eliminando conflitos que impediam o botao Salvar template de funcionar corretamente.',
                    'Sincronizacao do HTML editado foi reforcada antes do submit para evitar perda de conteudo ou caracteres invisiveis durante o salvamento do template.',
                ],
            ],
            [
                'version' => 'v1.66',
                'date' => '29/04/2026',
                'title' => 'Contratos com rodape por pagina, folha configuravel e editor mais completo',
                'items' => [
                    'Quadros de qualificacao do PDF passam a ficar empilhados, um abaixo do outro, preservando a organizacao interna em duas colunas invisiveis quando fizer sentido.',
                    'Rodape do contrato agora vira rodape real de pagina no PDF: o padrao do sistema aparece em todas as paginas quando nao houver customizacao, e o rodape personalizado substitui integralmente o padrao quando existir.',
                    'Templates passam a escolher tambem o tipo de folha entre A4, Oficio e Carta, e a geracao do PDF respeita esse formato junto com orientacao e margens.',
                    'Editor rico do modulo Contratos ganha tamanhos de fonte menores, tamanho digitavel, cor de fonte, insercao configuravel de linha horizontal e modal para montar tabelas com espessura de borda, inclusive invisivel.',
                ],
            ],
            [
                'version' => 'v1.65',
                'date' => '29/04/2026',
                'title' => 'Hotfix na geracao do PDF de contratos',
                'items' => [
                    'Corrigida a montagem das linhas de qualificacao do PDF do contrato, eliminando o erro array_values() expects exactly 1 argument, 2 given ao gerar a versao final.',
                    'A estrutura dos quadros de contratante e contratada continua com o novo layout, agora sem quebrar a geracao do arquivo.',
                ],
            ],
            [
                'version' => 'v1.64',
                'date' => '29/04/2026',
                'title' => 'PDF de contratos com qualificação mais elegante, anexos selecionáveis e áreas customizáveis',
                'items' => [
                    'O PDF do contrato deixa de inserir automaticamente a linha final de cidade e data fora do template, respeitando apenas o texto montado no próprio modelo.',
                    'A área de qualificação padrão foi redesenhada em dois quadros lado a lado, com linhas inteligentes e exibição apenas dos dados realmente preenchidos para contratante e contratada.',
                    'Templates passam a ter editores ricos separados para cabeçalho, qualificação e rodapé, com suporte a imagens, ícones do Font Awesome, quebra de página e marcadores de número total de páginas.',
                    'Geração de PDF passa a aceitar anexos documentais do cadastro no final do contrato, com modal de seleção tanto na tela de visualização quanto na edição do contrato já existente.',
                ],
            ],
            [
                'version' => 'v1.63',
                'date' => '29/04/2026',
                'title' => 'Hotfix no salvamento de templates e painel recolhido de variaveis',
                'items' => [
                    'A tela de template agora sincroniza explicitamente o editor rico antes do submit, evitando falso positivo de Template atualizado com sucesso quando o conteudo alterado ainda nao tinha ido para o campo oculto.',
                    'O bloco Variaveis liberadas passa a abrir recolhido por padrao e ganha botao para mostrar ou ocultar, deixando a tela mais limpa durante a edicao.',
                    'O painel informa quantas variaveis estao liberadas no template sem precisar expandir a lista inteira.',
                ],
            ],
            [
                'version' => 'v1.62',
                'date' => '29/04/2026',
                'title' => 'Hotfix no preview do contrato em modo de edicao',
                'items' => [
                    'O botao Carregar ou atualizar preview volta a funcionar ao editar contratos, mesmo quando o texto do editor for apagado antes de recarregar o template.',
                    'O frontend deixa de enviar o _method=PUT no fetch do preview, passa a exigir JSON e trata melhor respostas inesperadas para evitar o alerta bruto de Unexpected token.',
                    'O backend agora reconhece editor vazio mesmo quando sobram tags residuais como paragrafos em branco, permitindo recarregar o template de forma limpa.',
                ],
            ],
            [
                'version' => 'v1.61',
                'date' => '28/04/2026',
                'title' => 'Hotfix de Blade no template de contratos',
                'items' => [
                    'A tela de edicao de templates do modulo Contratos volta a abrir normalmente apos remover expressoes Blade sensiveis envolvendo chaves de variaveis e @php curto com arrays.',
                    'A montagem visual dos tokens {{variavel}} foi reescrita de forma segura no editor, na tela de templates, nas configuracoes e na listagem de variaveis.',
                ],
            ],
            [
                'version' => 'v1.60',
                'date' => '28/04/2026',
                'title' => 'Contratos com lixeira e historico global de assinaturas, mais rastreabilidade no DataJud',
                'items' => [
                    'Lista de contratos ganha lixeira com restauracao, exclusao apenas logica e confirmacao visual antes de mover o documento para fora da listagem principal.',
                    'Configuracoes do modulo Contratos passam a ter um historico consolidado de assinaturas digitais, com botao Abrir para saltar direto para a OS ou o contrato de origem.',
                    'Sincronizacao diaria do DataJud passa a registrar saida propria em storage/logs/datajud-sync.log, evitar sobreposicao de execucao e gravar resumo da rotina no log de auditoria.',
                    'Leitura dos movimentos do DataJud ficou mais amigavel, com datas formatadas e tentativa de expor anexos/localizadores quando o payload publico trouxer links de documentos.',
                    'Base inicial da wiki operacional do sistema foi adicionada dentro de docs/wiki para comecar o manual versionado junto do codigo.',
                ],
            ],
            [
                'version' => 'v1.59',
                'date' => '28/04/2026',
                'title' => 'Hotfix na identidade do signatario na Assinafy',
                'items' => [
                    'Ao reenviar documentos para assinatura com um e-mail ja usado em outra OS ou contrato, a integracao passa a atualizar o cadastro remoto do signatario com os dados do modal atual antes de reutiliza-lo.',
                    'Com isso, alterar nome, e-mail e telefone no modal de assinatura nao contamina o cadastro local do condomino e evita que o documento seguinte saia em nome da pessoa errada.',
                ],
            ],
            [
                'version' => 'v1.58',
                'date' => '28/04/2026',
                'title' => 'Templates de contratos com contato do sindico',
                'items' => [
                    'Catalogo de variaveis do modulo Contratos passa a expor e-mail e telefone do sindico para uso direto em templates e documentos gerados.',
                    'Renderizacao das variaveis do contrato agora busca automaticamente o e-mail principal e o telefone principal do cadastro do sindico, respeitando tanto PF quanto PJ.',
                    'Tabela de variaveis do modulo ganha sincronizacao dedicada para exibir as novas chaves sem depender de reset ou recarga manual do catalogo.',
                ],
            ],
            [
                'version' => 'v1.57',
                'date' => '28/04/2026',
                'title' => 'Variaveis de sindico mais claras e assinatura digital mais operacional',
                'items' => [
                    'Modulo Contratos ganha novas variaveis explicitas para sindico, com endereco completo, cidade, estado, CEP, tipo de pessoa, CNPJ da empresa e CPF do representante quando houver PJ.',
                    'Catalogo de variaveis deixa de confundir CPF e CNPJ do sindico, mantendo chave principal de documento e separando campos especificos para PF, PJ e representante.',
                    'Assinatura digital passa a aceitar mensagem padrao com variaveis dinamicas para contratos e OS, inclusive nome do condominio, unidade, devedor, tipo do documento e numero da OS.',
                    'Tela de assinatura recebe mascara para celular, CPF/CNPJ, papel no documento em dropdown e inclusao um a um de signatarios/testemunhas pre-cadastrados nas configuracoes.',
                    'Painel da assinatura ganha botao Copiar link e o card da OS foi reposicionado para baixo de Andamentos, deixando a leitura operacional mais natural.',
                ],
            ],
            [
                'version' => 'v1.56',
                'date' => '28/04/2026',
                'title' => 'Contratos com sindico dedicado, preview mais inteligente e integracao financeira',
                'items' => [
                    'Cadastro de contratos passa a aceitar sindicatura direta com dropdown proprio de sindicos, inclusive aproveitando o sindico do condominio quando ele ja estiver vinculado no cadastro.',
                    'Templates ganham titulo padrao de contrato, usado para preencher automaticamente a criacao do documento e reduzir digitacao repetitiva na Etapa 1.',
                    'Editor rico de templates e contratos recebe legenda nos botoes, selecao de fonte, tamanho de fonte, alinhamento de paragrafo e insercao de tabela simples.',
                    'Renderizacao das variaveis do sindico agora trata pessoa juridica com empresa, CNPJ e representante pessoa fisica quando houver, preservando melhor a qualificacao contratual.',
                    'Etapa financeira do contrato passa a integrar banco/conta e forma de pagamento do Financeiro 360, com campos monetarios em R$ e alerta para reajuste ou vencimento nos 30 dias anteriores.',
                    'Prazo indeterminado fica marcado por padrao e desativa automaticamente a data de termino enquanto estiver ativo.',
                ],
            ],
            [
                'version' => 'v1.55',
                'date' => '28/04/2026',
                'title' => 'Assinatura digital integrada com Assinafy em Contratos e OS',
                'items' => [
                    'Configuracoes da Assinafy entram no modulo Contratos, com ambiente, credenciais, URL/token de webhook e sincronizacao automatica da assinatura digital.',
                    'Contratos passam a ter aba propria de Assinaturas, com envio do PDF final para assinatura, acompanhamento por signatario, eventos do provider e download de documento assinado, certificado e pacote.',
                    'OS de cobranca ganham painel operacional de assinatura digital no proprio detalhe, com envio do termo de acordo salvo e sincronizacao do status diretamente pela Assinafy.',
                    'Webhook e sincronizacao manual atualizam o painel de conferenca, baixam artefatos quando a assinatura conclui e refletem o resultado no GED e nos status do contrato/OS.',
                    'Telas foram blindadas para nao quebrar caso o deploy suba antes da migration da assinatura digital ser executada no servidor.',
                ],
            ],
            [
                'version' => 'v1.54',
                'date' => '26/04/2026',
                'title' => 'Financeiro 360 com importacao mais segura e exportacao selecionada',
                'items' => [
                    'Importacao financeira em lote deixa de sobrescrever codigo interno de contas a receber, contas a pagar, contas bancarias e reembolsos quando o registro ja existe.',
                    'Listagens principais do Financeiro 360 passam a permitir exportar tudo ou apenas os registros marcados em CSV, XLSX, PDF e impressao.',
                    'Conciliacao bancaria, historico de importacao e relatorios PDF do financeiro receberam limpeza estrutural e ajuste de layout para reduzir ruido visual e inconsistencias de codificacao.',
                    'Exportacoes agora respeitam melhor filtros de conciliacao, faturamento selecionado e status de parcelamentos no momento de gerar os arquivos.',
                ],
            ],
            [
                'version' => 'v1.53',
                'date' => '26/04/2026',
                'title' => 'Hotfix no editor de contratos',
                'items' => [
                    'Tela de Novo contrato volta a abrir normalmente apos a remocao de expressoes Blade com chaves aninhadas no editor rico do modulo Contratos.',
                    'Seletores de variaveis do editor passaram a montar os tokens em PHP antes da renderizacao, evitando erros de compilacao em producao.',
                    'As telas de Templates e Variaveis do modulo tambem receberam o mesmo ajuste preventivo para evitar falhas semelhantes ao exibir placeholders dinamicos.',
                ],
            ],
            [
                'version' => 'v1.52',
                'date' => '26/04/2026',
                'title' => 'Novo modulo Contratos integrado ao ecossistema Âncora',
                'items' => [
                    'Sistema ganha o modulo Contratos com dashboard, CRUD completo, templates, categorias, variaveis, configuracoes e relatorios proprios no mesmo padrao visual do restante da plataforma.',
                    'Fluxo de criacao passa a permitir escolher template, substituir variaveis automaticamente, editar o preview do documento e gerar PDF versionado sem sobrescrever historico anterior.',
                    'Contratos agora aceitam vinculo com clientes, condominios, unidades, propostas e processos, alem de anexos dedicados e campos preparados para futura integracao financeira.',
                    'Cadastros de clientes, condominos, condominios e unidades passam a exibir os contratos relacionados quando existirem, facilitando navegacao cruzada entre os modulos.',
                    'Seeders idempotentes foram adicionados para popular o modulo com categorias, configuracoes, variaveis, templates iniciais, item de menu e permissoes de rota.',
                ],
            ],
            [
                'version' => 'v1.51',
                'date' => '25/04/2026',
                'title' => 'Campos monetarios no cadastro de condominios',
                'items' => [
                    'Valor do boleto e valor de cancelamento de boleto passam a usar mascara de moeda brasileira no formulario de cadastro e edicao de condominios.',
                    'Os dois campos agora exibem o prefixo R$ e formatacao automatica em tempo real para reduzir erro de digitacao e melhorar a leitura.',
                    'Compatibilidade com a gravacao existente foi preservada para que o backend continue convertendo os valores normalmente sem alterar as regras de negocio.',
                ],
            ],
            [
                'version' => 'v1.50',
                'date' => '25/04/2026',
                'title' => 'Exportacao completa do modulo Clientes',
                'items' => [
                    'Clientes avulsos, parceiros, condominios, unidades e condominos passam a ter exportacao em CSV, XLS e PDF a partir dos filtros ja usados em cada listagem.',
                    'PDFs ganham cabecalho com branding, resumo executivo e fichas detalhadas por registro para facilitar conferencia cadastral e compartilhamento.',
                    'Exportacao em XLS foi preparada para abrir diretamente no Excel e a nova permissao clientes.export centraliza o controle de acesso a esses relatorios.',
                ],
            ],
            [
                'version' => 'v1.49',
                'date' => '25/04/2026',
                'title' => 'Persistencia da foto de perfil entre deploys',
                'items' => [
                    'Upload da foto de perfil deixa de gravar no caminho efemero public/assets/uploads/users e passa a usar o disco publico do Laravel em storage/app/public.',
                    'Docker passa a preparar o atalho public/storage para servir a foto corretamente no ambiente Linux do deploy.',
                    'Compatibilidade com fotos antigas foi mantida, mas novos uploads passam a usar o caminho persistivel preparado para EasyPanel.',
                ],
            ],
            [
                'version' => 'v1.48',
                'date' => '25/04/2026',
                'title' => 'Criacao interna de demandas no backoffice',
                'items' => [
                    'Modulo Demandas ganha o fluxo de Nova demanda, permitindo abertura manual diretamente pelo backoffice sem depender do Portal do Cliente.',
                    'Formulario interno permite vincular categoria, tag inicial, responsavel, prioridade, condominio, entidade cliente, usuario do portal e anexos ja na abertura.',
                    'Mensagem inicial pode nascer interna ou visivel no portal, preservando o uso do mesmo modulo tanto para demandas operacionais quanto para interacoes com clientes.',
                ],
            ],
            [
                'version' => 'v1.47',
                'date' => '25/04/2026',
                'title' => 'Preview da foto em Meus dados',
                'items' => [
                    'Tela de Meus dados passa a exibir pre-visualizacao imediata da foto escolhida no proprio formulario antes de salvar.',
                    'Card lateral do perfil tambem se atualiza na hora com a nova imagem selecionada, mantendo o usuario seguro sobre o resultado do upload.',
                    'Quando nao houver foto escolhida, a tela volta para a foto atual ou para as iniciais do usuario automaticamente.',
                ],
            ],
            [
                'version' => 'v1.46',
                'date' => '25/04/2026',
                'title' => 'Solicitacao de boleto detalhada cota a cota',
                'items' => [
                    'Corpo do e-mail passa a listar cada cota atualizada em sua propria linha, com vencimento, total individual e composicao resumida de original, atualizacao, juros e multa.',
                    'Honorarios, custas processuais e taxas de boleto deixam de aparecer agregados junto das cotas e passam para um bloco separado de fechamento.',
                    'Leitura fica mais fiel a conferencia operacional da administradora, sem repetir totais globais das cotas quando o objetivo e enxergar cada debito separadamente.',
                ],
            ],
            [
                'version' => 'v1.45',
                'date' => '25/04/2026',
                'title' => 'E-mail de boleto com detalhamento completo da cobrança',
                'items' => [
                    'Corpo do e-mail da solicitacao de boleto passa a trazer resumo da memoria TJES e composicao detalhada da cobranca para facilitar a conferencia da administradora.',
                    'Bloco de composicao destaca principal, atualizacao monetaria, juros, multa, custas, taxas de boleto, abatimento, subtotal do debito, honorarios e total geral quando houver.',
                    'Layout foi mantido em estrutura segura para Outlook e webmails, preservando leitura rapida sem depender exclusivamente do PDF anexo.',
                ],
            ],
            [
                'version' => 'v1.44',
                'date' => '25/04/2026',
                'title' => 'Template de e-mail compatível com Outlook',
                'items' => [
                    'Solicitacao de boleto por e-mail foi reconstruida em estrutura classica de tabelas, com fallbacks mais seguros para Outlook desktop.',
                    'Cabecalho, blocos de dados, vencimentos e rodape deixam de depender de divs, gradientes e estilos modernos que costumam quebrar no Outlook.',
                    'Layout permanece consistente nos webmails e ganha renderizacao mais previsivel no Outlook e clientes baseados em Word.',
                ],
            ],
            [
                'version' => 'v1.43',
                'date' => '25/04/2026',
                'title' => 'Autoajuste de namespace IMAP para Itens enviados',
                'items' => [
                    'Espelhamento IMAP agora tenta automaticamente variacoes como INBOX.Sent e INBOX/Sent quando o servidor exigir prefixo de namespace para a pasta de enviados.',
                    'Mensagens de sucesso passam a informar qual pasta foi aceita pelo servidor IMAP, facilitando auditoria e configuracao fina da conta.',
                    'Tela de configuracao do IMAP de cobranca ganhou orientacao mais clara com exemplos de pasta de enviados para servidores comuns.',
                ],
            ],
            [
                'version' => 'v1.42',
                'date' => '25/04/2026',
                'title' => 'Espelhamento IMAP sem dependencia nativa do servidor',
                'items' => [
                    'Espelhamento de e-mails enviados para Itens enviados passa a usar conexao IMAP direta via socket no proprio PHP, sem depender da extensao nativa imap do servidor.',
                    'Dockerfile volta ao build estavel do EasyPanel, removendo a tentativa de compilar bibliotecas IMAP indisponiveis na imagem Debian atual.',
                    'Fluxo de solicitacao de boleto continua com auditoria por OS e o deploy deixa de ficar preso a pacotes nativos do container.',
                ],
            ],
            [
                'version' => 'v1.41',
                'date' => '25/04/2026',
                'title' => 'Hotfix do build IMAP no EasyPanel',
                'items' => [
                    'Build Docker do ambiente EasyPanel passa a usar o pacote libc-client2007e-dev, compatível com Debian Trixie, no lugar do pacote legado libc-client-dev.',
                    'Extensao IMAP continua preparada para compilacao com SSL/Kerberos, agora sem quebrar o deploy por nome de pacote inexistente na imagem atual.',
                ],
            ],
            [
                'version' => 'v1.40',
                'date' => '25/04/2026',
                'title' => 'Hotfix do espelhamento IMAP e layout do e-mail de boleto',
                'items' => [
                    'Dockerfile do ambiente passa a instalar a extensao IMAP do PHP com suporte a SSL/Kerberos, permitindo espelhar os e-mails de solicitacao de boleto em Itens enviados.',
                    'Template HTML da solicitacao de boleto deixa de usar flex nas linhas de vencimento e passa a renderizar em tabela, corrigindo o problema de data e valor aparecerem colados no Gmail.',
                    'Linhas de vencimento agora saem sempre no formato data - descricao opcional - valor, com separacao visual mais estavel entre clientes de e-mail.',
                ],
            ],
            [
                'version' => 'v1.39',
                'date' => '24/04/2026',
                'title' => 'Solicitacao de boleto por e-mail na OS de cobranca',
                'items' => [
                    'OS de cobranca ganha o botao Solicitar Boleto, com validacoes para exigir plano de pagamento fechado, administradora vinculada, e-mails de cobranca e memoria TJES salva.',
                    'Cadastro de administradora passa a aceitar uma lista propria de e-mails do setor de cobranca, separada dos e-mails gerais do parceiro.',
                    'Configuracoes ganham SMTP de cobranca e IMAP de cobranca para envio dedicado e preparo do espelhamento em Itens enviados.',
                    'Cada solicitacao enviada passa a registrar espelho do HTML, destinatarios, status SMTP/IMAP e anexo da memoria TJES dentro da propria OS para auditoria.',
                ],
            ],
            [
                'version' => 'v1.38',
                'date' => '24/04/2026',
                'title' => 'Hotfix da migration do historico de unidades',
                'items' => [
                    'Migration do historico de proprietarios e locatarios passa a se adaptar ao schema legado do banco, usando INT assinado para client_units e client_entities.',
                    'A mesma migration agora repara automaticamente tabelas parcialmente criadas apos tentativa que falhou no deploy, recriando foreign keys e ajustando tipos de coluna.',
                ],
            ],
            [
                'version' => 'v1.37',
                'date' => '24/04/2026',
                'title' => 'Perfil, alertas e historico de unidades',
                'items' => [
                    'Listagem de unidades passa a usar ordenacao natural descendente para numeros puros, evitando que a unidade 1001 apareca antes da 801 por ordem alfabetica.',
                    'Atualizacao monetaria TJES agora abre com honorarios padrao de 10 por cento e o PDF da memoria exibe o valor total tambem por extenso.',
                    'OS de cobranca ganha modal para carregar o payload n8n sob demanda, deixando a tela principal mais limpa.',
                    'Cadastro de unidades e condominos passa a registrar historico de proprietarios e locatarios vinculados a cada unidade.',
                    'Header ganha menu de perfil com Meus dados, upload de foto, troca de tema, link para novidades e contador de usuarios online.',
                    'Login passa a oferecer opcao de manter conectado por 12 horas, enquanto sessoes comuns expiram por inatividade curta.',
                    'Configuracoes ganham alerta global interno para avisos como manutencao programada, exibido no topo do sistema para a equipe.',
                ],
            ],
            [
                'version' => 'v1.36',
                'date' => '23/04/2026',
                'title' => 'Configuracoes por abas',
                'items' => [
                    'Tela de Configuracoes passa a ser organizada por abas internas, mantendo tudo centralizado no mesmo modulo.',
                    'Agora e possivel alternar entre Geral, Catalogos, Demandas e Usuarios/Acesso para reduzir poluicao visual e acelerar manutencoes.',
                    'Links diretos com hash, como os vindos do dashboard de demandas, abrem automaticamente a aba correta dentro de Configuracoes.',
                ],
            ],
            [
                'version' => 'v1.35',
                'date' => '23/04/2026',
                'title' => 'Kanban e SLA de demandas',
                'items' => [
                    'Modulo Demandas ganha dashboard proprio com panorama por tag, alertas de SLA vencido e demandas a vencer com menos de 10 por cento do prazo restante.',
                    'Novo Kanban permite mover demandas por tags coloridas, recalculando automaticamente status, prazo de SLA e historico interno.',
                    'Configuracoes ganham card de Demandas para criar, editar e excluir tags com cor, SLA, status interno e visibilidade no Portal do Cliente.',
                    'Portal do Cliente passa a exibir o rotulo publico da tag quando autorizado, preservando tags internas e informacoes estrategicas do escritorio.',
                ],
            ],
            [
                'version' => 'v1.34',
                'date' => '23/04/2026',
                'title' => 'Portal do Cliente com contexto por condominio',
                'items' => [
                    'Portal do Cliente ganha seletor persistente de condominio no topo, com opcao Todos para visao macro de usuarios com multiplos vinculos.',
                    'Dashboard, processos, cobrancas e solicitacoes passam a respeitar o condominio selecionado no portal.',
                    'Modulo Processos passa a localizar condominios na busca de cliente/adverso e permite vincular condominio tambem no polo adverso.',
                    'Solicitacoes do portal podem ser editadas ou canceladas pelo usuario que abriu a demanda, sempre registrando a acao no historico.',
                ],
            ],
            [
                'version' => 'v1.33',
                'date' => '23/04/2026',
                'title' => 'Edicao e filtros dos indices TJES',
                'items' => [
                    'Modal de indices TJES passa a ter botao Editar em cada competencia cadastrada.',
                    'Formulario do indice e reaproveitado para alterar mes, ano, fator e fonte de competencias existentes.',
                    'Lista de competencias ganha filtro por ano e rolagem vertical para facilitar consulta em bases historicas extensas.',
                ],
            ],
            [
                'version' => 'v1.32',
                'date' => '22/04/2026',
                'title' => 'Indices TJES no Config e hotfix da memoria de calculo',
                'items' => [
                    'Configuracoes ganham o botao INDICES TJES com modal para consultar todas as competencias ATM cadastradas e inserir o proximo fator mensal.',
                    'Cadastro manual de indice aceita virgula ou ponto no fator e atualiza a competencia existente quando mes/ano ja estiverem cadastrados.',
                    'Persistencia da memoria de calculo TJES ficou retrocompativel com bancos que ainda nao receberam as colunas de taxas de boleto, evitando erro 500 ao salvar o calculo.',
                    'PDF da memoria de calculo passa a buscar taxas de boleto tambem pelo payload salvo, preservando a visualizacao correta em ambientes antigos.',
                ],
            ],
            [
                'version' => 'v1.31',
                'date' => '20/04/2026',
                'title' => 'Hotfix do layout do Portal do Cliente',
                'items' => [
                    'Layout do Portal do Cliente deixa de usar diretiva Blade inline no topo do arquivo, evitando erro de parse em producao.',
                    'Menu do portal foi simplificado para uma montagem mais compativel com cache de views e PHP 8.3.',
                ],
            ],
            [
                'version' => 'v1.30',
                'date' => '20/04/2026',
                'title' => 'Portal com multiplos condominios por usuario',
                'items' => [
                    'Usuarios do Portal do Cliente passam a poder ser vinculados a mais de um condominio.',
                    'Escopo de processos, cobrancas e demandas do portal passa a considerar todos os condominios vinculados ao usuario.',
                    'Abertura de solicitacao no portal pede o condominio relacionado quando o usuario possui multiplos vinculos.',
                    'Cadastro de usuarios do portal passa a reabrir o modal e exibir erros detalhados quando alguma validacao impedir o salvamento.',
                ],
            ],
            [
                'version' => 'v1.29',
                'date' => '20/04/2026',
                'title' => 'Portal do Cliente e Demandas',
                'items' => [
                    'Nova area externa do Portal do Cliente com login proprio por subdominio, sessao separada e troca obrigatoria de senha no primeiro acesso.',
                    'Portal ganha dashboard, processos, cobrancas, solicitacoes e Minha Conta, sempre respeitando o escopo do cliente ou condominio vinculado.',
                    'Novo modulo interno de Demandas permite triar solicitacoes do portal, responder clientes, controlar status, prioridade, responsavel e anexos.',
                    'Cadastro de usuarios do portal foi integrado ao modulo Clientes com permissoes por recurso e vinculo a condominio ou entidade cliente.',
                ],
            ],
            [
                'version' => 'v1.28',
                'date' => '19/04/2026',
                'title' => 'Dashboard completo do modulo Processos',
                'items' => [
                    'Novo dashboard de Processos com indicadores gerais, processos ativos, encerrados, particulares e movimentos DataJud/manuais.',
                    'Tela traz graficos de evolucao mensal, distribuicao por status e panorama financeiro do acervo processual.',
                    'Hub e menu principal passam a abrir Processos pelo dashboard, mantendo a lista e o cadastro rapido no proprio modulo.',
                ],
            ],
            [
                'version' => 'v1.27',
                'date' => '19/04/2026',
                'title' => 'Hub compacto com mais módulos por linha',
                'items' => [
                    'Cards da página inicial foram reduzidos para comportar melhor o crescimento dos módulos.',
                    'Grade do Hub passa a exibir até cinco cards por linha em telas grandes.',
                    'Ícones, espaçamentos e descrições foram compactados mantendo contraste e acesso rápido.',
                ],
            ],
            [
                'version' => 'v1.26',
                'date' => '19/04/2026',
                'title' => 'Notificação de movimentações processuais',
                'items' => [
                    'Sistema passa a avisar no layout quando houver novas movimentações em processos acessíveis ao usuário.',
                    'Notificação possui botão Ver para listar os processos movimentados e Ciente para ocultar o aviso até surgir nova movimentação.',
                    'Reconhecimento é salvo por usuário e respeita processos particulares e permissões de visualização do módulo Processos.',
                ],
            ],
            [
                'version' => 'v1.25',
                'date' => '19/04/2026',
                'title' => 'Fases DataJud com mais detalhes',
                'items' => [
                    'Movimentos importados pelo DataJud passam a exibir complementos tabelados, codigo TPU, orgao julgador do movimento, dados da capa, assuntos, sistema, formato e datas de atualizacao.',
                    'Nova sincronizacao atualiza os andamentos DataJud ja existentes, permitindo enriquecer fases importadas anteriormente sem apagar o processo.',
                    'Tela de fases destaca os detalhes DataJud em bloco proprio para leitura mais clara.',
                ],
            ],
            [
                'version' => 'v1.24',
                'date' => '19/04/2026',
                'title' => 'Hotfix de permissões do módulo Processos',
                'items' => [
                    'Seed de permissões do módulo Processos passa a respeitar a estrutura real da tabela route_permissions.',
                    'Migration deixa de exigir updated_at em bancos legados que possuem apenas created_at nas permissões por rota.',
                ],
            ],
            [
                'version' => 'v1.23',
                'date' => '19/04/2026',
                'title' => 'Hotfix da migration do módulo Processos',
                'items' => [
                    'Migration do módulo Processos passa a usar o mesmo tipo de ID do cadastro legado de clientes.',
                    'Tentativas parcialmente criadas no banco são reparadas antes de retomar a criação das tabelas de fases e anexos.',
                ],
            ],
            [
                'version' => 'v1.22',
                'date' => '19/04/2026',
                'title' => 'Módulo Processos com fases, anexos e DataJud',
                'items' => [
                    'Novo módulo Processos para cadastrar casos judiciais e administrativos com dados principais, partes, valores, descrição e encerramento.',
                    'Configurações ganham cadastros próprios do módulo Processos, incluindo status, tipo de ação, tipo de processo, posições, natureza, possibilidade de ganho, encerramento e tribunal DataJud.',
                    'Processos passam a ter abas de fases/andamentos e anexos, com marcações de privacidade e parecer revisado.',
                    'Sincronização com a API pública DataJud preparada por processo e por rotina diária às 06:00 via Laravel Scheduler.',
                    'Processos particulares ficam restritos ao responsável, ao usuário que cadastrou e aos superadmins.',
                ],
            ],
            [
                'version' => 'v1.21',
                'date' => '19/04/2026',
                'title' => 'Correção visual dos gráficos de cobrança',
                'items' => [
                    'Cards de gráficos do dashboard de cobrança passam a conter corretamente os gráficos ApexCharts dentro da coluna.',
                    'Containers de evolução dos acordos e OS criadas receberam contenção responsiva para não invadir o card de OS recentes.',
                    'Configuração dos gráficos passa a declarar largura de 100% para respeitar o tamanho disponível do card.',
                ],
            ],
            [
                'version' => 'v1.20',
                'date' => '19/04/2026',
                'title' => 'Dashboard de cobrança com visão mensal e evolução',
                'items' => [
                    'Dashboard de cobrança ganha cards de OS no mês, acordos no mês, honorários do mês e honorários anual.',
                    'Card de valor em acordos passa a deixar claro o total anual e ganha contraponto mensal.',
                    'Bloco Fluxo sugerido foi substituído por gráficos de evolução mensal de acordos, honorários e OS criadas.',
                    'Evolução respeita o ano selecionado no filtro do dashboard, mantendo o mês de referência alinhado ao período escolhido.',
                ],
            ],
            [
                'version' => 'v1.19',
                'date' => '16/04/2026',
                'title' => 'OS mais profissional e relatório de faturamento',
                'items' => [
                    'Formulário da OS passa a consolidar etapa/situação, entrada, honorários e parcelas em um único bloco financeiro mais objetivo.',
                    'Status da entrada ganha opção Outro com campo livre, sem aumentar a complexidade do cadastro principal.',
                    'Novo relatório de faturamento em cobrança com filtros por status, condomínio, tipo de cobrança e período de faturamento.',
                    'Relatório agrupa dados por condomínio, bloco e unidade, somando acordo, valor recebido, valor projetado e honorários.',
                    'Exportação em PDF do relatório passa a usar a logo do branding e layout próprio para fechamento financeiro.',
                ],
            ],
            [
                'version' => 'v1.18',
                'date' => '16/04/2026',
                'title' => 'Timezone global de São Paulo',
                'items' => [
                    'Timezone global do Laravel alterado para America/Sao_Paulo por padrão.',
                    'Datas e horários gerados por now(), PDFs, logs, timelines e relatórios passam a seguir o horário de Brasília/São Paulo.',
                    'Arquivo .env.example passa a declarar APP_TIMEZONE=America/Sao_Paulo para deixar a configuração explícita em novos ambientes.',
                ],
            ],
            [
                'version' => 'v1.17',
                'date' => '16/04/2026',
                'title' => 'Logs mais claros para auditoria',
                'items' => [
                    'Tela de logs passa a exibir ações em linguagem clara, como Criou condomínio, Atualizou unidade e Importou unidades.',
                    'Detalhes técnicos de requisição passam a ser substituídos por mensagens compreensíveis, incluindo o nome do registro quando disponível.',
                    'Filtros de ações e módulos agora mostram rótulos amigáveis em vez de nomes técnicos de rotas e tabelas.',
                    'Logs específicos de importação, cobrança e proposta deixam de gerar uma segunda linha genérica duplicada no middleware.',
                ],
            ],
            [
                'version' => 'v1.16',
                'date' => '16/04/2026',
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
