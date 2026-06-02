# Âncora — Documentação de Arquitetura

> Documento vivo. Descreve **como o sistema é hoje** (junho/2026), em produção.
> Para propostas de evolução/refatoração, ver [`MELHORIAS.md`](./MELHORIAS.md).

---

## 1. Visão geral

O Âncora é um ERP jurídico/condominial monolítico em **Laravel 12 / PHP 8.2**, servindo
um escritório (painel administrativo interno) e seus clientes (Portal do Cliente web/PWA +
app Android nativo). A base nasceu de um sistema legado (dump MySQL) e foi reescrita sobre
o template **TailAdmin** (Tailwind v4 + Alpine.js + Vite), preservando o schema e os
mecanismos legados.

**Dimensão atual** (jun/2026): ~64k linhas de PHP em `app/`, 92 models, 67 controllers,
~80 services/support, 221 views Blade, 80 migrations.

### Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12, PHP 8.2 |
| Banco | MySQL (schema legado) |
| Front (painel) | Blade + TailAdmin (Tailwind CSS v4) + Alpine.js, build via Vite |
| Mobile | Android nativo (Kotlin + Jetpack Compose + Material 3) em `mobile/` |
| PDF | mpdf/mpdf, Chromium headless (termos/contratos), scripts Python em `scripts/` |
| DOCX | phpoffice/phpword |
| Mensageria | Evolution API (WhatsApp) |
| IA | OpenAI e Gemini (providers plugáveis) |
| Assinatura | Assinafy (integração externa) + assinador interno |
| Push mobile | Firebase Cloud Messaging (FCM) |
| Jurídico | DataJud (CNJ) para movimentações processuais |
| Deploy | Docker / EasyPanel (ver `GUIA_DEPLOY_EASYPANEL.md`) |
| Testes | Pest 4 (10 arquivos hoje) |

---

## 2. Domínios / Módulos de negócio

O sistema é organizado em módulos. A navegação e o acesso são controlados por
`system_modules` (tabela) + permissões por rota.

| Módulo | Controller(s) principais | Função |
|---|---|---|
| **Propostas** | `ProposalController`, `ProposalDocumentController` | Funil comercial, documento premium, anexos, histórico |
| **Clientes** | `ClientsController`, `ClientExportController`, `ClientPortalUserController` | CRUD de avulsos, contatos, condomínios, unidades, timeline, importação CSV |
| **Cobranças** | `CobrancaController` (⚠️ 7.245 linhas) | OS de cobrança, cotas, parcelas, GED, importação XLS de inadimplência, termos de acordo (PDF), atualização monetária (TJES), boletos, notificações |
| **Financeiro** | `FinancialController`, `FinancialImportController`, `FinancialExportController` | "Financeiro 360": contas, fluxo de caixa, recebíveis/pagáveis, conciliação, relatórios |
| **Contratos** | `ContractController` + `ContractCategory/Template/Variable/Settings/ReportController` | Templates, variáveis, versões, render PDF, vínculo financeiro |
| **Processos** | `ProcessController`, `ProcessNotificationController` | Casos jurídicos, partes, fases, movimentações (DataJud), notificações automáticas |
| **Demandas** | `DemandController` | Tickets/demandas com categorias, tags, SLA, mensagens, anexos |
| **Assinatura Eletrônica** | `ElectronicSignerController`, `DocumentSignatureController`, `AssinafyWebhookController` | Upload de PDFs, fluxo de assinatura, webhooks Assinafy |
| **IA** | `AiOfficeChatController` + serviços em `app/Services/Ai` | Chat do escritório, base legal global, RAG (chunks/embeddings), histórico |
| **Automação (WhatsApp)** | `Internal/AutomationController`, `EvolutionWebhookController` | Atendimento automatizado de acordos via Evolution API |
| **Configuração** | `ConfigController` (2.177 linhas) | Branding, módulos, perfis de acesso, SMTP/IMAP, IA, Evolution, push, índices TJES |
| **Portal do Cliente** | `app/Http/Controllers/Portal/*` | Dashboard, demandas, processos, cobranças, chat IA do síndico, PWA |
| **Logs/Auditoria** | `LogController`, `ChangelogController` | Auditoria de ações, versionamento |
| **Busca global** | `SearchController` | Busca cross-módulo |

---

## 3. Superfícies de entrada (entry points)

