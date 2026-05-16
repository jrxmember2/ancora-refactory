# Mobile App do Ancora Clientes

Projeto Android nativo em:

- `mobile/ancora-clientes-android`

## Garantias principais

- sem `WebView`
- sem abrir Chrome para acessar o portal
- sem navegador externo para Processos, Solicitacoes ou Leme IA
- login nativo
- dashboard nativo
- lista e detalhe de processos nativos
- lista, criacao e detalhe de solicitacoes nativos
- chat Leme IA nativo

## Fluxo obrigatorio de bootstrap

1. Splash screen nativa.
2. Verificacao de URL salva no `DataStore`.
3. Se nao existir URL, abrir `Conectar ao Ancora`.
4. Validar `GET /api/mobile/v1/health`.
5. Salvar URL normalizada.
6. Seguir para login, biometria ou dashboard conforme sessao.

## Navegacao

Abas principais:

- Inicio
- Processos
- Solicitacoes
- Leme IA
- Notificacoes
- Perfil

## Recursos implementados

- URL dinamica por instancia
- token protegido com `EncryptedSharedPreferences`
- biometria via `BiometricPrompt`
- push via `Firebase Cloud Messaging`
- contexto de condominio
- filtros por status e condominio
- preview nativo de imagem para anexos publicos
- fallback via `FileProvider` para arquivos nao imagem

## Build validado neste ambiente

Comando executado:

```bash
gradlew.bat :app:assembleDebug
```

Saida gerada:

- `mobile/ancora-clientes-android/app/build/outputs/apk/debug/app-debug.apk`

## Conferencia anti-browser

Busca recomendada:

```bash
rg -n "WebView|android\.webkit|CustomTabsIntent|ACTION_VIEW|Uri\.parse|startActivity|browser|chrome" mobile/ancora-clientes-android -S
```

Resultado esperado:

- nenhum uso de `WebView`
- nenhum uso de `android.webkit`
- nenhum uso de `CustomTabsIntent`
- `ACTION_VIEW` apenas para abrir arquivo publico baixado via `FileProvider`
