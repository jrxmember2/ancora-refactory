# Âncora Hub

Aplicativo Android nativo do escritório para acesso interno ao ecossistema Âncora, com autenticação própria em `/api/hub/v1`, biometria, notificações push, navegação mobile e módulos internos voltados para operação diária.

## Visão geral

- Nome visível: `Âncora Hub`
- Package e Application ID: `br.com.serratech.ancora.hub`
- Root project: `ancora-hub-android`
- App 100% nativo Android
- Sem `WebView`
- Sem `android.webkit`
- Sem abertura do sistema web no navegador

## Arquitetura

O app segue a arquitetura base do `Âncora Clientes`, adaptada para uso interno do escritório:

- `core/`
  - `AppContainer`: composição manual de dependências
  - `network/`: Retrofit, OkHttp, interceptors e base URL dinâmica
  - `security/`: biometria e armazenamento seguro
  - `session/`: bootstrap de sessão, splash e navegação inicial
  - `push/`: Firebase Cloud Messaging e notificações nativas
- `data/`
  - `api/`: contratos Retrofit
  - `dto/`: payloads de request e response
  - `local/`: DataStore e `EncryptedSharedPreferences`
  - `repository/`: regras de consumo da API e tratamento de erro
- `domain/`
  - `model/`: modelos usados pela UI
  - `usecase/`: fluxos de bootstrap e validação
- `ui/`
  - `components/`: design system do app
  - `navigation/`: rotas e deep links internos
  - `screens/`: telas nativas em Jetpack Compose
  - `theme/`: Material 3, tipografia, cores e espaçamentos

## Stack

- Kotlin
- Jetpack Compose
- Material 3
- Navigation Compose
- Retrofit + OkHttp
- Kotlin Serialization
- Coroutines
- ViewModel
- DataStore
- Firebase Cloud Messaging
- BiometricPrompt
- Android Keystore com `EncryptedSharedPreferences`
- Splash Screen API

## Como abrir no Android Studio

1. Abra a pasta `mobile/ancora-hub-android`.
2. Aguarde a sincronização do Gradle.
3. Confirme o JDK 17 no Android Studio.
4. Confirme o Android SDK configurado.
5. Se for testar push, adicione o arquivo `app/google-services.json`.

## Como configurar a instância

1. Instale o app.
2. Abra o `Âncora Hub`.
3. Aguarde a splash por pelo menos 2 segundos.
4. Na tela `Conectar ao Âncora`, informe a URL da instância.
5. Toque em `Validar endereço`.
6. O app chamará `GET /api/hub/v1/health`.
7. Após a validação, prossiga para o login.

## Como configurar Firebase

1. Crie um app Android no Firebase com o package `br.com.serratech.ancora.hub`.
2. Baixe o arquivo `google-services.json`.
3. Coloque o arquivo em `mobile/ancora-hub-android/app/google-services.json`.
4. Gere um novo build do app.
5. No backend, configure as variáveis da service account do Firebase Cloud Messaging.

Exemplo de configuração no backend:

```env
SERVICES_FCM_ENABLED=true
SERVICES_FCM_PROJECT_ID=seu-project-id
SERVICES_FCM_SERVICE_ACCOUNT_JSON_BASE64=base64_da_service_account
```

## Como testar push

1. Faça login no app com um usuário interno.
2. Garanta que o aparelho registrou o token em `/api/hub/v1/devices/register`.
3. Rode no backend:

```bash
php artisan hub:push:test 1 --title="Notificação do Âncora Hub" --body="Teste de push" --route=hub://notifications/1
```

4. Valide o recebimento da push com o app aberto, em segundo plano e fechado.
5. Toque na notificação e confirme a abertura da tela correspondente.

## Como testar biometria

1. Faça login com e-mail e senha.
2. Aceite `Deseja ativar a biometria?`.
3. Confirme a biometria no aparelho.
4. Feche e reabra o app.
5. Confirme o prompt `Desbloquear Âncora Hub`.
6. Aprove a biometria.
7. Valide a navegação para o dashboard sem novo login.

## Como gerar APK debug

Windows:

```bash
gradlew.bat clean
gradlew.bat :app:assembleDebug
```

Linux/macOS:

```bash
./gradlew clean
./gradlew :app:assembleDebug
```

Saída:

- `app/build/outputs/apk/debug/app-debug.apk`

## Como gerar AAB release

Windows:

```bash
gradlew.bat :app:bundleRelease
```

Linux/macOS:

```bash
./gradlew :app:bundleRelease
```

Antes do build release:

- configure assinatura em `signingConfigs`
- valide `versionCode` e `versionName`
- mantenha segredos fora do repositório

## Como trocar ícone

Arquivos principais:

- `app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml`
- `app/src/main/res/mipmap-anydpi-v26/ic_launcher_round.xml`
- `app/src/main/res/drawable/ic_launcher_foreground.xml`
- `app/src/main/res/mipmap-*`

Depois de trocar os assets, gere um novo build.

## Como trocar a logo da splash

Arquivos principais:

- `app/src/main/res/drawable/logo_ancora_hub.png`
- `app/src/main/res/drawable/splash_logo.xml`
- `app/src/main/res/values/themes.xml`

Depois de trocar os assets da splash, gere um novo build e valide o tempo mínimo de 2 segundos.

## Como publicar na Play Store

1. Revise nome, ícone, splash, versão e package.
2. Gere o AAB com `bundleRelease`.
3. Assine com a chave oficial do app.
4. Suba o AAB no Google Play Console.
5. Preencha ficha da loja, política de privacidade e classificação indicativa.
6. Valide permissões de biometria, notificações e deep links internos.

## Política de sessão

- Sem URL salva: abre `Conectar ao Âncora`
- Sem token salvo: abre login
- Com biometria ativa: pede biometria, valida `/api/hub/v1/me` e entra
- Sem biometria ativa: valida `/api/hub/v1/me` na abertura e entra
- Com biometria: sessão renovável por uso com inatividade de 30 dias
- Sem biometria: sessão renovável por uso com inatividade de 24 horas
- `401` da API: sessão local é limpa e o app volta para o login

## Troubleshooting

### A instância do Âncora Hub ainda não foi configurada

Execute as migrations do Hub no backend:

```bash
php artisan migrate --force
php artisan optimize:clear
```

As tabelas mínimas esperadas são:

- `hub_api_tokens`
- `hub_device_tokens`
- `hub_notifications`
- `hub_push_dispatches`
- `hub_app_login_logs`

### Sessão inválida ou expirada

- confirme a URL da instância
- verifique se o token ainda está válido no backend
- faça login novamente se necessário

### Push não chega

- confira `google-services.json`
- confira permissões de notificação no aparelho
- valide o registro em `/api/hub/v1/devices/register`
- confira a configuração FCM do backend

### Biometria não abre

- confira se o aparelho tem biometria cadastrada
- confira se a biometria está ativa nas configurações do app
- se a chave segura tiver sido invalidada, o app exigirá novo login

### Garantia anti-WebView

O app não usa:

- `WebView`
- `android.webkit`
- `CustomTabsIntent`
- Chrome para navegar no Âncora
- navegador externo para abrir módulos internos

Para conferir:

```bash
rg -n "WebView|android\\.webkit|CustomTabsIntent|chrome|browser" app/src/main
```

O resultado esperado é vazio.
