# Automacao WhatsApp de Cobranca

## Resumo da arquitetura

- A API interna fica no backend do Ancora e o n8n atua apenas como orquestrador.
- O endpoint principal recebe a mensagem, resolve ou cria a sessao, executa a maquina de estados e devolve um JSON padronizado com a proxima acao.
- A logica de cobranca reutiliza o servico existente `App\Services\CobrancaMonetaryUpdateService::calculate`.
- A OS de cobranca continua centralizada em `cobranca_cases`.
- O aceite do acordo abre automaticamente uma demanda no modulo `demands`.

## Reaproveitamento da base existente

- OS de cobranca: `App\Models\CobrancaCase`
- Quotas em aberto: `App\Models\CobrancaCaseQuota`
- Atualizacao TJES: `App\Services\CobrancaMonetaryUpdateService`
- Demandas: `App\Models\Demand`, `App\Models\DemandMessage`, `App\Models\DemandCategory`
- Condominios, blocos, unidades e responsaveis: `ClientCondominium`, `ClientBlock`, `ClientUnit`, `ClientEntity`
- Auditoria existente: `audit_logs`

## Rotas

- `POST /api/internal/automation/whatsapp/process-message`

Middleware aplicado:

- `automation.internal`

## Tabelas novas

- `automation_sessions`
- `automation_session_messages`
- `automation_validation_challenges`
- `automation_debt_snapshots`
- `automation_agreement_proposals`
- `automation_audit_logs`

## Tabelas ajustadas

- `client_condominiums`
  - `boleto_fee_amount`
  - `boleto_cancellation_fee_amount`
- `demands`
  - `automation_session_id`
  - `automation_agreement_proposal_id`
  - `sla_due_at`
- `cobranca_monetary_updates`
  - `boleto_fee_total`
  - `boleto_cancellation_fee_total`

## Configuracao principal

Arquivo:

- `config/automation.php`

Pontos alteraveis:

- mensagens do fluxo
- timeout da sessao
- maximo de tentativas
- limite de busca
- quantidade de opcoes dos desafios
- regra da data do primeiro pagamento
- percentuais de honorarios
- ativacao das taxas de boleto/cancelamento
- status e SLA padrao da demanda
- token e allowlist da API interna

## Como o n8n deve chamar

Header:

- `X-Integration-Token: <AUTOMATION_INTERNAL_TOKEN>`

Payload exemplo:

```json
{
  "channel": "whatsapp",
  "provider": "evolution",
  "phone": "5511999999999",
  "external_contact_id": "optional-contact",
  "external_message_id": "msg-123",
  "message_text": "2",
  "timestamp": "2026-04-21T10:00:00-03:00",
  "metadata": {}
}
```

Resposta exemplo:

```json
{
  "ok": true,
  "version": "v1",
  "session": {
    "protocol": "AUT-2026-000001",
    "current_flow": "collection",
    "current_step": "collection_choose_condominium",
    "status": "active"
  },
  "action": {
    "type": "reply",
    "message": "Digite o nome do condominio.",
    "options": [],
    "human_handover": false,
    "close_session": false
  },
  "data": {
    "condominium": null,
    "unit": null,
    "debts": null,
    "proposal": null
  }
}
```

## Onde alterar regras de negocio

- Maquina de estados: `App\Services\Automation\AutomationConversationService`
- Busca de condominio/bloco/unidade: `App\Services\Automation\AutomationLookupService`
- Validacao por nome/CPF: `App\Services\Automation\AutomationValidationService`
- Consolidacao de debitos: `App\Services\Automation\AutomationDebtService`
- Regras de acordo e data limite: `App\Services\Automation\AutomationAgreementService`
- Criacao de demanda: `App\Services\Automation\AutomationDemandService`

## Observacoes importantes

- O primeiro contato apenas apresenta o menu inicial.
- Antes da dupla validacao o fluxo nao expoe dados sensiveis.
- A idempotencia usa `provider + external_message_id`.
- O SLA de 24 horas uteis foi implementado pulando fins de semana. Nao ha calendario de feriados.
- Para custas processuais em cobranca judicial, a automacao reutiliza o ultimo registro de atualizacao monetaria salvo na OS quando houver valor de custas.

## Testes

Arquivos:

- `tests/Feature/Automation/WhatsAppAutomationTest.php`
- `tests/Unit/Automation/AutomationAgreementServiceTest.php`
- `tests/Unit/Automation/AutomationValidationServiceTest.php`

Comando previsto:

```bash
php artisan test
```

No workspace atual nao foi possivel executar a suite completa porque o projeto esta sem `vendor/` e sem Composer disponivel no PATH. A sintaxe dos arquivos PHP alterados foi validada com `php -l`.
