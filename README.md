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
  - anexos PDF
  - histórico
  - documento premium em HTML/print
- módulo Clientes com:
  - visão geral
  - CRUD de avulsos, contatos, condomínios e unidades
  - anexos, timeline e importação CSV de unidades
- módulo Cobranças com:
  - dashboard
  - CRUD de OS
  - cotas, parcelas, GED e timeline
  - importação XLS/XLSX de inadimplência
- busca global inicial
- configuração inicial de branding/módulos/usuários
- permissões por rota
- logs e auditoria
- Dockerfile para EasyPanel
- SQL base legado copiado para `database/sql`

## O que ainda depende do próximo corte

- dashboard executivo consolidado
- geração real de PDF binário para o documento premium
- revisão fina dos perfis de acesso por operação
- filas, scheduler e automações

## Estrutura importante

- `app/Http/Controllers` → controllers do novo core
- `app/Models` → models mapeados para as tabelas atuais
- `app/Services` → regras de propostas e dashboard
- `app/Support` → auth, settings e menu
- `resources/views` → telas Blade adaptadas ao TailAdmin
- `database/sql/ancora_mysql_full_corrigido.sql` → base SQL atual para importar no MySQL

## Subida local

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
npm run build
php artisan serve
```

> Observação: este projeto usa o schema legado do Âncora. Para uma base funcional, prefira importar o SQL completo no MySQL antes de acessar o sistema.

## Banco de dados

Esta reescrita foi preparada para **não perder o mecanismo** do sistema atual.
Por isso, a rota mais segura é manter **MySQL** nesta fase e importar o SQL legado:

1. `database/sql/ancora_mysql_full_corrigido.sql`
2. `database/sql/2026_03_module_hub.sql`
3. `database/sql/2026_03_proposta_premium.sql`
4. `database/sql/2026_03_desktop_permissions.sql`
5. `database/sql/2026_04_route_permissions.sql`
6. `database/sql/2026_04_cobranca_module.sql`
7. `database/sql/2026_04_cobranca_importacao.sql`

Se preferir usar migrations para os incrementos mais novos, importe o SQL completo e rode apenas as migrations específicas de permissões, Cobranças e importação. Não rode migrations sem entender o estado do banco, porque a base nasceu de um dump legado.

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