O monólito expõe **quatro superfícies distintas**, cada uma com seu próprio middleware
de autenticação. Esta é uma característica central da arquitetura:

| Superfície | Rotas | Auth (middleware) | Consumidor |
|---|---|---|---|
| **Painel interno** | `routes/web.php` (`ancora.*`) | `ancora.auth` (sessão custom) | Funcionários do escritório |
| **Portal do Cliente** | `routes/web.php` (`portal.*`) | `portal.auth` | Clientes (web/PWA) |
| **API Mobile** | `routes/api.php` (`/api/mobile/v1`) | `mobile.api.auth` (token) | App Android "Âncora Clientes" |
| **API Hub** | `routes/api.php` (`/api/hub/v1`) | `hub.api.auth` (token) | App/integração "Âncora Hub" |
| **Webhooks/Automação** | `routes/api.php` | `automation.internal` (token+IP) | Evolution API, Assinafy |

> `routes/web.php` tem **68 KB** — todas as rotas do painel e do portal em um único arquivo.

---

## 4. Autenticação e autorização

### 4.1 Auth do painel — **custom, não usa o Auth do Laravel**

Decisão herdada do legado: a tabela `users` usa `password_hash` legado, então o sistema
**não usa o guard/Auth padrão do Laravel**. Em vez disso:

- `App\Support\AncoraAuth` (classe estática) guarda o usuário em `session('auth_user')`
  como um array (id, role, module_permissions, route_permissions, session_minutes…).
- Middleware `EnsureAncoraAuthenticated` valida a sessão; `TrackAncoraSessionActivity`
  controla expiração por inatividade (30 min padrão / 720 min "lembrar").
- `AppServiceProvider::boot()` injeta o usuário em **todas** as views via
  `View::composer('*')` (`$ancoraAuthUser`, menu, branding, alertas, versão).

> ⚠️ Implicação: ferramentas e pacotes que assumem `Auth::user()` / `auth()` **não
> funcionam** aqui. Toda lógica de "usuário logado" passa por `AncoraAuth`.

As demais superfícies têm seus próprios mecanismos:
- **Portal**: `ClientPortalAuth` + `EnsureClientPortalAuthenticated` (sessão separada).
- **Mobile/Hub**: tokens em `*_api_tokens`, gerenciados por `*ApiTokenManager`.

### 4.2 Autorização — permissões por rota

- Cada rota recebe `->middleware('ancora.route:<nome.da.rota>')`.
- `EnsureRoutePermission` checa contra `route_permissions` do usuário (cacheadas na sessão).
- `superadmin` ignora as checagens.
- O **catálogo** de permissões disponíveis vive em `App\Support\AncoraRouteCatalog`
  (lista canônica agrupada por módulo); perfis de acesso são montados em
  `ConfigController` e persistidos por seeds/migrations.
- Acesso a módulo (visibilidade no menu) = `system_modules` + `AncoraAuth::hasModule()`.

---

## 5. Camadas e organização de código

```
app/
├── Http/
│   ├── Controllers/        67 controllers (alguns gigantes — ver §7)
│   │   ├── Api/Hub/V1/      API do "Âncora Hub"
│   │   ├── Api/Mobile/V1/   API do app cliente
│   │   ├── Portal/          Portal do Cliente
│   │   ├── Auth/            Login / reset de senha
│   │   ├── Internal/        Automação (webhook interno)
│   │   └── Concerns/        traits compartilhadas entre controllers
│   ├── Middleware/          11 middlewares (5 mecanismos de auth distintos)
│   └── Requests/            Form Requests (parcial — ver §7)
├── Models/                  92 Eloquent models (mapeados ao schema legado)
├── Services/                Regras de negócio por domínio
│   ├── Ai/ (+ Providers, Knowledge)
│   ├── Automation/
│   ├── Hub/  e  Mobile/
│   └── ... (Financial*, Contract*, Cobranca*, Evolution*, ...)
├── Support/                 Helpers/catálogos/value-objects sem estado HTTP
│   ├── AncoraAuth / AncoraMenu / AncoraSettings / AncoraRouteCatalog
│   ├── Automation/ Contracts/ Financeiro/ Hub/ Mobile/ Signatures/
├── Observers/               Eventos de model (Demand, ProcessCase, ...)
├── Jobs/                    Push assíncrono (FCM)
├── Console/Commands/        Comandos artisan (push test)
├── Providers/               AppServiceProvider (único)
└── View/Components/         Componentes Blade do TailAdmin
```

