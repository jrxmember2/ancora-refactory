# Manual de Operacao - Ancora Hub

## 1. Objetivo do sistema

O Ancora Hub centraliza a operacao do escritorio em um unico ambiente:

- Hub inicial
- Clientes
- Condominios
- Unidades
- Condominos
- Propostas
- Cobrancas
- Processos
- Demandas
- Contratos
- Financeiro 360
- Portal do Cliente
- Configuracoes

O objetivo do sistema e organizar a rotina juridica, operacional e administrativa do escritorio com rastreabilidade, historico e integracao entre modulos.

## 2. Perfis de acesso

O sistema pode ter perfis diferentes conforme o usuario:

- superadmin
- administracao interna
- operacional
- juridico
- financeiro
- usuarios do portal do cliente

Cada usuario deve receber apenas os modulos e rotas que realmente precisa acessar.

## 3. Tela inicial - Hub

Ao entrar no sistema, o usuario visualiza o Hub.

No Hub ficam os cards de acesso rapido aos modulos habilitados para aquele usuario.

Boas praticas:

- usar o Hub como ponto de entrada principal;
- observar alertas globais no topo do sistema;
- acompanhar notificacoes de movimentacao de processos;
- revisar novidades quando houver nova entrega publicada.

## 4. Modulo Clientes

O modulo Clientes concentra a base cadastral principal do sistema.

### 4.1 O que pode ser cadastrado

- clientes avulsos
- parceiros e fornecedores
- condominios
- unidades
- condominos
- usuarios do portal do cliente

### 4.2 Boas praticas

- preencher CPF/CNPJ corretamente;
- manter e-mails e telefones atualizados;
- vincular unidades ao condominio correto;
- revisar historico de proprietario e locatario quando houver troca;
- usar anexos quando houver documentos importantes.

## 5. Modulo Propostas

O modulo Propostas organiza as propostas comerciais e juridicas do escritorio.

Fluxo recomendado:

1. criar proposta;
2. preencher escopo, valores e servicos;
3. revisar documento;
4. gerar PDF;
5. anexar retorno do cliente quando necessario.

## 6. Modulo Cobrancas

O modulo Cobrancas controla as OSs de cobranca.

### 6.1 Fluxo basico

1. criar OS;
2. vincular condominio, unidade e devedor;
3. cadastrar quotas em aberto;
4. calcular atualizacao monetaria TJES;
5. gerar termo de acordo, quando aplicavel;
6. solicitar boleto;
7. acompanhar andamentos, GED e assinatura digital.

### 6.2 Recursos importantes

- memoria de calculo TJES;
- PDF da atualizacao;
- pedido de boleto por e-mail;
- historico dos e-mails enviados;
- assinatura digital do termo;
- GED da OS;
- timeline de andamentos.

## 7. Modulo Processos

O modulo Processos organiza o contencioso do escritorio.

### 7.1 Dados principais

- numero do processo
- cliente
- adverso
- tipo
- status
- natureza
- valores processuais
- fases
- anexos

### 7.2 Integracao com DataJud

Quando o processo possui numero CNJ e tribunal DataJud configurados, o sistema pode sincronizar movimentacoes automaticamente.

Recomendacoes:

- conferir se o numero CNJ esta correto;
- conferir se o tribunal DataJud esta correto;
- acompanhar a data da ultima sincronizacao;
- revisar os detalhes do movimento importado;
- usar o botao `Sincronizar DataJud` para teste manual quando necessario.

## 8. Modulo Demandas

O modulo Demandas concentra solicitacoes internas e do Portal do Cliente.

### 8.1 O que e possivel fazer

- abrir demanda interna;
- receber demanda do portal;
- responder cliente;
- anexar arquivos;
- acompanhar historico;
- organizar por tags;
- acompanhar SLA;
- usar o kanban operacional.

### 8.2 Boas praticas

- manter a tag atualizada;
- usar status claros;
- nao expor mensagem interna ao cliente quando nao for apropriado;
- acompanhar alertas de SLA a vencer e vencido.

## 9. Modulo Contratos

O modulo Contratos concentra contratos, termos, aditivos, distratos e documentos correlatos.

### 9.1 Estrutura do modulo

- dashboard
- contratos
- templates
- categorias
- variaveis
- relatorios
- configuracoes

### 9.2 Fluxo de criacao de contrato

1. abrir `Contratos > Novo contrato`;
2. preencher dados principais;
3. escolher template;
4. revisar dados contratuais e financeiros;
5. revisar o preview editavel;
6. salvar rascunho ou gerar PDF;
7. anexar documentos complementares;
8. se necessario, enviar para assinatura digital.

### 9.3 Templates

Os templates servem como base dos contratos.

Eles aceitam variaveis dinamicas, por exemplo:

- dados do cliente;
- dados do condominio;
- dados do sindico;
- dados da unidade;
- dados do contrato;
- dados de signatarios e testemunhas pre-cadastrados.

