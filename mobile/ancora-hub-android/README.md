# Âncora Hub

App Android nativo do escritório para acesso interno ao ecossistema Âncora.

## Identificação

- Package: `br.com.serratech.ancora.hub`
- Application ID: `br.com.serratech.ancora.hub`
- Nome visível: `Âncora Hub`
- API principal: `/api/hub/v1`

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
- Android Keystore via `EncryptedSharedPreferences`
- Splash Screen API

## Como abrir no Android Studio

1. Abra a pasta `mobile/ancora-hub-android`.
2. Aguarde a sincronização do Gradle.
3. Confirme `JAVA_HOME` apontando para um JDK 17.
4. Confirme `ANDROID_HOME` ou `ANDROID_SDK_ROOT` apontando para o Android SDK.
5. Se for testar push, adicione `app/google-services.json`.

## Como configurar Firebase

1. Crie um app Android com package `br.com.serratech.ancora.hub`.
2. Baixe o arquivo `google-services.json`.
3. Coloque o arquivo em `app/google-services.json`.
4. Gere um novo build do app depois de adicionar o arquivo.
5. Confirme no backend se a estrutura de push do Hub está ativa e acessível.

## Como gerar APK debug

Windows:

```bash
gradlew.bat :app:assembleDebug
```

Linux/macOS:

```bash
./gradlew :app:assembleDebug
```

APK gerado:

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

Antes disso:

- configure assinatura em `signingConfigs`
- valide `applicationId`, `versionCode` e `versionName`
- armazene segredos fora do repositório

## Como trocar ícone ou logo

Arquivos principais:

- `app/src/main/res/drawable/logo_ancora_hub.png`
- `app/src/main/res/drawable/ic_launcher_foreground.xml`
- `app/src/main/res/drawable/splash_logo.xml`
- `app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml`
- `app/src/main/res/mipmap-anydpi-v26/ic_launcher_round.xml`

Depois de trocar assets, gere um novo build do app.

## Fluxo de sessão

- Sem URL salva: abre `Conectar ao Âncora`.
- Sem token salvo: abre login.
- Com biometria ativa: pede biometria, valida `/api/hub/v1/me` e entra.
- Sem biometria ativa: valida `/api/hub/v1/me` na abertura e entra.
- Com biometria: a sessão é renovável por uso com janela de inatividade de 30 dias.
- Sem biometria: a sessão é renovável por uso com janela de inatividade de 24 horas.
- Se o backend responder `401`, o token local é apagado e o app volta para o login.
- Se a biometria for removida ou a chave segura falhar, o token é limpo e o login completo é exigido.
- Se a biometria for desativada no perfil, a sessão atual é encerrada e o próximo acesso volta pelo login.

## Como testar URL dinâmica

1. Instale o app.
2. Abra o app.
3. Aguarde a splash screen nativa por pelo menos 2 segundos.
4. Se não houver URL salva, a tela `Conectar ao Âncora` abre.
5. Informe a URL da instância.
6. O app valida `GET /api/hub/v1/health`.
7. Somente depois disso o login nativo é liberado.

## Como testar login

1. Configure a URL da instância.
2. Entre com um e-mail e uma senha válidos de usuário interno.
3. Confirme a abertura do dashboard nativo do Âncora Hub.
4. Confirme o registro do dispositivo se o Firebase estiver configurado.
5. Confirme que nenhuma tela abre navegador.

## Como testar biometria

1. Faça login normalmente.
2. Quando o app perguntar `Deseja ativar a biometria?`, toque em `Ativar biometria`.
3. Aprove a biometria no prompt do aparelho.
4. Feche o app.
5. Abra novamente.
6. Confirme a exibição do prompt `Desbloquear Âncora Hub`.
7. Aprove a biometria.
8. Confirme que o app valida `/api/hub/v1/me` e entra no dashboard.
9. Teste também o fallback `Entrar com e-mail e senha`.

## Checklist manual da Fase 3

1. Login com biometria ativa.
2. Login sem biometria.
3. Reabertura do app com biometria.
4. Reabertura do app sem biometria.
5. Token expirado retornando ao login.
6. Logout manual removendo sessão local.
7. Troca de URL limpando token e sessão local.
8. Backend retornando `401` em `/api/hub/v1/me`.
9. Biometria indisponível no aparelho.

## Como confirmar que não usa WebView

O app:

- não usa `WebView`
- não usa `android.webkit`
- não usa `CustomTabsIntent`
- não abre Chrome para navegar no Âncora
- não abre navegador externo para acessar módulos internos

Para conferir no código:

```bash
rg -n "WebView|android\\.webkit|CustomTabsIntent|ACTION_VIEW|chrome|browser" app/src/main
```

O resultado esperado para esta fase é vazio.
