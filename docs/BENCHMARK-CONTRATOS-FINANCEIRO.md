# Benchmark EasyJur × DataJuri → Âncora — Contratos & Financeiro

> Origem: engenharia reversa dos concorrentes em `~/advogacopy/RELATORIO_ENGENHARIA_REVERSA_ANCORA.md`
> (capturas reais de EasyJur e DataJuri, 01/06/2026).
> Foco deste documento: **o que dá para melhorar HOJE em Contratos e Financeiro** trazendo
> o melhor das duas ferramentas, **sem reescrever** e sem perder nada do que já existe.

## Premissa (leia antes de tudo)

O relatório propõe um **schema PostgreSQL do zero**. Isso é **referência conceitual, não o
plano**. A realidade do Âncora:

- O **Financeiro 360 do Âncora já é mais completo que o do DataJuri**: já tem plano de contas
  (categorias + DRE group), centros de custo, contas bancárias (PIX/saldo/limite), contas a
  receber/pagar com juros/multa/correção/desconto, **recorrência + séries**, **conciliação OFX**
  (statements/reconciliations/import), custas processuais, reembolsos, parcelamento, fluxo de
  caixa real+previsto, DRE, transações e prestação de contas.
- O módulo de **Contratos já tem** templates HTML com header/footer/margens, **variáveis
  catalogadas** (metamodelo), versões, anexos, **9 status**, tipos (inclui Aditivo/Distrato/
  Procuração), **billing types** (inclui honorários sobre êxito), recorrência, **índice de
  reajuste + periodicidade + `next_adjustment_date`**, prazo indeterminado, multa, vínculo a
  cliente/condomínio/unidade/proposta/processo e geração de lançamentos financeiros.

Portanto: **MySQL fica**, os módulos ficam. Vamos **cravar features pontuais** nas estruturas
existentes (colunas nullable retrocompatíveis + jobs + telas), nunca recriar.

---

## CONTRATOS — o que já temos vs o que trazer

| # | Feature dos concorrentes | Status no Âncora | Ação proposta | Esforço/Risco |
|---|---|---|---|---|
| C0 | Templates, variáveis, reajuste por índice, êxito, vínculo a processo | ✅ Já existe (igual ou melhor) | — | — |
| **C1** | Reajuste de honorário (INPC/IPCA/IGP-M) | ⚠️ Campos existem (`adjustment_index`, `next_adjustment_date`) mas **aplicação é manual** | **Motor de reajuste automático**: job diário que, ao chegar `next_adjustment_date`, aplica o índice (reaproveitar os índices que o **Cobranças/TJES já calcula**), atualiza `monthly_value` e registra versão/histórico | 🟡 |
| **C2** | EasyJur trata **Contrato ↔ Aditivo** explicitamente | ⚠️ "Aditivo" existe só como *tipo* solto | Adicionar `parent_contract_id` (nullable) em `contracts` → aditivo amarrado ao contrato-pai, somando efeitos financeiros | 🟢 |
| **C3** | Status com **assinatura** (Aguardando Assinatura → Assinado) | ⚠️ Status existem, mas **não conversam** com o módulo de Assinatura | **Amarrar Contrato → Assinador Eletrônico** (que o Âncora **já tem**): ao enviar p/ assinatura muda status; webhook Assinafy promove p/ `assinado`. Ganho grande reaproveitando módulo pronto, **sem schema novo** | 🟢 |
| **C4** | Honorário **de êxito** ligado ao desfecho | ⚠️ `billing_type=honorarios_sobre_exito` existe; falta o gancho | Quando o **processo vinculado** encerra com resultado favorável, gerar recebível de êxito (% sobre valor) automaticamente | 🟡 |
| **C5** | Honorário **por hora / timesheet** | ❌ Não existe timesheet | (Baixa prioridade p/ condominial, que é mensal) Avaliar tabela `timesheet` ligada a evento/processo só se houver demanda real | 🔴 |
| **C6** | Widgets de **vencimento de contrato** | ⚠️ Dados existem (`end_date`, `next_adjustment_date`) | Card no dashboard: contratos a vencer / reajuste próximo / aguardando assinatura | 🟢 |

## FINANCEIRO — o que já temos vs o que trazer

