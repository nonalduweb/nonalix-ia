# Nonalix IA — Déploiement

---

## 1. Développement local

Prérequis : **Docker Desktop** (démarré) et **Git**. Aucun PHP ni Composer local.

```bash
git clone <dépôt> nonalix-ia && cd nonalix-ia
cp .env.example .env
make install
```

`make install` construit les images, attend PostgreSQL, installe les dépendances,
génère `APP_KEY`, migre, sème les données de démonstration et démarre la stack.

### Domaines locaux

Le routage est contraint par domaine : `http://localhost` ne suffit pas pour
tester les quatre espaces. Ajouter au fichier hosts
(`C:\Windows\System32\drivers\etc\hosts` sous Windows, `/etc/hosts` ailleurs) :

```
127.0.0.1  nonalixia.test app.nonalixia.test admin.nonalixia.test api.nonalixia.test
```

| Espace | URL |
|---|---|
| Site commercial | http://nonalixia.test |
| Espace client | http://app.nonalixia.test |
| Administration | http://admin.nonalixia.test |
| API et webhooks | http://api.nonalixia.test |
| Horizon | http://app.nonalixia.test/horizon *(super-admin uniquement)* |
| Mailpit | http://localhost:8025 |

Compte de démonstration : `demo@nonalixia.test` / `password`.

### Front en mode watch

```bash
docker compose --profile dev up -d vite
```

### Commandes courantes

```bash
make test        # suite Pest complète
make fresh       # recrée la base (DESTRUCTIF)
make logs s=horizon
make health      # PostgreSQL + pgvector + Redis
make shell
```

---

## 2. Connecter un numéro WhatsApp

Étapes côté client, après création de son compte :

1. **Meta Business** — créer une application de type *Business* sur
   developers.facebook.com, y ajouter le produit *WhatsApp*.
2. **Numéro** — ajouter et vérifier un numéro dans le WhatsApp Manager.
   Ce numéro ne doit être rattaché à aucun compte WhatsApp personnel.
3. **Jeton permanent** — créer un *System User* dans les paramètres Business,
   lui accorder `whatsapp_business_messaging` et `whatsapp_business_management`,
   puis générer un jeton **sans expiration**.
4. **Saisie dans Nonalix** — `app.nonalixia.com/settings/whatsapp` :
   `waba_id`, `phone_number_id`, jeton d'accès, `app_secret`.
