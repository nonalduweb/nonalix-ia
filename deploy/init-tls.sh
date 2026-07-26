#!/usr/bin/env bash
# Nonalix IA — première mise en service TLS.
#
# À lancer UNE FOIS, depuis la racine du dépôt sur le VPS, après que les
# enregistrements DNS pointent vers ce serveur.
#
#   ./deploy/init-tls.sh
#
# Le vhost HTTPS ne peut pas être actif avant l'émission du certificat :
# Nginx refuse de démarrer si ssl_certificate désigne un fichier absent.
# Ce script démarre donc en HTTP seul, obtient le certificat, puis bascule.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"
AVAILABLE="docker/nginx/available"
ENABLED="docker/nginx/enabled"

# shellcheck disable=SC1091
set -a; . ./.env; set +a

: "${NONALIX_DOMAIN_MARKETING:?absent de .env}"
: "${NONALIX_DOMAIN_APP:?absent de .env}"
: "${NONALIX_DOMAIN_ADMIN:?absent de .env}"
: "${NONALIX_DOMAIN_API:?absent de .env}"
: "${NONALIX_SUPPORT_EMAIL:?absent de .env}"

DOMAINS=(
  "$NONALIX_DOMAIN_MARKETING"
  "$NONALIX_DOMAIN_APP"
  "$NONALIX_DOMAIN_ADMIN"
  "$NONALIX_DOMAIN_API"
)

# --- 1. Vérifier que le DNS pointe bien ici ---------------------------------
# Sans cela, certbot consomme un des 5 échecs/heure autorisés par Let's Encrypt
# et le diagnostic arrive trop tard.
PUBLIC_IP="$(curl -fsS --max-time 10 https://api.ipify.org)"
echo "IP publique de ce serveur : $PUBLIC_IP"

for domain in "${DOMAINS[@]}"; do
    resolved="$(getent ahostsv4 "$domain" | awk 'NR==1{print $1}' || true)"
    if [ "$resolved" != "$PUBLIC_IP" ]; then
        echo "ARRÊT : $domain résout vers '${resolved:-rien}', attendu $PUBLIC_IP."
        echo "Corriger la zone DNS puis attendre la propagation."
        exit 1
    fi
    echo "  $domain -> $resolved"
done

# --- 2. Démarrer la stack en HTTP seul --------------------------------------
mkdir -p "$ENABLED"
rm -f "$ENABLED"/*.conf
cp "$AVAILABLE/00-http.conf" "$ENABLED/"

$COMPOSE up -d --wait postgres redis
$COMPOSE up -d app reverb web

# --- 3. Émettre le certificat SAN -------------------------------------------
# --cert-name fige le chemin /etc/letsencrypt/live/<marketing>/ auquel le
# vhost HTTPS fait référence : sans lui, certbot suffixerait en -0001 lors
# d'une réémission et Nginx chercherait un chemin qui n'existe plus.
CERT_ARGS=()
for domain in "${DOMAINS[@]}"; do CERT_ARGS+=(-d "$domain"); done

$COMPOSE run --rm --entrypoint certbot certbot certonly \
    --webroot -w /var/www/certbot \
    --cert-name "$NONALIX_DOMAIN_MARKETING" \
    --email "$NONALIX_SUPPORT_EMAIL" \
    --agree-tos --no-eff-email --non-interactive \
    "${CERT_ARGS[@]}"

# --- 4. Activer le vhost HTTPS ----------------------------------------------
cp "$AVAILABLE/10-https.conf" "$ENABLED/"
$COMPOSE exec web nginx -t
$COMPOSE exec web nginx -s reload

echo
echo "TLS actif. Vérifier : curl -I https://$NONALIX_DOMAIN_APP"
echo "Lancer ensuite ./deploy/deploy.sh pour la mise en service applicative."
