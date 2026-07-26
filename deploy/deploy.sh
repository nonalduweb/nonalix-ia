#!/usr/bin/env bash
# Nonalix IA — déploiement et mise à jour en production.
#
#   ./deploy/deploy.sh            # récupère, construit, migre, bascule
#   ./deploy/deploy.sh --no-pull  # redéploie le code déjà présent
#
# Ordre imposé : migrer AVANT de remplacer les conteneurs. Dans l'autre sens,
# un worker encore actif écrit dans un schéma qu'il ne connaît pas.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"
PULL=true
[ "${1:-}" = "--no-pull" ] && PULL=false

# shellcheck disable=SC1091
set -a; . ./.env; set +a

if [ "${APP_ENV:-}" != "production" ]; then
    echo "ARRÊT : APP_ENV vaut '${APP_ENV:-vide}' et non 'production'."
    exit 1
fi
: "${APP_KEY:?absente — sans elle tous les secrets chiffrés sont illisibles}"

if [ ! -f docker/nginx/enabled/10-https.conf ]; then
    echo "ARRÊT : le vhost HTTPS n'est pas actif. Lancer d'abord ./deploy/init-tls.sh."
    exit 1
fi

# --- 1. Sauvegarde de sécurité avant migration ------------------------------
# Une migration qui échoue à mi-chemin laisse un schéma incohérent : ce dump
# est le seul moyen de revenir en arrière.
if $COMPOSE ps --status running --services | grep -qx postgres; then
    mkdir -p storage/backups
    dump="storage/backups/pre-deploy-$(date +%F-%H%M%S).sql.gz"
    echo "Sauvegarde préalable -> $dump"
    $COMPOSE exec -T postgres pg_dump -U "${DB_USERNAME:-nonalix}" \
        "${DB_DATABASE:-nonalix}" | gzip > "$dump"
fi

# --- 2. Récupérer le code ----------------------------------------------------
if [ "$PULL" = true ]; then
    git pull --ff-only
fi

# --- 3. Construire les images -----------------------------------------------
# `web` dépend de `app` (il en copie public/) : construire les deux ensemble.
$COMPOSE build app web

# --- 4. Migrer, conteneurs actuels encore en place ---------------------------
# --no-deps : ne pas réveiller un conteneur applicatif de l'ancienne version.
$COMPOSE run --rm --no-deps -e RUN_MIGRATIONS=false app php artisan migrate --force

# --- 5. Basculer -------------------------------------------------------------
$COMPOSE up -d --no-deps app web reverb

# Horizon termine ses jobs en cours puis s'arrête ; le nouveau conteneur
# repart sur le nouveau code.
$COMPOSE exec -T app php artisan horizon:terminate || true
$COMPOSE up -d --no-deps horizon scheduler

# --- 6. Vérifier -------------------------------------------------------------
$COMPOSE exec -T app php artisan nonalix:health
$COMPOSE exec -T web nginx -t

echo
echo "Déployé. Conteneurs :"
$COMPOSE ps

# Ne garder que 10 sauvegardes pré-déploiement (les vraies sauvegardes sont
# les dumps chiffrés quotidiens, hors de ce serveur).
ls -1t storage/backups/pre-deploy-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --
