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

### 3.2 Variables d'environnement

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # php artisan key:generate --show
APP_URL=https://app.nonalixia.com

NONALIX_DOMAIN_MARKETING=nonalixia.com
NONALIX_DOMAIN_APP=app.nonalixia.com
NONALIX_DOMAIN_ADMIN=admin.nonalixia.com
NONALIX_DOMAIN_API=api.nonalixia.com

SESSION_DOMAIN=.nonalixia.com     # le point initial est requis
SESSION_SECURE_COOKIE=true

DOCKER_TARGET=app
WHATSAPP_ALLOW_UNSIGNED=false     # toute autre valeur = webhooks ouverts
```

### 3.3 DNS

Quatre enregistrements A vers l'IP du serveur :
`nonalixia.com`, `app`, `admin`, `api`.

### 3.4 TLS

Certificat SAN couvrant les quatre noms :

```bash
certbot certonly --standalone \
  -d nonalixia.com -d app.nonalixia.com \
  -d admin.nonalixia.com -d api.nonalixia.com
```

Monter les certificats dans le conteneur Nginx, ajouter le bloc `listen 443 ssl`
et activer l'en-tête HSTS (commenté dans `docker/nginx/default.conf`).

### 3.5 Première mise en service

```bash
export DOCKER_TARGET=app
docker compose build
docker compose up -d postgres redis

docker compose run --rm app php artisan migrate --force
docker compose run --rm -e NONALIX_ADMIN_EMAIL=admin@nonalixia.com \
    app php artisan db:seed --class=PermissionSeeder
docker compose run --rm -e NONALIX_ADMIN_EMAIL=admin@nonalixia.com \
    app php artisan db:seed --class=PlanSeeder
docker compose run --rm -e NONALIX_ADMIN_EMAIL=admin@nonalixia.com \
    app php artisan db:seed --class=SuperAdminSeeder

docker compose up -d
```

Le mot de passe du super-admin est affiché **une seule fois**. Activer la 2FA
dès la première connexion : ce compte voit toute la plateforme.

### 3.6 Déploiement sans interruption

```bash
git pull --ff-only
docker compose build app

docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan config:cache
docker compose run --rm app php artisan route:cache
docker compose run --rm app php artisan view:cache
docker compose run --rm app php artisan event:cache

docker compose up -d --no-deps app nginx reverb

# Les workers finissent leur job courant, puis redémarrent avec le nouveau code.
docker compose exec app php artisan horizon:terminate
docker compose up -d --no-deps horizon scheduler
```

**Ordre important** : migrer avant de remplacer les conteneurs, sinon un worker
encore actif écrit dans un schéma qu'il ne connaît pas.

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
