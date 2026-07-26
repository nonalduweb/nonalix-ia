-- Nonalix IA — extensions requises, créées à l'initialisation du volume.
-- Rejoué par les migrations (CREATE EXTENSION IF NOT EXISTS) pour les bases
-- déjà existantes et pour la base de test.

CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS vector;     -- pgvector : type vector, index HNSW
CREATE EXTENSION IF NOT EXISTS pg_trgm;    -- recherche floue sur les contacts

-- Base de test dédiée : les tests tournent en transaction sur leur propre base
-- pour ne jamais toucher aux données de développement.
SELECT 'CREATE DATABASE nonalix_testing OWNER ' || current_user
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'nonalix_testing')\gexec

\connect nonalix_testing
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
