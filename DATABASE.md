# Nonalix IA — Schéma de base de données

PostgreSQL 16 + extensions `pgcrypto` (UUID) et `vector` (pgvector ≥ 0.7).

Conventions appliquées à l'ensemble du schéma :

- **Clés primaires UUID v7** (`uuid` PostgreSQL) — non devinables, générables côté client,
  ordonnées dans le temps (donc sans fragmentation d'index, contrairement à l'UUID v4).
- **`tenant_id` en première position de tout index composite** : c'est le prédicat le plus
  sélectif et il est présent dans 100 % des requêtes métier.
- **Unicité toujours composite avec `tenant_id`** — jamais d'unicité globale sur une donnée
  métier, sous peine de collision entre deux clients (deux entreprises peuvent parfaitement
  avoir le même contact).
- **`ON DELETE CASCADE`** depuis `tenants` : supprimer un tenant purge l'intégralité de ses
  données, sans orphelins.
- **Soft delete** (`deleted_at`) uniquement là où la restauration a un sens métier
  (tenants, users, documents). Les messages et événements ne sont jamais soft-deleted.
- **Horodatage `timestamptz`** systématique, stockage en UTC, conversion à l'affichage selon
  `business_profiles.timezone`.
- **JSONB** pour les payloads externes et les structures ouvertes ; jamais pour une donnée
  qu'on doit filtrer ou agréger fréquemment.

Légende : 🔒 colonne chiffrée applicativement (cast `encrypted`) · ⚡ indexée · 🔑 unique

---

## 1. Socle plateforme

### `tenants`
L'entreprise cliente. Racine de toute l'isolation.

| Colonne | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `name` | varchar(160) | raison sociale |
| `slug` | varchar(80) | 🔑 global (sous-domaine futur, identifiant lisible) |
| `status` | varchar(20) | ⚡ `trial` \| `active` \| `past_due` \| `suspended` \| `closed` |
| `plan_id` | uuid FK plans | `ON DELETE RESTRICT` |
| `trial_ends_at` | timestamptz | |
| `suspended_at` / `suspension_reason` | timestamptz / text | suspension par NONALIX |
| `settings` | jsonb | préférences non structurantes (locale, format de date…) |
| `created_at` / `updated_at` / `deleted_at` | timestamptz | |

### `plans`
Catalogue commercial. Table centrale (pas de `tenant_id`).

| Colonne | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `name` / `slug` | varchar | 🔑 slug |
| `price_cents` | integer | 0 pour le plan d'essai |
| `currency` | char(3) | ISO 4217, défaut `EUR` |
| `interval` | varchar(10) | `month` \| `year` |
| `quotas` | jsonb | `{messages_sent: 5000, ai_requests: 3000, documents: 50, users: 5, storage_mb: 500}` |
| `features` | jsonb | drapeaux (`rag`, `api_access`, `templates`…) |
| `overage_policy` | varchar(10) | `block` \| `soft` |
| `is_active` / `position` | boolean / smallint | |

### `subscriptions`
Rattachement tenant ↔ plan dans le temps. **Aucune intégration de paiement en Phase 1** :
la table existe pour porter l'état, la facturation automatique viendra en Phase 5.

`id`, `tenant_id`⚡, `plan_id`, `status` (`trialing|active|past_due|canceled|expired`),
`starts_at`, `ends_at`, `canceled_at`, `external_reference` (futur ID Stripe), `meta` jsonb.

### `users`

| Colonne | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid **nullable** ⚡ | `null` ⇒ staff NONALIX |
| `name`, `email` | varchar | 🔑 `(email)` global — une adresse = un compte |
| `email_verified_at` | timestamptz | |
| `password` | varchar | bcrypt (coût 12) |
| `two_factor_secret` | text 🔒 | TOTP |
| `two_factor_recovery_codes` | text 🔒 | |
| `two_factor_confirmed_at` | timestamptz | 2FA active seulement si non nul |
| `is_super_admin` | boolean | défaut `false` |
| `status` | varchar(20) | `active` \| `invited` \| `disabled` |
| `locale`, `last_login_at`, `last_login_ip` | | |
| `remember_token`, timestamps, `deleted_at` | | |

