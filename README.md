# Nonalix IA

Plateforme SaaS multi-tenant permettant à plusieurs entreprises de connecter
leur propre numéro WhatsApp Business et de déployer un agent IA personnalisé.

**Statut : Phase 1 (MVP) — back-end et interface complets, tests non encore exécutés.**

---

## Documentation

| Document | Contenu |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Découpage applicatif, multi-tenant, couche IA, WhatsApp, sécurité |
| [DATABASE.md](DATABASE.md) | Schéma complet, index, pgvector, rétention |
| [ROADMAP.md](ROADMAP.md) | Phases, critères de sortie, dette assumée, risques |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Installation locale, connexion WhatsApp, production, sauvegardes |

## Démarrage

```bash
cp .env.example .env
make install
```

Puis ajouter au fichier hosts :

```
127.0.0.1  nonalixia.test app.nonalixia.test admin.nonalixia.test api.nonalixia.test
```

Compte de démonstration : `demo@nonalixia.test` / `password` sur
http://app.nonalixia.test

```bash
make help    # liste des commandes
make test    # suite Pest
make health  # PostgreSQL + pgvector + Redis
```

---

## Stack

Laravel 13 · PHP 8.4 · PostgreSQL 16 + pgvector · Redis 7 · Horizon · Reverb
· Vue 3 + Inertia 2 + Tailwind 4 · Meta WhatsApp Cloud API v23 · Pest 4
· Docker Compose + Nginx

Fournisseurs IA interchangeables : OpenAI, Anthropic, Gemini — via les
interfaces `App\Contracts\AI\ChatProvider` et `EmbeddingProvider`. Changer de
fournisseur pour une entreprise se fait en base, sans redéploiement.

---

## Ce qui est livré

**Multi-tenant** — `tenant_id` sur toute table métier, scope global qui *lève*
plutôt que de laisser passer une requête non filtrée, policies revérifiant
l'appartenance, unicité composite en base, tests d'isolation bloquants.

**WhatsApp** — webhooks à signature HMAC vérifiée sur le corps brut en
`hash_equals`, idempotence par clé unique, traitement asynchrone, fenêtre de
service 24 h appliquée en amont, statuts de livraison monotones, opt-in/opt-out.

**Agent IA** — trois adaptateurs de fournisseurs, boucle d'outils bornée,
mémoire glissante, RAG pgvector cloisonné par tenant, garde-fous anti-invention
de tarifs, repli et transfert humain, coût tracé par appel.

**RAG** — extraction PDF / DOCX / TXT / URL (avec protection SSRF), découpage
récursif à chevauchement, embeddings par lots, index HNSW.

**Exploitation** — quotas Redis réconciliés en base, journal d'audit immuable,
incidents agrégés, impersonation tracée et limitée dans le temps.

**Interface** — 30 pages Inertia couvrant les quatre espaces : messagerie
opérateur temps réel, contacts, prospects, base de connaissances, configuration
(entreprise, agent, WhatsApp, prestations, FAQ, utilisateurs), administration
NONALIX et site commercial. Chaque route rendue par un contrôleur a sa vue.

## Ce qui reste à faire en Phase 1

- **Exécuter la suite Pest** (bloqué localement : Docker Desktop requis).
- Envoi des e-mails d'invitation et de réinitialisation (§1.9 de la feuille de route).
- Documentation OpenAPI de l'API publique.
- Import des médias entrants (téléchargement depuis Meta).
- **Pages légales** : `Marketing/Legal`, `Privacy` et `Terms` sont des gabarits
  structurés. Les mentions entre crochets doivent être complétées et validées
  juridiquement avant toute mise en ligne publique.

Voir [ROADMAP.md](ROADMAP.md) pour le détail.

---

## Sécurité

Aucun secret n'est présent dans le code. Les jetons Meta, secrets applicatifs
et clés IA sont chiffrés au repos via `APP_KEY` (cast `encrypted`).

> **`APP_KEY` doit être sauvegardée séparément des dumps de base.** Sans elle,
> tous les comptes WhatsApp connectés deviennent irrécupérables.

Signaler une vulnérabilité : security@nonalixia.com
