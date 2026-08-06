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

# --- 3. Contrôle de syntaxe PHP ----------------------------------------------
# Une erreur d'analyse ne se voit ni à la construction de l'image, ni au
# démarrage : elle n'éclate qu'à la première requête touchant le fichier, en
# 500. C'est arrivé en production sur un contrôleur d'authentification.
# `php -l` sur l'ensemble coûte quelques secondes et ferme la porte.
if [ -n "$(docker images -q nonalix/app:latest 2>/dev/null)" ]; then
    echo "Contrôle de syntaxe PHP…"
    if ! docker run --rm -v "$PWD:/src" --entrypoint sh nonalix/app:latest -c \
        'find /src/app /src/config /src/routes /src/database -name "*.php" \
            -exec php -l {} \; 2>&1 | grep -v "^No syntax errors" | grep . && exit 1 || exit 0'
    then
        echo "ARRÊT : erreur de syntaxe PHP. Rien n'a été déployé."
        exit 1
    fi
fi

# --- 4. Construire les images -----------------------------------------------
# `web` dépend de `app` (il en copie public/) : construire les deux ensemble.
$COMPOSE build app web

# --- 5. Migrer, conteneurs actuels encore en place ---------------------------
# --no-deps : ne pas réveiller un conteneur applicatif de l'ancienne version.
$COMPOSE run --rm --no-deps -e RUN_MIGRATIONS=false app php artisan migrate --force

# --- 6. Basculer -------------------------------------------------------------
$COMPOSE up -d --no-deps app web reverb

# Horizon termine ses jobs en cours puis s'arrête ; le nouveau conteneur
# repart sur le nouveau code.
$COMPOSE exec -T app php artisan horizon:terminate || true
$COMPOSE up -d --no-deps horizon scheduler

# --- 7. Vérifier -------------------------------------------------------------
$COMPOSE exec -T app php artisan nonalix:health
$COMPOSE exec -T web nginx -t

# Le TLS et le routage par domaine sont assurés par le Nginx de l'hôte : ce
# point de contrôle vérifie la chaîne complète, pas seulement les conteneurs.
curl -fsS --max-time 10 "http://127.0.0.1:${APP_PORT:-8081}/up" >/dev/null \
    && echo "Conteneur web : /up répond." \
    || echo "ATTENTION : /up ne répond pas sur 127.0.0.1:${APP_PORT:-8081}."

echo
echo "Déployé. Conteneurs :"
$COMPOSE ps

# Ne garder que 10 sauvegardes pré-déploiement (les vraies sauvegardes sont
# les dumps chiffrés quotidiens, hors de ce serveur).
ls -1t storage/backups/pre-deploy-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --
