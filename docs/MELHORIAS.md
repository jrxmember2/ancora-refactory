# Âncora — Plano de Melhorias / "Consertar a colcha de retalhos"

> **Princípio nº 1: nada de big bang.** O sistema está em produção e funciona. Toda
> melhoria aqui é incremental, reversível e com a funcionalidade existente preservada.
> Refatoração de comportamento = primeiro um teste que prova o comportamento atual,
> depois a mudança.

Legenda de esforço/risco: 🟢 baixo · 🟡 médio · 🔴 alto.
Prioridade: **P1** (faça primeiro / habilita o resto) → **P3** (quando der).

---

## P1 — Fundações para refatorar com segurança

Estes itens não mudam comportamento; eles criam a **rede de segurança** para mexer no resto.

### 1.1 🟢 Higiene do repositório
- Remover/arquivar resíduos: `tmp_ensurehub.txt`, `ancora_processos_fix.patch`,
  `tailadmin-laravel.png` (mover para `docs/referencias-pdf/` ou apagar).
- Garantir que `scripts/__pycache__` esteja no `.gitignore`.
- **Por quê:** reduz ruído e sinais de "patchwork"; zero risco.

### 1.2 🟡 Definir uma fonte de verdade do schema
Hoje há `database/sql/` **e** `database/migrations/` (ver ARQUITETURA §6). Proposta:
1. Tirar um dump do **banco de produção atual** como baseline versionado
   (`database/sql/baseline_<data>.sql`), marcado como "estado canônico".
2. Documentar no README qual SQL importar + quais migrations rodar, em ordem, para um
   ambiente limpo bater 1:1 com produção.
3. Daqui pra frente: **todo** novo schema só via migration (nunca editar SQL legado).
- **Por quê:** elimina a divergência entre ambientes — a maior causa de bugs de deploy.
- **Não fazer:** rodar `migrate:fresh` em produção; reescrever as migrations antigas.

### 1.3 🟡 Caracterização por testes (antes de refatorar)
Antes de quebrar qualquer controller gigante, escrever **testes de caracterização**
(feature tests que exercitam a rota e congelam a saída atual). Priorizar Cobranças,
Financeiro e Importações — onde o risco financeiro é real.
- Hoje: 10 arquivos Pest. Meta P1: cobrir os fluxos críticos de Cobranças/Financeiro.
- **Por quê:** sem isso, qualquer refatoração é aposta.

### 1.4 🟢 Pint + análise estática no CI
- `laravel/pint` já está no `composer.json` — adotar como gate (já existe config?).
- Adicionar PHPStan/Larastan em nível baixo (ex. level 3) e subir gradualmente.
- **Por quê:** trava regressões de estilo/tipo sem tocar em lógica.

---

## P2 — Quebrar os monólitos (incremental, módulo a módulo)

### 2.1 🔴 Decompor `CobrancaController` (7.245 linhas)
Estratégia "strangler" — **não** reescrever de uma vez:
- O controller já tem fronteiras naturais (importação, termos de acordo, atualização
  monetária, boletos, notificações, anexos/timeline). Extrair **um grupo por vez** para
  controllers dedicados sob `App\Http\Controllers\Cobranca\` (ex.
  `CobrancaImportController`, `CobrancaAgreementController`, `CobrancaMonetaryController`).
- Mover regra de negócio para os Services que **já existem**
  (`CobrancaMonetaryUpdateService`, `CobrancaAgreementTermService`, etc.) — o controller
  vira fino.
- Manter os **nomes de rota idênticos** (só muda o `uses` no `web.php`) → zero impacto
  em views, permissões e links.
- Cada extração: teste de caracterização → mover → rodar testes → commit pequeno.

### 2.2 🟡 Aplicar o mesmo a `ClientsController`, `ProcessController`, `ConfigController`, `FinancialController`
Mesma técnica. Ordem sugerida por risco/retorno: Config (mais isolado) →
Clientes → Processos → Financeiro.

### 2.3 🟡 Fatiar `routes/web.php` (68 KB)
Dividir em arquivos por domínio carregados via `Route::middleware(...)->group(base_path('routes/web/cobranca.php'))`.
Mantém URLs e nomes; só reorganiza. Reduz conflitos de merge drasticamente.

### 2.4 🟡 Padronizar validação em Form Requests
Migrar `$request->validate()` inline para `App\Http\Requests\*` (o diretório já existe e
é usado em Automação). Centraliza regras, facilita reuso e teste.

---

## P3 — Evoluções estruturais (avaliar custo/benefício)

### 3.1 🔴 Auth custom (`AncoraAuth`) — *avaliar, não migrar às cegas*
Hoje o painel não usa o Auth do Laravel (ver ARQUITETURA §4.1). Migrar para um
`UserProvider`/guard custom **mantendo o `password_hash` legado** permitiria usar Gates,
Policies e middleware padrão. **Alto risco** (sessão, expiração, permissões cacheadas) —
só com a rede de testes do P1 pronta e em ambiente de staging. Pode ficar como está se o
custo não compensar; é dívida tolerável.

### 3.2 🟡 Documentar as APIs (Hub e Mobile)
Gerar um contrato OpenAPI/Swagger das rotas `/api/hub/v1` e `/api/mobile/v1`. Já há
`docs/mobile-api.md` — expandir e versionar. Protege o app Android de quebras de contrato.

### 3.3 🟡 Observabilidade
- Logs estruturados por canal (cobrança, automação, IA, push) em `config/logging.php`.
- Painel/alertas para falhas de integração (Evolution, FCM, Assinafy, DataJud).

### 3.4 🟢 Consolidar a documentação
Esta pasta `docs/` passa a ser o índice único: ARQUITETURA + MELHORIAS + os guias
existentes (mobile, easypanel, automação) + a wiki. Manter `ARQUITETURA.md` atualizado a
cada mudança estrutural.

---

## Como vamos trabalhar (proposta de processo)

1. **Sempre** começar pelo teste de caracterização do que será tocado.
2. Commits pequenos e reversíveis; um comportamento por PR.
3. Nomes de rota e contratos de API são **imutáveis** salvo decisão explícita.
4. Mudança de schema só por migration, nunca editando SQL legado.
5. Validar em staging com dump de produção antes de qualquer item 🔴.

---

## Sequência recomendada (primeiros passos concretos)

1. [P1.1] Limpar resíduos da raiz.
2. [P1.4] Ligar Pint + PHPStan no fluxo local.
3. [P1.2] Tirar baseline do schema de produção e documentar o caminho de bootstrap.
4. [P1.3] Escrever testes de caracterização para 1 fluxo de Cobranças.
5. [P2.1] Extrair o **primeiro** sub-controller de Cobranças (sugestão: importação).

> Cada passo é independente e pode parar a qualquer momento sem deixar o sistema pela metade.
</content>