### 9.4 Assinatura digital

O modulo Contratos possui integracao com a Assinafy.

Fluxo recomendado:

1. configurar a Assinafy em `Contratos > Configuracoes`;
2. revisar signatarios padrao;
3. gerar o PDF final;
4. abrir a aba `Assinaturas`;
5. montar os participantes do envio;
6. enviar para assinatura;
7. acompanhar o painel de status;
8. baixar original, assinado, certificado e pacote quando disponiveis.

## 10. Modulo Financeiro 360

O modulo Financeiro 360 e o ERP financeiro central do escritorio.

### 10.1 Estrutura principal

- dashboard
- fluxo de caixa
- contas a receber
- contas a pagar
- faturamento
- bancos e contas
- conciliacao bancaria
- cobrancas
- inadimplencia
- centros de custo
- categorias financeiras
- parcelamentos
- reembolsos
- custas processuais
- prestacao de contas
- DRE
- relatorios
- configuracoes

### 10.2 Uso recomendado

- manter categorias e centros de custo padronizados;
- usar bancos e contas corretamente;
- dar baixa apenas com conferenca;
- usar filtros antes de exportar relatorios;
- revisar conciliacao periodicamente.

## 11. Portal do Cliente

O Portal do Cliente e uma area segregada para clientes externos.

Nele o cliente pode:

- acessar dashboard proprio;
- visualizar processos permitidos;
- visualizar cobrancas permitidas;
- abrir solicitacoes;
- acompanhar respostas;
- trocar senha;
- filtrar por condominio quando possuir mais de um vinculo.

## 12. Configuracoes

A area de Configuracoes concentra parametros globais do sistema.

Exemplos:

- branding
- favicon
- modulos
- alerta global
- SMTP
- indices TJES
- configuracao de demandas
- usuarios internos
- perfis de acesso

Boas praticas:

- alterar configuracoes sensiveis apenas com revisao;
- registrar testes apos mexer em SMTP, integracoes ou indices;
- manter acessos de usuarios revisados periodicamente.

## 13. Exportacoes e importacoes

O sistema possui exportacoes em varios modulos.

Formatos mais comuns:

- CSV
- XLSX
- PDF

Recomendacoes:

- sempre filtrar antes de exportar;
- validar se a exportacao deve considerar tudo ou apenas selecionados;
- guardar PDFs oficiais quando forem documentos de auditoria.

## 14. Boas praticas operacionais

### Cadastro

- evitar duplicidade de pessoas e condominios;
- revisar dados obrigatorios antes de salvar;
- manter documentos e contatos atualizados.

### Processos e cobrancas

- registrar andamentos relevantes;
- anexar documentos no GED certo;
- revisar calculos antes de aplicar na OS;
- acompanhar assinaturas e boletos enviados.

### Contratos

- padronizar templates;
- revisar variaveis antes de gerar o PDF final;
- usar historico de versoes;
- nunca substituir documento assinado sem gerar nova versao.

### Financeiro

- usar categorias consistentes;
- conferir baixas;
- revisar saldos por conta;
- acompanhar vencimentos e inadimplencia.

## 15. Rotina diaria sugerida

1. abrir o Hub;
2. verificar alertas globais;
3. verificar notificacoes de processos;
4. revisar demandas novas;
5. revisar cobrancas em andamento;
6. revisar contratos pendentes de assinatura;
7. conferir movimentacoes financeiras do dia;
8. validar pendencias de faturamento e vencimentos.

## 16. Rotina semanal sugerida

1. revisar contratos proximos do vencimento;
2. revisar reajustes proximos;
3. revisar contas a receber vencidas;
4. revisar contas a pagar da semana;
5. revisar DRE e fluxo de caixa;
6. revisar processos sem movimentacao recente;
7. revisar lixeira e cadastros inconsistentes.

## 17. Solucao de problemas

### Nao apareceu um modulo no menu

- verificar se a migration/seed foi rodada;
- verificar se o modulo esta habilitado;
- verificar se o usuario tem permissao para o modulo.

### Assinatura digital nao envia

- conferir configuracao da Assinafy;
- conferir se o PDF existe;
- conferir e-mail, telefone e documento dos signatarios;
- conferir webhook e status no painel.

### DataJud nao sincroniza

- conferir numero do processo;
- conferir tribunal DataJud;
- conferir chave da API;
- conferir scheduler do Laravel;
- revisar logs da sincronizacao.

### PDF nao gera

- conferir template;
- conferir corpo editavel;
- conferir dados obrigatorios;
- revisar logs do servidor se houver falha de renderizacao.

## 18. Controle de revisao do manual

Sempre que um modulo receber alteracao relevante:

1. atualizar esta documentacao;
2. registrar a data da revisao;
3. informar brevemente o que mudou.

### Ultima revisao

- 28/04/2026
