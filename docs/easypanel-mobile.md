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

## Variaveis minimas para mobile

```env
APP_URL=https://seu-dominio
QUEUE_CONNECTION=database
MOBILE_API_TOKEN_TTL_DAYS=30
FCM_ENABLED=true
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
