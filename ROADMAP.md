# Nonalix IA — Feuille de route

Découpage en phases livrables. **Une phase ne démarre que lorsque la précédente est testée
et déployée.** Les phases 5 et suivantes sont hors périmètre du MVP et ne doivent influencer
le code d'aujourd'hui que par les points d'extension déjà en place.

Estimations en jours-homme pour **un développeur senior** à temps plein.

---

## Phase 0 — Fondations *(≈ 3 j)* ✅ en cours

Objectif : un `docker compose up` qui donne une application Laravel fonctionnelle, testée,
avec PostgreSQL + pgvector, Redis, Horizon et Reverb.

- [x] `ARCHITECTURE.md`, `DATABASE.md`, `ROADMAP.md`
- [x] Docker : `Dockerfile` multi-stage, `docker-compose.yml`, Nginx, entrypoint
- [x] Squelette Laravel 13, `composer.json`, `.env.example`
- [x] Routage multi-domaines (`marketing`, `app`, `admin`, `api`, `webhooks`)
- [x] Configuration `config/nonalix.php`, `config/ai.php`, `config/whatsapp.php`
- [x] Pest configuré, CI-ready

**Critère de sortie** : `docker compose up` + `php artisan migrate` + `php artisan test` au vert.

---

## Phase 1 — MVP *(≈ 25 j)* ← périmètre courant

### 1.1 Multi-tenant et sécurité *(4 j)*
- Migrations `tenants`, `plans`, `subscriptions`, `users`, permissions Spatie (mode `teams`)
- Trait `BelongsToTenant`, `TenantScope`, `TenantContext`, middleware `ResolveTenant`
- Policies pour toutes les ressources exposées
- Authentification + **2FA TOTP** + codes de récupération
- Rate limiters, `EnsureSuperAdmin`, `EnsureTwoFactor`
- `audit_logs` + `AuditLogger` + observers
- **Tests d'isolation inter-tenant sur chaque ressource** (bloquant)

### 1.2 Configuration de l'entreprise *(3 j)*
- `business_profiles`, `business_hours`, `services`, `faqs`, `agents`
- CRUD Inertia + FormRequests + Policies
- Écran de configuration de l'agent IA (prompt, ton, modèle, fournisseur)

### 1.3 Connexion WhatsApp *(4 j)*
- `whatsapp_accounts` avec secrets chiffrés
- Écran de connexion manuelle + « Tester la connexion » (appel Meta réel)
- `GET`/`POST /webhooks/whatsapp/{tenant}` : handshake + vérification HMAC-SHA256
- `webhook_events` + idempotence + `ProcessWebhookEventJob`
- `CloudApiClient` : envoi texte, template, média ; gestion des erreurs Meta
- `SendWhatsAppMessageJob` + fenêtre de 24 h + statuts de livraison

### 1.4 Agent IA *(5 j)*
- Contrats `ChatProvider` / `EmbeddingProvider`, DTOs, `AiProviderManager`
- Implémentations OpenAI, Anthropic, Gemini (chat + tool-calling)
- `PromptBuilder`, `ConversationMemory`, `AgentRunner`
- Outils : `request_human_handover`, `qualify_lead`, `list_services`, `get_business_hours`
- `GenerateAgentReplyJob` + verrou par conversation + repli en cas d'échec
- `ai_usage_logs` : tokens, coût, latence

### 1.5 RAG *(4 j)*
- `documents`, `document_chunks` + index HNSW
- Import PDF / DOCX / TXT / URL → `DocumentIngestionService`
- Chunking récursif avec chevauchement, embeddings par lots
- `VectorSearchService` filtré par tenant, seuil de similarité
- Écran base de connaissances : upload, statut de traitement, suppression, réindexage

### 1.6 Messagerie opérateur *(4 j)* — fait
- [x] Boîte de réception temps réel (Reverb) : liste, fil, filtres, recherche
- [x] Attribution, notes internes, reprise humaine, activation/désactivation de l'IA
- [x] Statuts de prospects, fiche contact
- [ ] Présence des opérateurs (canal déclaré, interface reportée en Phase 2)

### 1.7 Administration NONALIX *(3 j)* — fait
- [x] Gestion des entreprises, utilisateurs, plans, quotas
- [x] Consommation IA / WhatsApp par tenant
- [x] Incidents, journal d'audit, suspension et réactivation
- [x] Impersonation tracée et limitée dans le temps

### 1.8 Quotas, statistiques et API *(3 j)*
- `QuotaService` (Redis + réconciliation), middleware `EnforceTenantQuota`
- Tableau de bord : messages, conversations, taux de reprise humaine, prospects, coût IA
- API publique v1 : contacts, conversations, messages, envoi — Sanctum + OpenAPI
- Documentation d'API générée