> Contrainte applicative (testée) : `is_super_admin = true` ⟹ `tenant_id IS NULL`.

### Permissions — `spatie/laravel-permission` en mode `teams`
Tables `roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
`role_has_permissions`, avec `team_id = tenant_id`. Les rôles applicatifs de Phase 1 :

| Rôle | Portée | Capacités |
|---|---|---|
| `super-admin` | NONALIX | tout, sur `admin.nonalixia.com` |
| `owner` | tenant | tout sur son tenant, y compris facturation et suppression |
| `admin` | tenant | configuration, agent IA, utilisateurs, base de connaissances |
| `agent` | tenant | messagerie, prospects, notes — pas la configuration |
| `viewer` | tenant | lecture seule (statistiques, conversations) |

### `audit_logs`
Insert-only. Aucune route de mise à jour ni de suppression n'existe.

`id`, `tenant_id`⚡ nullable, `user_id` nullable, `action` (`whatsapp_account.updated`),
`auditable_type`/`auditable_id`⚡, `changes` jsonb (avant/après, **secrets rédigés**),
`ip_address` inet, `user_agent`, `context` jsonb, `created_at`⚡.

### `incidents`
`id`, `tenant_id` nullable⚡, `level` (`info|warning|error|critical`), `source`
(`whatsapp|ai|quota|webhook|system`), `code`, `title`, `context` jsonb,
`occurrences` integer, `first_seen_at`, `last_seen_at`, `resolved_at`, `resolved_by`.
Les incidents identiques sont **agrégés** par `(tenant_id, source, code)` plutôt que dupliqués.

### `usage_counters`
Réconciliation des compteurs Redis. `id`, `tenant_id`⚡, `metric`, `period` (`2026-07`),
`value` bigint, `recorded_at`. 🔑 `(tenant_id, metric, period)`.

### `ai_usage_logs`
Détail par appel, pour la facturation à l'usage et le débogage.
`id`, `tenant_id`⚡, `conversation_id` nullable, `agent_id`, `provider`, `model`,
`operation` (`chat|embedding`), `input_tokens`, `output_tokens`, `cost_micros` bigint,
`latency_ms`, `status`, `error_code`, `created_at`⚡.

> `cost_micros` : coût en **micro-centimes d'euro** (entier). Aucun flottant pour de l'argent.

---

## 2. Configuration de l'entreprise

### `business_profiles` (1–1 avec le tenant)
`id`, `tenant_id` 🔑, `legal_name`, `description` text, `industry`, `website`,
`email`, `phone`, `address_line1/2`, `postal_code`, `city`, `country` char(2),
`timezone` (défaut `Europe/Paris`), `currency` char(3), `languages` jsonb.

### `business_hours`
`id`, `tenant_id`⚡, `day_of_week` smallint (0 = dimanche … 6), `opens_at` time,
`closes_at` time, `is_closed` boolean. Plusieurs lignes par jour possibles (coupure midi).

### `services`
Le catalogue lu par l'agent IA (« services et tarifs »).
`id`, `tenant_id`⚡, `name`, `description` text, `price_cents` integer nullable,
`price_type` (`fixed|from|hourly|quote`), `currency`, `duration_minutes`,
`category`, `is_active`⚡, `position`.

### `faqs`
`id`, `tenant_id`⚡, `question`, `answer` text, `category`, `position`, `is_active`⚡.

### `agents`
Configuration de l'agent IA. Un agent actif par tenant en Phase 1 (la table en supporte
plusieurs pour la suite).

| Colonne | Type | Notes |
|---|---|---|
| `id`, `tenant_id`⚡ | | |
| `name` | varchar | |
| `provider` | varchar(20) | `openai` \| `anthropic` \| `gemini` |
| `model` | varchar(80) | ex. `claude-sonnet-5` |
| `temperature` | numeric(3,2) | défaut 0.30 |
| `max_tokens` | integer | défaut 1024 |
| `system_prompt` | text | instructions métier saisies par le client |
| `persona`, `tone`, `language` | varchar | |
| `greeting_message`, `fallback_message` | text | |
| `memory_window` | smallint | défaut 12 messages |
| `rag_enabled` | boolean | défaut `true` |
| `rag_top_k` | smallint | défaut 5 |
| `rag_min_score` | numeric(4,3) | défaut 0.750 |
| `handover_keywords` | jsonb | `["humain","conseiller","agent"]` |
| `enabled_tools` | jsonb | liste blanche d'outils |
| `active_hours_only` | boolean | ne répondre que pendant les horaires |
| `is_active` | boolean | |

---

## 3. WhatsApp

### `whatsapp_accounts`

| Colonne | Type | Notes |
|---|---|---|
| `id`, `tenant_id`⚡ | | |
| `waba_id` | varchar(40) | WhatsApp Business Account ID |
| `phone_number_id` | varchar(40) | 🔑 **global** — un numéro Meta ne peut appartenir qu'à un tenant |
| `display_phone_number` | varchar(32) | E.164 |
| `verified_name` | varchar(160) | renvoyé par Meta |
| `access_token` | text 🔒 | System User token |
| `app_secret` | text 🔒 | vérification des webhooks |
| `webhook_verify_token` | text 🔒 | généré par Nonalix |
| `business_id`, `quality_rating`, `messaging_limit` | varchar | |
| `status` | varchar(20) | `pending` \| `connected` \| `error` \| `disconnected` |
| `connected_at`, `last_verified_at`, `last_error` | | |

> `phone_number_id` unique **globalement** (et non par tenant) : c'est la seule exception à la
> règle §conventions, et elle est délibérée — elle empêche deux clients de revendiquer le même
> numéro et garantit qu'un webhook se résout vers un tenant et un seul.

### `message_templates`
`id`, `tenant_id`⚡, `whatsapp_account_id`, `meta_template_id`, `name`, `language`,
`category` (`marketing|utility|authentication`), `status`
(`pending|approved|rejected|paused|disabled`), `components` jsonb, `rejected_reason`,
`synced_at`. 🔑 `(tenant_id, name, language)`.

### `webhook_events`
Journal brut + garantie d'idempotence.

`id`, `tenant_id` nullable⚡, `provider` (défaut `whatsapp`), `event_type`,
`idempotency_key` varchar(64) 🔑 **global**, `signature_valid` boolean,
`payload` jsonb, `status` (`received|processed|failed|ignored`)⚡, `attempts`,
`error` text, `received_at`, `processed_at`.

Purge automatique des événements `processed` de plus de 30 jours (commande planifiée).

### `contacts`
`id`, `tenant_id`⚡, `wa_id` varchar(32) (numéro E.164 sans `+`), `phone_number`,
`name`, `profile_name` (nom WhatsApp public), `email`, `locale`,
`opt_in_status` (`unknown|opted_in|opted_out`)⚡, `opt_in_at`, `opt_out_at`, `opt_in_source`,
`attributes` jsonb, `last_message_at`, `blocked_at`, timestamps.
🔑 `(tenant_id, wa_id)`.

### `consent_logs`
Preuve de consentement, insert-only : `id`, `tenant_id`, `contact_id`⚡,
`action` (`opt_in|opt_out`), `channel`, `source` (`keyword|dashboard|import|api`),
`raw_message`, `ip_address`, `created_at`.

### `conversations`

| Colonne | Type | Notes |
|---|---|---|
| `id`, `tenant_id`⚡ | | |
| `contact_id` | uuid FK | |
| `whatsapp_account_id` | uuid FK | |
| `channel` | varchar(20) | défaut `whatsapp` — point d'extension omnicanal |
| `status` | varchar(20) | ⚡ `open` \| `pending` \| `snoozed` \| `closed` |
| `ai_enabled` | boolean | l'opérateur peut couper l'IA sur une conversation |
| `assigned_user_id` | uuid nullable⚡ | attribution à un opérateur |
| `handover_at`, `handover_reason` | | reprise humaine |
| `last_message_at` | timestamptz ⚡ | tri de la boîte de réception |
| `last_inbound_at` | timestamptz | base du calcul de la fenêtre 24 h |
| `window_expires_at` | timestamptz | `last_inbound_at + 24h`, dénormalisé pour filtrer vite |
| `unread_count` | integer | |
| `closed_at`, `closed_by` | | |

⚡ Index composite `(tenant_id, status, last_message_at desc)` — requête principale de la boîte
de réception. 🔑 `(tenant_id, contact_id, channel)` tant que la conversation est ouverte
(index unique partiel `WHERE closed_at IS NULL`) : une seule conversation active par contact.

### `messages`

| Colonne | Type | Notes |
|---|---|---|
| `id`, `tenant_id`⚡, `conversation_id`⚡ | | |
| `wamid` | varchar(128) | 🔑 `(tenant_id, wamid)` — clé d'idempotence Meta |
| `direction` | varchar(3) | `in` \| `out` |
| `sender_type` | varchar(10) | `contact` \| `ai` \| `agent` \| `system` |
| `sender_user_id` | uuid nullable | renseigné si `sender_type = agent` |
| `type` | varchar(20) | `text`, `image`, `document`, `audio`, `video`, `sticker`, `location`, `contacts`, `template`, `interactive`, `reaction`, `unsupported` |
| `body` | text | contenu textuel ou légende |
| `media` | jsonb | `{id, mime_type, sha256, filename, storage_path}` |
| `template_id` | uuid nullable | si envoi par template |
| `context_wamid` | varchar(128) | message cité / réponse |
| `status` | varchar(12) | ⚡ `queued`\|`sent`\|`delivered`\|`read`\|`failed` |
| `error` | jsonb | code et message d'erreur Meta bruts |
| `sent_at`, `delivered_at`, `read_at`, `failed_at` | timestamptz | |
| `ai_meta` | jsonb | provider, modèle, tokens, chunks RAG utilisés, outils appelés |
| `created_at`⚡ | | |

Index `(tenant_id, conversation_id, created_at desc)` pour le fil de discussion.
Les transitions de statut sont **monotones** : un webhook `delivered` arrivant après `read`
est ignoré (protection contre le désordre de livraison des webhooks).

### `conversation_notes`
Notes internes, invisibles du contact.
`id`, `tenant_id`, `conversation_id`⚡, `user_id`, `body` text, timestamps.

---

## 4. Base de connaissances (RAG)

### `documents`
`id`, `tenant_id`⚡, `title`, `source_type` (`pdf|docx|txt|url|manual`), `source_url`,
`storage_path`, `mime_type`, `size_bytes`, `checksum` sha256,
`status` (`pending|extracting|chunking|embedding|ready|failed`)⚡, `error`,
`chunks_count`, `tokens_count`, `embedding_provider`, `embedding_model`,
`processed_at`, `uploaded_by`, timestamps, `deleted_at`.
🔑 `(tenant_id, checksum)` — un même fichier n'est pas indexé deux fois.

### `document_chunks`

| Colonne | Type | Notes |
|---|---|---|
| `id`, `tenant_id`⚡ | | |
| `document_id` | uuid FK cascade⚡ | |
| `position` | integer | ordre dans le document |
| `content` | text | le texte du fragment |
| `tokens` | integer | |
| `metadata` | jsonb | page, titre de section, URL d'origine |
| `embedding` | **vector(1536)** | pgvector |
| `created_at` | | |

**Index vectoriel** :

```sql
CREATE INDEX document_chunks_embedding_idx
    ON document_chunks USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);

