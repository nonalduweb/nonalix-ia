#!/usr/bin/env bash
# Nonalix IA — installe le vhost hôte et obtient le certificat TLS.
#
# À lancer UNE FOIS en root sur le VPS, depuis la racine du dépôt, APRÈS que
# les enregistrements DNS des quatre domaines pointent vers ce serveur.
#
#   sudo ./deploy/setup-host-nginx.sh
#
# Le TLS est géré par le certbot système déjà en place (timer certbot.timer),
# et non par un conteneur : le VPS héberge aussi nonalix-ci.com et il ne doit
# y avoir qu'un seul propriétaire des ports 80/443.
set -euo pipefail

cd "$(dirname "$0")/.."

VHOST_NAME="nonalixia.com"
AVAILABLE="/etc/nginx/sites-available/$VHOST_NAME"
ENABLED="/etc/nginx/sites-enabled/$VHOST_NAME"

# shellcheck disable=SC1091
set -a; . ./.env; set +a
: "${NONALIX_SUPPORT_EMAIL:?absent de .env}"

DOMAINS=(
  nonalixia.com www.nonalixia.com
  app.nonalixia.com admin.nonalixia.com api.nonalixia.com
)

[ "$(id -u)" -eq 0 ] || { echo "ARRÊT : lancer en root."; exit 1; }

# --- 1. Le DNS doit déjà pointer ici ----------------------------------------
# Sans cette vérification, certbot consomme un des 5 échecs/heure autorisés
# par Let's Encrypt et l'on se retrouve bloqué une heure pour rien.
PUBLIC_IP="$(curl -fsS --max-time 10 https://api.ipify.org)"
echo "IP publique de ce serveur : $PUBLIC_IP"

FAILED=0
for domain in "${DOMAINS[@]}"; do
    resolved="$(getent ahostsv4 "$domain" | awk 'NR==1{print $1}' || true)"
    if [ "$resolved" = "$PUBLIC_IP" ]; then
        printf '  OK       %-24s -> %s\n' "$domain" "$resolved"
    else
        printf '  MANQUANT %-24s -> %s\n' "$domain" "${resolved:-ne résout pas}"
        FAILED=1
    fi
done

if [ "$FAILED" -eq 1 ]; then
    echo
    echo "ARRÊT : créer les enregistrements A manquants vers $PUBLIC_IP,"
    echo "puis attendre la propagation avant de relancer ce script."
    exit 1
fi

# --- 2. Le conteneur web doit répondre --------------------------------------
# Poser le vhost avant que la cible existe donnerait des 502 le temps du
# diagnostic, et certbot validerait un domaine qui ne sert rien.
APP_PORT="${APP_PORT:-8081}"
if ! curl -fsS --max-time 5 "http://127.0.0.1:$APP_PORT/up" >/dev/null; then
    echo "ARRÊT : rien ne répond sur http://127.0.0.1:$APP_PORT/up"
    echo "Démarrer d'abord la stack : docker compose -f docker-compose.prod.yml up -d"
    exit 1
fi
echo "Conteneur web joignable sur 127.0.0.1:$APP_PORT"

# --- 3. Installer le vhost ---------------------------------------------------
if [ -e "$AVAILABLE" ]; then
    cp -a "$AVAILABLE" "$AVAILABLE.bak-$(date +%F-%H%M%S)"
    echo "Vhost existant sauvegardé."
fi

install -m 0644 deploy/nginx-host.conf "$AVAILABLE"
ln -sfn "$AVAILABLE" "$ENABLED"

# nginx -t avant reload : une configuration invalide laisserait le serveur sur
# l'ancienne, mais un redémarrage ultérieur échouerait et couperait AUSSI
# nonalix-ci.com.
nginx -t
systemctl reload nginx
echo "Vhost HTTP actif."

# --- 4. Certificat TLS -------------------------------------------------------
# --nginx laisse certbot injecter les directives TLS et la redirection 443 —
# c'est ainsi que le vhost nonalix-ci.com est géré sur ce serveur, et c'est ce
# qui permet au timer certbot.timer de renouveler sans intervention.
CERT_ARGS=()
for domain in "${DOMAINS[@]}"; do CERT_ARGS+=(-d "$domain"); done

certbot --nginx \
    --cert-name "$VHOST_NAME" \
    --email "$NONALIX_SUPPORT_EMAIL" \
    --agree-tos --no-eff-email --non-interactive \
    --redirect \
    "${CERT_ARGS[@]}"

nginx -t
systemctl reload nginx

echo
echo "Terminé. Vérifier :"
for domain in nonalixia.com app.nonalixia.com admin.nonalixia.com api.nonalixia.com; do
    echo "  curl -I https://$domain"
done
echo
echo "Renouvellement automatique : systemctl list-timers certbot.timer"
