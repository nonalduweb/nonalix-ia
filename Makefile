## Nonalix IA — raccourcis de développement.
## Toutes les commandes passent par Docker : aucun PHP local n'est requis.

DC := docker compose
APP := $(DC) exec app

.DEFAULT_GOAL := help
.PHONY: help install up down restart logs shell test test-unit test-feature \
        migrate fresh seed lint fix horizon health build assets

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

install: ## Première installation complète
	@test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d postgres redis
	@echo "Attente de PostgreSQL…"
	@until $(DC) exec -T postgres pg_isready -q; do sleep 1; done
	$(DC) run --rm app composer install
	$(DC) run --rm app php artisan key:generate
	$(DC) run --rm app php artisan migrate --seed
	$(DC) up -d
	@echo ""
	@echo "  Application : http://localhost"
	@echo "  Démo        : demo@nonalixia.test / password"
	@echo "  Mailpit     : http://localhost:8025 (profil dev)"

up: ## Démarre la stack
	$(DC) up -d

down: ## Arrête la stack
	$(DC) down

restart: ## Redémarre la stack
	$(DC) restart

logs: ## Suit les logs (make logs s=horizon pour un service)
	$(DC) logs -f $(s)

shell: ## Ouvre un shell dans le conteneur applicatif
	$(APP) bash

test: ## Lance la suite Pest complète
	$(APP) php artisan test

test-unit: ## Tests unitaires seulement
	$(APP) php artisan test --testsuite=Unit

test-feature: ## Tests fonctionnels seulement
	$(APP) php artisan test --testsuite=Feature

migrate: ## Applique les migrations
	$(APP) php artisan migrate

fresh: ## Recrée la base et rejoue les seeders (DESTRUCTIF)
	$(APP) php artisan migrate:fresh --seed

seed: ## Rejoue les seeders
	$(APP) php artisan db:seed

lint: ## Vérifie le style (Pint, sans modifier)
	$(APP) ./vendor/bin/pint --test

fix: ## Corrige le style
	$(APP) ./vendor/bin/pint

horizon: ## Redémarre les workers après un déploiement
	$(APP) php artisan horizon:terminate

health: ## Vérifie PostgreSQL, pgvector et Redis
	$(APP) php artisan nonalix:health

build: ## Reconstruit les images Docker
	$(DC) build --no-cache

assets: ## Compile les assets front pour la production
	$(DC) run --rm vite npm run build