### 1.9 Consolidation *(2 j)*
- Consentement et désabonnement (mots-clés `STOP` / `START`)
- Sauvegardes chiffrées + procédure de restauration testée
- `DEPLOYMENT.md`, health checks, tableau de bord Horizon sécurisé
- Couverture de tests ≥ 80 % sur les services critiques

**Critères de sortie du MVP**
1. Une entreprise se connecte, branche son numéro, configure son agent et reçoit une réponse
   IA correcte sur un vrai WhatsApp, en moins de 15 minutes de configuration.
2. Aucun test d'isolation inter-tenant ne peut être mis en échec.
3. Un rejeu de webhook ne crée jamais de doublon.
4. La suite Pest passe intégralement.

---

## Phase 2 — Fiabilité et exploitation *(≈ 10 j)*

- Laravel Pulse, métriques applicatives, alertes
- Réponses aux médias entrants (transcription audio, lecture d'images)
- Réactions, messages interactifs (boutons, listes)
- Gestion des templates : création et soumission à Meta depuis le dashboard
- Campagnes sortantes simples (segments + template + limitation de débit)
- Export CSV des prospects et conversations
- Recherche plein texte dans les conversations
- File d'attente et distribution automatique aux opérateurs

## Phase 3 — Intelligence *(≈ 12 j)*

- Résumés automatiques de conversation
- Analyse de sentiment et détection d'intention
- Suggestions de réponse à l'opérateur (copilote)
- A/B testing de prompts, versionnage des prompts
- Évaluation qualité de l'agent (jeu de tests par tenant, scoring)
- RAG avancé : reranking, recherche hybride (BM25 + vecteurs), citations des sources

## Phase 4 — Croissance *(≈ 12 j)*

- **Embedded Signup Meta** (onboarding WhatsApp en un clic)
- Webhooks sortants pour les clients
- Intégrations : HubSpot, Pipedrive, Google Sheets, Zapier/Make
- Multi-numéros par tenant
- Marque blanche (domaine personnalisé, logo, couleurs)
- Rôles et permissions personnalisables

## Phase 5 — Monétisation *(≈ 10 j)*

- Facturation automatique (Stripe : abonnements, dépassements, factures)
- Portail de paiement, relances, changement de plan en libre-service
- Essai gratuit avec conversion, codes promotionnels
- Tableau de bord de revenus côté NONALIX

## Phase 6 — Expansion *(≈ 20 j)*

- Omnicanal : Instagram DM, Messenger, SMS, e-mail
- Application mobile (React Native ou Flutter) pour les opérateurs
- IA vocale (appels entrants)
- Marketplace de modèles d'agents par secteur

---

## Dette technique acceptée en Phase 1

Décisions assumées, à revisiter explicitement :

| Décision | Raison | Échéance de revue |
|---|---|---|
| Connexion WhatsApp manuelle (pas d'Embedded Signup) | dépendance à la validation Meta Tech Provider | Phase 4 |
| Un seul agent actif par tenant | simplifie l'UX et le routage | Phase 3 |
| Pas de partitionnement de `document_chunks` | inutile sous ~500 k chunks | quand le seuil est atteint |
| Dimension d'embedding figée à 1536 | mélanger des espaces vectoriels casse la recherche | Phase 3 (multi-espaces) |
| Statistiques calculées à la volée | volumes faibles au démarrage | quand la latence dépasse 1 s |
| Pas de paiement en ligne | validation commerciale avant automatisation | Phase 5 |

---

## Risques identifiés

| Risque | Impact | Mitigation |
|---|---|---|
| Refus ou délai de validation Meta | bloque la mise en production | démarrer la vérification Business dès la Phase 1 ; le mode manuel n'exige pas le statut Tech Provider |
| Dégradation de la qualité du numéro (Meta) | limitation d'envoi | respect strict de la fenêtre 24 h, opt-out honoré, surveillance de `quality_rating` |
| Coût IA non maîtrisé | marge négative | quotas durs, `cost_micros` par appel, alertes de seuil, modèles économiques par défaut |
| Panne d'un fournisseur IA | agents muets | interface `ChatProvider` + repli configurable sur un second fournisseur |
| Fuite inter-tenant | fin de la crédibilité commerciale | scope + policy + tests bloquants ; audit de sécurité avant lancement |
| Hallucination sur des tarifs | litige client | tarifs injectés depuis `services`, prompt de garde, transfert humain au moindre doute |
