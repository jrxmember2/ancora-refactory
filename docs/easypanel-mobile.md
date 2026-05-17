# EasyPanel para a API Mobile do Ancora

O EasyPanel sobe o backend Laravel. O app Android nao sobe no EasyPanel.

## Passo a passo

1. Suba a aplicacao Laravel usando o `Dockerfile` da raiz.
2. Configure o `.env` com banco, URL publica, fila e FCM.
3. Rode as migrations.
4. Rode `storage:link`.
5. Limpe cache e gere caches de config e rotas.
6. Suba um worker para a fila.
7. Teste `GET /api/mobile/v1/health`.
8. Teste login mobile.
9. Teste push com o comando artisan.

## Variaveis minimas para a API mobile funcionar

```env
APP_URL=https://seu-dominio
CLIENT_PORTAL_DOMAIN=cliente.seu-dominio.com.br
MOBILE_API_TOKEN_TTL_DAYS=30
SESSION_DRIVER=file
SESSION_DOMAIN=
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
FCM_ENABLED=false
```

Esse bloco acima e suficiente para:

- responder `/api/mobile/v1/health`
- autenticar no app
- listar processos, solicitacoes, notificacoes e Leme IA
- trocar URL da instancia e manter sessao mobile

## Variaveis recomendadas para producao no EasyPanel

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ancora.serratech.tec.br
CLIENT_PORTAL_DOMAIN=cliente.serratech.tec.br
SESSION_DOMAIN=
CACHE_STORE=file
SESSION_DRIVER=file
FILESYSTEM_DISK=local
MOBILE_API_TOKEN_TTL_DAYS=30
```

Observacoes:

- `SESSION_DOMAIN=` vazio esta correto quando voce quer isolar a sessao interna do portal do cliente.
- `FILESYSTEM_DISK=local` pode permanecer assim se os uploads atuais do projeto ja usam o storage local/volume persistido.
- `APP_ENV=local` e `APP_DEBUG=true` funcionam tecnicamente, mas nao sao recomendados em producao.

## Variaveis obrigatorias para push assíncrono

Se voce quiser push do app funcionando do jeito certo em producao, troque a fila para `database` e configure o Firebase:

```env
QUEUE_CONNECTION=database
FCM_ENABLED=true
FCM_PROJECT_ID=
FCM_SERVICE_ACCOUNT_JSON_BASE64=
```

Sem isso:

- o app mobile funciona
- o login funciona
- a API mobile funciona
- mas o push fica desativado ou inadequado para producao

## Comparando com o seu .env atual

Seu `.env` atual no EasyPanel esta quase pronto para a API mobile basica. O que eu recomendo ajustar:

- manter `APP_URL=https://ancora.serratech.tec.br`
- manter `CLIENT_PORTAL_DOMAIN=cliente.serratech.tec.br`
- manter `SESSION_DOMAIN=`
- manter `SESSION_DRIVER=file`
- manter `FILESYSTEM_DISK=local`
- manter `DB_*` como esta
- trocar `APP_ENV=local` por `APP_ENV=production`
- trocar `APP_DEBUG=true` por `APP_DEBUG=false`
- trocar `QUEUE_CONNECTION=sync` por `QUEUE_CONNECTION=database` se for usar push e fila assíncrona
- adicionar `MOBILE_API_TOKEN_TTL_DAYS=30`
- adicionar `FCM_ENABLED`, `FCM_PROJECT_ID` e `FCM_SERVICE_ACCOUNT_JSON_BASE64` quando for ativar push

Exemplo mais aderente ao seu ambiente:

```env
APP_NAME="Ancora"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ancora.serratech.tec.br
CLIENT_PORTAL_DOMAIN=cliente.serratech.tec.br
SESSION_DOMAIN=

APP_KEY=...

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=ancora-hub_ancora-db
DB_PORT=3306
DB_DATABASE=ancora-db1
DB_USERNAME=ancora
DB_PASSWORD=...

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MOBILE_API_TOKEN_TTL_DAYS=30
FCM_ENABLED=false
FCM_PROJECT_ID=
FCM_SERVICE_ACCOUNT_JSON_BASE64=
```

## Comandos importantes

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan queue:work --sleep=3 --tries=3 --timeout=120
php artisan mobile:push:test {client_portal_user_id}
```

## Worker sugerido

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

## Checklist final

- `/api/mobile/v1/health` responde `ok`
- login mobile funciona
- tokens FCM sao registrados em `client_portal_device_tokens`
- notificacoes sao criadas em `client_portal_notifications`
- o worker esta consumindo a fila
- o app abre telas internas e nao o navegador
