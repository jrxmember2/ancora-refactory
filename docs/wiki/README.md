# Wiki do Ancora Hub

Este diretorio foi criado para concentrar a documentacao operacional do sistema.

## Estrutura sugerida

- `manual-operacao-ancora-hub.md`
  Manual principal de uso do sistema.
- `modulos/`
  Materiais especificos por modulo.
- `checklists/`
  Rotinas operacionais, implantacao e validacoes.
- `faq/`
  Perguntas recorrentes do escritorio.

## Como transformar isso em uma wiki

### Opcao 1: wiki interna do proprio repositorio

1. Mantenha os arquivos neste diretorio `docs/wiki`.
2. Crie um indice principal neste `README.md`.
3. Adicione links entre as paginas em Markdown.
4. Versione tudo junto com o codigo.
5. Sempre que um fluxo do sistema mudar, atualize a pagina correspondente no mesmo deploy.

Essa opcao e a mais simples porque a documentacao nasce junto do produto e nao se perde.

### Opcao 2: GitHub Wiki

1. Abra o repositorio no GitHub.
2. Va na aba `Settings`.
3. Em `Features`, habilite `Wikis`.
4. Abra a aba `Wiki`.
5. Crie a pagina inicial `Home`.
6. Copie o conteudo base do arquivo `manual-operacao-ancora-hub.md`.
7. Crie paginas separadas por modulo:
   - Hub
   - Clientes
   - Propostas
   - Cobrancas
   - Processos
   - Contratos
   - Financeiro 360
   - Portal do Cliente
   - Configuracoes
8. Adicione um menu lateral com links entre as paginas.
9. Defina um responsavel interno para revisar a wiki a cada mudanca relevante do sistema.

### Opcao 3: Notion ou Google Sites

1. Crie uma pagina principal `Manual Ancora Hub`.
2. Crie subpaginas por modulo.
3. Use o conteudo desta pasta como base inicial.
4. Mantenha o documento com data da ultima revisao.
5. Sempre que uma funcionalidade for alterada, atualize o manual no mesmo dia.

## Regra de ouro

Toda nova funcionalidade relevante deve sair com:

1. codigo implementado;
2. validacao minima;
3. trecho do manual atualizado.

Assim a wiki acompanha o sistema e nao vira documento abandonado.
