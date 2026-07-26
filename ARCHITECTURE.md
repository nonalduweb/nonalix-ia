# Nonalix IA — Architecture

> Plateforme SaaS multi-tenant permettant à une entreprise de connecter son propre
> numéro WhatsApp Business et de déployer un agent IA personnalisé.

Version du document : 1.0 — Phase 1 (MVP)

---

## 1. Vue d'ensemble

Nonalix IA est une **application Laravel unique, centralisée et multi-tenant**. Il n'y a pas
un déploiement par client : une seule base de code, une seule base de données, une seule
flotte de workers. L'isolation se fait **au niveau applicatif** via une colonne `tenant_id`
présente sur toutes les tables métier, doublée d'un `Global Scope` Eloquent, de `Policies`
et de contraintes d'unicité composites en base.

```
                         ┌──────────────────────────────┐
   Visiteur ────────────►│  nonalixia.com  (marketing)  │
                         └──────────────────────────────┘

                         ┌──────────────────────────────┐
   Client ──────────────►│  app.nonalixia.com           │  Vue 3 + Inertia
                         │  Dashboard + Messagerie      │  ← Reverb (WSS)
                         └──────────────────────────────┘

                         ┌──────────────────────────────┐
   Staff NONALIX ───────►│  admin.nonalixia.com         │  Vue 3 + Inertia
                         └──────────────────────────────┘

                         ┌──────────────────────────────┐
   Meta / Intégrateurs ─►│  api.nonalixia.com           │  REST + Webhooks
                         └──────────────────────────────┘
```

### 1.1 Choix structurant : un domaine, quatre « espaces »

Les quatre sous-domaines pointent vers **le même conteneur applicatif**. Le routage est
assuré par des groupes de routes contraints par domaine (`Route::domain(...)`), configurés
depuis `config/nonalix.php` afin que les environnements de dev (`*.nonalixia.test`) et de
production soient interchangeables sans toucher au code.

| Domaine | Fichier de routes | Middleware de base | Auth |
|---|---|---|---|
| `nonalixia.com` | `routes/marketing.php` | `web` | anonyme |
| `app.nonalixia.com` | `routes/app.php` | `web`, `auth`, `2fa`, `tenant` | session + 2FA |
| `admin.nonalixia.com` | `routes/admin.php` | `web`, `auth`, `2fa`, `super-admin` | session + 2FA |
| `api.nonalixia.com` | `routes/api.php`, `routes/webhooks.php` | `api`, `throttle`, `auth:sanctum` | Bearer (Sanctum) / signature Meta |

Les webhooks Meta sont volontairement **hors** de `auth:sanctum` : ils s'authentifient par
signature HMAC (voir §6.2).

---

## 2. Stack technique

| Couche | Technologie | Rôle |
|---|---|---|
| Framework | **Laravel 13** (PHP 8.3+) | socle applicatif |
| Base de données | **PostgreSQL 16 + pgvector** | données métier + embeddings |
| Cache / file d'attente | **Redis 7** | cache, sessions, queues, verrous, compteurs de quota |
| Workers | **Laravel Horizon** | supervision des queues, priorités, retries |
| Front | **Vue 3 + Inertia.js 2 + Tailwind CSS 4** | SPA sans API dédiée, SSR-ready |
| Temps réel | **Laravel Reverb** | WebSocket (nouveaux messages, statuts, présence) |
| Auth API | **Laravel Sanctum** | tokens personnels par tenant |
| Permissions | **spatie/laravel-permission** (mode `teams`) | rôles scoping par tenant |
| Messagerie | **Meta WhatsApp Cloud API v23.0** | envoi / réception |
| IA | OpenAI, Anthropic, Google Gemini | via une abstraction maison (§5) |
| Tests | **Pest 4** | unitaires, feature, architecture |
| Runtime | **Docker Compose + Nginx + PHP-FPM** | dev et production |

### 2.1 Pourquoi ces choix

