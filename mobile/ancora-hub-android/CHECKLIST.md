# Checklist de validação do Âncora Hub

## Fluxo principal

- [ ] O app abre sem crash
- [ ] A splash aparece por pelo menos 2 segundos
- [ ] A URL dinâmica funciona
- [ ] O login funciona
- [ ] A biometria funciona
- [ ] A sessão renovável funciona
- [ ] O logout funciona

## Push e navegação

- [ ] O push funciona
- [ ] O dashboard carrega
- [ ] Os deep links internos funcionam
- [ ] As notificações abrem a tela correta

## Permissões e segurança

- [ ] Os módulos respeitam permissão
- [ ] O app não usa WebView
- [ ] O app não abre Chrome
- [ ] Os textos estão com acentuação correta

## Build e entrega

- [ ] O build debug gera
- [ ] O build release gera
- [ ] O `lint` passa
- [ ] Os testes locais passam