| # | Feature dos concorrentes | Status no Âncora | Ação proposta | Esforço/Risco |
|---|---|---|---|---|
| F0 | Receber/pagar, OFX, custas, reembolso, parcelamento, fluxo, DRE | ✅ Já existe (mais completo que DataJuri) | — | — |
| **F1** | **Boleto/PIX bancário real** (EasyJur via ASAAS `wallet_id`; DataJuri boleto registrado + situação) | ❌ Hoje "boleto" = **pedido por e-mail manual** (`CobrancaController::requestBoleto`); sem gateway | **Maior ganho.** Integrar gateway (ASAAS) para **emitir boleto/PIX registrado** → **webhook de pagamento → baixa automática** do recebível/quota → atualiza inadimplência. Infra de webhook+fila **já existe** (Evolution/Assinafy). Serve direto o core condominial | 🔴 |
| **F2** | **Plano de contas hierárquico** (DataJuri: "sub-categoria de") | ⚠️ `financial_categories` é **plana** (só `dre_group`) | Adicionar `parent_id` (nullable) em `financial_categories` → DRE com drill-down. Retrocompatível | 🟢 |
| **F3** | **Conciliação automática** (sugestão de match) | ⚠️ OFX importa, mas o match é **manual** | Sugerir match por valor+data+documento na tela de conciliação (`financial_statements` × `financial_transactions`) | 🟡 |
| **F4** | **Previsão de fluxo** com saldo projetado por conta / ocultar vencidas | ⚠️ `cash_flow` previsto existe | Refinar o relatório: saldo projetado por conta e filtro "não mostrar vencidas" (sem schema novo) | 🟢 |
| **F5** | **Listas com colunas/filtros salvos** (visão nomeada — killer feature do DataJuri) | ❌ Não existe | Aplicar a receber/pagar (e depois cross-módulo). Já listado no [`MELHORIAS.md`](./MELHORIAS.md) §3 como item de UX | 🟡 |

---

## Plano "trazer hoje" — ordenado por valor × baixo risco

> Regra de ouro (igual ao [`MELHORIAS.md`](./MELHORIAS.md)): coluna nova = **nullable e
> retrocompatível**; nada de mexer em dado existente; teste de caracterização antes.

1. **F2 — Plano de contas hierárquico** 🟢
   1 migration (`parent_id` nullable em `financial_categories`) + ajuste de tela/DRE. Rápido, habilita DRE melhor, zero risco a dados atuais.
2. **C3 — Contrato → Assinador Eletrônico** 🟢
   Reaproveita módulo de assinatura existente; status já existem. Ganho alto, sem schema novo.
3. **C6 + F4 — Widgets/relatórios de vencimento e fluxo projetado** 🟢
   Só leitura sobre campos existentes. Entrega percepção de valor rápido.
4. **C2 — Aditivo vinculado ao contrato-pai** 🟢
   1 coluna `parent_contract_id` nullable + UI de "novo aditivo a partir deste contrato".
5. **C1 — Reajuste automático de contrato** 🟡
   Job + reuso dos índices que o Cobranças já calcula. Campos de destino já existem.
6. **C4 — Honorário de êxito no encerramento do processo** 🟡
   Observer no `ProcessCase` (já existe um) → gera recebível de êxito.
7. **F3 — Sugestão de match na conciliação** 🟡
8. **F1 — Boleto/PIX com baixa automática (gateway ASAAS)** 🔴
   **Maior valor, maior esforço** — tratar como projeto próprio, por último, com homologação do gateway em staging. É o item que transforma a régua de inadimplência condominial em algo ponta-a-ponta.

---

## Mapa de reuso (o que já está pronto e devemos aproveitar)

- **Índices de correção** (TJES/IPCA): já calculados no módulo Cobranças → reusar em C1 e em êxito.
- **Webhooks + fila** (Evolution/Assinafy, `QUEUE_CONNECTION=database`): infra pronta para F1.
- **Assinador Eletrônico** (`ElectronicSignerController`, Assinafy): pronto para C3.
- **Observers** (`ProcessCaseObserver` já registrado): gancho pronto para C4.
- **Vínculo contrato↔processo↔financeiro** (FKs já existem): pré-requisito de C4 e F1 já atendido.

> Próximo passo concreto sugerido: começar por **F2** (migration `parent_id` em
> `financial_categories`) com teste de caracterização da tela de categorias/DRE.
</content>
