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

1. `database/sql/ancora_mysql_full_corrigido.sql`
2. `database/sql/2026_03_module_hub.sql`
3. `database/sql/2026_03_proposta_premium.sql`
4. `database/sql/2026_03_desktop_permissions.sql`
5. `database/sql/2026_04_route_permissions.sql`
6. `database/sql/2026_04_cobranca_module.sql`
7. `database/sql/2026_04_cobranca_importacao.sql`
8. `database/sql/2026_04_cobranca_termos_acordo.sql`

Se a importação de Cobranças for aplicada por migrations em vez de SQL, rode:

```bash
php artisan migrate --path=database/migrations/2026_04_01_000010_create_route_permissions_tables.php --force
php artisan migrate --path=database/migrations/2026_04_07_000100_create_cobranca_tables.php --force
php artisan migrate --path=database/migrations/2026_04_10_000200_create_cobranca_import_tables.php --force
php artisan migrate --path=database/migrations/2026_04_10_000210_seed_cobranca_import_permissions.php --force
php artisan migrate --path=database/migrations/2026_04_11_000300_create_cobranca_agreement_terms_table.php --force
php artisan optimize:clear
```

> O termo de acordo depende da tabela `cobranca_agreement_terms`. Se o código for publicado antes desta migration/SQL, o sistema exibe o rascunho e o PDF, mas bloqueia o salvamento de customizações até o banco ser atualizado.

> A emissão do PDF do termo usa Chromium headless instalado pelo `Dockerfile`.

## 5. Volumes

Crie persistência para:

- `/var/www/html/storage`
- `/var/www/html/public/uploads`
- `/var/www/html/public/assets/uploads`
- `/var/www/html/public/branding`

## 6. Primeiro acesso

Acesse `/login` usando um usuário já existente da tabela `users`.
A autenticação utiliza `password_hash`, preservando o mecanismo do sistema anterior.


## Importação de inadimplência

Como o Dockerfile foi ajustado para leitura de planilhas, faça um novo build da imagem no EasyPanel antes de subir o container. O container instala `python3`, `openpyxl` e `xlrd`; isso é necessário para processar arquivos `.xls`. Arquivos `.xlsx` também têm leitura nativa em PHP via `ZipArchive`.
