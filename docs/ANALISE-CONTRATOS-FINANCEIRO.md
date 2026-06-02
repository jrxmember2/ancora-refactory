# Análise funcional — Contratos & Financeiro (pré-code)

> Revisão de campos, regras e funcionamento **antes** de implementar mudanças.
> Base: leitura do código real (controllers, services, requests, catálogos) em jun/2026.
> Legenda: ✅ OK · ⚠️ Ajustar/avaliar · 🐛 Bug · 🧹 Limpeza.

---

## A. CONTRATOS — "Novo contrato" e listagem

### Campos e validação (`StoreContractRequest` / `normalizedPayload`)
✅ Validação robusta: `type`, `status`, `billing_type`, `recurrence`, `adjustment_periodicity`,
`payment_method` todos com `Rule::in(catálogo)`. Conversão monetária pt-BR (`moneyFromInput`)
tratando `1.234,56`. Datas com `end_date >= start_date`.

### Regras condicionais (`applyConditionalContractPayload`) — boas e coerentes ✅
- Contrato de assessoria condominial → assume `billing_type=mensal` + `recurrence=mensal`.
- `indefinite_term` → zera `end_date`.
- Termo de acordo / Confissão / Distrato → zera índice/periodicidade/próximo reajuste.
- Distrato → sem geração financeira e sem recorrência.
- `billing_type=unica` → 1 parcela, zera mensal/recorrência/reajuste.
- `billing_type=parcelada` → zera mensal/recorrência/reajuste.
- `mensal` → zera nº de parcelas; se indeterminado, zera valor total.
- `honorarios_sobre_exito` / `sob_demanda` → zera quase tudo e **não** gera financeiro.
- `payment_method` espécie/dinheiro → zera conta bancária.
- status rescindido/cancelado/arquivado → desliga geração financeira.

### Guardas financeiras (`contractSaveGuardResponse`) ✅
- Validação estrita do financeiro **só** quando `generate_financial_entries` + status `ativo/assinado`.
- Confirmação extra para salvar ativo/assinado **sem** gerar lançamentos.
- Decisão manter / recriar / atualizar abertos+futuros quando já existem lançamentos.

### Pontos a avaliar
- ⚠️ **Honorário de êxito não vira recebível** por design (`honorarios_sobre_exito` desliga o
  financeiro). Falta o gancho "processo encerrado favorável → gera recebível de êxito" (gap C4 do
  benchmark). Decidir se entra agora.
- ⚠️ **Aditivo é só um `type`**, sem `parent_contract_id` ligando ao contrato-pai (gap C2). Hoje um
  aditivo não soma efeitos nem aparece amarrado ao contrato original.
- ⚠️ `penalty_percentage` validado como `string max:20` — não restringe faixa 0–100. Recomendo
  validar numérico/intervalo (a regra já impede valor+percentual juntos ✅).
- ⚠️ Inconsistência leve: o dashboard de Financeiro conta `aguardando_assinatura` como
  "contratos faturando", mas a geração só ocorre em `ativo/assinado`. Alinhar o indicador.
- ⚠️ `type` é lista fixa em `ContractCatalog::types()` — novo tipo exige código (não é cadastro).
  Aceitável hoje; anotar caso queiram tornar configurável.

---

## B. Gerador de contrato & Templates

### Render (`ContractRenderService`) ✅
- Substituição `{{variavel}}` + fragmentos (header/footer/qualification), **valor por extenso**
  pt-BR, qualificação do síndico **PF/PJ** (com representante/sócio), endereços, presets de
  assinatura, paginação (`{{numero_pagina}}`/`{{total_paginas}}`).
- Variáveis catalogadas (`ContractVariableCatalog`) → metamodelo já existe.

### PDF (`ContractPdfService`) ✅
- Cadeia de fallback: **mpdf** (PHP puro, primário → funciona sem binário) → Chromium DevTools →
  wkhtmltopdf → Chromium print. Versões persistidas em `contract_versions` + `final_pdf_path`.
- Anexos como apêndice (merge de PDF/imagens) com seleção por entidade vinculada.

### Pontos a avaliar
- ⚠️ **Dados da Contratada hardcoded** em `ContractRenderService::DEFAULT_CONTRACTED_PARTY`
  (nome, CNPJ, OAB da Rebeca). Deveria vir de `AncoraSettings`/branding — frágil se a marca mudar.
- ⚠️ **Placeholders órfãos**: variáveis sem valor permanecem como `{{x}}` no documento (`strtr`
  direto, sem limpeza final). Avaliar remover/realçar placeholders não resolvidos antes do PDF.
- ⚠️ Se mpdf falhar **e** não houver Chromium/wkhtml, lança exceção. Em produção o Docker instala
  Chromium (README) — confirmar que o ambiente de homologação também tem, ou confiar no mpdf.

---

## C. FINANCEIRO — análise de campos