- **Un seul PostgreSQL partagé** plutôt qu'une base par tenant : les volumes attendus (quelques
  centaines d'entreprises, quelques millions de messages) ne justifient pas la complexité
  opérationnelle d'une base par client, et pgvector impose un index HNSW par table qu'on ne
  veut pas dupliquer N fois. L'isolation est garantie par le code + les tests (§4.4).
- **Inertia plutôt qu'une SPA + API** : un seul modèle d'autorisation (les Policies Laravel),
  pas de duplication de la validation, pas de couche API à sécuriser pour l'interface interne.
  L'API publique (`api.nonalixia.com`) reste séparée et versionnée.
- **Reverb plutôt que Pusher** : self-hosted, pas de coût par message, et le trafic temps réel
  d'une messagerie est intense (typing, statuts de livraison, présence des agents).

---

## 3. Découpage applicatif

L'application suit une organisation par **domaine métier** plutôt que par type technique.

```
app/
├── Contracts/                  # Interfaces (contrats) — aucune implémentation
│   ├── AI/
│   │   ├── ChatProvider.php
│   │   ├── EmbeddingProvider.php
│   │   └── ProviderRegistry.php
│   ├── WhatsApp/
│   │   └── WhatsAppClient.php
│   └── Knowledge/
│       ├── DocumentExtractor.php
│       └── TextChunker.php
│
├── Data/                       # DTOs immuables (readonly classes)
│   ├── AI/  ChatRequest, ChatMessage, ChatResponse, ToolCall, TokenUsage, EmbeddingResult
│   ├── WhatsApp/  InboundMessage, OutboundMessage, DeliveryStatus, WebhookEnvelope
│   └── Knowledge/  Chunk, ExtractedDocument
│
├── Enums/                      # backed enums PHP 8.3
│   ├── ConversationStatus, MessageDirection, MessageStatus, MessageType,
│   ├── LeadStatus, DocumentStatus, TenantStatus, AiProvider, SenderType, OptInStatus
│
├── Models/                     # Eloquent — un modèle = une table
├── Policies/                   # une policy par modèle exposé
│
├── Services/                   # logique métier orchestrée, sans I/O HTTP direct
│   ├── AI/
│   │   ├── AiProviderManager.php          # résout le driver (openai|anthropic|gemini)
│   │   ├── Providers/{OpenAI,Anthropic,Gemini}ChatProvider.php
│   │   ├── Providers/{OpenAI,Gemini}EmbeddingProvider.php
│   │   ├── AgentRunner.php                # boucle agent : prompt → tools → réponse
│   │   ├── PromptBuilder.php              # assemble le system prompt du tenant
│   │   ├── ConversationMemory.php         # fenêtre glissante de N messages
│   │   └── Tools/{Handover,QualifyLead,ListServices,BusinessHours}Tool.php
│   ├── Knowledge/
│   │   ├── DocumentIngestionService.php
│   │   ├── Extractors/{Pdf,Docx,Txt,Url}Extractor.php
│   │   ├── RecursiveTextChunker.php
│   │   └── VectorSearchService.php        # recherche pgvector filtrée par tenant
│   ├── WhatsApp/
│   │   ├── CloudApiClient.php             # seul point de sortie HTTP vers Meta
│   │   ├── WebhookSignatureVerifier.php
│   │   ├── WebhookParser.php              # payload Meta → DTO
│   │   ├── MessageDispatcher.php
│   │   └── ConversationWindow.php         # règle des 24 h
│   ├── Billing/QuotaService.php           # compteurs Redis + réconciliation SQL
│   ├── Audit/AuditLogger.php
│   └── Tenancy/TenantContext.php          # tenant courant (request ou job)
│
├── Jobs/                       # tout I/O externe passe par un job
│   ├── WhatsApp/{ProcessWebhookEvent,SendWhatsAppMessage,SyncTemplates}Job.php
│   ├── AI/GenerateAgentReplyJob.php
│   └── Knowledge/{ExtractDocument,EmbedDocumentChunk}Job.php
│
├── Http/
│   ├── Middleware/{ResolveTenant,EnsureSuperAdmin,EnsureTwoFactor,EnforceTenantQuota}.php
│   ├── Controllers/{App,Admin,Api,Webhook}/...
│   ├── Requests/                # FormRequest = seule source de validation
│   └── Resources/               # sérialisation API
│
├── Events/ + Listeners/         # broadcast Reverb, effets de bord découplés
└── Support/                     # helpers transverses (Money, PhoneNumber, Redaction)
```

