# Deploy no EasyPanel

## 1. Banco MySQL

Crie um serviço MySQL no EasyPanel com um banco chamado `ancora`.

## 2. App

Crie um **App Service** apontando para este repositório/zip usando o `Dockerfile` da raiz.

## 3. Variáveis

Configure:

- `APP_NAME="Âncora"`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://ancora.serratech.tec.br`
- `DB_CONNECTION=mysql`
- `DB_HOST=<host-mysql>`
- `DB_PORT=3306`
- `DB_DATABASE=ancora`
- `DB_USERNAME=<usuario>`
- `DB_PASSWORD=<senha>`
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`

## 4. Importação do banco

Depois que o MySQL estiver no ar, importe:

1. `database/sql/ancora_mysql_full.sql`
2. `database/sql/2026_03_module_hub.sql`
3. `database/sql/2026_03_proposta_premium.sql`
4. `database/sql/2026_03_desktop_permissions.sql`

## 5. Volumes

Crie persistência para:

- `/var/www/html/storage`
- `/var/www/html/public/uploads`
- `/var/www/html/public/assets/uploads`
- `/var/www/html/public/branding`

## 6. Primeiro acesso

Acesse `/login` usando um usuário já existente da tabela `users`.
A autenticação utiliza `password_hash`, preservando o mecanismo do sistema anterior.
