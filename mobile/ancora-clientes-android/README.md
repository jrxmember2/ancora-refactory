# Ancora Clientes

App Android nativo do Portal do Cliente do Ancora.

Package:

- `br.com.serratech.ancora.clientes`

Nome visivel:

- `Ancora Clientes`

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

## Abrir no Android Studio

1. Abra a pasta `mobile/ancora-clientes-android`.
2. Aguarde a sync do Gradle.
3. Confirme `JAVA_HOME` apontando para um JDK 17.
4. Confirme `ANDROID_HOME` ou `ANDROID_SDK_ROOT` apontando para o Android SDK.
5. Se for testar push, adicione `app/google-services.json`.

## Firebase

1. Crie um app Android com package `br.com.serratech.ancora.clientes`.
2. Baixe o arquivo `google-services.json`.
3. Coloque o arquivo em `app/google-services.json`.
4. Configure no backend:

```env
FCM_ENABLED=true
FCM_PROJECT_ID=
FCM_SERVICE_ACCOUNT_JSON_BASE64=
```

## Build debug

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

## Build release / AAB

Bundle release:

```bash
gradlew.bat :app:bundleRelease
```

Antes disso:

- configure assinatura no Android Studio ou no `app/build.gradle`
- ajuste `signingConfigs`
- armazene segredos fora do repositorio
- valide o `applicationId`, `versionCode` e `versionName`

## Assinatura

Exemplo de fluxo:

1. Gerar ou importar um keystore.
2. Criar `signingConfigs.release`.
3. Apontar `buildTypes.release.signingConfig` para a configuracao correta.
4. Gerar o AAB com `:app:bundleRelease`.

## Fluxo inicial da URL

1. Instale o app.
2. Abra o app.
3. Veja a splash screen nativa com a marca do Ancora.
4. Se nao houver URL salva, a tela `Conectar ao Ancora` abre antes do login.
5. Informe a URL da instancia.
6. O app valida `GET /api/mobile/v1/health`.
7. Somente depois disso o login nativo e liberado.

## Trocar a URL da instancia

Voce pode trocar a URL:

- pela engrenagem na tela de login
- ou em `Perfil > Alterar endereco do Ancora`

Ao trocar a URL:

- a sessao atual e encerrada
- o token local e removido
- o FCM registrado e limpo
- a nova URL so e salva se o `health` responder corretamente

## Testar login

1. Configure a URL da instancia.
2. Entre com login, chave ou e-mail validos do Portal do Cliente.
3. Confirme a abertura do dashboard nativo.
4. Confirme que nenhuma tela abre navegador.

## Testar biometria

1. Faca login normalmente.
2. Aceite a ativacao de biometria quando o app oferecer.
3. Feche o app.
4. Abra novamente.
5. Confirme a exibicao do prompt `Desbloquear Ancora Clientes`.
6. Teste tambem o fallback `Entrar com senha`.

## Testar push

1. Faca login no app.
2. Confirme o registro do dispositivo em `/api/mobile/v1/devices/register`.
3. No backend, rode:

```bash
php artisan mobile:push:test {client_portal_user_id}
```

4. Toque na notificacao.
5. Confirme que o app abre a tela interna correta.

## Testar Leme IA

1. Abra a aba `Leme IA`.
2. Verifique o carregamento do historico.
3. Envie uma mensagem.
4. Confirme a mensagem otimista, o estado `Leme esta digitando...` e o scroll automatico.
5. Teste `Copiar resposta`.
6. Teste `Limpar conversa`.

## Testar anexos

1. Abra uma solicitacao com anexo publico.
2. Toque em uma imagem e confirme o preview nativo dentro do app.
3. Toque em um arquivo nao imagem e confirme a abertura via `FileProvider`.

## Trocar icone ou logomarca

Arquivos principais:

- `app/src/main/res/drawable/logo_ancora_clientes.png`
- `app/src/main/res/drawable/ic_launcher_foreground.xml`
- `app/src/main/res/drawable/ic_launcher_background.xml`
- `app/src/main/res/drawable/splash_logo.xml`
- `app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml`
- `app/src/main/res/mipmap-anydpi-v26/ic_launcher_round.xml`

Depois de trocar assets, gere novo build.

## Confirmacao anti-WebView

Este app:

- nao usa `WebView`
- nao usa `android.webkit`
- nao usa `CustomTabsIntent`
- nao abre Chrome para acessar o Portal do Cliente
- nao abre navegador externo para processos, solicitacoes ou Leme IA
- nao usa `ACTION_VIEW` para abrir o Portal do Cliente

Observacao:

- existe uso de `ACTION_VIEW` apenas como fallback para abrir arquivo publico baixado via `FileProvider`

## Como confirmar que continua nativo

1. Navegue por Inicio, Processos, Solicitacoes, Leme IA, Notificacoes e Perfil.
2. Confirme que todas as telas sao Compose.
3. Receba um push e toque nele.
4. Verifique que o app navega internamente.
5. Rode uma busca no codigo por `WebView`, `android.webkit`, `CustomTabsIntent`, `ACTION_VIEW`, `chrome` e `browser`.