5. **Webhook** — dans l'application Meta, produit WhatsApp → Configuration :
   - URL de rappel : `https://api.nonalixia.com/webhooks/whatsapp/{tenant_uuid}`
     (affichée dans l'écran de configuration) ;
   - jeton de vérification : celui généré par Nonalix ;
   - s'abonner au champ **messages**.
6. **Valider** — bouton « Tester la connexion ». Le nom vérifié et la note de
   qualité doivent remonter.

> Le jeton et l'`app_secret` sont chiffrés en base (AES-256 via `APP_KEY`).
> **Perdre `APP_KEY` rend tous les comptes WhatsApp irrécupérables** : les
> clients devraient re-saisir leurs identifiants. Sauvegarder cette clé
> séparément des dumps de base.

---

## 3. Production

### 3.1 Dimensionnement de départ

| Composant | Recommandation |
|---|---|
| VPS applicatif | 4 vCPU / 8 Go — app, Nginx, Horizon, Reverb, scheduler |
| PostgreSQL | 2 vCPU / 4 Go, 50 Go SSD, extension `vector` disponible |
| Redis | 1 Go, `maxmemory-policy noeviction` |

`noeviction` est impératif : avec une politique d'éviction, Redis pourrait
supprimer des jobs en attente ou des compteurs de quota sous pression mémoire.

### 3.2 Serveur de production actuel

| | |
|---|---|
| Hôte | `srv1765821` — `46.202.194.151` (Hostinger, Ubuntu 24.04 LTS) |
| Dépôt | `/root/nonalix-ia`, cloné depuis `github-nonalix-ia:nonalduweb/nonalix-ia.git` |
| Stack | `docker compose -f docker-compose.prod.yml` |

**Ce VPS héberge aussi le projet `nonalix-ci`** (`/root/nonalix-ci` :
frontend, backend, MySQL, n8n), servi par le Nginx **système** qui détient les
ports 80 et 443, avec un certbot système renouvelé par `certbot.timer`.

Conséquence directe : la stack Nonalix IA **ne prend pas 80/443**. Elle publie
son conteneur web sur `127.0.0.1:8081` et le Nginx de l'hôte relaie. Toute
tentative de terminer le TLS depuis Docker ferait tomber `nonalix-ci.com`.

```
Internet ─443─> Nginx hôte ──> 127.0.0.1:8081 ──> nonalix-web ──> nonalix-app
             (TLS, certbot)                        (Nginx)        (PHP-FPM)
                    │                                  └────────> nonalix-reverb
                    └─────────> 127.0.0.1:3000/8000/5678  (nonalix-ci, intact)
```

Le clonage utilise une clé de déploiement dédiée en lecture seule
(`/root/.ssh/nonalix_ia_deploy`, alias `github-nonalix-ia` dans
`/root/.ssh/config`) : elle est révocable sans toucher aux accès de
`nonalix-ci`.

### 3.3 Variables d'environnement

Partir de `.env.production.example`, qui documente chaque valeur.

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # openssl rand -base64 32, préfixé de "base64:"
APP_URL=https://app.nonalixia.com

NONALIX_DOMAIN_MARKETING=nonalixia.com
NONALIX_DOMAIN_APP=app.nonalixia.com
NONALIX_DOMAIN_ADMIN=admin.nonalixia.com
NONALIX_DOMAIN_API=api.nonalixia.com

SESSION_DOMAIN=.nonalixia.com     # le point initial est requis
SESSION_SECURE_COOKIE=true

APP_PORT=8081                     # boucle locale, relayée par le Nginx hôte
WHATSAPP_ALLOW_UNSIGNED=false     # toute autre valeur = webhooks ouverts
```

Les secrets sont **générés sur le serveur**, jamais repris du poste de
développement : une fuite du poste ne doit pas compromettre la production.

`REVERB_APP_KEY` est un cas particulier — elle est lue au **build** de l'image
(elle finit dans le bundle JavaScript). La changer impose un
`docker compose -f docker-compose.prod.yml build app web`, pas un simple
redémarrage.

### 3.4 DNS

Cinq enregistrements vers l'IP du serveur. La zone de `nonalixia.com` est
hébergée sur les serveurs de noms Hostinger (`nova`/`cosmos.dns-parking.com`)
et se gère depuis le hPanel du compte propriétaire du domaine — **aucun
transfert de domaine n'est nécessaire**, même si le VPS est sur un autre
compte.

| Type | Nom | Valeur |
|---|---|---|
| A | `@` | `46.202.194.151` |
| A | `app` | `46.202.194.151` |
| A | `admin` | `46.202.194.151` |
| A | `api` | `46.202.194.151` |
| CNAME | `www` | `nonalixia.com` |

### 3.5 Première mise en service

```bash
cd /root/nonalix-ia
cp .env.production.example .env && chmod 600 .env   # puis renseigner
docker compose -f docker-compose.prod.yml build app web
docker compose -f docker-compose.prod.yml up -d postgres redis

docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
for seeder in PermissionSeeder PlanSeeder SuperAdminSeeder; do
  docker compose -f docker-compose.prod.yml run --rm \
    -e NONALIX_ADMIN_EMAIL=admin@nonalixia.com app php artisan db:seed --class=$seeder
done

docker compose -f docker-compose.prod.yml up -d
sudo ./deploy/setup-host-nginx.sh      # vhost hôte + certificat TLS
```

`setup-host-nginx.sh` refuse de s'exécuter tant que le DNS ne pointe pas vers
le serveur et que `/up` ne répond pas : sans ces gardes, chaque essai raté
consommerait un des cinq échecs horaires autorisés par Let's Encrypt.

Le mot de passe du super-admin est affiché **une seule fois**. Activer la 2FA
dès la première connexion : ce compte voit toute la plateforme.

### 3.6 Déploiement sans interruption

```bash
cd /root/nonalix-ia && ./deploy/deploy.sh
```

Le script enchaîne : dump PostgreSQL de sécurité, `git pull`, build des images
`app` et `web`, migration, bascule des conteneurs, arrêt propre d'Horizon,
contrôle de santé.

**Ordre important** : migrer avant de remplacer les conteneurs, sinon un worker
encore actif écrit dans un schéma qu'il ne connaît pas. C'est aussi pourquoi
`RUN_MIGRATIONS=false` en production — laisser l'entrypoint migrer ferait
courir plusieurs conteneurs sur la même migration au démarrage.

`web` est reconstruit en même temps que `app` : il embarque `public/`, donc les
assets Vite. Ne reconstruire que `app` servirait le nouveau manifeste avec les
anciens fichiers, et toutes les pages tomberaient en erreur.

---

## 4. Sauvegardes

```bash
# Dump chiffré quotidien
docker compose exec -T postgres pg_dump -U nonalix nonalix \
  | gzip | gpg --encrypt --recipient backup@nonalixia.com \
  > "backup-$(date +%F).sql.gz.gpg"
```

À sauvegarder également, hors de la base :

- **`APP_KEY`** — sans elle, tous les secrets chiffrés sont perdus ;
- `storage/app/private/` — documents de la base de connaissances et médias.

Rétention 30 jours. **Tester la restauration tous les trimestres** : une
sauvegarde jamais restaurée n'est pas une sauvegarde.

```bash
gpg --decrypt backup-2026-07-24.sql.gz.gpg | gunzip \
  | docker compose exec -T postgres psql -U nonalix nonalix
```

---

## 5. Supervision

| Vérification | Comment |
|---|---|
| Santé applicative | `GET /up` et `php artisan nonalix:health` |
| Files d'attente | Horizon — alerter si `webhooks` dépasse 30 s d'attente |
| Incidents | `admin.nonalixia.com/incidents`, niveau `critical` |
| Qualité des numéros | `whatsapp_accounts.quality_rating` — une baisse précède une limitation Meta |
| Coût IA | `admin.nonalixia.com/usage` — surveiller la marge par client |

### Signaux d'alerte

- **Signatures de webhook invalides** → incident `critical`. Soit l'`app_secret`
  d'un client a changé, soit quelqu'un tente d'injecter de faux messages.
- **Taux de reprise humaine > 30 %** → l'agent ne tient pas son rôle ; revoir
  le prompt et la base de connaissances de ce client.
- **File `ai` qui s'allonge** → fournisseur en difficulté ; vérifier le repli.

---

## 6. Incidents courants

| Symptôme | Cause probable | Action |
|---|---|---|
| Aucun message ne remonte | Webhook non abonné au champ `messages`, ou URL erronée | Vérifier la configuration Meta et `webhook_events` |
| Signature invalide en boucle | `app_secret` obsolète | Le client doit re-saisir le secret de son application |
| L'IA ne répond pas | Agent inactif, quota atteint, ou conversation transférée | Vérifier `agents.is_active`, les quotas, `conversations.handover_at` |
| Envoi refusé | Fenêtre de 24 h fermée | Utiliser un template approuvé |
| Recherche RAG vide | Documents non `ready`, ou modèle d'embedding modifié | Relancer l'indexation depuis l'écran Connaissances |
| `Extension vector absente` | Volume PostgreSQL créé sans pgvector | `CREATE EXTENSION vector;` puis relancer les migrations |