**Règle d'or** : un contrôleur ne contient jamais de logique métier. Il valide (FormRequest),
autorise (Policy), délègue (Service ou Job), répond (Resource ou Inertia).

---

## 4. Multi-tenant

### 4.1 Modèle retenu : *shared database, shared schema, discriminator column*

Chaque table métier porte `tenant_id` (UUID, FK `tenants.id`, `ON DELETE CASCADE`, indexée
en tête de chaque index composite).

### 4.2 Résolution du tenant

Trois chemins d'entrée, une seule source de vérité (`TenantContext`) :

| Contexte | Source du tenant |
|---|---|
| `app.nonalixia.com` | `auth()->user()->tenant_id` |
| `api.nonalixia.com` | le token Sanctum est rattaché à un `User`, donc à un tenant |
| Webhook Meta | le segment d'URL `/webhooks/whatsapp/{tenant:uuid}` + vérification que le `phone_number_id` du payload appartient bien à ce tenant |
| Job en queue | le `tenant_id` est **sérialisé explicitement** dans le job et réinjecté dans `TenantContext` au `handle()` |

Le middleware `ResolveTenant` positionne le contexte, vérifie que le tenant est `active`
(sinon 403 « compte suspendu »), et le nettoie en fin de requête.

### 4.3 Application automatique

Le trait `BelongsToTenant` :

1. enregistre un `TenantScope` global qui ajoute `where tenant_id = ?` à **toutes** les requêtes ;
2. remplit `tenant_id` automatiquement à la création (`creating` hook) ;
3. **lève une exception** si aucun tenant n'est en contexte et que le scope n'a pas été
   explicitement désactivé — le silence est le pire échec possible ici.

Le contournement (`Model::withoutTenantScope()`) est réservé aux commandes d'administration
et aux jobs de maintenance, et il est **tracé dans le journal d'audit**.

### 4.4 Garanties de non-fuite

- **Policies** : toute action passe par une Policy qui revérifie `$model->tenant_id === $user->tenant_id`.
  Ceinture *et* bretelles : le scope global peut être désactivé par erreur, pas les deux.
- **Contraintes SQL** : unicité toujours composite (`unique(tenant_id, wa_id)`, jamais `unique(wa_id)`).
- **Route model binding** : résolu à travers le scope global, donc un ID d'un autre tenant
  produit un 404, pas un 403 (pas de fuite d'information sur l'existence de la ressource).
- **Canaux de broadcast** : `tenant.{tenantId}.*`, autorisation vérifiant l'appartenance.
- **Tests d'isolation obligatoires** : chaque ressource exposée a un test
  `it('ne laisse pas un tenant voir les données d'un autre')`. Un test d'architecture Pest
  vérifie que tout modèle du dossier `Models/` (hors liste blanche centrale) utilise
  `BelongsToTenant`.

### 4.5 Utilisateurs NONALIX (super-admins)

`users.tenant_id` est **nullable**. Un utilisateur NONALIX a `tenant_id = null` et
`is_super_admin = true`. Il n'accède qu'à `admin.nonalixia.com`. Pour consulter les données
d'un client (support), il doit passer par une **impersonation explicite, limitée dans le temps
et systématiquement auditée** — jamais par une désactivation silencieuse du scope.

---

## 5. Couche IA — fournisseurs interchangeables