**Convenção observada**: `Controller` → `Service` (regra de negócio) → `Model`.
Os `Support/*Presenter` formatam dados para as APIs; os `Support/*Catalog` são listas
canônicas (rotas, variáveis de contrato, documentos IA).

---

## 6. Banco de dados — estratégia híbrida (ponto sensível)

O banco **nasceu de um dump legado** e convive com migrations. Há **duas fontes de verdade**:

1. `database/sql/` — dumps SQL do legado + incrementos manuais (ex.
   `ancora_mysql_full_corrigido.sql`, `2026_04_cobranca_*.sql`).
2. `database/migrations/` — 80 migrations para incrementos novos.

> ⚠️ O README alerta: **não rodar migrations sem entender o estado do banco**. O fluxo
> de bootstrap recomendado é importar o SQL completo e depois rodar apenas migrations
> específicas. Há migrations "defensivas" (`ensure_*_columns_exist`) que existem
> justamente para reconciliar bancos importados de SQL com o estado esperado.

Isso é a maior origem da sensação de "colcha de retalhos" e o ponto que mais exige
cuidado em qualquer mudança. Ver [`MELHORIAS.md` §1](./MELHORIAS.md).

---

## 7. Pontos de dívida técnica (mapa honesto)

| Item | Evidência | Risco |
|---|---|---|
| **Controllers gigantes** | `CobrancaController` 7.245 ln, `ClientsController` 2.581, `ProcessController` 2.183, `ConfigController` 2.177, `FinancialController` 1.551 | Difícil manter/testar; lógica de negócio misturada com HTTP |
| **`routes/web.php` monolítico** | 68 KB num arquivo | Difícil navegar; conflitos de merge |
| **Schema híbrido SQL+migrations** | `database/sql/` vs `migrations/` | Ambientes podem divergir; risco em deploy |
| **Cobertura de testes baixa** | 10 arquivos Pest p/ ~64k ln | Refatorar sem rede de segurança |
| **Arquivos soltos na raiz** | `tmp_ensurehub.txt`, `ancora_processos_fix.patch`, `tailadmin-laravel.png` | Ruído; resíduos de patches manuais |
| **Validação inconsistente** | Mistura de Form Requests e `$request->validate()` inline | Regras duplicadas/dispersas |
| **Auth custom** | `AncoraAuth` estático em sessão | Impede uso do ecossistema Laravel (policies, gates, Sanctum) |

---

## 8. Integrações externas

| Integração | Onde | Config |
|---|---|---|
| Evolution API (WhatsApp) | `EvolutionApiService`, `EvolutionWebhookController` | `config/automation.php`, painel Config |
| OpenAI / Gemini | `Services/Ai/Providers/*` | `config/services.php`, painel Config IA |
| Assinafy (assinatura) | `AssinafyService`, `AssinafyWebhookController` | `config/services.php` |
| Firebase (FCM push) | `FirebaseCloudMessagingService`, Jobs | `FCM_*` no `.env` |
| DataJud (CNJ) | `ProcessDataJudService` | `DATAJUD_*` no `.env` |
| SMTP / IMAP (cobrança) | `AncoraMail`, `AncoraBillingMail` | painel Config |

---

## 9. Como subir localmente

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
# Importar o schema legado (ver README §Banco de dados) ANTES de acessar:
#   1) database/sql/ancora_mysql_full_corrigido.sql  e demais incrementos
npm run build
php artisan serve
```

Fluxo "dev" completo (server + queue + logs + vite): `composer dev`.
Testes: `composer test` (`php artisan test`).

---

## 10. Arquivos de referência rápidos

- `bootstrap/app.php` — aliases de middleware e tratamento de exceções da API Hub.
- `app/Providers/AppServiceProvider.php` — observers + View composer global.
- `app/Support/AncoraAuth.php` — coração da autenticação do painel.
- `app/Support/AncoraRouteCatalog.php` — catálogo de permissões por rota.
- `routes/web.php` / `routes/api.php` — todas as rotas.
- `README.md` — instruções de deploy, banco e mobile.
- `docs/` — `mobile-api.md`, `mobile-app.md`, `easypanel-mobile.md`,
  `automation-whatsapp-integration.md`, `wiki/manual-operacao-ancora-hub.md`.
</content>
</invoke>