CREATE INDEX document_chunks_tenant_idx ON document_chunks (tenant_id);
```

La recherche filtre **toujours** par `tenant_id` avant le calcul de distance :

```sql
SELECT id, document_id, content, metadata,
       1 - (embedding <=> :query) AS score
FROM document_chunks
WHERE tenant_id = :tenant
  AND 1 - (embedding <=> :query) >= :min_score
ORDER BY embedding <=> :query
LIMIT :k;
```

> Note d'exploitation : HNSW combiné à un filtre `WHERE` peut retourner moins de `k` résultats
> si le filtre est très sélectif. `SET hnsw.ef_search = 100` est appliqué par la session de
> recherche pour compenser. Au-delà de ~500 000 chunks, on partitionnera la table par `tenant_id`.

---

## 5. Prospects

### `leads`
`id`, `tenant_id`⚡, `contact_id`⚡, `conversation_id` nullable,
`status` (`new|contacted|qualified|unqualified|won|lost`)⚡, `score` smallint (0–100),
`qualification` jsonb (réponses collectées par l'agent : besoin, budget, échéance…),
`intent`, `source` (`whatsapp_ai|manual|api`), `assigned_user_id`,
`qualified_at`, `qualified_by` (`ai|user`), `lost_reason`, `next_action_at`, timestamps.
🔑 `(tenant_id, contact_id)` sur les leads ouverts (index partiel).

---

## 6. Diagramme relationnel (Phase 1)

```
plans ──< subscriptions >── tenants ──< users
                              │
                              ├──< business_profiles (1-1)
                              ├──< business_hours
                              ├──< services
                              ├──< faqs
                              ├──< agents
                              │
                              ├──< whatsapp_accounts ──< message_templates
                              ├──< webhook_events
                              │
                              ├──< contacts ──< consent_logs
                              │        │
                              │        └──< conversations ──< messages
                              │                   │              └── (template_id)
                              │                   └──< conversation_notes
                              │
                              ├──< leads ──(contact_id, conversation_id)
                              │
                              ├──< documents ──< document_chunks [vector(1536)]
                              │
                              ├──< audit_logs
                              ├──< incidents
                              ├──< usage_counters
                              └──< ai_usage_logs
