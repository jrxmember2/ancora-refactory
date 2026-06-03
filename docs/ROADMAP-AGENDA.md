# Roadmap — Módulo Agenda / Prazos (+ integração Google/Microsoft)

> Módulo novo do Âncora para prazos processuais, audiências, reuniões, tarefas e
> compromissos. Tudo aditivo (tabela/módulo/rotas novas), seguindo as convenções do
> sistema (system_modules + permissões por rota + Blade/TailAdmin + Controller→Service→Model).

## Fase 1 — Módulo Agenda interno (MVP) ← **em construção**
- Tabela `agenda_events` (tipo, status, prioridade, `is_fatal`, `start_at`/`end_at`, responsável,
  solicitante, local, lembrete, vínculos a processo/demanda/cliente/contrato, baixa, soft delete).
- `AgendaCatalog` (tipos: prazo, audiência, reunião, tarefa, compromisso, diligência, perícia, outro;
  status: aberto/concluído/cancelado — "atrasado" é derivado; prioridades).
- `AgendaEvent` (relações + scopes `open`/`overdue`/`upcoming`/`forUser`), `AgendaService` (resumo do painel).
- `AgendaController` (calendar, index filtrável, create/store, show, edit/update, complete, destroy) + Form Requests.
- Views: calendário mensal (server-side), lista com filtros, form, show.
- Rotas `agenda.*` com `ancora.route:`; módulo `agenda` em system_modules; grupo `agenda` no `AncoraRouteCatalog`; item no `AncoraMenu`.
- Integração: botão "Novo prazo" na tela do Processo (prefill) + alerta de prazos (fatais/atrasados/próximos)
  no painel via `View::composer` (mesmo padrão do `processMovementNotification`, guardado).
- Teste isolado do `AgendaService` (derivação de atrasado/próximos).

## Fase 1.5 — Feed ICS (Nível 1, sem OAuth) 🟢 ← **concluída**
- Link de assinatura `.ics` por usuário (`/agenda/feed/{token}.ics`, rota pública por token) + `.ics`
  por evento (`agenda.ics`). Token em `users.calendar_feed_token` (gerado sob demanda na tela do calendário).
- `App\Support\Agenda\IcsBuilder` (RFC 5545: CRLF, escaping, UTC, dia inteiro). Card "Assinar no
  Google/Outlook/Apple" no calendário + botão "Adicionar ao calendário (.ics)" no evento.
- Assinável no Google Calendar / Outlook / Apple. Somente leitura (Âncora→calendário), sem credenciais.
- Obs.: locale do Carbon fixado em pt_BR no `AppServiceProvider` (meses por extenso em portugues).

## Fase 2 — Push via OAuth (Nível 2, uma via) 🟡 ← **código concluído** (ativa com credenciais)
- Conectar conta Google (Google Cloud + escopo `calendar.events`) e Microsoft (Azure AD + `Calendars.ReadWrite`).
- Tokens OAuth por usuário **criptografados** (`calendar_connections`, cast `encrypted`); mapeamento
  evento↔externo em `agenda_event_syncs`. Cria/atualiza/remove no calendário do responsável.
- Fluxo OAuth: `agenda.calendar.connect`/`callback`/`disconnect` (`CalendarConnectionController`, state anti-CSRF).
- Provedores: `GoogleCalendarProvider` + `MicrosoftCalendarProvider` (interface comum, `CalendarProviders`).
- Sincronização via `CalendarSyncService` disparada por `SyncAgendaEventToCalendarsJob` (fila), **totalmente
  guardada** (try/catch por conexão, nunca derruba o request; renova token via refresh).
- UI: card "Sincronizar com Google/Outlook" no calendário (Conectar/Desconectar) — só aparece se o provedor
  tiver credenciais (`isConfigured`).
- **Para ativar (env):** `GOOGLE_CALENDAR_CLIENT_ID/SECRET`, `MICROSOFT_CALENDAR_CLIENT_ID/SECRET`
  (+ `MICROSOFT_CALENDAR_TENANT`). Redirect URIs a registrar:
  `/agenda/integracoes/google/callback` e `/agenda/integracoes/microsoft/callback`.
  Sem credenciais, os botões não aparecem e a sincronização é no-op. Verificação live pendente de credenciais.

## Extra — Cores customizadas nos compromissos 🟢 ← **concluída**
- Coluna `agenda_events.color` (hex). Seletor de cor + "Aplicar cor" no formulário. Cor de fundo nos chips
  do calendário e ponto colorido na lista, com **cor da letra contrastante automática** (`App\Support\ColorContrast`, luminância WCAG).

## Fase 3 — Sincronização bidirecional (Nível 3) 🔴
- Webhooks Google (`watch`) / Microsoft Graph (`subscriptions`) + sync tokens + resolução de conflitos.
- Reaproveita fila (`database`) e infra de webhooks (hoje Evolution/Assinafy) + HTTPS do EasyPanel.
- Tabelas de vínculo (`agenda_event_syncs`: provider, external_event_id, sync_token) e conexões por usuário.

## Decisões assumidas (padrões)
Tipos conforme acima; calendário mensal server-side primeiro; alertas só in-app na Fase 1
(e-mail/WhatsApp na Fase 2+); vínculos v1 = processo/demanda/cliente/contrato; timesheet fora de escopo
(item separado). Pergunta aberta para Fase 2: o escritório usa Google Workspace, Microsoft 365 ou ambos
(muda o esforço de verificação/configuração).