### Contas a receber (`StoreFinancialReceivableRequest`) ✅ (completo)
- `original/interest/penalty/correction/discount` → **`final_amount` é derivado** no ledger
  (`receivableFinalAmount`), assim como `received_amount`/`status`/`received_at` (fonte única ✅).
- Competência + vencimento, recorrência + `occurrences` + `repeat_until` (séries), `collection_stage`,
  vínculos a cliente/condomínio/unidade/contrato/processo/categoria/centro/conta. Tudo com `Rule::in`.

### Contas a pagar (`StoreFinancialPayableRequest`)
- ⚠️ **Assimetria**: pagáveis têm só `recurrence` (string), **sem** `occurrences`/`repeat_until`
  nem geração de série. Despesa recorrente real (aluguel, salário, software) **não** gera parcelas
  futuras automaticamente, ao contrário dos recebíveis. Forte candidato a padronizar.

### Bancos/Contas (`StoreFinancialAccountRequest`) ✅
- Campos coerentes (tipo, PIX, saldo inicial, limite, titular). Valores monetários como string
  (padrão do projeto, convertidos depois) ✅.

### Outros pontos
- ⚠️ `status='vencido'` existe como opção **manual** no catálogo, mas o ledger **sobrescreve** o
  status na baixa/sync. Avaliar se "vencido" deve ser apenas derivado (nunca manual).
- 🧹 **Código morto** em `ContractFinancialService`: `buildMonthlySchedule`,
  `buildInstallmentSchedule`, `buildSingleSchedule` não são chamados (substituídos por
  `FinancialReceivableSeriesService::buildContractRows`). Remover.

---

## D. DRE — está 100% funcional?

**Quase.** A estrutura existe e calcula receita bruta → líquida → custos → despesas → resultado,
agrupando por `category.dre_group` (8 grupos). Mas há **2 bugs** e questões conceituais:

- 🐛 **BUG 1 — `dre_group` não validado.** `categoriesStore/Update` aceitam `dre_group` como
  string livre (`nullable|string|max:80`). Se alguém cadastrar uma categoria com grupo inválido
  (ex.: `"receita"`, `"Receitas"`, com acento), `dreData()` acessa `$groups[$invalido]` **não
  inicializado** → warning *undefined array key* e o valor **some do DRE e do resultado**
  silenciosamente. **Correção:** `Rule::in(array_keys(FinancialCatalog::dreGroups()))` no request +
  endurecer `dreData()` para cair no grupo padrão quando o grupo for desconhecido.

- 🐛 **BUG 2 — transferências/ajustes entram no DRE como despesa.** Em `dreData()` o sinal é
  `entrada ? +1 : -1`; logo `transferencia` e `ajuste` viram **-1 (despesa)**. Transferência entre
  contas próprias **não é despesa** e distorce o resultado. **Correção:** filtrar
  `transaction_type` para considerar só `entrada`/`saida` (e decidir o tratamento de
  `reembolso`/`repasse`). Relacionado: `accountBalance` também ignora `transferencia` e o
  `destination_account_id` — transferência entre contas hoje **não move saldos**.

- ⚠️ **CONCEITUAL — DRE é regime de CAIXA** (`transaction_date`), não competência. DRE contábil é
  por **competência** (`competence_date`, que já existe nos recebíveis/pagáveis). Decidir: rotular
  claramente "DRE (caixa)" e/ou oferecer alternância caixa × competência.

- ⚠️ Grupos `deducoes`, `resultado_financeiro`, `outros_resultados` **não têm categoria semeada** →
  sempre zero até o usuário criar categorias com esses grupos. OK, mas a tela de categorias deveria
  expor o seletor de `dre_group` com os rótulos do catálogo (ligado ao BUG 1).

---

## Ordem sugerida para o "code" (baixo risco → maior valor)

1. 🐛 **DRE BUG 1** — validar `dre_group` por catálogo + endurecer `dreData()` (seletor de grupo na
   tela de categorias). *Pequeno, corrige silenciosa perda de valores no DRE.*
2. 🐛 **DRE BUG 2** — excluir `transferencia`/`ajuste` do DRE; revisar saldo em transferência.
3. 🧹 Remover código morto `build*Schedule` em `ContractFinancialService`.
4. ⚠️ Contratada via settings/branding (tirar hardcode) + tratar placeholders órfãos no gerador.
5. ⚠️ Padronizar **recorrência de contas a pagar** (occurrences/repeat_until + série), igual aos recebíveis.
6. ⚠️ Alinhar indicador "contratos faturando" do dashboard com a regra real (`ativo/assinado`).
7. ⏭️ (maiores, já no benchmark) Honorário de êxito automático (C4) e Aditivo vinculado (C2);
   DRE por competência (opção).

> Itens 1–3 são corrigíveis com baixíssimo risco e melhoram a confiabilidade do DRE imediatamente.
> Sugiro começar por eles assim que aprovado.
</content>
