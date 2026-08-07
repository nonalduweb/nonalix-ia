<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Partage de ressources entre origines (CORS)
    |--------------------------------------------------------------------------
    |
    | Seule l'API du widget de chat est concernée. Le widget est chargé sur le
    | site du client — une origine tierce, inconnue à l'avance et différente
    | pour chaque entreprise — alors que l'API répond sur le domaine de
    | l'espace client. Sans ces en-têtes, le navigateur bloque les deux appels.
    |
    | L'espace client lui-même n'est PAS listé : il est piloté par Inertia
    | depuis sa propre origine et n'a aucun besoin de CORS. L'y ajouter
    | ouvrirait ses routes authentifiées à des lectures inter-origines.
    |
    */

    'paths' => ['widget/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    // Le widget est un produit d'intégration : le domaine de chaque client
    // nous est inconnu. L'ouverture est sans risque ici car ces routes sont
    // anonymes et `supports_credentials` reste à false — aucun cookie de
    // session n'est transmis, donc aucune requête ne peut être « montée »
    // sur l'identité d'un utilisateur connecté.
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
