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

## Extra — Recorrência, participantes, anexos e importação 🟢 ← **concluída**
- **Recorrência**: `recurrence` (diária/semanal/quinzenal/mensal) + `recurrence_until` geram a série na criação
  (ligados por `recurrence_group`). Lógica testada (`AgendaRecurrenceTest`).
- **Participantes**: `agenda_event_participants` (N:N com users); multi-select no form, exibidos no evento e
  **incluídos no lembrete por e-mail**.
- **Anexos**: `agenda_event_attachments` (upload/download/excluir, disco `public`), card no evento.
- **Importar eventos externos**: ramo opcional no inbound (Fase 3) que cria compromissos a partir de eventos
  criados direto no Google/Outlook — **desligado por padrão** (`*_CALENDAR_IMPORT_EXTERNAL`).

## Fase 3 — Sincronização bidirecional (Nível 3) 🔴 ← **código concluído** (verificação live pendente)
- **Desligada por padrão** (`GOOGLE_CALENDAR_WEBHOOKS_ENABLED` / `MICROSOFT_CALENDAR_WEBHOOKS_ENABLED`).
- Webhooks públicos (sem CSRF/sessão, em `routes/api.php`): `/api/agenda/webhooks/google` e `/api/agenda/webhooks/microsoft`
  (handshake de validação do Graph + verificação de `clientState`/`X-Goog-Channel-Token`).
- Inscrições em `calendar_subscriptions`; criadas ao conectar (se habilitado) e renovadas por
  `agenda:renew-calendar-subscriptions` (a cada 6h). Google via `events/watch` + sync token; Microsoft via `subscriptions`.
- **Não-destrutivo e bounded:** só atualiza eventos que o Âncora criou (mapeados em `agenda_event_syncs`);
  exclusão externa → marca `status=cancelado` (reversível); **não importa** eventos externos arbitrários.
- `CalendarInboundSyncService` (aplicação testada), `ProcessCalendarWebhookJob` (fila), providers implementam
  `CalendarWebhookProviderInterface`. Tudo guardado (nunca lança no request).
- **Verificação ponta-a-ponta pendente de staging** com credenciais + URL pública HTTPS (não testável aqui).

## Extra — Lembretes de prazos por e-mail/WhatsApp 🟢 ← **concluída**
- `agenda_events.reminder_sent_at` + `users.phone` (campo no perfil "Meus dados").
- `AgendaReminderService` + comando `agenda:send-reminders` (agendado a cada 5 min) envia ao responsável:
  e-mail (SMTP do app) e WhatsApp (Evolution API) — cada canal guardado, idempotente (`reminder_sent_at`).
- **Requer o scheduler rodando** (`php artisan schedule:run` por cron a cada minuto) além do worker de fila.

## Decisões assumidas (padrões)
Tipos conforme acima; calendário mensal server-side primeiro; alertas só in-app na Fase 1
(e-mail/WhatsApp na Fase 2+); vínculos v1 = processo/demanda/cliente/contrato; timesheet fora de escopo
(item separado). Pergunta aberta para Fase 2: o escritório usa Google Workspace, Microsoft 365 ou ambos
(muda o esforço de verificação/configuração).
