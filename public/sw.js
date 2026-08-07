// Service Worker pour Nonalix IA - Permet l'installation PWA sur mobile.
const CACHE_NAME = 'nonalix-ia-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Laisse le réseau traiter toutes les requêtes en direct (évite de perturber les WebSockets Reverb).
    // Ce écouteur vide suffit à satisfaire les critères d'installation de Google Chrome et Safari.
    event.respondWith(fetch(event.request).catch(() => {
        // En cas de panne réseau complète.
    }));
});