### 5.1 Contrats

```php
interface ChatProvider
{
    public function name(): AiProvider;
    public function chat(ChatRequest $request): ChatResponse;   // supporte le tool-calling
    public function supportsTools(): bool;
    public function estimateCost(TokenUsage $usage, string $model): int; // en micro-centimes
}

interface EmbeddingProvider
{
    public function name(): AiProvider;
    public function embed(array $texts): EmbeddingResult;  // batch
    public function dimensions(): int;
}
```

Aucun code métier ne connaît OpenAI, Anthropic ou Gemini. `AgentRunner` ne dépend que de
`ChatProvider`, résolu à l'exécution par `AiProviderManager` à partir de la configuration du
**tenant** (`agents.provider` + `agents.model`). Changer de fournisseur pour une entreprise =
changer une ligne en base, sans redéploiement.

### 5.2 Normalisation

Chaque provider traduit le format maison (`ChatRequest` / `ChatResponse`) vers son API et
retour. Les divergences absorbées par l'adaptateur :

- `system` séparé (Anthropic) vs message de rôle `system` (OpenAI) vs `systemInstruction` (Gemini) ;
- schémas de tools : `tools[].function` (OpenAI), `tools[].input_schema` (Anthropic),
  `functionDeclarations` (Gemini) ;
- comptage des tokens et nommage des champs d'usage ;
- codes d'erreur et politique de retry (429 / 5xx → backoff exponentiel avec jitter).

### 5.3 Dimensions d'embeddings

