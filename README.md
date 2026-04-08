# Âncora Foundation — Laravel + TailAdmin Rewrite

Base de reescrita big bang do Âncora usando:

- Laravel 12
- TailAdmin Laravel
- Tailwind CSS v4
- Alpine.js
- Vite
- MySQL
- EasyPanel

## O que já foi portado nesta base

- shell principal do painel com sidebar/header em TailAdmin
- login custom preservando tabela `users` com `password_hash`
- hub modular baseado em `system_modules`
- dashboard executivo placeholder
- módulo Propostas com:
  - dashboard comercial
  - listagem com filtros
  - cadastro
  - edição
  - visualização
  - histórico básico
  - logs básicos
- módulo Clientes com:
  - visão geral
  - listagens de avulsos, contatos, condomínios e unidades
- busca global inicial
- configuração inicial de branding/módulos/usuários
- logs e auditoria
- Dockerfile para EasyPanel
- SQL base legado copiado para `database/sql`

## O que ainda depende do próximo corte

- upload/download de anexos de propostas
- documento premium/PDF
- CRUD completo de configurações
- CRUD completo do módulo Clientes
- políticas finas de permissão por rota
- filas, scheduler e automações

## Estrutura importante

- `app/Http/Controllers` → controllers do novo core
- `app/Models` → models mapeados para as tabelas atuais
- `app/Services` → regras de propostas e dashboard
- `app/Support` → auth, settings e menu
- `resources/views` → telas Blade adaptadas ao TailAdmin
- `database/sql/ancora_mysql_full.sql` → base SQL atual para importar no MySQL

## Subida local

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
npm run build
php artisan serve
```

## Banco de dados

Esta reescrita foi preparada para **não perder o mecanismo** do sistema atual.
Por isso, a rota mais segura é manter **MySQL** nesta fase e importar o SQL legado:

- `database/sql/ancora_mysql_full.sql`
- depois aplicar, se necessário:
  - `database/sql/2026_03_module_hub.sql`
  - `database/sql/2026_03_proposta_premium.sql`
  - `database/sql/2026_03_desktop_permissions.sql`

## EasyPanel

### Serviço app

Use o `Dockerfile` da raiz.

Variáveis mínimas:

```env
APP_NAME="Âncora"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ancora.serratech.tec.br
DB_CONNECTION=mysql
DB_HOST=<host-do-servico-mysql>
DB_PORT=3306
DB_DATABASE=ancora
DB_USERNAME=<usuario>
DB_PASSWORD=<senha>
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### Volume recomendado

Persistir pelo menos:

- `/var/www/html/storage`
- `/var/www/html/public/uploads`
- `/var/www/html/public/assets/uploads`
- `/var/www/html/public/branding`

### Observação importante

Este pacote foi preparado estruturalmente, mas o build não foi executado neste ambiente porque aqui não havia Composer disponível.
No deploy da VPS/EasyPanel, o `Dockerfile` resolve isso instalando Composer dependencies e assets do Vite.
