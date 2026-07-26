#!/bin/sh
# Nonalix IA — point d'entrée des conteneurs applicatifs (app, horizon, scheduler, reverb).
set -e

log() { printf '[entrypoint] %s\n' "$1"; }

# --- Attente des dépendances -------------------------------------------------
wait_for() {
    host="$1"; port="$2"; label="$3"; tries=0
    until nc -z "$host" "$port" 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            log "ABANDON : $label ($host:$port) injoignable après 60 s."
            exit 1
        fi
        sleep 1
    done
    log "$label disponible."
}

[ -n "${DB_HOST:-}" ]    && wait_for "$DB_HOST"    "${DB_PORT:-5432}"    "PostgreSQL"
[ -n "${REDIS_HOST:-}" ] && wait_for "$REDIS_HOST" "${REDIS_PORT:-6379}" "Redis"

# --- Préparation (uniquement dans le conteneur applicatif principal) ---------
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then

    if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
        log "Installation des dépendances Composer…"
        composer install --no-interaction --prefer-dist --no-progress
    fi

    if [ -z "${APP_KEY:-}" ]; then
        log "APP_KEY absente — génération."
        php artisan key:generate --force --no-interaction
    fi

    mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        log "Migrations…"
        php artisan migrate --force --no-interaction
    fi

    if [ "${APP_ENV:-local}" = "production" ]; then
        log "Mise en cache de la configuration…"
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
    else
        php artisan optimize:clear >/dev/null 2>&1 || true
    fi

    # -e et non -L : sur un bind mount Windows, un lien symbolique créé dans le
    # conteneur n'est pas toujours reconnu comme tel par le shell, et
    # storage:link échouerait alors à chaque démarrage.
    if [ ! -e public/storage ]; then
        php artisan storage:link >/dev/null 2>&1 || true
    fi
fi

log "Démarrage : $*"
exec "$@"
