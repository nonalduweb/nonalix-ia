<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

// Le site commercial est public : le lien de connexion pointe vers l'espace
// client, sur un autre sous-domaine.
const appUrl = computed(
    () => `https://${page.props.domains?.app ?? 'app.nonalixia.com'}`,
);

const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-full flex-col bg-white dark:bg-slate-950">
        <header class="border-b border-slate-200 dark:border-slate-800">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <Link href="/" class="text-lg font-semibold tracking-tight">Nonalix&nbsp;IA</Link>

                <nav class="flex items-center gap-6 text-sm">
                    <Link href="/tarifs" class="text-slate-600 hover:text-slate-900 dark:text-slate-300">
                        Tarifs
                    </Link>
                    <a :href="appUrl" class="btn-primary">Se connecter</a>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-slate-200 dark:border-slate-800">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-slate-500">
                <p>© {{ year }} Nonalix IA</p>

                <nav class="flex gap-5">
                    <Link href="/mentions-legales" class="hover:text-slate-900">Mentions légales</Link>
                    <Link href="/confidentialite" class="hover:text-slate-900">Confidentialité</Link>
                    <Link href="/conditions-utilisation" class="hover:text-slate-900">CGU</Link>
                </nav>
            </div>
        </footer>
    </div>
</template>
