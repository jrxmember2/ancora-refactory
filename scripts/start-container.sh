#!/bin/sh
set -eu

APP_ROOT="/var/www/html"

ensure_dir() {
    mkdir -p "$1"
}

fix_permissions() {
    target="$1"

    if [ ! -e "$target" ]; then
        return
    fi

    chown -R www-data:www-data "$target"
    chmod -R ug+rwX "$target"
}

ensure_dir "$APP_ROOT/storage/app/public/avatars/users"
ensure_dir "$APP_ROOT/storage/framework/cache"
ensure_dir "$APP_ROOT/storage/framework/sessions"
ensure_dir "$APP_ROOT/storage/framework/views"
ensure_dir "$APP_ROOT/storage/logs"
ensure_dir "$APP_ROOT/bootstrap/cache"
ensure_dir "$APP_ROOT/public/uploads"
ensure_dir "$APP_ROOT/public/assets/uploads"
ensure_dir "$APP_ROOT/public/branding"
ensure_dir "$APP_ROOT/public/build"

ln -sfn "$APP_ROOT/storage/app/public" "$APP_ROOT/public/storage"

fix_permissions "$APP_ROOT/storage"
fix_permissions "$APP_ROOT/bootstrap/cache"
fix_permissions "$APP_ROOT/public/uploads"
fix_permissions "$APP_ROOT/public/assets/uploads"
fix_permissions "$APP_ROOT/public/branding"
fix_permissions "$APP_ROOT/public/build"

exec apache2-foreground
