<script setup>
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

/*
 * GABARIT À COMPLÉTER AVANT MISE EN LIGNE.
 *
 * Les valeurs entre crochets doivent être remplacées par les informations
 * réelles de la société éditrice. Publier ces mentions avec des données
 * inventées expose à une sanction et n'a aucune valeur juridique.
 */
const sections = [
    {
        title: 'Éditeur du site',
        rows: [
            ['Dénomination sociale', '[raison sociale]'],
            ['Forme juridique', '[SAS, SARL…]'],
            ['Capital social', '[montant] €'],
            ['Siège social', '[adresse complète]'],
            ['RCS', '[ville et numéro d\'immatriculation]'],
            ['N° TVA intracommunautaire', '[FR……]'],
            ['Directeur de la publication', '[nom du représentant légal]'],
            ['Contact', '[adresse e-mail de contact]'],
        ],
    },
    {
        title: 'Hébergement',
        rows: [
            ['Hébergeur', '[raison sociale de l\'hébergeur]'],
            ['Adresse', '[adresse de l\'hébergeur]'],
            ['Contact', '[téléphone ou e-mail]'],
        ],
    },
];
</script>

<template>
    <Head title="Mentions légales" />

    <MarketingLayout>
        <article class="mx-auto max-w-3xl px-4 py-16">
            <h1 class="mb-8 text-3xl font-semibold tracking-tight">Mentions légales</h1>

            <div class="mb-10 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>Gabarit non finalisé.</strong> Les informations ci-dessous doivent
                être complétées par la société éditrice avant toute mise en ligne
                publique. Ce contenu n'a aucune valeur juridique en l'état.
            </div>

            <section v-for="section in sections" :key="section.title" class="mb-10">
                <h2 class="mb-4 text-lg font-semibold">{{ section.title }}</h2>
                <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <div v-for="[label, value] in section.rows" :key="label" class="flex gap-4 py-2.5">
                        <dt class="w-64 shrink-0 text-slate-500">{{ label }}</dt>
                        <dd class="font-mono text-xs text-slate-400">{{ value }}</dd>
                    </div>
                </dl>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold">Propriété intellectuelle</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    L'ensemble des éléments composant ce site — structure, textes, logos,
                    interfaces — est protégé par le droit de la propriété intellectuelle.
                    Toute reproduction ou représentation, totale ou partielle, sans
                    autorisation écrite préalable est interdite.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-lg font-semibold">Signalement</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Pour signaler un contenu illicite ou une vulnérabilité de sécurité,
                    écrivez à <strong>[adresse de signalement]</strong>.
                </p>
            </section>
        </article>
    </MarketingLayout>
</template>