```

---

## 7. Ordre des migrations

L'ordre est contraint par les clés étrangères :

```
0001  extensions          (pgcrypto, vector)
0002  plans
0003  tenants             → plans
0004  users               → tenants
0005  permission tables   (spatie, teams)
0006  subscriptions       → tenants, plans
0007  business_profiles / business_hours / services / faqs
0008  agents
0009  whatsapp_accounts
0010  message_templates   → whatsapp_accounts
0011  webhook_events
0012  contacts / consent_logs
0013  conversations       → contacts, whatsapp_accounts, users
0014  messages            → conversations, message_templates
0015  conversation_notes
0016  documents
0017  document_chunks     → documents   (+ index HNSW)
0018  leads
0019  audit_logs / incidents / usage_counters / ai_usage_logs
0020  jobs, failed_jobs, cache, sessions (tables Laravel)
```

---

## 8. Stratégie de rétention

| Donnée | Rétention par défaut | Mécanisme |
|---|---|---|
| `webhook_events` (processed) | 30 jours | commande planifiée quotidienne |
| Médias WhatsApp | 90 jours | purge du stockage + `media.storage_path` vidé |
| `messages` | configurable par tenant (défaut : illimité) | job de purge par tenant |
| `ai_usage_logs` | 13 mois | agrégation mensuelle puis purge du détail |
| `audit_logs` | 24 mois | archivage froid puis purge |
| Tenant supprimé | purge complète à J+30 | `CASCADE` + suppression du stockage |
