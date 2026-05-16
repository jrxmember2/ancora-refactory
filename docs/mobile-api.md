# Mobile API do Ancora Clientes

Base path:

- `/api/mobile/v1`

Objetivo:

- atender o app Android nativo `Ancora Clientes`
- reaproveitar regras do Portal do Cliente
- expor apenas dados publicos e permitidos ao usuario autenticado

## Principios de seguranca

- sem renderizar o portal web
- sem confiar em IDs vindos do app sem revalidacao
- processos privados nao retornam
- andamentos privados nao retornam
- mensagens internas nao retornam
- anexos internos nao retornam
- notificacoes respeitam a audiencia real do portal
- erros de negocio devolvem `401`, `403`, `404` ou `422` sem stack trace

## Endpoints

Health:

- `GET /health`

Autenticacao:

- `POST /auth/login`
- `POST /auth/logout`
- `POST /auth/change-password`
- `GET /me`

Dispositivos e push:

- `POST /devices/register`
- `POST /devices/unregister`

Dashboard:

- `GET /dashboard`

Condominios:

- `GET /condominiums`
- `POST /context/condominium`

Processos:

- `GET /processes`
- `GET /processes/{id}`

Solicitacoes:

- `GET /demands`
- `GET /demands/{id}`
- `POST /demands`
- `POST /demands/{id}/reply`
- `POST /demands/{id}/cancel`
- `GET /demand-categories`
- `GET /demands/{demand}/attachments/{attachment}/download`

Notificacoes:

- `GET /notifications`
- `POST /notifications/{id}/read`
- `POST /notifications/read-all`

Leme IA:

- `GET /leme/history`
- `POST /leme/chat`
- `DELETE /leme/history`

## Health check esperado

```json
{
  "status": "ok",
  "app": "ancora",
  "mobile_api": true,
  "version": "1.0.0"
}
```

## Push

Tabelas envolvidas:

- `client_portal_api_tokens`
- `client_portal_device_tokens`
- `client_portal_notifications`

Variaveis esperadas:

```env
QUEUE_CONNECTION=database
MOBILE_API_TOKEN_TTL_DAYS=30
FCM_ENABLED=true
FCM_PROJECT_ID=
FCM_SERVICE_ACCOUNT_JSON_BASE64=
```

Comandos uteis:

```bash
php artisan migrate --force
php artisan mobile:push:test 123
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

## Leme IA

Payload de envio:

```json
{
  "message": "texto do usuario",
  "conversation_id": 10,
  "context": {
    "screen": "mobile_app"
  }
}
```

Resposta:

```json
{
  "answer": "resposta do Leme IA",
  "conversation_id": 10,
  "created_at": "2026-05-16T12:00:00-03:00"
}
```
