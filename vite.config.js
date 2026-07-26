import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    // Inertia sert les pages depuis Laravel : les URL d'assets
                    // doivent rester relatives à la racine publique.
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],

    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },

    server: {
        host: '0.0.0.0',
        port: 5173,
        // Le conteneur Vite ne voit pas les événements inotify du volume
        // monté sous Windows : le polling est la seule option fiable.
        watch: { usePolling: true },
        hmr: { host: 'localhost' },
    },

    build: {
        // Ne PAS définir `manifest` ici : laravel-vite-plugin le fixe à
        // 'manifest.json' pour que le fichier atterrisse à la racine de
        // public/build, là où Laravel le cherche. La valeur `true` de Vite le
        // placerait dans public/build/.vite/ et toute page lèverait
        // « Vite manifest not found ».
        sourcemap: false,
    },
});