pgvector exige une dimension fixe par colonne. La plateforme **fige 1536 dimensions**
(`text-embedding-3-small` d'OpenAI, `gemini-embedding-001` tronqué via `outputDimensionality`).
La colonne stocke aussi `embedding_provider` et `embedding_model` : changer de modèle
d'embedding impose un **réindexage complet du tenant**, exécuté par un job dédié. Cette
contrainte est documentée et volontairement rigide — mélanger deux espaces vectoriels
produirait des résultats de recherche silencieusement faux.

### 5.4 Boucle de l'agent

```
Message entrant
  └─► GenerateAgentReplyJob (queue: ai)
        1. Verrou Redis  lock:conversation:{id}   (évite deux réponses concurrentes)
        2. Vérifie quota IA du tenant             (sinon → message de repli + incident)
        3. PromptBuilder : identité + persona + horaires + services/tarifs + FAQ + garde-fous
        4. ConversationMemory : N derniers messages (défaut 12, configurable par tenant)
        5. VectorSearchService : top-K chunks (défaut 5, seuil de similarité 0.75)
        6. ChatProvider->chat()
        7. Si tool_call → exécute l'outil → réinjecte → boucle (max 4 itérations)
        8. Persiste le message sortant + AiUsageLog (tokens, coût, latence)
        9. SendWhatsAppMessageJob (queue: whatsapp)
```

Outils exposés à l'agent en Phase 1 : `request_human_handover`, `qualify_lead`,
`list_services`, `get_business_hours`. Chaque outil est une classe implémentant `AgentTool`,
déclarée par tenant — un tenant ne peut pas invoquer un outil qu'il n'a pas activé.

### 5.5 Garde-fous

- Budget de tokens par requête plafonné (`max_tokens` + troncature de la mémoire).
- Le prompt système rappelle explicitement à l'agent de ne pas inventer de tarifs et de
  transférer à un humain en cas de doute.
- Détection de mots-clés de transfert (`handover_keywords`) avant même l'appel au LLM.
- Message de repli (`fallback_message`) si le fournisseur échoue après retries.

---

## 6. Intégration WhatsApp

### 6.1 Modèle de connexion (Phase 1 : manuel)

L'entreprise fournit depuis son dashboard :
`waba_id`, `phone_number_id`, `access_token` (System User token permanent) et `app_secret`.
Nonalix génère un `webhook_verify_token` unique et affiche l'URL de callback à coller dans
la console Meta :

```
https://api.nonalixia.com/webhooks/whatsapp/{tenant_uuid}
```

Un bouton « Tester la connexion » appelle l'API Meta pour valider le token et récupérer le
nom vérifié et la note de qualité. L'Embedded Signup (onboarding automatisé) est
explicitement **hors périmètre du MVP** (Phase 4).

### 6.2 Réception — chemin critique

```
POST /webhooks/whatsapp/{tenant}
  1. Lit le corps BRUT (avant tout parsing JSON)
  2. Vérifie X-Hub-Signature-256 = HMAC-SHA256(raw_body, app_secret) en hash_equals()
     → signature invalide : 401, incident enregistré, aucune donnée persistée
  3. Enregistre un WebhookEvent (payload complet, hash d'idempotence)
  4. Répond 200 en < 200 ms   ← Meta réessaie et dégrade la qualité au-delà
  5. Dispatch ProcessWebhookEventJob (queue: webhooks)
```

**Idempotence** : `webhook_events.idempotency_key` est un index unique
(`sha256(tenant_id + wamid + type + status)`). Meta rejoue les webhooks ; une clé déjà connue
est acquittée par 200 sans retraitement. Les jobs sont conçus pour être rejouables sans effet
de bord (`updateOrCreate` sur `wamid`, transitions de statut monotones : on n'écrase jamais
`read` par `delivered` arrivé en retard).

`GET /webhooks/whatsapp/{tenant}` gère le handshake de vérification Meta
(`hub.mode`, `hub.verify_token`, `hub.challenge`).

### 6.3 Émission

Tout envoi passe par `SendWhatsAppMessageJob` (jamais d'appel HTTP synchrone dans une requête) :

- vérifie la **fenêtre de service de 24 h** : hors fenêtre, seul un *template* approuvé
  est autorisé, sinon le message est rejeté avec une erreur explicite ;
- vérifie le consentement (`contacts.opt_in_status`) et le quota du tenant ;
- déduplique via une clé Redis pour éviter le double envoi lors d'un retry ;
- persiste le `wamid` retourné, puis les statuts (`sent` → `delivered` → `read`) arrivent par webhook ;
- `failed` → l'erreur Meta est stockée telle quelle et affichée à l'opérateur.

Limites de débit Meta : throttling par `phone_number_id` via un seau Redis, avec backoff
exponentiel sur les 429/613.

---

## 7. Traitement asynchrone

| Queue | Priorité | Contenu | Timeout | Tentatives |
|---|---|---|---|---|
| `webhooks` | haute | parsing des événements Meta | 30 s | 5 |
| `whatsapp` | haute | envois sortants | 60 s | 5 |
| `ai` | normale | génération des réponses | 120 s | 3 |
| `knowledge` | basse | extraction + embeddings | 600 s | 3 |
| `default` | basse | audit, notifications, statistiques | 60 s | 3 |

Horizon supervise l'ensemble, avec `balance: auto`, des seuils d'alerte sur les temps
d'attente et `Horizon::routeSlackNotificationsTo` pour les incidents. Les jobs échoués
alimentent la table `incidents` visible côté admin NONALIX.

---

## 8. Temps réel (Reverb)

| Canal | Type | Événements |
|---|---|---|
| `tenant.{tenantId}.conversations` | privé | `ConversationUpdated`, `ConversationAssigned` |
| `tenant.{tenantId}.conversation.{id}` | privé | `MessageCreated`, `MessageStatusUpdated`, `AgentTyping` |
| `tenant.{tenantId}.presence` | présence | opérateurs en ligne |

L'autorisation des canaux (`routes/channels.php`) vérifie `tenantId === $user->tenant_id`
**et** la permission `conversations.view`. Un identifiant de tenant dans un nom de canal
n'est jamais une preuve d'appartenance.

---

## 9. Sécurité

| Risque | Mesure |
|---|---|
| Fuite inter-tenant | scope global + policies + unicité composite + tests dédiés (§4.4) |
| Secrets en base | `access_token`, `app_secret`, `verify_token`, clés IA : cast `encrypted` (AES-256-GCM via `APP_KEY`) |
| Secrets dans le code | interdits ; tout par variables d'environnement ; `.env.example` sans valeur réelle |
| Webhooks falsifiés | HMAC-SHA256 en `hash_equals`, corps brut, rejet en 401 |
| Rejeu / doublons | clé d'idempotence unique + verrous Redis + jobs rejouables |
| Vol de session | 2FA TOTP obligatoire pour les rôles à privilèges, cookies `secure`+`httpOnly`+`SameSite=Lax` |
| Escalade de privilèges | `spatie/laravel-permission` en mode `teams`, permissions vérifiées côté serveur uniquement |
| Abus / DoS | rate limiting par tenant et par IP, quotas applicatifs, throttling des envois |
| Perte de données | dumps PostgreSQL chiffrés quotidiens (rétention 30 j) + WAL, restauration testée |
| Traçabilité | `audit_logs` immuable (insert-only) sur toute action sensible |
| Données personnelles | rétention configurable, purge des médias, export et suppression sur demande (RGPD) |

**Rate limiters** (`AppServiceProvider`) : `api` 120 req/min/token, `webhooks` 600 req/min/tenant,
`login` 5 essais/min/IP+email, `ai` plafonné par le quota du plan.

---

## 10. Quotas et coûts

Compteurs incrémentés dans Redis en temps réel (clé `quota:{tenant}:{metric}:{période}`) puis
réconciliés en base par une commande planifiée. Métriques suivies en Phase 1 :

- `messages_sent`, `messages_received`
- `ai_requests`, `ai_input_tokens`, `ai_output_tokens`, `ai_cost_micros`
- `documents_stored`, `storage_bytes`, `active_users`

Dépassement → comportement défini par le plan : `block` (refus + notification) ou `soft`
(autorisé, facturé en dépassement, alerte admin). Le MVP implémente `block` par défaut.

---

## 11. Observabilité

- **Logs** structurés JSON, canal `stack` (stdout + fichier), avec `tenant_id`, `request_id`,
  `user_id` injectés par un middleware. Les tokens et numéros de téléphone sont **rédigés**
  (`Support/Redaction`).
- **Horizon** : métriques de queue, jobs échoués.
- **Pulse** : santé applicative (Phase 2).
- **Table `incidents`** : signature invalide, échec fournisseur IA, quota dépassé, erreur Meta —
  consultable et résolvable depuis `admin.nonalixia.com`.
- **Health check** : `GET /up` (Laravel) + `GET /health` vérifiant PostgreSQL, Redis et pgvector.

---

## 12. Déploiement

Voir `DEPLOYMENT.md`. En résumé :

```
Internet ──► Nginx (TLS, Let's Encrypt)
               ├─► php-fpm (app)          × N
               ├─► reverb  (WebSocket)    × 1
               └─► /            statique  (public/build)

Conteneurs annexes : postgres(pgvector), redis, horizon (worker), scheduler
```

Les images de production sont construites en multi-stage (composer + node → runtime PHP-FPM
Alpine, sans outillage de build). Le déploiement est sans interruption : `migrate --force`,
`config:cache`, `route:cache`, `view:cache`, `horizon:terminate` (les workers redémarrent
avec le nouveau code après avoir fini leur job courant).

---

## 13. Hors périmètre (explicitement)

Application mobile, omnicanal (Instagram / Messenger / SMS), facturation automatique
(Stripe / paiement), Embedded Signup Meta, marketplace de templates, IA vocale.
Ces sujets sont planifiés dans `ROADMAP.md` mais **aucun code ne doit les anticiper** au-delà
des points d'extension déjà présents (interface `ChatProvider`, table `subscriptions`,
enum `channel` sur les conversations).
