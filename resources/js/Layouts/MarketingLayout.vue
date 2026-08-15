<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

const page = usePage();

// Le site commercial est public : les liens pointent vers l'espace client,
// sur un autre sous-domaine.
const appUrl = computed(() => `https://${page.props.domains?.app ?? 'app.nonalixia.com'}`);
// Un visiteur n'a pas de code : tout appel a l'action le mene a la
// demande, jamais a l'inscription, qui le renverrait sans recours.

const year = new Date().getFullYear();
</script>

<template>
    <!-- `marketing` force le rendu clair : une identité de marque qui change
         selon les réglages du visiteur n'en est plus une. -->
    <div class="marketing flex min-h-full flex-col bg-white text-slate-900">
        <!-- En-tête collant et translucide : la navigation reste accessible
             sur des pages longues sans jamais peser visuellement. -->
        <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/85 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <Link href="/" class="shrink-0" aria-label="Nonalix IA — accueil">
                    <BrandLogo />
                </Link>

                <nav class="flex items-center gap-7 text-[15px]">
                    <Link href="/tarifs" class="text-slate-600 transition hover:text-slate-900">
                        Tarifs
                    </Link>
                    <a :href="appUrl" class="hidden text-slate-600 transition hover:text-slate-900 sm:block">
                        Se connecter
                    </a>
                    <Link href="/demande" class="btn-ink">Demander un accès</Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="mt-24 border-t border-slate-100">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-10 text-sm text-slate-500">
                <p>© {{ year }} NONALIX CI SARL. Tous droits réservés. <span class="hidden sm:inline text-slate-300 mx-1">|</span> <span class="block sm:inline text-slate-400">NONALIX IA est un produit de NONALIX CI</span></p>

                <nav class="flex flex-wrap gap-6">
                    <Link href="/mentions-legales" class="transition hover:text-slate-900">Mentions légales</Link>
                    <Link href="/confidentialite" class="transition hover:text-slate-900">Confidentialité</Link>
                    <Link href="/conditions-utilisation" class="transition hover:text-slate-900">CGU</Link>
                    <Link href="/delete-data" class="transition hover:text-slate-900">Suppression des données</Link>
                </nav>
            </div>
        </footer>
    </div>
</template>
